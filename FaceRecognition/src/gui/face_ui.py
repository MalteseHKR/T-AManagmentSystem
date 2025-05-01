import os
import threading
import logging
import cv2
import numpy as np
from datetime import datetime, timedelta
from PyQt5.QtWidgets import QWidget, QLabel, QVBoxLayout, QHBoxLayout
from PyQt5.QtGui import QImage, QPixmap, QFont, QGuiApplication, QPalette, QBrush
from PyQt5.QtCore import QTimer, Qt
from dotenv import load_dotenv
from mysql.connector import connect
from utils import speak_message

# Load environment variables
load_dotenv()

logger = logging.getLogger(__name__)
# Ensure Qt plugin path is set
os.environ["QT_QPA_PLATFORM_PLUGIN_PATH"] = "/usr/lib/qt/plugins"

class FaceRecognitionUI(QWidget):
    def __init__(self, verbose=False):
        super().__init__()
        self.verbose = verbose

        # Setup NFS upload path
        self.nfs_upload_path = "/mnt/nfs_uploads"
        os.makedirs(self.nfs_upload_path, exist_ok=True)

        # Liveness & duplicate punch control
        self.last_punches = {}
        self.fake_face_counter = 0
        self.fake_face_timeout = None
                # Unknown user notification throttle
        self.unknown_timeout = None

        # Instruction cycle
        self.instructions = ["Hold still for a moment", "Ensure your face is well-lit"]
        self.current_instruction = 0
        self.instruction_timer = QTimer()
        self.instruction_timer.timeout.connect(self.show_next_instruction)

        # Fullscreen setup
        self.setWindowTitle("Face Recognition System")
        screen = QGuiApplication.primaryScreen().geometry()
        w, h = screen.width(), screen.height()
        self.setGeometry(0, 0, w, h)
        self.setWindowFlags(Qt.FramelessWindowHint)

        # Background logo
        script_dir = os.path.dirname(__file__)
        bg_pix = QPixmap(os.path.join(script_dir, "logo.png")).scaled(
            w, h, Qt.KeepAspectRatioByExpanding, Qt.SmoothTransformation
        )
        palette = QPalette()
        palette.setBrush(QPalette.Window, QBrush(bg_pix))
        self.setPalette(palette)
        self.setAutoFillBackground(True)

        # Build UI
        self.setup_ui()
        logger.info("UI setup complete")

        # Frame buffers
        half_w = w // 2
        feed_h = int(half_w * 9 / 16)
        self.frames = {
            'frame': np.zeros((feed_h, half_w, 3), dtype=np.uint8),
            'thermal_frame': np.zeros((feed_h, half_w, 3), dtype=np.uint8)
        }
        self.shared_frames = self.frames

        # Timers
        self.timer = QTimer()
        self.timer.timeout.connect(self.update_frame)
        self.timer.start(100)

        # Start folder watcher
        self.start_folder_watcher()

    def setup_ui(self):
        layout = QVBoxLayout(self)
        layout.setContentsMargins(0, 0, 0, 0)
        layout.setSpacing(10)

        # Header
        header = QLabel("Face Recognition System")
        header.setFont(QFont("Arial", 24, QFont.Bold))
        header.setAlignment(Qt.AlignCenter)
        header.setStyleSheet("background: transparent;")
        layout.addWidget(header)

        # Shift feeds up by 40px
        layout.addSpacing(-40)

        # Video feeds
        feed_w = self.width() // 2
        feed_h = int(feed_w * 9 / 16)
        video_layout = QHBoxLayout()
        video_layout.setContentsMargins(0, 0, 0, 0)
        video_layout.setSpacing(10)

        self.image_label = QLabel()
        self.image_label.setFixedSize(feed_w, feed_h)
        self.image_label.setAlignment(Qt.AlignCenter)
        self.image_label.setStyleSheet("background: transparent;")
        video_layout.addWidget(self.image_label)

        self.thermal_label = QLabel()
        self.thermal_label.setFixedSize(feed_w, feed_h)
        self.thermal_label.setAlignment(Qt.AlignCenter)
        self.thermal_label.setStyleSheet("background: transparent;")
        video_layout.addWidget(self.thermal_label)

        layout.addLayout(video_layout)

        # Status labels
        self.camera_status = QLabel("Camera: Initializing...")
        self.camera_status.setFont(QFont("Arial", 14))
        self.camera_status.setAlignment(Qt.AlignCenter)
        self.camera_status.setStyleSheet("background: transparent;")
        layout.addWidget(self.camera_status)

        self.status_label = QLabel("Please look at the camera")
        self.status_label.setFont(QFont("Arial", 20))
        self.status_label.setAlignment(Qt.AlignCenter)
        self.status_label.setStyleSheet("background: transparent; color: black;")
        layout.addWidget(self.status_label)

    def show_next_instruction(self):
        self.current_instruction = (self.current_instruction + 1) % len(self.instructions)
        self.status_label.setText(self.instructions[self.current_instruction])

    def start_camera_capture(self):
        from core.camera import capture_frames
        from core import fr
        fr.load_known_faces()
        load_dotenv()
        user, pwd, ip, port = (
            os.getenv('CAMERA_USER'),
            os.getenv('CAMERA_PASSWORD'),
            os.getenv('CAMERA_IP'),
            os.getenv('CAMERA_PORT')
        )
        rtsp1 = f"rtsp://{user}:{pwd}@{ip}:{port}/cam/realmonitor?channel=1&subtype=0"
        rtsp2 = f"rtsp://{user}:{pwd}@{ip}:{port}/cam/realmonitor?channel=2&subtype=0"
        threading.Thread(
            target=lambda: capture_frames(rtsp1, rtsp2, shared_dict=self.shared_frames),
            daemon=True
        ).start()

    def start_folder_watcher(self):
        try:
            from core.folder_watcher import Watcher
            threading.Thread(target=Watcher().run, daemon=True).start()
        except:
            pass

    @staticmethod
    def extract_user_id(name):
        try:
            return int(name.split(" - ")[0])
        except:
            return None

    def get_punch_type(self, user_id):
        conn = connect(
            user=os.getenv('DB_USER'),
            password=os.getenv('DB_PASSWORD'),
            host=os.getenv('DB_HOST'),
            database=os.getenv('DB_NAME')
        )
        cursor = conn.cursor()
        cursor.execute(
            "SELECT punch_type FROM log_Information WHERE user_id=%s ORDER BY date_time_event DESC LIMIT 1",
            (user_id,)
        )
        row = cursor.fetchone()
        cursor.close()
        conn.close()
        return 'OUT' if row and row[0] == 'IN' else 'IN'

    def capture_punch_photo(self, frame, name, user_id):
        ts = datetime.now().strftime('%Y%m%d_%H%M%S')
        fn = name.replace(' ', '_')
        path = os.path.join(self.nfs_upload_path, f"{fn}_{user_id}_{ts}.jpg")
        cv2.imwrite(path, frame)
        return f"/uploads/{os.path.basename(path)}"

    def update_frame(self):
        frame = self.frames['frame']
        therm = self.frames['thermal_frame']
        if frame.sum() == 0 or therm.sum() == 0:
            self.camera_status.setText("Camera: Waiting for feed...")
            self.instruction_timer.stop()
            self.status_label.setText("Please look at the camera")
            return

        self.camera_status.setText("✅ Cameras active")
        self.image_label.setPixmap(self.convert_cv_qt(frame, self.image_label.size()))
        self.thermal_label.setPixmap(self.convert_cv_qt(therm, self.thermal_label.size()))

        from core import fr
        names = fr.recognize_faces(frame, therm)
        now = datetime.now()

        for name in names:
            if name == "Unknown":
                # throttle notifications to once per 5s
                if self.unknown_timeout and now < self.unknown_timeout:
                    continue
                self.unknown_timeout = now + timedelta(seconds=5)
                # delayed popup and TTS
                def notify_unknown():
                    self.instruction_timer.stop()
                    self.status_label.setStyleSheet(
                        "background-color: #F44336; color: white; padding: 10px; border-radius: 5px;"
                    )
                    self.status_label.setText("❌ User not found, please contact HR department")
                    speak_message("User not found, please contact HR department")
                    # revert after 3s
                    QTimer.singleShot(3000, lambda: (
                        self.status_label.setStyleSheet("background: transparent; color: black;"),
                        self.status_label.setText("Please look at the camera"),
                        self.instruction_timer.start(3000)
                    ))
                QTimer.singleShot(500, notify_unknown)
                continue
            if name == "Fake Face":
                continue

            # fake-face lockout
            if self.fake_face_timeout and now < self.fake_face_timeout:
                continue

            uid = self.extract_user_id(name)
            if uid is None:
                continue

            # liveness blink detection
            if not fr.detect_blink(frame):
                self.fake_face_counter += 1
                if self.fake_face_counter >= 10:
                    speak_message("Fake Face Detected, please try again in 30 seconds")
                    self.fake_face_timeout = now + timedelta(seconds=30)
                    self.fake_face_counter = 0
                continue

            self.fake_face_counter = 0

            # duplicate punch suppression
            last = self.last_punches.get(uid)
            if last and (now - last).total_seconds() < 30:
                continue

            self.last_punches[uid] = now
            punch_type = self.get_punch_type(uid)
            photo_url = self.capture_punch_photo(frame, name, uid)
            self.record_attendance(name, uid, punch_type, photo_url, now)

        # restart instructions or default text
        if names and not self.instruction_timer.isActive():
            self.current_instruction = -1
            self.instruction_timer.start(3000)
        elif not names:
            self.instruction_timer.stop()
            self.status_label.setStyleSheet("background: transparent; color: black;")
            self.status_label.setText("Please look at the camera")

    
    def record_attendance(self, name, user_id, punch_type, photo_url, now):
        """Insert a punch record into log_Information and show confirmation."""
        self.instruction_timer.stop()

        device_id = 1
        longitude = float(os.getenv('LONGITUDE', '14.47631000'))
        latitude  = float(os.getenv('LATITUDE',  '35.92584060'))

        conn = connect(
            user=os.getenv('DB_USER'),
            password=os.getenv('DB_PASSWORD'),
            host=os.getenv('DB_HOST'),
            database=os.getenv('DB_NAME')
        )
        cursor = conn.cursor()
        cursor.execute(
            "INSERT INTO log_Information "
            "(date_time_saved, date_time_event, device_id, user_id, punch_type, photo_url, "
            "longitude, latitude, punch_date, punch_time) "
            "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            (
                now, now, device_id, user_id, punch_type,
                photo_url, longitude, latitude,
                now.date(), now.time()
            )
        )
        conn.commit()
        cursor.close()
        conn.close()

        # display only first name
        first_name = name.split(" - ")[-1].split()[0]
        time_str = now.strftime('%H:%M:%S')

        # green banner
        self.status_label.setStyleSheet(
            "background-color: #4CAF50; color: white; padding: 10px; border-radius: 5px;"
        )
        self.status_label.setText(f"👋 {first_name} punched {punch_type} at {time_str}")

        # TTS
        speak_message(f"Welcome {first_name}, punch {punch_type}")

        # revert after 3s
        QTimer.singleShot(
            3000,
            lambda: (
                self.status_label.setStyleSheet("background: transparent; color: black;"),
                self.status_label.setText("Please look at the camera")
            )
        )

    def convert_cv_qt(self, img, target_size):
        """Convert an OpenCV image to QPixmap scaled to target_size."""
        rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        h, w, _ = rgb.shape
        qimg = QImage(rgb.data, w, h, w * 3, QImage.Format_RGB888)
        pix = QPixmap.fromImage(qimg)
        return pix.scaled(target_size, Qt.KeepAspectRatio)

    def closeEvent(self, event):
        event.accept()

