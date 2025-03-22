# config.py

# RTSP URLs
VISUAL_RTSP_URL = "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=1&subtype=0"
THERMAL_RTSP_URL = "rtsp://admin:PeakySTC2025!!@192.168.8.10:554/cam/realmonitor?channel=2&subtype=0"

# Server URLs
SERVER_URL = "http://192.168.10.10:5000/upload"
NEW_FACES_URL = "http://192.168.10.10:5000/get_new_faces"

# OpenVPN configuration
OPENVPN_CONFIG_PATH = "/home/cdadmin/Desktop/FaceRecognition/open.ovpn"
CREDENTIALS_PATH = "/home/cdadmin/Desktop/FaceRecognition/credentials.txt"
VPN_USERNAME = "dmercieca"
VPN_PASSWORD = "Yw7Oh5BlLc392pBUu3Mv"

# Directory to store downloaded face images
UPLOAD_FOLDER = "/home/cdadmin/Desktop/FaceRecognition/uploads"

# Frame settings
FRAME_WIDTH = 640
FRAME_HEIGHT = 480
FRAME_INTERVAL = 1000 / 10  # 10 FPS

# Update interval for known faces
UPDATE_INTERVAL = 60  # Update every 60 seconds
