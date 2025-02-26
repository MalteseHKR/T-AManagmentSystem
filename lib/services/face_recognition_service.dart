import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';

class FaceRecognitionService {
  late final FaceDetector _faceDetector;

  FaceRecognitionService() {
    // Create detector with platform-specific options
    _faceDetector = FaceDetector(
      options: FaceDetectorOptions(
        enableLandmarks: true,
        enableClassification: true,
        enableTracking: true,
        minFaceSize: Platform.isIOS ? 0.1 : 0.15, // Lower threshold for iOS
        performanceMode: Platform.isIOS ? FaceDetectorMode.fast : FaceDetectorMode.accurate,
      ),
    );
  }

  Future<bool> validateFace(File imageFile) async {
    try {
      // Print debugging information
      print('Starting face validation on ${Platform.isIOS ? 'iOS' : 'Android'}');
      print('Image file path: ${imageFile.path}');
      print('Image file size: ${await imageFile.length()} bytes');
      
      // Add small delay for iOS to ensure the file is fully written
      if (Platform.isIOS) {
        await Future.delayed(Duration(milliseconds: 300));
      }
      
      // Prepare input image based on platform
      final InputImage inputImage = await _prepareInputImage(imageFile);
      
      // First detection attempt
      var faces = await _faceDetector.processImage(inputImage);
      print('Face detection completed. Found ${faces.length} faces');
      
      // For iOS only: If no faces detected, try again with more lenient settings
      if (faces.isEmpty && Platform.isIOS) {
        print('iOS first attempt failed, trying with alternative settings...');
        
        // Create a temporary face detector with more lenient settings
        final lenientDetector = FaceDetector(
          options: FaceDetectorOptions(
            enableLandmarks: false,
            enableClassification: false,
            enableTracking: false,
            minFaceSize: 0.05,
            performanceMode: FaceDetectorMode.fast,
          ),
        );
        
        try {
          faces = await lenientDetector.processImage(inputImage);
          print('Second attempt completed. Found ${faces.length} faces');
          await lenientDetector.close();
        } catch (e) {
          print('Second detection attempt error: $e');
          await lenientDetector.close();
        }
      }
      
      // If no faces detected or multiple faces detected, return false
      if (faces.isEmpty) {
        print('Face validation failed: No face detected');
        return false;
      }
      
      if (faces.length > 1) {
        print('Face validation failed: Multiple faces detected (${faces.length})');
        return false;
      }
      
      // Get the first (and only) detected face
      final Face face = faces.first;
      
      // Check if the face is looking at the camera (head is not tilted too much)
      // Slightly more lenient on iOS
      final double maxAngle = Platform.isIOS ? 20.0 : 15.0;
      
      if (face.headEulerAngleY != null && 
          (face.headEulerAngleY! < -maxAngle || face.headEulerAngleY! > maxAngle)) {
        print('Face validation failed: Head is tilted too much horizontally (${face.headEulerAngleY})');
        return false;
      }
      
      if (face.headEulerAngleZ != null && 
          (face.headEulerAngleZ! < -maxAngle || face.headEulerAngleZ! > maxAngle)) {
        print('Face validation failed: Head is tilted too much vertically (${face.headEulerAngleZ})');
        return false;
      }
      
      // Check if eyes are open - less strict on iOS
      final double minEyeOpenProbability = Platform.isIOS ? 0.3 : 0.5;
      
      if (face.leftEyeOpenProbability != null && 
          face.rightEyeOpenProbability != null) {
        print('Eye open probabilities: Left: ${face.leftEyeOpenProbability}, Right: ${face.rightEyeOpenProbability}');
        
        if (face.leftEyeOpenProbability! < minEyeOpenProbability || 
            face.rightEyeOpenProbability! < minEyeOpenProbability) {
          print('Face validation failed: Eyes are not fully open');
          return false;
        }
      }
      
      // Check if face is taking up enough of the frame
      final double faceSize = face.boundingBox.width * face.boundingBox.height;
      final double imageWidth = inputImage.metadata?.size?.width ?? 0.0;
      final double imageHeight = inputImage.metadata?.size?.height ?? 0.0;
      final double imageSize = imageWidth * imageHeight;
      
      // Use a lower threshold for iOS
      final double minFaceSizeRatio = Platform.isIOS ? 0.05 : 0.1;
      
      print('Face size: $faceSize, Image size: $imageSize, Ratio: ${imageSize > 0 ? (faceSize / imageSize) : "N/A"}');
      
      if (imageSize > 0 && (faceSize / imageSize) < minFaceSizeRatio) {
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
  
  Future<InputImage> _prepareInputImage(File imageFile) async {
    if (Platform.isIOS) {
      try {
        // Load the image using the image package
        final bytes = await imageFile.readAsBytes();
        final image = img.decodeImage(bytes);
        
        if (image != null) {
          // Print image details for debugging
          print('Original image dimensions: ${image.width} x ${image.height}');
          
          // Resize the image to improve detection on iOS (if needed)
          final int maxDimension = 1280; // Good balance for face detection
          img.Image resizedImage;
          
          if (image.width > maxDimension || image.height > maxDimension) {
            if (image.width > image.height) {
              resizedImage = img.copyResize(
                image,
                width: maxDimension,
                height: (image.height * maxDimension / image.width).round(),
              );
            } else {
              resizedImage = img.copyResize(
                image,
                width: (image.width * maxDimension / image.height).round(),
                height: maxDimension,
              );
            }
            print('Resized image dimensions: ${resizedImage.width} x ${resizedImage.height}');
          } else {
            resizedImage = image;
          }
          
          // Use yuv format for iOS (often works better with ML Kit)
          final processedImage = img.copyRotate(resizedImage, angle: 0); // Ensure orientation
          
          // Save the processed image to a temporary file
          final tempDir = await getTemporaryDirectory();
          final tempFile = File('${tempDir.path}/processed_face_image.jpg');
          await tempFile.writeAsBytes(img.encodeJpg(processedImage, quality: 90));
          
          print('Processed image saved to: ${tempFile.path}');
          
          
          return InputImage.fromFile(tempFile);
        }
      } catch (e) {
        print('Error preprocessing image for iOS: $e');
        // Fall back to direct file input
      }
    }
    
    // Default case (Android or if iOS processing fails)
    return InputImage.fromFilePath(imageFile.path);
  }

  void dispose() {
    _faceDetector.close();
  }
}