import os
import cv2
import face_recognition
import pickle
import dlib
import numpy as np
import re

# ✅ Directories for storing face data
test_faces_folder = "/home/cdadmin/remote_documents"
known_faces_folder = "/home/cdadmin/Desktop/FaceRecognition/known_faces"

# ✅ Load Dlib’s shape predictor model
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LANDMARK_MODEL = os.path.join(BASE_DIR, "shape_predictor_68_face_landmarks.dat")

if not os.path.exists(LANDMARK_MODEL):
    raise RuntimeError(f"❌ ERROR: Missing landmark model file: {LANDMARK_MODEL}")

detector = dlib.get_frontal_face_detector()
predictor = dlib.shape_predictor(LANDMARK_MODEL)

# ✅ Lists to store known encodings & names
known_face_encodings = []
known_face_names = []

# ✅ Store last frame for motion detection
last_frame = None
motion_threshold = 5000  # Minimum pixel difference for detecting movement


def extract_user_id(name):
    """Extracts the user ID from the image filename.
    Assumes user ID is the numeric part right before the file extension."""
    base = os.path.splitext(name)[0]  # Remove file extension
    match = re.search(r'_(\d+)$', base)  # Match an underscore followed by digits at the end
    return int(match.group(1)) if match else None

def detect_blink(frame):
    """✅ Detects eye blinking to prevent fake face attacks."""
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = detector(gray)

    for face in faces:
        landmarks = predictor(gray, face)
        left_eye = [landmarks.part(n) for n in range(36, 42)]
        right_eye = [landmarks.part(n) for n in range(42, 48)]

        left_eye_ratio = get_eye_aspect_ratio(left_eye)
        right_eye_ratio = get_eye_aspect_ratio(right_eye)

        avg_eye_ratio = (left_eye_ratio + right_eye_ratio) / 2

        if avg_eye_ratio < 0.22:  
            return True  # ✅ Blinking detected

    return False  # 🚫 No blinking detected (Possible fake image)

def get_eye_aspect_ratio(eye):
    """✅ Calculates the eye aspect ratio (EAR) for blink detection."""
    A = np.linalg.norm(np.array([eye[1].x, eye[1].y]) - np.array([eye[5].x, eye[5].y]))
    B = np.linalg.norm(np.array([eye[2].x, eye[2].y]) - np.array([eye[4].x, eye[4].y]))
    C = np.linalg.norm(np.array([eye[0].x, eye[0].y]) - np.array([eye[3].x, eye[3].y]))
    
    return (A + B) / (2.0 * C) if C != 0 else 0

def detect_motion(frame):
    """✅ Detects slight movements in a face to confirm it's not a photo."""
    global last_frame

    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    if last_frame is None:
        last_frame = gray
        return False  

    frame_diff = cv2.absdiff(last_frame, gray)
    motion_score = np.sum(frame_diff)

    last_frame = gray

    return motion_score > motion_threshold  # ✅ Returns True if movement is detected

def detect_reflection(frame):
    """✅ Detects natural light reflection on the face to prevent photo spoofing."""
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)

    _, thresh = cv2.threshold(blurred, 230, 255, cv2.THRESH_BINARY)
    bright_pixels = cv2.countNonZero(thresh)

    return bright_pixels > 500  # ✅ If enough bright pixels, likely a real face

