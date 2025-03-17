import cv2
import numpy as np
import time
import threading
from vpn import start_vpn, stop_vpn
from face_recognition import update_known_faces, recognize_faces
from camera import capture_frames
from utils import display_combined_frame

# RTSP URLs
visual_rtsp_url = "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=1&subtype=0"
thermal_rtsp_url = "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=2&subtype=0"

# Server URL
server_url = "http://192.168.10.10:5000/upload"

# Start OpenVPN connection
vpn_process, credentials_path = start_vpn("dmercieca", "Yw7Oh5BlLc392pBUu3Mv")

# Capture frames from the visual and thermal streams
frames = capture_frames(visual_rtsp_url, thermal_rtsp_url)

# Initialize known faces
known_face_encodings = []
known_face_names = []

# Periodically update known faces list
update_interval = 60  # Update every 60 seconds
last_update_time = time.time()

while True:
    start_time = time.time()

    frame = frames['frame']
    thermal_frame = frames['thermal_frame']

    if frame is None or thermal_frame is None:
        print("Waiting for feed...")
        time.sleep(1)
        continue

    # Recognize faces
    face_names = recognize_faces(frame, thermal_frame, known_face_encodings, known_face_names, server_url)

    # Display the combined frame
    display_combined_frame(frame, thermal_frame, face_names)

    # Control the frame rate
    elapsed_time = (time.time() - start_time) * 1000
    if elapsed_time < 100:
        time.sleep((100 - elapsed_time) / 1000)

    # Periodically update known faces list
    if time.time() - last_update_time > update_interval:
        update_known_faces()
        last_update_time = time.time()

# Stop OpenVPN connection and remove credentials file
stop_vpn(vpn_process, credentials_path)