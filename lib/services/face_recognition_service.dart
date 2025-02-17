import 'dart:io';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:image/image.dart' as img;

class FaceRecognitionService {
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      enableLandmarks: true,
      enableClassification: true,
      enableTracking: true,
      minFaceSize: 0.15,
      performanceMode: FaceDetectorMode.accurate,
    ),
  );

  Future<bool> validateFace(File imageFile) async {
    try {
      // Convert image file to InputImage
      final inputImage = InputImage.fromFile(imageFile);
      
      // Detect faces in the image
      final List<Face> faces = await _faceDetector.processImage(inputImage);
      
      // If no faces detected or multiple faces detected, return false
      if (faces.isEmpty || faces.length > 1) {
        print('Face validation failed: ${faces.isEmpty ? "No face detected" : "Multiple faces detected"}');
        return false;
      }
      
      // Get the first (and only) detected face
      final Face face = faces.first;
      
      // Check if the face is looking at the camera (head is not tilted too much)
      if (face.headEulerAngleY != null && 
          (face.headEulerAngleY! < -15 || face.headEulerAngleY! > 15)) {
        print('Face validation failed: Head is tilted too much horizontally');
        return false;
      }
      
      if (face.headEulerAngleZ != null && 
          (face.headEulerAngleZ! < -15 || face.headEulerAngleZ! > 15)) {
        print('Face validation failed: Head is tilted too much vertically');
        return false;
      }
      
      // Check if eyes are open
      if (face.leftEyeOpenProbability != null && 
          face.rightEyeOpenProbability != null) {
        if (face.leftEyeOpenProbability! < 0.5 || 
            face.rightEyeOpenProbability! < 0.5) {
          print('Face validation failed: Eyes are not fully open');
          return false;
        }
      }
      
      // Check if face is taking up enough of the frame
      final double faceSize = face.boundingBox.width * face.boundingBox.height;
      final double imageWidth = inputImage.metadata?.size.width?.toDouble() ?? 0.0;
      final double imageHeight = inputImage.metadata?.size.height?.toDouble() ?? 0.0;
      final double imageSize = imageWidth * imageHeight;
      
      if (imageSize > 0 && (faceSize / imageSize) < 0.1) {
        print('Face validation failed: Face is too small in frame');
        return false;
      }

      print('Face validation successful');
      return true;
    } catch (e) {
      print('Error validating face: $e');
      return false;
    }
  }
  
  void dispose() {
    _faceDetector.close();
  }
}