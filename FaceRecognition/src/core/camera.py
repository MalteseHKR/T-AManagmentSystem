import cv2
import threading
import time
import numpy as np
import logging

logger = logging.getLogger(__name__)

class CameraStreamManager:
    def __init__(self, visual_url, thermal_url,
                 target_height=480, target_width=640,
                 retry_delay=5, timeout=10, shared_dict=None):
        self.visual_url = visual_url
        self.thermal_url = thermal_url
        self.target_height = target_height
        self.target_width = target_width
        self.retry_delay = retry_delay
        self.timeout = timeout

        self.frames = shared_dict if shared_dict is not None else {
            'frame': np.zeros((target_height, target_width, 3), dtype=np.uint8),
            'thermal_frame': np.zeros((target_height, target_width, 3), dtype=np.uint8)
        }

        self.visual_cap = self.try_open_camera(self.visual_url, "visual")
        self.thermal_cap = self.try_open_camera(self.thermal_url, "thermal")

        if self.visual_cap:
            threading.Thread(target=self.loop_cam, args=("visual",), daemon=True).start()
        if self.thermal_cap:
            threading.Thread(target=self.loop_cam, args=("thermal",), daemon=True).start()

    def try_open_camera(self, rtsp_url, cam_type):
        logger.info("Attempting to connect to %s camera…", cam_type)
        cap = None
        start = time.time()
        while time.time() - start < self.timeout:
            cap = cv2.VideoCapture(rtsp_url, cv2.CAP_FFMPEG)
            if cap.isOpened():
                logger.info("%s camera connected.", cam_type.capitalize())
                cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                return cap
            cap.release()
            time.sleep(1)
        logger.warning(
            "%s camera unavailable after %s seconds. Using black frames.",
            cam_type.capitalize(), self.timeout
        )
        return None

    def resize_frame(self, frame):
        if frame is None:
            return None
        h, w = frame.shape[:2]
        scale = min(self.target_width / w, self.target_height / h)
        new_w, new_h = int(w * scale), int(h * scale)
        resized = cv2.resize(frame, (new_w, new_h), interpolation=cv2.INTER_AREA)
        top = (self.target_height - new_h) // 2
        left = (self.target_width - new_w) // 2
        canvas = np.zeros((self.target_height, self.target_width, 3), dtype=np.uint8)
        canvas[top:top+new_h, left:left+new_w] = resized
        return canvas

    def loop_cam(self, cam_type):
        cap = self.visual_cap if cam_type == "visual" else self.thermal_cap
        key = 'frame' if cam_type == "visual" else 'thermal_frame'
        url = self.visual_url if cam_type == "visual" else self.thermal_url

        while True:
            if cap is None or not cap.isOpened():
                logger.warning(
                    "%s feed lost. Reconnecting in %s s…",
                    cam_type.capitalize(), self.retry_delay
                )
                time.sleep(self.retry_delay)
                cap = self.try_open_camera(url, cam_type)

            ret, frame = cap.read() if cap else (False, None)
            logger.debug(
                "[%s] ret=%s, frame=%s",
                cam_type, ret, 'Yes' if frame is not None else 'None'
            )

            if ret and frame is not None:
                resized = self.resize_frame(frame)
                if resized is not None:
                    self.frames[key] = resized
                    logger.debug("[%s] Frame updated in shared dict", cam_type)


def capture_frames(
    visual_rtsp_url, thermal_rtsp_url,
    target_height=480, target_width=640,
    retry_delay=5, timeout=10, shared_dict=None
):
    """
    Entry point for face_ui.py
    """
    manager = CameraStreamManager(
        visual_url=visual_rtsp_url,
        thermal_url=thermal_rtsp_url,
        target_height=target_height,
        target_width=target_width,
        retry_delay=retry_delay,
        timeout=timeout,
        shared_dict=shared_dict
    )
    return manager.frames
