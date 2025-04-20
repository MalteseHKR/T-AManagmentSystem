// lib/services/enhanced_liveness_detection_service.dart
import 'dart:async';
import 'dart:io';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:path_provider/path_provider.dart';
import 'package:image/image.dart' as img;

// Liveness detection methods
enum LivenessMethod {
  blinkDetection,
  headMovement,
  smileDetection,
  eyesDirectionChange,
  randomCombination
}

// Enhanced liveness check state
enum LivenessCheckState {
  notStarted,
  inProgress,
  actionRequired,
  processing,
  completed,
  failed
}

class EnhancedLivenessDetectionService {
  static const String TAG = "LivenessDetection";
  
  // Settings for different liveness checks
  static const double BLINK_THRESHOLD = 0.2;
  static const double HEAD_TURN_THRESHOLD = 12.0; // In degrees
  static const double SMILE_THRESHOLD = 0.7; 
  
  // Processing rates
  static const int PROCESSING_INTERVAL_ANDROID = 300; // ms
  static const int PROCESSING_INTERVAL_IOS = 500; // ms
  
  // Configure the fallback security level
  static const int MAX_ATTEMPTS_BEFORE_FALLBACK = 3;
  static const int AUTO_COMPLETE_TIME_IOS = 30; // seconds
  
  // Main state variables
  late FaceDetector _faceDetector;
  LivenessCheckState _currentState = LivenessCheckState.notStarted;
  
  // Action verification flags
  bool _blinkDetected = false;
  bool _headMovementDetected = false;
  bool _smileDetected = false;
  bool _eyeDirectionChangeDetected = false;
  
  // Current requested action
  LivenessMethod _currentRequiredAction = LivenessMethod.blinkDetection;
  String _currentInstructionText = "Preparing liveness check...";
  
  // Track previous face states
  double? _lastEyeOpenProbability;
  double? _lastHeadEulerY;
  double? _lastSmileProbability;
  InputImage? _lastProcessedImage;
  
  // Auto-complete safety for iOS
  Timer? _autoCompleteTimer;
  int _failedAttempts = 0;
  
  // Track total session time
  DateTime? _startTime;
  static const Duration _timeoutDuration = Duration(seconds: 30);
  
  // Face image sequence for security validation
  final List<File> _faceImageSequence = [];
  
  // Getters for state
  LivenessCheckState get currentState => _currentState;
  bool get isCompleted => _currentState == LivenessCheckState.completed;
  String get instructionText => _currentInstructionText;
  
  // Get progress percentage based on completed checks
  double _calculateProgress() {
    double progress = 0.0;
    int tasksCompleted = 0;
    int requiredTasks = 2; // Need at least 2 tasks for completion
    
    if (_blinkDetected) tasksCompleted++;
    if (_headMovementDetected) tasksCompleted++;
    if (_smileDetected) tasksCompleted++;
    if (_eyeDirectionChangeDetected) tasksCompleted++;
    
    progress = tasksCompleted / requiredTasks;
    
    // Cap at 1.0 if we've completed more than required
    return min(1.0, progress);
  }
  
  // Get random liveness method
  LivenessMethod get randomAction {
    final random = Random();
    const methods = LivenessMethod.values;
    // Don't use the random combination itself as an action
    return methods[random.nextInt(methods.length - 1)];
  }
  
  // Constructor
  EnhancedLivenessDetectionService() {
    // Initialize face detector with improved settings for iOS
    _faceDetector = FaceDetector(
      options: FaceDetectorOptions(
        enableLandmarks: true,
        enableClassification: true,
        enableTracking: true,
        minFaceSize: Platform.isIOS ? 0.1 : 0.15, // Lower threshold for iOS
        performanceMode: FaceDetectorMode.accurate, // Use accurate mode for both platforms
      ),
    );
  }
  
  // Start liveness detection session
  void startLivenessCheck() {
    debugPrint('$TAG: Starting liveness check');
    
    // Reset state
    _currentState = LivenessCheckState.inProgress;
    _blinkDetected = false;
    _headMovementDetected = false;
    _smileDetected = false;
    _eyeDirectionChangeDetected = false;
    _lastEyeOpenProbability = null;
    _lastHeadEulerY = null;
    _lastSmileProbability = null;
    _faceImageSequence.clear();
    _failedAttempts = 0;
    
    // Start timeout timer
    _startTime = DateTime.now();
    
    // Choose initial action
    _setRandomAction();
    
    // Start auto-complete timer for iOS if needed
    if (Platform.isIOS) {
      _startAutoCompleteTimer();
    }
  }
  
