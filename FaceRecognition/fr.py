import os
import cv2
import face_recognition
import pickle
import numpy as np

# Directories
test_faces_folder = "/home/cdadmin/remote_documents"
known_faces_folder = "/home/cdadmin/Desktop/FaceRecognition/known_faces"

# Global lists to store known face encodings and names
known_face_encodings = []
known_face_names = []

# ✅ **Thermal Detection Thresholds** (Adjust based on real-world testing)
THERMAL_MEAN_THRESHOLD = 100  
THERMAL_VARIANCE_THRESHOLD = 50  
THERMAL_MAX_THRESHOLD = 150  

def extract_user_id(name):
    """
    Extracts the user ID from the image filename.
    Example: "Daniel Mercieca4 MD286" -> Extracts "MD286" as user_id.
    """
    parts = name.split()
    return parts[-1] if len(parts) > 1 else "Unknown"


def filter_human_temp(thermal_image, min_temp=35, max_temp=40):
    """✅ Filters the thermal image to keep only human-range temperatures (35-40°C)."""
    mask = cv2.inRange(thermal_image, min_temp, max_temp)  # Keep only human-range temperatures
    return cv2.bitwise_and(thermal_image, thermal_image, mask=mask)

def update_known_faces(model='hog'):
    """
    Reads images from test_faces_folder, extracts face encodings,
    and saves them with names including their ID.
    """
    global known_face_encodings, known_face_names  

    encodings_path = os.path.join(known_faces_folder, 'known_face_encodings.pkl')
    names_path = os.path.join(known_faces_folder, 'known_face_names.pkl')

    # Delete existing encodings to prevent duplicates
    if os.path.exists(encodings_path):
        os.remove(encodings_path)
    if os.path.exists(names_path):
        os.remove(names_path)

    # Clear in-memory lists before updating
    known_face_encodings = []
    known_face_names = []

    test_faces = [f for f in os.listdir(test_faces_folder) if os.path.isfile(os.path.join(test_faces_folder, f))]

    for face_file in test_faces:
        file_path = os.path.join(test_faces_folder, face_file)

        if os.path.exists(file_path):
            print(f"🔍 Processing {face_file}...")

            image = cv2.imread(file_path)
            if image is None:
                print(f"❌ ERROR: Unable to read {file_path}. Skipping.")
                continue

            if image.shape[0] > 800 or image.shape[1] > 800:
                image = cv2.resize(image, (800, 800))

            rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
            del image  

            face_locations = face_recognition.face_locations(rgb_image, model=model)
            face_encodings = face_recognition.face_encodings(rgb_image, face_locations)
            del rgb_image  

            if face_encodings:
                name_with_id = os.path.splitext(face_file)[0]  
                known_face_encodings.append(face_encodings[0])
                known_face_names.append(name_with_id)
                print(f"✅ Added: {name_with_id}")

    # Save to pickle files
    with open(encodings_path, 'wb') as f:
        pickle.dump(known_face_encodings, f)
    with open(names_path, 'wb') as f:
        pickle.dump(known_face_names, f)

    print(f"✅ Updated known faces: {len(known_face_encodings)} loaded.")

def load_known_faces():
    """
    Loads known faces and names from saved pickle files.
    """
    global known_face_encodings, known_face_names  

    encodings_path = os.path.join(known_faces_folder, 'known_face_encodings.pkl')
    names_path = os.path.join(known_faces_folder, 'known_face_names.pkl')

    if os.path.exists(encodings_path) and os.path.exists(names_path):
        try:
            with open(encodings_path, 'rb') as f:
                known_face_encodings = pickle.load(f)
            with open(names_path, 'rb') as f:
                known_face_names = pickle.load(f)

            print(f"✅ Loaded {len(known_face_encodings)} known faces.")
            print(f"🔍 Face Names: {known_face_names}")

        except (EOFError, pickle.UnpicklingError) as e:
            print(f"❌ ERROR: Corrupt pickle file. Re-run update_known_faces(). ({e})")
            known_face_encodings = []
            known_face_names = []
    else:
        print("⚠️ No known faces found! Run update_known_faces() first.")
        known_face_encodings = []
        known_face_names = []

def recognize_faces(frame, thermal_frame, known_face_encodings, known_face_names, model='hog'):
    """
    Recognizes faces in a video frame, verifies them using the thermal frame,
    and returns the names (with user IDs) only if the person is physically present.
    """
    face_names = []

    if frame is None or thermal_frame is None or frame.size == 0 or thermal_frame.size == 0:
        print("⚠️ ERROR: One or both frames are empty! Skipping recognition.")
        return face_names

    if frame.shape[:2] != thermal_frame.shape[:2]:
        print(f"⚠️ ERROR: Visual and thermal frames are different sizes! "
              f"Visual: {frame.shape}, Thermal: {thermal_frame.shape}")
        return face_names

    rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    face_locations = face_recognition.face_locations(rgb_frame, model=model)
    face_encodings = face_recognition.face_encodings(rgb_frame, face_locations)

    for face_encoding, (top, right, bottom, left) in zip(face_encodings, face_locations):
        if not known_face_encodings:
            print("⚠️ WARNING: No known face encodings available. Reloading faces...")
            load_known_faces()
            if not known_face_encodings:
                face_names.append("Unknown")
                continue

        matches = face_recognition.compare_faces(known_face_encodings, face_encoding, tolerance=0.5)  # Lower tolerance
        face_distances = face_recognition.face_distance(known_face_encodings, face_encoding)

        if len(face_distances) == 0:
            print("⚠️ WARNING: No face distances calculated, marking as Unknown.")
            face_names.append("Unknown")
            continue

        best_match_index = np.argmin(face_distances)
        name = "Unknown"

        if matches[best_match_index]:
            name = known_face_names[best_match_index]  

            # ✅ **Ensure bounding box is within frame dimensions**
            h, w, _ = thermal_frame.shape
            top = max(0, top)
            bottom = min(h, bottom)
            left = max(0, left)
            right = min(w, right)

            face_thermal_region = thermal_frame[top:bottom, left:right]

            if face_thermal_region is None or face_thermal_region.size == 0:
                print(f"⚠️ ERROR: face_thermal_region is empty! Skipping {name}.")
                continue

            # ✅ **Convert thermal region to grayscale**
            thermal_gray = cv2.cvtColor(face_thermal_region, cv2.COLOR_BGR2GRAY)

            # ✅ **Analyze heat levels**
            avg_thermal_intensity = np.mean(thermal_gray)
            max_thermal_intensity = np.max(thermal_gray)
            thermal_variance = np.var(thermal_gray)

            # ✅ **Validate human presence using heat checks**
            if (avg_thermal_intensity < THERMAL_MEAN_THRESHOLD or
                thermal_variance < THERMAL_VARIANCE_THRESHOLD or
                max_thermal_intensity < THERMAL_MAX_THRESHOLD):

                print(f"🚫 REJECTED: {name} (No valid heat signature detected!)")
                name = "Fake Face"
            else:
                print(f"✅ VERIFIED: {name} (Human detected in thermal)")

        face_names.append(name)

    return face_names
