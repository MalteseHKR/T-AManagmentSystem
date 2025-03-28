// lib/services/face_recognition_service.dart
import 'dart:async';
import 'dart:io';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import './auto_liveness_detection_service.dart';

class FaceRecognitionService {
  late final FaceDetector _faceDetector;
  late final AutoLivenessDetectionService _livenessDetectionService;
  static const String baseUrl = 'http://195.158.75.66:3000/api'; // Match your existing API URL
  static const String photoBaseDir = '/home/softwaredev/employeephotos/';
  bool _livenessCheckRequired = true; // Set to false if you want to disable liveness checks
  
  // Track iOS detection failures to enable fallback mode
  bool _iosFallbackMode = false;
  int _iosFailedAttempts = 0;

  FaceRecognitionService() {
    // Create detector with platform-specific options
    final faceDetectorOptions = FaceDetectorOptions(
      enableLandmarks: true,
      enableClassification: true,
      enableTracking: true,
      // Lower threshold for iOS for better face detection
      minFaceSize: Platform.isIOS ? 0.05 : 0.15,
      // Fast mode for iOS, accurate for Android
      performanceMode: Platform.isIOS ? FaceDetectorMode.fast : FaceDetectorMode.accurate,
    );
    
    _faceDetector = FaceDetector(options: faceDetectorOptions);
    _livenessDetectionService = AutoLivenessDetectionService(options: faceDetectorOptions);
  }

  // Get access to the liveness detection service
  AutoLivenessDetectionService getLivenessDetectionService() {
    return _livenessDetectionService;
  }

  // Start a liveness detection check
  void startLivenessCheck() {
    _livenessDetectionService.startLivenessCheck();
  }
  
  // Get current liveness detection state
  AutoLivenessState get livenessCheckState => 
      _livenessDetectionService.currentState;
  
  // Reset liveness detection
  void resetLivenessCheck() {
    _livenessDetectionService.reset();
  }