  // iOS-specific auto-complete timer
  void _startAutoCompleteTimer() {
    _autoCompleteTimer?.cancel();
    _autoCompleteTimer = Timer(const Duration(seconds: AUTO_COMPLETE_TIME_IOS), () {
      if (_currentState != LivenessCheckState.completed && 
          _currentState != LivenessCheckState.failed) {
        debugPrint('$TAG: iOS safety timer triggered - setting to failed state');
        // Never auto-complete, always set to failed state on timeout
        _currentState = LivenessCheckState.failed;
      }
    });
  }
  
  void reset() {
    debugPrint('$TAG: Resetting liveness service');
    _currentState = LivenessCheckState.notStarted;
    _blinkDetected = false;
    _headMovementDetected = false;
    _smileDetected = false;
    _eyeDirectionChangeDetected = false;
    _lastEyeOpenProbability = null;
    _lastHeadEulerY = null;
    _lastSmileProbability = null;
    _startTime = null;
    _faceImageSequence.clear();
    _failedAttempts = 0;
    
    // Cancel any running timers
    _autoCompleteTimer?.cancel();
    _autoCompleteTimer = null;
  }

  // Force completion (for testing or fallback)
  void forceCompletion() {
    debugPrint('$TAG: Force completing liveness check');
    _blinkDetected = true;
    _headMovementDetected = true;
    _smileDetected = true;
    _eyeDirectionChangeDetected = true;
    _currentState = LivenessCheckState.completed;
  }

  // Clean up resources
  void dispose() {
    _faceDetector.close();
    _autoCompleteTimer?.cancel();
    _faceImageSequence.clear();
  }

  // Set a random action requirement
  void _setRandomAction() {
    // Decide on action
    _currentRequiredAction = randomAction;
    
    // Set instruction based on the action
    switch (_currentRequiredAction) {
      case LivenessMethod.blinkDetection:
        _currentInstructionText = "Please blink your eyes";
        break;
      case LivenessMethod.headMovement:
        _currentInstructionText = "Please turn your head slightly left and right";
        break;
      case LivenessMethod.smileDetection:
        _currentInstructionText = "Please smile";
        break;
      case LivenessMethod.eyesDirectionChange:
        _currentInstructionText = "Please look to the side and back";
        break;
      default:
        _currentInstructionText = "Please follow the instructions";
        break;
    }
    
    _currentState = LivenessCheckState.actionRequired;
  }
  
  // Set a new action that hasn't been completed yet
  void _setNewAction() {
    LivenessMethod newAction;
    
    do {
      newAction = randomAction;
    } while (
      (newAction == LivenessMethod.blinkDetection && _blinkDetected) ||
      (newAction == LivenessMethod.headMovement && _headMovementDetected) ||
      (newAction == LivenessMethod.smileDetection && _smileDetected) ||
      (newAction == LivenessMethod.eyesDirectionChange && _eyeDirectionChangeDetected)
    );
    
    _currentRequiredAction = newAction;
    
    // Set instruction based on the action
    switch (_currentRequiredAction) {
      case LivenessMethod.blinkDetection:
        _currentInstructionText = "Now please blink your eyes";
        break;
      case LivenessMethod.headMovement:
        _currentInstructionText = "Now please turn your head slightly left and right";
        break;
      case LivenessMethod.smileDetection:
        _currentInstructionText = "Now please smile";
        break;
      case LivenessMethod.eyesDirectionChange:
        _currentInstructionText = "Now please look to the side and back";
        break;
      default:
        _currentInstructionText = "Please continue following the instructions";
        break;
    }
    
    _currentState = LivenessCheckState.actionRequired;
  }
  
