import os
import requests
import face_recognition

# Directory to store downloaded face images
upload_folder = "/home/cdadmin/Desktop/FaceRecognition/uploads"
new_faces_url = "http://192.168.10.10:5000/get_new_faces"

def fetch_new_faces():
    response = requests.get(new_faces_url)
    if response.status_code == 200:
        return response.json()
    return []

def update_known_faces():
    new_faces = fetch_new_faces()
    for face_file in new_faces:
        file_path = os.path.join(upload_folder, face_file)
        if os.path.exists(file_path):
            image = face_recognition.load_image_file(file_path)
            face_encodings = face_recognition.face_encodings(image)
            if face_encodings:
                known_face_encodings.append(face_encodings[0])
                name_surname_id = os.path.splitext(face_file)[0].split()
                user_id = name_surname_id[-1]  # Assuming ID is the last part
                name_surname = " ".join(name_surname_id[:-1])  # Join the rest as name and surname
                known_face_names.append(name_surname)
        else:
            print(f"File not found: {file_path}")

def recognize_faces(frame, thermal_frame, known_face_encodings, known_face_names, server_url):
    # Face recognition logic here
    # ...
    return face_names