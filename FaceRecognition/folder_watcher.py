import time
import os
import multiprocessing
from watchdog.observers.polling import PollingObserver
from watchdog.events import FileSystemEventHandler
from fr import update_known_faces, load_known_faces

# Directory to monitor
WATCH_DIRECTORY ="/home/cdadmin/remote_documents"

# Lock to prevent multiple processes from running `update_known_faces()` at the same time
update_lock = multiprocessing.Lock()
update_running = multiprocessing.Value('b', False)  # Flag to track if update is running

class Watcher:
    """Watches the folder for new images and triggers face updates."""
    def __init__(self):
        self.observer = PollingObserver()  # Use PollingObserver instead of Observer

    def run(self):
        """Starts the folder watcher."""
        event_handler = Handler()
        self.observer.schedule(event_handler, WATCH_DIRECTORY, recursive=False)
        self.observer.start()
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            self.observer.stop()
        self.observer.join()


class Handler(FileSystemEventHandler):
    """Handles new image events and triggers `update_known_faces()`."""
    def on_created(self, event):
        """Triggered when a new file is added to `test_faces/`."""
        if event.is_directory:
            return None  # Ignore directories

        file_path = event.src_path
        file_name = os.path.basename(file_path)

        # Only process image files
        if not file_name.lower().endswith(('.jpg', '.jpeg', '.png')):
            print(f"⚠️ WARNING: Ignored non-image file {file_name}")
            return

        print(f"📸 New image detected: {file_name}")

        # Ensure the file is fully copied before processing
        last_size = -1
        for _ in range(5):  # Retry for up to 10 seconds
            try:
                current_size = os.path.getsize(file_path)
                if current_size == last_size and current_size > 0:
                    break  # File has finished copying
                last_size = current_size
                print(f"⏳ Waiting for file to finish copying: {file_name}")
                time.sleep(2)
            except FileNotFoundError:
                print(f"⚠️ WARNING: File {file_name} not found, waiting...")
                time.sleep(2)

        with update_lock:
            if update_running.value:
                print("⚠️ WARNING: Face update is already running. Skipping duplicate.")
                return
            
            update_running.value = True  # Set flag to prevent multiple runs

            print(f"✅ Processing image: {file_path}")

            # Start `update_known_faces()` in a separate process
            process = multiprocessing.Process(target=update_known_faces)
            process.start()
            process.join()

            print("🔄 Reloading known faces after update...")
            load_known_faces()  # Ensure faces are correctly reloaded

            update_running.value = False  # Reset flag

if __name__ == '__main__':
    print("🔄 Starting folder watcher...")
    watcher = Watcher()
    watcher.run()