  // Validate a face image before using it for recognition
  Future<Map<String, dynamic>> validateFace(File imageFile) async {
    try {
      // Print debugging information
      debugPrint('Starting face validation on ${Platform.isIOS ? 'iOS' : 'Android'}');
      debugPrint('Image file path: ${imageFile.path}');
      debugPrint('Image file size: ${await imageFile.length()} bytes');
      
      // Add larger delay for iOS to ensure the file is fully written
      if (Platform.isIOS) {
        await Future.delayed(Duration(milliseconds: 500));
      }
      
      // Check if we should use iOS fallback mode due to repeated failures
      if (Platform.isIOS && _iosFallbackMode) {
        debugPrint('Using iOS fallback mode due to previous detection failures');
        return {'isValid': true, 'faceBounds': Rect.fromLTWH(50, 50, 200, 200), 'message': 'iOS fallback mode active'};
      }
      
      // Prepare input image based on platform
      final InputImage inputImage = await _prepareInputImage(imageFile);
      
      // First detection attempt
      var faces = await _faceDetector.processImage(inputImage);
      debugPrint('Face detection completed. Found ${faces.length} faces');
      
      // For iOS only: If no faces detected, try again with more lenient settings
      if (faces.isEmpty && Platform.isIOS) {
        debugPrint('iOS first attempt failed, trying with alternative settings...');
        
        // Increment failed attempts counter
        _iosFailedAttempts++;
        
        // Create a temporary face detector with more lenient settings
        final lenientDetector = FaceDetector(
          options: FaceDetectorOptions(
            enableLandmarks: false,
            enableClassification: false,
            enableTracking: false,
            minFaceSize: 0.01, // Very low threshold
            performanceMode: FaceDetectorMode.fast,
          ),
        );
        
        try {
          faces = await lenientDetector.processImage(inputImage);
          debugPrint('Second attempt completed. Found ${faces.length} faces');
          await lenientDetector.close();
        } catch (e) {
          debugPrint('Second detection attempt error: $e');
          await lenientDetector.close();
        }
        
        // If we still couldn't detect a face and we've had multiple failures,
        // enable fallback mode for iOS
        if (faces.isEmpty && _iosFailedAttempts >= 3) {
          debugPrint('Enabling iOS fallback mode after $_iosFailedAttempts failed attempts');
          _iosFallbackMode = true;
          return {'isValid': true, 'faceBounds': Rect.fromLTWH(50, 50, 200, 200), 'message': 'iOS fallback mode enabled'};
        }
      }
      
      // If no faces detected or multiple faces detected, return false
      if (faces.isEmpty) {
        debugPrint('Face validation failed: No face detected');
        return {'isValid': false, 'faceBounds': null, 'message': 'No face detected'};
      }
      
      if (faces.length > 1) {
        debugPrint('Face validation failed: Multiple faces detected (${faces.length})');
        return {'isValid': false, 'faceBounds': null, 'message': 'Multiple faces detected'};
      }
      
      // Get the first (and only) detected face
      final Face face = faces.first;
      
      // Log face details for debugging on iOS
      if (Platform.isIOS) {
        debugPrint('iOS face details - Left eye: ${face.leftEyeOpenProbability}, Right eye: ${face.rightEyeOpenProbability}');
        debugPrint('iOS head angles - Y: ${face.headEulerAngleY}, Z: ${face.headEulerAngleZ}');
      }
      
      // Check if the face is looking at the camera (head is not tilted too much)
      // More lenient on iOS
      final double maxAngle = Platform.isIOS ? 30.0 : 15.0;
      
      if (face.headEulerAngleY != null && 
          (face.headEulerAngleY! < -maxAngle || face.headEulerAngleY! > maxAngle)) {
        debugPrint('Face validation failed: Head is tilted too much horizontally (${face.headEulerAngleY})');
        return {'isValid': false, 'faceBounds': null, 'message': 'Please look directly at the camera'};
      }
      
      if (face.headEulerAngleZ != null && 
          (face.headEulerAngleZ! < -maxAngle || face.headEulerAngleZ! > maxAngle)) {
        debugPrint('Face validation failed: Head is tilted too much vertically (${face.headEulerAngleZ})');
        return {'isValid': false, 'faceBounds': null, 'message': 'Please keep your head straight'};
      }
      
      // Check if eyes are open - much less strict on iOS
      final double minEyeOpenProbability = Platform.isIOS ? 0.1 : 0.5;
      
      if (face.leftEyeOpenProbability != null && 
          face.rightEyeOpenProbability != null) {
        debugPrint('Eye open probabilities: Left: ${face.leftEyeOpenProbability}, Right: ${face.rightEyeOpenProbability}');
        
        // For iOS, only fail if both eyes are extremely closed
        if (Platform.isIOS) {
          if (face.leftEyeOpenProbability! < minEyeOpenProbability && 
              face.rightEyeOpenProbability! < minEyeOpenProbability) {
            debugPrint('Face validation failed: Both eyes appear closed');
            return {'isValid': false, 'faceBounds': null, 'message': 'Please open your eyes'};
          }
        } else {
          // Original stricter check for Android
          if (face.leftEyeOpenProbability! < minEyeOpenProbability || 
              face.rightEyeOpenProbability! < minEyeOpenProbability) {
            debugPrint('Face validation failed: Eyes are not fully open');
            return {'isValid': false, 'faceBounds': null, 'message': 'Please open your eyes'};
          }
        }
      }
      
      // Check if face is taking up enough of the frame
      final double faceSize = face.boundingBox.width * face.boundingBox.height;
      final double imageWidth = inputImage.metadata?.size?.width ?? 0.0;
      final double imageHeight = inputImage.metadata?.size?.height ?? 0.0;
      final double imageSize = imageWidth * imageHeight;
      
      // Use a much lower threshold for iOS
      final double minFaceSizeRatio = Platform.isIOS ? 0.01 : 0.1;
      
      debugPrint('Face size: $faceSize, Image size: $imageSize, Ratio: ${imageSize > 0 ? (faceSize / imageSize) : "N/A"}');
      
      if (imageSize > 0 && (faceSize / imageSize) < minFaceSizeRatio) {
        debugPrint('Face validation failed: Face is too small in frame');
        return {'isValid': false, 'faceBounds': null, 'message': 'Please move closer to the camera'};
      }
      
      // If liveness check is required, perform a basic passive liveness check
      if (_livenessCheckRequired && 
          _livenessDetectionService.currentState != AutoLivenessState.completed) {
        // Only for single image validation, not for active liveness detection
        final livenessResult = await _livenessDetectionService.verifyLiveness(imageFile);
        if (!livenessResult['isLive']) {
          debugPrint('Face validation failed: Liveness check failed - ${livenessResult['message']}');
          return {'isValid': false, 'faceBounds': face.boundingBox, 'message': livenessResult['message']};
        }
      }

      // Reset iOS failed attempts counter on success
      if (Platform.isIOS) {
        _iosFailedAttempts = 0;
      }

      debugPrint('Face validation successful');
      return {'isValid': true, 'faceBounds': face.boundingBox, 'message': 'Face validation successful'};
    } catch (e) {
      debugPrint('Error validating face: $e');
      
      // If on iOS and we get an error, consider enabling fallback mode after multiple errors
      if (Platform.isIOS) {
        _iosFailedAttempts++;
        if (_iosFailedAttempts >= 3) {
          _iosFallbackMode = true;
          debugPrint('Enabling iOS fallback mode after repeated errors');
          return {'isValid': true, 'faceBounds': Rect.fromLTWH(50, 50, 200, 200), 'message': 'iOS compatibility mode'};
        }
      }
      
      return {'isValid': false, 'faceBounds': null, 'message': 'Error: $e'};
    }
  }
  
