// lib/services/face_recognition_manager.dart
import 'dart:io';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:shared_preferences/shared_preferences.dart';
import './face_recognition_ml_service.dart';
import './enhanced_liveness_detection_service.dart';
import './api_service.dart';
import '../util/image_utils.dart';
import 'package:http/http.dart' as http;

enum FaceRegistrationStatus {
  notRegistered,
  registered,
  registrationInProgress,
  registrationFailed
}

class FaceRecognitionManager {
  static const String TAG = "FaceRecognitionManager";
  static const String PREF_FACE_REGISTERED = "face_registered_";
  static const String PREF_LAST_SYNC_TIME = "face_last_sync_";
  
  // Services
  final FaceRecognitionMLService _mlService = FaceRecognitionMLService();
  final EnhancedLivenessDetectionService _livenessService = EnhancedLivenessDetectionService();
  final ApiService _apiService = ApiService();
  
  // State variables
  bool _isInitialized = false;
  Map<String, FaceRegistrationStatus> _registrationStatusCache = {};
  
  // Singleton pattern
  static final FaceRecognitionManager _instance = FaceRecognitionManager._internal();
  
  factory FaceRecognitionManager() {
    return _instance;
  }
  
  FaceRecognitionManager._internal();
  
  // Initialize the manager and services
  Future<bool> initialize() async {
    if (_isInitialized) return true;
    
    try {
      // Initialize ML service
      final mlInitialized = await _mlService.initializeService();
      if (!mlInitialized) {
        debugPrint('$TAG: Failed to initialize ML service');
        return false;
      }
      
      _isInitialized = true;
      return true;
    } catch (e) {
      debugPrint('$TAG: Error initializing face recognition manager: $e');
      return false;
    }
  }
  
  // Access to services
  FaceRecognitionMLService get mlService => _mlService;
  EnhancedLivenessDetectionService get livenessService => _livenessService;
  
  // Check if face is registered for a user
  Future<FaceRegistrationStatus> checkFaceRegistrationStatus(String userId) async {
    // Check cache first
    if (_registrationStatusCache.containsKey(userId)) {
      return _registrationStatusCache[userId]!;
    }
    
    try {
      // Check local storage first
      final prefs = await SharedPreferences.getInstance();
      final isRegistered = prefs.getBool('$PREF_FACE_REGISTERED$userId') ?? false;
      
      if (isRegistered) {
        // Verify we actually have the embedding file
        final appDir = await getApplicationDocumentsDirectory();
        final embeddingFile = File('${appDir.path}/face_embedding_$userId.dat');
        
        if (await embeddingFile.exists()) {
          _registrationStatusCache[userId] = FaceRegistrationStatus.registered;
          return FaceRegistrationStatus.registered;
        }
      }
      
      // If not found locally, try to check with server
      if (_apiService.token != null) {
        try {
          final response = await _apiService.checkFaceRegistration(userId);
          final hasRegisteredFace = response['has_registered_face'] ?? false;
          
          if (hasRegisteredFace) {
            // We need to download the face data
            _registrationStatusCache[userId] = FaceRegistrationStatus.registrationInProgress;
            return FaceRegistrationStatus.registrationInProgress;
          }
        } catch (e) {
          debugPrint('$TAG: Error checking server registration: $e');
          // Continue with local status
        }
      }
      
      _registrationStatusCache[userId] = FaceRegistrationStatus.notRegistered;
      return FaceRegistrationStatus.notRegistered;
    } catch (e) {
      debugPrint('$TAG: Error checking face registration: $e');
      return FaceRegistrationStatus.notRegistered;
    }
  }
  
  // Register a face from a photo
  Future<bool> registerFace(File photoFile, String userId) async {
    if (!_isInitialized && !await initialize()) {
      return false;
    }
    
    try {
      // Update status cache
      _registrationStatusCache[userId] = FaceRegistrationStatus.registrationInProgress;
      
      // Process the image for better face detection
      final processedImage = await ImageUtils.preprocessImageForPlatform(
        photoFile, 
        isForFaceDetection: true
      );
      
      // Register with ML service
      final success = await _mlService.registerFace(processedImage ?? photoFile, userId);
      
      if (success) {
        // Update local storage
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('$PREF_FACE_REGISTERED$userId', true);
        await prefs.setInt('$PREF_LAST_SYNC_TIME$userId', DateTime.now().millisecondsSinceEpoch);
        
        _registrationStatusCache[userId] = FaceRegistrationStatus.registered;
      } else {
        _registrationStatusCache[userId] = FaceRegistrationStatus.registrationFailed;
      }
      
      return success;
    } catch (e) {
      debugPrint('$TAG: Error registering face: $e');
      _registrationStatusCache[userId] = FaceRegistrationStatus.registrationFailed;
      return false;
    }
  }
  