def update_known_faces():
    """✅ Reads images, extracts encodings, and saves them."""
    global known_face_encodings, known_face_names  

    encodings_path = os.path.join(known_faces_folder, 'known_face_encodings.pkl')
    names_path = os.path.join(known_faces_folder, 'known_face_names.pkl')

    known_face_encodings = []
    known_face_names = []

    test_faces = [f for f in os.listdir(test_faces_folder) if os.path.isfile(os.path.join(test_faces_folder, f))]

    if not test_faces:
        print("⚠️ No face images found in training folder! Ensure images exist.")
        return

    for face_file in test_faces:
        file_path = os.path.join(test_faces_folder, face_file)

        if os.path.exists(file_path):
            print(f"🔍 Processing {face_file}...")

            image = cv2.imread(file_path)
            if image is None:
                print(f"❌ ERROR: Unable to read {file_path}. Skipping.")
                continue

            rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
            face_locations = face_recognition.face_locations(rgb_image, model="hog")
            face_encodings = face_recognition.face_encodings(rgb_image, face_locations)

            if not face_encodings:
                print(f"🚫 No face found in {face_file}, skipping.")
                continue

            try:
                filename = os.path.splitext(face_file)[0]
                match = re.match(r'^([A-Za-z]+)_([A-Za-z]+)\d*_(\d+)$', filename)

                if match:
                    first_name = match.group(1)
                    last_name = match.group(2)
                    emp_id = match.group(3)
                    display_name = f"{emp_id} - {first_name} {last_name}"
                else:
                    print(f"⚠️ Skipping file with invalid format: {face_file}")
                    continue  # ✅ This was missing before!

                if len(face_encodings) > 1:
                    print(f"⚠️ Multiple faces detected in {face_file}. Using the first encoding.")

                known_face_encodings.append(face_encodings[0])
                known_face_names.append(display_name)
                print(f"✅ Added: {display_name}")

            except Exception as e:
                print(f"⚠️ Error parsing or processing {face_file}: {e}")

    # ✅ Save the encodings and names
    with open(encodings_path, 'wb') as f:
        pickle.dump(known_face_encodings, f)
    with open(names_path, 'wb') as f:
        pickle.dump(known_face_names, f)

    print(f"✅ Updated known faces: {len(known_face_encodings)} loaded.")

def load_known_faces():
    """✅ Loads known faces and names from saved pickle files, auto-updates if missing or empty."""
    global known_face_encodings, known_face_names  

    encodings_path = os.path.join(known_faces_folder, 'known_face_encodings.pkl')
    names_path = os.path.join(known_faces_folder, 'known_face_names.pkl')

    need_update = False

    # Check if pickle files exist
    if not os.path.exists(encodings_path) or not os.path.exists(names_path):
        print("⚠️ No known face data found. Attempting to update from test faces folder...")
        need_update = True
    else:
        try:
            with open(encodings_path, 'rb') as f:
                known_face_encodings = pickle.load(f)
            with open(names_path, 'rb') as f:
                known_face_names = pickle.load(f)

            # If loaded but empty, treat as failed
            if not known_face_encodings or not known_face_names:
                print("⚠️ Loaded face data is empty. Rebuilding known faces...")
                need_update = True
            else:
                print(f"✅ Loaded {len(known_face_encodings)} known faces.")
                print(f"🔍 Face Names: {known_face_names}")

        except (EOFError, pickle.UnpicklingError) as e:
            print(f"❌ ERROR: Corrupt pickle file. Rebuilding known faces. ({e})")
            known_face_encodings = []
            known_face_names = []
            need_update = True

    if need_update:
        update_known_faces()


def recognize_faces(frame, thermal_frame=None):
    """
    Recognizes faces in a video frame and optionally verifies them using a thermal frame.
    """
    face_names = []

    if frame is None:
        print("⚠️ ERROR: Frame is empty! Skipping recognition.")
        return face_names

    # Convert frame to RGB
    rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    
    # Detect faces
    face_locations = face_recognition.face_locations(rgb_frame, model="hog")
    face_encodings = face_recognition.face_encodings(rgb_frame, face_locations)

    for face_encoding, (top, right, bottom, left) in zip(face_encodings, face_locations):
        matches = face_recognition.compare_faces(known_face_encodings, face_encoding, tolerance=0.5)
        face_distances = face_recognition.face_distance(known_face_encodings, face_encoding)

        if len(face_distances) == 0:
            face_names.append("Unknown")
            continue

        best_match_index = np.argmin(face_distances)
        name = "Unknown"

        if matches[best_match_index]:
            name = known_face_names[best_match_index]

            if thermal_frame is not None:
                # ✅ Add Thermal Verification (Optional)
                h, w, _ = thermal_frame.shape
                top, bottom, left, right = max(0, top), min(h, bottom), max(0, left), min(w, right)
                face_thermal_region = thermal_frame[top:bottom, left:right]

                if face_thermal_region is None or face_thermal_region.size == 0:
                    print(f"⚠️ ERROR: Thermal frame is empty! Skipping {name}.")
                    continue

                # Convert to grayscale for temperature analysis
                thermal_gray = cv2.cvtColor(face_thermal_region, cv2.COLOR_BGR2GRAY)
                avg_thermal_intensity = np.mean(thermal_gray)

                if avg_thermal_intensity < 100:
                    print(f"🚫 REJECTED: {name} (No valid heat signature detected!)")
                    name = "Fake Face"
                else:
                    print(f"✅ VERIFIED: {name} (Human detected in thermal)")

        face_names.append(name)

    return face_names