  // Process a frame for liveness detection
  Future<Map<String, dynamic>> processImage(File imageFile) async {
    if (_currentState == LivenessCheckState.notStarted || 
        _currentState == LivenessCheckState.completed ||
        _currentState == LivenessCheckState.failed) {
      return {
        'state': _currentState.toString(),
        'progress': 0.0,
        'message': 'Liveness detection not active',
        'blinkDetected': _blinkDetected,
        'headMovementDetected': _headMovementDetected,
        'smileDetected': _smileDetected
      };
    }
    
    // Check for timeout
    if (_startTime != null && DateTime.now().difference(_startTime!) > _timeoutDuration) {
      _currentState = LivenessCheckState.failed;
      return {
        'state': _currentState.toString(),
        'progress': 0.0,
        'message': 'Liveness check timed out. Please try again.',
        'blinkDetected': _blinkDetected,
        'headMovementDetected': _headMovementDetected,
        'smileDetected': _smileDetected
      };
    }
    
    // Set processing state
    _currentState = LivenessCheckState.processing;
    
    try {
      // Process image
      final inputImage = InputImage.fromFilePath(imageFile.path);
      final faces = await _faceDetector.processImage(inputImage);
      
      // Store this image for the sequence
      _faceImageSequence.add(imageFile);
      if (_faceImageSequence.length > 5) {
        _faceImageSequence.removeAt(0); // Keep only last 5 images
      }
      
      // Check if any face is detected - apply consistent logic for both platforms
      if (faces.isEmpty) {
        _failedAttempts++;
        
        // Don't use lenient failure handling for iOS
        return {
          'state': LivenessCheckState.actionRequired.toString(),
          'progress': _calculateProgress(),
          'message': 'No face detected. Please ensure your face is visible.',
          'blinkDetected': _blinkDetected,
          'headMovementDetected': _headMovementDetected,
          'smileDetected': _smileDetected
        };
      }
      
      // If multiple faces detected
      if (faces.length > 1) {
        _failedAttempts++;
        return {
          'state': LivenessCheckState.actionRequired.toString(),
          'progress': _calculateProgress(),
          'message': 'Multiple faces detected. Please ensure only your face is visible.',
          'blinkDetected': _blinkDetected,
          'headMovementDetected': _headMovementDetected,
          'smileDetected': _smileDetected
        };
      }
      
      // Reset failed attempts counter on success
      _failedAttempts = 0;
      
      // Get the detected face
      final face = faces.first;
      
      // Save this as the last processed image
      _lastProcessedImage = inputImage;
      
      // Process based on current required action
      return _processLivenessAction(face, inputImage);
    } catch (e) {
      debugPrint('$TAG: Error processing image: $e');
      
      _failedAttempts++;
      
      // Return a failure response for all platforms
      return {
        'state': LivenessCheckState.actionRequired.toString(),
        'progress': _calculateProgress(),
        'message': 'Error processing face. Please try again.',
        'blinkDetected': _blinkDetected,
        'headMovementDetected': _headMovementDetected,
        'smileDetected': _smileDetected
      };
    }
  }
  
  // Special handling for iOS - create a fallback response
  Map<String, dynamic> _createIosFallbackResponse() {
    debugPrint('$TAG: Creating iOS response with stricter fallback policy');
    
    // Never automatically mark as completed
    _currentState = LivenessCheckState.failed;
    
    return {
      'state': LivenessCheckState.failed.toString(),
      'progress': 0.0,
      'message': 'Verification failed. Please try again in better lighting conditions.',
      'blinkDetected': _blinkDetected,
      'headMovementDetected': _headMovementDetected,
      'smileDetected': _smileDetected,
      'eyeDirectionChangeDetected': _eyeDirectionChangeDetected
    };
  }
  
  // Process blink detection
  bool _processBlinkDetection(Face face) {
    // Check if we have eye openness probabilities
    if (face.leftEyeOpenProbability == null || face.rightEyeOpenProbability == null) {
      return false;
    }
    
    // Calculate average eye openness
    final double currentEyeOpenness = 
        (face.leftEyeOpenProbability! + face.rightEyeOpenProbability!) / 2.0;
    
    // If we have previous measurements
    if (_lastEyeOpenProbability != null) {
      // Make threshold more forgiving for iOS
      final threshold = Platform.isIOS ? 0.3 : BLINK_THRESHOLD;
      
      // Check for significant change (blink)
      if (_lastEyeOpenProbability! > 0.6 && currentEyeOpenness < threshold) {
        debugPrint('$TAG: Blink detected');
        return true;
      }
    }
    
    // Save current state for next comparison
    _lastEyeOpenProbability = currentEyeOpenness;
    return false;
  }
  