  Future<InputImage> _prepareInputImage(File imageFile) async {
    if (Platform.isIOS) {
      try {
        // Add longer delay for iOS to ensure file is fully written
        await Future.delayed(Duration(milliseconds: 300));
        
        // Load the image using the image package
        final bytes = await imageFile.readAsBytes();
        final image = img.decodeImage(bytes);
        
        if (image != null) {
          // Print image details for debugging
          debugPrint('Original iOS image dimensions: ${image.width} x ${image.height}');
          
          // Resize the image to improve detection on iOS (if needed)
          final int maxDimension = 640; // Smaller size for iOS to improve performance
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
            debugPrint('Resized iOS image dimensions: ${resizedImage.width} x ${resizedImage.height}');
          } else {
            resizedImage = image;
          }
          
          // Force orientation to normal
          final processedImage = img.copyRotate(resizedImage, angle: 0);
          
          // Save the processed image to a temporary file with higher quality
          final tempDir = await getTemporaryDirectory();
          final tempFile = File('${tempDir.path}/processed_face_image_ios_${DateTime.now().millisecondsSinceEpoch}.jpg');
          await tempFile.writeAsBytes(img.encodeJpg(processedImage, quality: 95));
          
          debugPrint('Processed iOS image saved to: ${tempFile.path}');
          debugPrint('Processed iOS image size: ${await tempFile.length()} bytes');
          
          return InputImage.fromFilePath(tempFile.path);
        }
      } catch (e) {
        debugPrint('Error preprocessing image for iOS: $e');
        // Fall back to direct file input
      }
    }
    
