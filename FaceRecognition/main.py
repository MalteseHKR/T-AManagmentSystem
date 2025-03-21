import os
import cv2
import numpy as np
import time
import mysql.connector
import re
import threading
import fr  # Import face recognition module
from datetime import datetime
from camera import capture_frames  # Import camera handling module
from utils import display_combined_frame, show_punch_alert
import multiprocessing
import folder_watcher  # Import folder watcher to auto-update faces

# ✅ Connect to `garrison_records` MySQL
print("🔄 Connecting to Garrison Database...")
try:
    db = mysql.connector.connect(
        host="192.168.1.5",
        user="peaky",
        password="gXkbqb90quESInlDJx1U!",
        database="garrison_records"
    )
    cursor = db.cursor()
    print("✅ Connected to `garrison_records` database!")
except mysql.connector.Error as err:
    print(f"❌ MySQL Connection Failed: {err}")
    exit()

# ✅ Ensure NFS Uploads Directory Exists
nfs_upload_path = "/mnt/nfs_uploads"
if not os.path.exists(nfs_upload_path):
    os.makedirs(nfs_upload_path)

# ✅ Start Folder Watcher in a separate process
if __name__ == '__main__':
    watcher_process = multiprocessing.Process(target=folder_watcher.Watcher().run)
    watcher_process.start()

# ✅ Start Capturing Frames
frames = capture_frames(
    "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=1&subtype=0",
    "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=2&subtype=0"
)

print("🔄 Loading known faces...")
fr.load_known_faces()

# ✅ Track Last Punch Times
last_punches = {}

def extract_user_id(name):
    """Extracts the correct user ID from the new filename format."""
    parts = name.split()  # Splitting by spaces
    if len(parts) > 1 and parts[-1].isdigit():  # Last part is the user ID (e.g., "Daniel Mercieca2 3")
        return int(parts[-1])  # ✅ Extract last number as ID
    return None  # Return None if no valid ID found

def get_punch_type(user_id):
    """Determine if the user should be punched IN or OUT."""
    sql = "SELECT punch_type FROM log_Information WHERE user_id = %s ORDER BY date_time_event DESC LIMIT 1"
    cursor.execute(sql, (user_id,))
    last_punch = cursor.fetchone()

    return "OUT" if last_punch and last_punch[0] == "IN" else "IN"

def capture_punch_photo(frame, user_name, user_id):
    """Capture and save a punch-in image with correctly formatted filename."""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")  # ✅ Timestamp for uniqueness
    safe_name = user_name.replace(" ", "_")  # ✅ Replace spaces with underscores
    photo_filename = f"{safe_name}_{user_id}_{timestamp}.jpg"  # ✅ Unique filename

    # ✅ Save in `/mnt/nfs_uploads/`
    photo_path = os.path.join(nfs_upload_path, photo_filename)

    cv2.imwrite(photo_path, frame)
    print(f"📸 Saved Punch Photo: {photo_path}")

    # ✅ Return DB-friendly path (`/uploads/`)
    return f"/uploads/{photo_filename}"


def punch_attendance(name):
    """Logs attendance in the `garrison_records.log_Information` database."""
    global last_punches
    current_datetime = datetime.now()

    user_id = extract_user_id(name)  # ✅ Extract user ID from new format
    if user_id is None:
        print(f"⚠️ Skipping unknown user: {name}")
        return

    user_name = " ".join(name.split()[:-1])  # ✅ Extract full name without user ID

    # Prevent multiple punches within 30 seconds
    if user_id in last_punches:
        last_punch_time = last_punches[user_id]
        if (current_datetime - last_punch_time).total_seconds() < 30:
            print(f"⏳ {user_name} ({user_id}) recently punched. Skipping duplicate.")
            return

    punch_type = get_punch_type(user_id)  # ✅ Get IN/OUT status

    # Capture and save punch image
    frame = frames["frame"]
    photo_url = capture_punch_photo(frame, user_name, user_id)  # ✅ Now returns `/uploads/`

    # Extract punch date and time
    punch_date = current_datetime.strftime("%Y-%m-%d")
    punch_time = current_datetime.strftime("%H:%M:%S")

    # ✅ Insert punch into `log_Information`
    try:
        sql = """INSERT INTO log_Information 
                 (date_time_saved, date_time_event, device_id, punch_type, photo_url, longitude, latitude, punch_date, punch_time, user_id) 
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""
        values = (current_datetime, current_datetime, 1, punch_type, photo_url, "14.47631000", "35.92584060", punch_date, punch_time, user_id)
        cursor.execute(sql, values)
        db.commit()

        last_punches[user_id] = current_datetime  # ✅ Update last punch time

        print(f"✅ {user_name} ({user_id}) punched {punch_type} at {current_datetime}")
        show_punch_alert(user_name)

    except mysql.connector.Error as err:
        print(f"❌ MySQL Error: {err}")


try:
    while True:
        # ✅ Get latest frames
        frame_data = frames
        frame = frame_data["frame"]
        thermal_frame = frame_data["thermal_frame"]

        if frame is None or thermal_frame is None:
            print("⚠️ ERROR: One or both frames are empty! Skipping recognition.")
            time.sleep(1)
            continue

        # ✅ Perform Face Recognition
        face_names = fr.recognize_faces(frame, thermal_frame, fr.known_face_encodings, fr.known_face_names)

        for name in face_names:
            if name != "Unknown" and name != "Fake Face":  # ✅ Skip "Fake Face" entries
                punch_attendance(name)

        # ✅ Display camera feeds
        display_combined_frame(frame, thermal_frame, face_names)

except KeyboardInterrupt:
    print("🛑 Shutting down...")

cursor.close()
db.close()
cv2.destroyAllWindows()

