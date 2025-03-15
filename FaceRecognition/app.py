import cv2
import numpy as np
import time
import threading
import mysql.connector
import fr  # Import the face recognition module
from datetime import datetime
from camera import capture_frames
from utils import display_combined_frame
from folder_watcher import Watcher

# MySQL Database Connection
db = mysql.connector.connect(
    host="192.168.1.5",
    user="peaky",
    password="gXkbqb90quESInlDJx1U!",
    database="testdb"
)
cursor = db.cursor()

# RTSP Camera Feeds
VISUAL_RTSP_URL = "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=1&subtype=0"
THERMAL_RTSP_URL = "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=2&subtype=0"

# Start capturing frames from visual & thermal cameras
frames = capture_frames(VISUAL_RTSP_URL, THERMAL_RTSP_URL)

print("🔄 Loading known faces...")
fr.load_known_faces()

print(f"✅ Loaded {len(fr.known_face_encodings)} known face encodings.")
print(f"✅ Loaded {len(fr.known_face_names)} known face names: {fr.known_face_names}")

# Attendance tracking dictionary to prevent duplicate punches (1-minute cooldown)
last_punch_times = {}

# Start folder watcher (detects new faces)
watcher = Watcher()
watcher_thread = threading.Thread(target=watcher.run, daemon=True)
watcher_thread.start()

def punch_attendance(name):
    """
    Logs attendance by inserting a record into the MySQL database.
    Prevents duplicate entries within a cooldown period.
    """
    global last_punch_times
    current_time = datetime.now()
    
    # Extract user_id from filename (assuming format: "Name Surname ID.jpg")
    user_id = name.split()[-1] if name.split()[-1].isdigit() else "Unknown"

    # Prevent duplicate punching within 60 seconds
    if name in last_punch_times:
        last_punch_time = last_punch_times[name]
        if (current_time - last_punch_time).total_seconds() < 60:
            print(f"⏳ {name} already punched in. Skipping duplicate.")
            return

    try:
        sql = "INSERT INTO attendance (user_id, punch_time) VALUES (%s, %s)"
        values = (user_id, current_time)
        cursor.execute(sql, values)
        db.commit()
        last_punch_times[name] = current_time  # Update last punch time
        print(f"✅ Attendance punched for {name} at {current_time}")
    except mysql.connector.Error as err:
        print(f"❌ MySQL Error: {err}")

try:
    while True:
        start_time = time.time()

        frame = frames['frame']
        thermal_frame = frames['thermal_frame']

        if frame is None or thermal_frame is None:
            print("⏳ Waiting for camera feed...")
            time.sleep(1)
            continue

        # Recognize faces
        face_names = fr.recognize_faces(frame, fr.known_face_encodings, fr.known_face_names, model='hog')

        # Punch attendance for recognized faces
        for name in face_names:
            if name != "Unknown":
                punch_attendance(name)

        # Display UI
        display_combined_frame(frame, thermal_frame, face_names)

        # Control frame rate
        elapsed_time = (time.time() - start_time) * 1000
        if elapsed_time < 100:
            time.sleep((100 - elapsed_time) / 1000)

except KeyboardInterrupt:
    print("🛑 Shutting down...")

cursor.close()
db.close()
cv2.destroyAllWindows()