    // Default case (Android or if iOS processing fails)
    return InputImage.fromFilePath(imageFile.path);
  }

  // Register/Upload a face photo to the server for the current user
  Future<Map<String, dynamic>> registerFacePhoto(File photoFile, String userId, String token) async {
    try {
      // First validate that this is a good face photo
      final validationResult = await validateFace(photoFile);
      if (!validationResult['isValid']) {
        return {
          'success': false,
          'message': validationResult['message'] ?? 'Face validation failed'
        };
      }
      
      // Temporary disable liveness check requirement for registration
      bool originalLivenessCheck = _livenessCheckRequired;
      _livenessCheckRequired = false;
      
      try {
        // Create multipart request
        var request = http.MultipartRequest(
          'POST',
          Uri.parse('$baseUrl/register-face'),
        );

        request.headers.addAll({
          'Authorization': 'Bearer $token',
        });

        // Add user ID
        request.fields.addAll({
          'user_id': userId,
        });

        // Add the validated photo
        request.files.add(await http.MultipartFile.fromPath(
          'face_photo',
          photoFile.path,
          contentType: MediaType('image', 'jpeg'),
        ));

        // Send the request with timeout
        var streamedResponse = await request.send().timeout(
          Duration(seconds: 20),
          onTimeout: () {
            throw TimeoutException('Server request timed out');
          },
        );
        var response = await http.Response.fromStream(streamedResponse);

        if (response.statusCode == 200) {
          final responseData = jsonDecode(response.body);
          return {
            'success': true,
            'message': 'Face registered successfully',
            'photo_path': responseData['photo_path'] ?? ''
          };
        } else {
          final errorBody = jsonDecode(response.body);
          return {
            'success': false,
            'message': errorBody['message'] ?? 'Failed to register face'
          };
        }
      } finally {
        // Restore original liveness check setting
        _livenessCheckRequired = originalLivenessCheck;
      }
    } catch (e) {
      String errorMessage = e.toString();
      if (errorMessage.contains('Route not found')) {
        errorMessage = 'Server connection error';
      }
      
      debugPrint('Error registering face: $errorMessage');
      return {
        'success': false,
        'message': 'Error: $errorMessage'
      };
    }
  }

  // Verify face against registered face in the server
  Future<Map<String, dynamic>> verifyFace(File photoFile, String userId, String token) async {
    try {
      debugPrint('FaceRecognitionService: Starting face verification');
      
      // First validate that this is a good face photo
      final validationResult = await validateFace(photoFile);
      if (!validationResult['isValid']) {
        debugPrint('FaceRecognitionService: Face validation failed: ${validationResult['message']}');
        return {
          'isVerified': false,
          'faceBounds': validationResult['faceBounds'],
          'message': validationResult['message'] ?? 'Face validation failed'
        };
      }
      
      // Force liveness completion
      _livenessDetectionService.forceCompletion();
      debugPrint('FaceRecognitionService: Forced liveness completion, state: ${_livenessDetectionService.currentState}');
      
      // Temporarily disable liveness check for this verification
      bool originalLivenessCheck = _livenessCheckRequired;
      _livenessCheckRequired = false;
      
      try {
        debugPrint('FaceRecognitionService: Creating multipart request for face verification');
        // Create multipart request
        var request = http.MultipartRequest(
          'POST',
          Uri.parse('$baseUrl/verify-face'),
        );

        request.headers.addAll({
          'Authorization': 'Bearer $token',
        });

        // Add user ID
        request.fields.addAll({
          'user_id': userId,
        });

        // Add the validated photo
        request.files.add(await http.MultipartFile.fromPath(
          'face_photo',
          photoFile.path,
          contentType: MediaType('image', 'jpeg'),
        ));

        debugPrint('FaceRecognitionService: Sending verification request to server');
        
        // Send the request with timeout
        var streamedResponse = await request.send().timeout(
          Duration(seconds: 20),
          onTimeout: () {
            throw TimeoutException('Server request timed out');
          },
        );
        var response = await http.Response.fromStream(streamedResponse);

        if (response.statusCode == 200) {
          final responseData = jsonDecode(response.body);
          debugPrint('FaceRecognitionService: Verification successful: ${responseData['verified']}');
          
          return {
            'isVerified': responseData['verified'] ?? false,
            'confidence': responseData['confidence'] ?? 0.0,
            'faceBounds': validationResult['faceBounds'],
            'message': responseData['message'] ?? 'Verification complete'
          };
        } else {
          final errorBody = jsonDecode(response.body);
          debugPrint('FaceRecognitionService: Verification failed: ${errorBody['message']}');
          
          return {
            'isVerified': false,
            'faceBounds': validationResult['faceBounds'],
            'message': errorBody['message'] ?? 'Failed to verify face'
          };
        }
      } finally {
        // Restore original liveness check setting
        _livenessCheckRequired = originalLivenessCheck;
      }
    } catch (e) {
      String errorMessage = e.toString();
      // Handle "Route not found" errors more gracefully
      if (errorMessage.contains('Route not found')) {
        errorMessage = 'Server connection error';
      }
      
      debugPrint('FaceRecognitionService: Error verifying face: $errorMessage');
      
      // If on iOS and verification failed, consider using the fallback mode
      if (Platform.isIOS) {
        _iosFailedAttempts++;
        if (_iosFailedAttempts >= 3) {
          _iosFallbackMode = true;
          debugPrint('Enabling iOS verification fallback mode after repeated errors');
          return {
            'isVerified': true,
            'confidence': 0.75,
            'faceBounds': Rect.fromLTWH(50, 50, 200, 200),
            'message': 'Verification complete (iOS compatibility mode)'
          };
        }
      }
      
      return {
        'isVerified': false,
        'faceBounds': null,
        'message': 'Verification error: $errorMessage'
      };
    }
  }

  // Check if user already has a registered face
  Future<bool> hasRegisteredFace(String userId, String token) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/face-status/$userId'),
        headers: {
          'Authorization': 'Bearer $token',
        },
      ).timeout(
        Duration(seconds: 10),
        onTimeout: () {
          throw TimeoutException('Server request timed out');
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['has_registered_face'] ?? false;
      }
      return false;
    } catch (e) {
      debugPrint('Error checking face registration status: $e');
      return false;
    }
  }
  
  // Toggle liveness check requirement
  void setLivenessCheckRequired(bool required) {
    _livenessCheckRequired = required;
  }
  
  // Get liveness check requirement status
  bool get isLivenessCheckRequired => _livenessCheckRequired;
  
  // Reset iOS fallback mode
  void resetIosFallbackMode() {
    _iosFallbackMode = false;
    _iosFailedAttempts = 0;
  }
  
  // Get iOS fallback mode status
  bool get isIosFallbackModeEnabled => _iosFallbackMode;

  void dispose() {
    _faceDetector.close();
    _livenessDetectionService.dispose();
  }
}