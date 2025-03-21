import cv2
import threading
import time

def capture_frames(visual_rtsp_url, thermal_rtsp_url, target_height=480, target_width=640, retry_delay=5):
    """
    Captures frames from visual and thermal cameras while maintaining aspect ratio.
    Uses threading for independent frame capture and supports automatic reconnection.
    """
    visual_cap = cv2.VideoCapture(visual_rtsp_url, cv2.CAP_FFMPEG)
    thermal_cap = cv2.VideoCapture(thermal_rtsp_url, cv2.CAP_FFMPEG)

    visual_cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    thermal_cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)

    frames = {'frame': None, 'thermal_frame': None}

    def resize_frame(frame, target_size):
        """Resizes the frame while maintaining aspect ratio."""
        if frame is None:
            return None

        h, w = frame.shape[:2]
        target_h, target_w = target_size

        # Calculate the aspect ratio preserving resize
        scale = min(target_w / w, target_h / h)
        new_w = int(w * scale)
        new_h = int(h * scale)

        resized_frame = cv2.resize(frame, (new_w, new_h), interpolation=cv2.INTER_AREA)

        # Add black padding to maintain exact target size
        final_frame = cv2.copyMakeBorder(
            resized_frame,
            (target_h - new_h) // 2, (target_h - new_h + 1) // 2,  # Top, Bottom
            (target_w - new_w) // 2, (target_w - new_w + 1) // 2,  # Left, Right
            cv2.BORDER_CONSTANT, value=(0, 0, 0)  # Black border
        )

        return final_frame

    def handle_camera_reconnect(cap, cam_type, url):
        """Attempts to reconnect the camera if the feed is lost."""
        print(f"🔄 Attempting to reconnect {cam_type} camera...")
        cap.release()
        time.sleep(retry_delay)
        cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        return cap

    def thermalLoop():
        nonlocal thermal_cap
        while True:
            thermal_ret, thermal_frame = thermal_cap.read()
            if not thermal_ret or thermal_frame is None:
                print("⚠️ ERROR: Failed to capture thermal frame! Reconnecting...")
                thermal_cap = handle_camera_reconnect(thermal_cap, "thermal", thermal_rtsp_url)
            else:
                frames['thermal_frame'] = resize_frame(thermal_frame, (target_height, target_width))

    def visualLoop():
        nonlocal visual_cap
        while True:
            visual_ret, visual_frame = visual_cap.read()
            if not visual_ret or visual_frame is None:
                print("⚠️ ERROR: Failed to capture visual frame! Reconnecting...")
                visual_cap = handle_camera_reconnect(visual_cap, "visual", visual_rtsp_url)
            else:
                frames['frame'] = resize_frame(visual_frame, (target_height, target_width))

    t1 = threading.Thread(target=thermalLoop, name="thermalLoop", daemon=True)
    t2 = threading.Thread(target=visualLoop, name="visualLoop", daemon=True)

    t1.start()
    t2.start()

    return frames