  // Process head movement detection
  bool _processHeadMovement(Face face) {
    // Check if we have head angle
    if (face.headEulerAngleY == null) {
      return false;
    }
    
    // If we have previous measurements
    if (_lastHeadEulerY != null) {
      // Calculate the amount of movement
      final double movement = (_lastHeadEulerY! - face.headEulerAngleY!).abs();
      
      // Check for significant movement
      if (movement > HEAD_TURN_THRESHOLD) {
        debugPrint('$TAG: Head movement detected: $movement degrees');
        return true;
      }
    }
    
    // Save current state for next comparison
    _lastHeadEulerY = face.headEulerAngleY;
    return false;
  }
  
  // Process smile detection
  bool _processSmileDetection(Face face) {
    // Check if we have smile probability
    if (face.smilingProbability == null) {
      return false;
    }
    
    final double smileProbability = face.smilingProbability!;
    
    // If previous measurement exists
    if (_lastSmileProbability != null) {
      // Detect transition from not smiling to smiling
      if (_lastSmileProbability! < 0.3 && smileProbability > SMILE_THRESHOLD) {
        debugPrint('$TAG: Smile detected: $smileProbability');
        return true;
      }
    }
    
    // Save current state for next comparison
    _lastSmileProbability = smileProbability;
    return false;
  }
  
  // Process eye direction change
  bool _processEyeDirectionChange(Face face) {
    // This is more complex and may require landmarks
    // Simple implementation - check if face is not looking straight ahead
    if (face.headEulerAngleY != null && face.headEulerAngleZ != null) {
      // Detect if looking significantly to the side
      final double absY = face.headEulerAngleY!.abs();
      if (absY > 20.0) {
        debugPrint('$TAG: Eye direction change detected');
        return true;
      }
    }
    
    return false;
  }
  
  // Process specific liveness actions
  Map<String, dynamic> _processLivenessAction(Face face, InputImage inputImage) {
    // Default response values
    String message = _currentInstructionText;
    bool actionComplete = false;
    
    // Process based on current action
    switch (_currentRequiredAction) {
      case LivenessMethod.blinkDetection:
        actionComplete = _processBlinkDetection(face);
        if (actionComplete) {
          _blinkDetected = true;
          message = "Blink detected! ✓";
        }
        break;
        
      case LivenessMethod.headMovement:
        actionComplete = _processHeadMovement(face);
        if (actionComplete) {
          _headMovementDetected = true;
          message = "Head movement detected! ✓";
        }
        break;
        
      case LivenessMethod.smileDetection:
        actionComplete = _processSmileDetection(face);
        if (actionComplete) {
          _smileDetected = true;
          message = "Smile detected! ✓";
        }
        break;
        
      case LivenessMethod.eyesDirectionChange:
        actionComplete = _processEyeDirectionChange(face);
        if (actionComplete) {
          _eyeDirectionChangeDetected = true;
          message = "Eye movement detected! ✓";
        }
        break;
        
      default:
        // Fallback to blink detection
        actionComplete = _processBlinkDetection(face);
        if (actionComplete) {
          _blinkDetected = true;
          message = "Blink detected! ✓";
        }
        break;
    }
    
    // If action is complete, set a new random action
    if (actionComplete) {
      // If we've completed enough actions, finish the check
      if ((_blinkDetected && _headMovementDetected) || 
          (_blinkDetected && _smileDetected) || 
          (_headMovementDetected && _smileDetected) ||
          (_blinkDetected && _eyeDirectionChangeDetected)) {
        _currentState = LivenessCheckState.completed;
        message = "Liveness verification complete!";
      } else {
        // Otherwise, set a new action that hasn't been completed yet
        _setNewAction();
      }
    } else {
      // If not completed, return to action required state
      _currentState = LivenessCheckState.actionRequired;
    }
    
    return {
      'state': _currentState.toString(),
      'progress': _calculateProgress(),
      'message': message,
      'blinkDetected': _blinkDetected,
      'headMovementDetected': _headMovementDetected,
      'smileDetected': _smileDetected,
      'eyeDirectionChangeDetected': _eyeDirectionChangeDetected
    };
  }
}