import sys
import os
import logging
from dotenv import load_dotenv

# configure logging: INFO+ by default; suppress DEBUG noise
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    datefmt="%H:%M:%S"
)

# load environment variables from explicit path
env_path = "/home/cdadmin/Desktop/FR2/.env"
if not os.path.exists(env_path):
    logging.error(".env file not found at %s", env_path)
    sys.exit(1)
load_dotenv(env_path)
logger = logging.getLogger(__name__)
logger.info("Loaded .env from %s", env_path)
logger.debug(
    "CAMERA_IP=%s, CAMERA_PORT=%s, CAMERA_USER=%s, CAMERA_PASSWORD=%s",
    os.getenv("CAMERA_IP"), os.getenv("CAMERA_PORT"),
    os.getenv("CAMERA_USER"), os.getenv("CAMERA_PASSWORD")
)

# validate required env vars
required = ["CAMERA_IP", "CAMERA_PORT", "CAMERA_USER", "CAMERA_PASSWORD"]
missing = [k for k in required if not os.getenv(k)]
if missing:
    logger.error("Missing required env vars: %s", ", ".join(missing))
    sys.exit(1)

# ensure our src folder is on the path
sys.path.append(os.path.join(os.path.dirname(__file__), "src"))

from core import capture_frames
from utils import speak_message
from gui import FaceRecognitionUI
from PyQt5.QtWidgets import QApplication
from PyQt5.QtCore import QTimer, Qt

if __name__ == '__main__':
    app = QApplication(sys.argv)
    window = FaceRecognitionUI()
    # remove window decorations and make truly fullscreen
    window.setWindowFlags(Qt.FramelessWindowHint)
    window.showFullScreen()
    window.showFullScreen()

    # give the UI a moment to initialize, then start frame capture
    QTimer.singleShot(2000, window.start_camera_capture)

    sys.exit(app.exec_())