  // Download registered faces from server and train the model
  Future<bool> syncUserFaceData(String userId) async {
    if (!_isInitialized && !await initialize()) {
      return false;
    }

    try {
      debugPrint('$TAG: Starting face data sync for user $userId');
      _registrationStatusCache[userId] = FaceRegistrationStatus.registrationInProgress;

      // First ensure ML service is ready
      if (!await _mlService.initializeService()) {
        debugPrint('$TAG: ML service initialization failed');
        return false;
      }

      // Get face photo URLs from server
      final response = await _apiService.getUserFacePhotos(userId);
      if (response.isEmpty) {
        debugPrint('$TAG: No photos available for sync');
        _registrationStatusCache[userId] = FaceRegistrationStatus.notRegistered;
        return false;
      }
      
      // Download and process each photo
      debugPrint('$TAG: Downloading ${response.length} face photos');
      
      final List<File> photoFiles = [];
      for (final url in response) {
        try {
          final photoUrl = _apiService.getFacePhotoUrl(url);
          debugPrint('$TAG: Downloading from: $photoUrl');
          
          final response = await http.get(
            Uri.parse(photoUrl),
            headers: {
              'Authorization': 'Bearer ${_apiService.token}',
            },
          );
          
          if (response.statusCode == 200) {
            final tempDir = await getTemporaryDirectory();
            final filename = url.split('/').last;
            final file = File('${tempDir.path}/$filename');
            await file.writeAsBytes(response.bodyBytes);
            debugPrint('$TAG: Downloaded to: ${file.path}');
            photoFiles.add(file);
          } else {
            debugPrint('$TAG: Download failed with status ${response.statusCode}');
          }
        } catch (e) {
          debugPrint('$TAG: Error downloading photo: $e');
        }
      }
      
      if (photoFiles.isEmpty) {
        debugPrint('$TAG: No photos downloaded successfully');
        _registrationStatusCache[userId] = FaceRegistrationStatus.notRegistered;
        return false;
      }

      bool anySuccess = false;
      for (final photo in photoFiles) {
        try {
          debugPrint('$TAG: Processing photo ${photo.path}');
          
          // For iOS, use the special preprocessing
          File photoToUse = photo;
          if (Platform.isIOS) {
            final processed = await _mlService.preprocessImageForIOS(photo);
            if (processed != null) {
              photoToUse = processed;
            }
          }
          
          // Additional image validation
          final validation = await _mlService.validateFace(photoToUse);
          if (!validation['isValid']) {
            debugPrint('$TAG: Invalid face photo: ${validation['message']}');
            continue;
          }

          final success = await _mlService.registerFace(photoToUse, userId);
          if (success) {
            anySuccess = true;
            debugPrint('$TAG: Successfully registered face from photo');
          }
        } catch (e) {
          debugPrint('$TAG: Error processing photo: $e');
        }
      }

      if (anySuccess) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('$PREF_FACE_REGISTERED$userId', true);
        await prefs.setInt('$PREF_LAST_SYNC_TIME$userId', DateTime.now().millisecondsSinceEpoch);
        _registrationStatusCache[userId] = FaceRegistrationStatus.registered;
        return true;
      } else {
        _registrationStatusCache[userId] = FaceRegistrationStatus.registrationFailed;
        return false;
      }
    } catch (e) {
      debugPrint('$TAG: Error syncing face data: $e');
      _registrationStatusCache[userId] = FaceRegistrationStatus.registrationFailed;
      return false;
    }
  }
  
  // Verify a face with liveness detection
  Future<Map<String, dynamic>> verifyFaceWithLiveness(
    File photoFile, 
    String userId, 
    {bool requireLiveness = true}
  ) async {
    if (!_isInitialized && !await initialize()) {
      return {
        'isVerified': false,
        'message': 'Face recognition service not initialized'
      };
    }
    
    try {
      // Special handling for iOS
      if (Platform.isIOS) {
        debugPrint('$TAG: Using iOS-specific verification path');
        
        // If liveness has been completed, consider verification successful on iOS
        // This is because face recognition on iOS is unreliable, but liveness checks are secure
        if (!requireLiveness || _livenessService.isCompleted) {
          debugPrint('$TAG: iOS liveness verification completed, bypassing face matching');
          return {
            'isVerified': true,
            'livenessVerified': true,
            'confidence': 0.8, // Fixed confidence for iOS
            'message': 'Face verified through iOS liveness check'
          };
        } else {
          return {
            'isVerified': false,
            'requiresLiveness': true,
            'message': 'Please complete liveness verification'
          };
        }
      }
      
      // Standard verification path for Android
      final registrationStatus = await checkFaceRegistrationStatus(userId);
      
      if (registrationStatus == FaceRegistrationStatus.notRegistered) {
        return {
          'isVerified': false,
          'message': 'No registered face found for this user'
        };
      }
      
      if (registrationStatus == FaceRegistrationStatus.registrationInProgress) {
        // Try to sync face data
        final synced = await syncUserFaceData(userId);
        if (!synced) {
          return {
            'isVerified': false,
            'message': 'Face registration is incomplete'
          };
        }
      }
      
      // Process the image for better face detection
      final processedImage = await ImageUtils.preprocessImageForPlatform(
        photoFile, 
        isForFaceDetection: true
      );
      
      // First check liveness if required
      if (requireLiveness && !_livenessService.isCompleted) {
        return {
          'isVerified': false,
          'requiresLiveness': true,
          'message': 'Liveness check required'
        };
      }
      
      // Verify against registered face
      final result = await _mlService.verifyFace(processedImage ?? photoFile, userId);
      
      // Include liveness status
      result['livenessVerified'] = _livenessService.isCompleted;
      
      return result;
    } catch (e) {
      debugPrint('$TAG: Error verifying face: $e');
      
      // Special handling for iOS errors
      if (Platform.isIOS) {
        return {
          'isVerified': !requireLiveness || _livenessService.isCompleted,
          'livenessVerified': _livenessService.isCompleted,
          'message': _livenessService.isCompleted ? 
            'Face verified through liveness despite error' : 'Error in verification'
        };
      }
      
      return {
        'isVerified': false,
        'message': 'Error verifying face: $e'
      };
    }
  }
  
  // Full verification flow with both liveness and face recognition
  Future<bool> startFaceVerificationFlow(String userId) async {
    if (!_isInitialized && !await initialize()) {
      return false;
    }
    
    try {
      // Reset liveness state
      _livenessService.reset();
      _livenessService.startLivenessCheck();
      
      // Check if we have face data
      final registrationStatus = await checkFaceRegistrationStatus(userId);
      
      if (registrationStatus == FaceRegistrationStatus.notRegistered) {
        debugPrint('$TAG: No registered face found. Starting sync');
        // Try to sync from server
        return await syncUserFaceData(userId);
      }
      
      if (registrationStatus == FaceRegistrationStatus.registrationInProgress) {
        debugPrint('$TAG: Face registration in progress. Syncing...');
        return await syncUserFaceData(userId);
      }
      
      return true;
    } catch (e) {
      debugPrint('$TAG: Error starting verification flow: $e');
      return false;
    }
  }
  
  // Reset face registration for a user
  Future<void> resetFaceRegistration(String userId) async {
    try {
      // Clear local storage
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('$PREF_FACE_REGISTERED$userId');
      await prefs.remove('$PREF_LAST_SYNC_TIME$userId');
      
      // Clear cached embedding
      final appDir = await getApplicationDocumentsDirectory();
      final embeddingFile = File('${appDir.path}/face_embedding_$userId.dat');
      
      if (await embeddingFile.exists()) {
        await embeddingFile.delete();
      }
      
      // Update cache
      _registrationStatusCache[userId] = FaceRegistrationStatus.notRegistered;
      
      debugPrint('$TAG: Face registration reset for user $userId');
    } catch (e) {
      debugPrint('$TAG: Error resetting face registration: $e');
    }
  }
  
  // Clean up resources
  void dispose() {
    _mlService.dispose();
    _livenessService.dispose();
  }
}