// lib/screens/enhanced_liveness_detection_screen.dart
import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import '../services/enhanced_liveness_detection_service.dart';
import '../services/face_recognition_manager.dart';
import '../services/session_service.dart';
import '../util/image_utils.dart';

class EnhancedLivenessDetectionScreen extends StatefulWidget {
  final CameraDescription camera;
  final Map<String, dynamic> userDetails;
  final Function(bool success, File? capturedImage) onLivenessCheckComplete;

  const EnhancedLivenessDetectionScreen({
    Key? key,
    required this.camera,
    required this.userDetails,
    required this.onLivenessCheckComplete,
  }) : super(key: key);

  @override
  State<EnhancedLivenessDetectionScreen> createState() => _EnhancedLivenessDetectionScreenState();
}

class _EnhancedLivenessDetectionScreenState extends State<EnhancedLivenessDetectionScreen> with WidgetsBindingObserver {
  late CameraController _cameraController;
  late Future<void> _initializeCameraFuture;
  final FaceRecognitionManager _faceManager = FaceRecognitionManager();
  final SessionService _sessionService = SessionService();
  
  bool _isProcessing = false;
  bool _isCameraInitialized = false;
  String _statusMessage = 'Preparing liveness check...';
  double _progressValue = 0.0;
  LivenessCheckState _checkState = LivenessCheckState.notStarted;
  
  // Action indicators
  bool _blinkDetected = false;
  bool _headMovementDetected = false;
  bool _smileDetected = false;
  bool _eyeDirectionChangeDetected = false;
  
  // Timer for processing
  Timer? _processingTimer;
  
  // Flag to prevent multiple callbacks
  bool _callbackCalled = false;
  
  // Timer for auto-completion (especially useful for iOS)
  Timer? _autoCompleteTimer;
  
  // Final captured image
  File? _finalCapturedImage;
  
  // New variables to reduce flashing and improve iOS experience
  double _cameraOpacity = 1.0;
  int? _lastFullCapture;
  bool _retryAttempted = false;
  int _consecutiveNoFaceDetections = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initializeCamera();
    
    // Start a safety timer
    _startSafetyTimer();
  }
  
  void _startSafetyTimer() {
    // After 30 seconds, fail the check if not complete
    _autoCompleteTimer = Timer(Duration(seconds: 30), () {
      if (mounted && !_callbackCalled) {
        debugPrint('Safety timer triggered - failing liveness check');
        setState(() {
          _statusMessage = 'Verification timed out. Please try again.';
          _progressValue = 0.0;
          _checkState = LivenessCheckState.failed;
        });
        
        // Return failure
        _callbackCalled = true;
        widget.onLivenessCheckComplete(false, null);
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;
    
    // Handle app lifecycle changes
    if (state == AppLifecycleState.inactive || state == AppLifecycleState.paused) {
      // Release camera resources when app goes to background
      _pauseProcessing();
      if (_isCameraInitialized && _cameraController.value.isInitialized) {
        _cameraController.dispose();
        _isCameraInitialized = false;
      }
    } else if (state == AppLifecycleState.resumed) {
      // Reinitialize camera when app is resumed
      if (!_isCameraInitialized) {
        _initializeCamera().then((_) {
          _resumeProcessing();
        });
      }
    }
  }

  void _pauseProcessing() {
    _processingTimer?.cancel();
    _processingTimer = null;
  }

  void _resumeProcessing() {
    if (_processingTimer == null && _isCameraInitialized) {
      _startProcessing();
    }
  }

  Future<void> _initializeCamera() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    // Platform-specific resolution settings
    final ResolutionPreset resolution = Platform.isIOS 
        ? ResolutionPreset.high  //changed to high to test
        : ResolutionPreset.high;
        
    // Platform-specific image format
    final ImageFormatGroup formatGroup = Platform.isIOS
        ? ImageFormatGroup.yuv420
        : ImageFormatGroup.jpeg;
    
    try {
      // First dispose any existing camera controller
      if (_isCameraInitialized && _cameraController.value.isInitialized) {
        await _cameraController.dispose();
      }
    } catch (e) {
      // Ignore errors during disposal
      debugPrint('Camera disposal error (can be ignored): $e');
    }
    
    _cameraController = CameraController(
      widget.camera,
      resolution,
      enableAudio: false,
      imageFormatGroup: formatGroup,
    );
    
    try {
      _initializeCameraFuture = _cameraController.initialize();
      await _initializeCameraFuture;
      _isCameraInitialized = true;
      
      // For iOS, set some optimal camera settings
      if (Platform.isIOS) {
        try {
          await _cameraController.setExposureMode(ExposureMode.auto);
          await _cameraController.setFlashMode(FlashMode.off);
          await _cameraController.setFocusMode(FocusMode.auto);
        } catch (e) {
          debugPrint('Error setting camera parameters: $e');
        }
      }
      
      if (mounted) {
        setState(() {
          _statusMessage = 'Ready to start liveness check';
        });
        
        // Start liveness detection
        _startLivenessCheck();
      }
    } catch (e) {
      debugPrint('Camera initialization error: $e');
      if (mounted) {
        setState(() {
          _statusMessage = 'Camera error: $e';
        });
        
        // Always return failure on camera errors
        _callbackCalled = true;
        widget.onLivenessCheckComplete(false, null);
      }
    }
  }
  
  void _startLivenessCheck() {
    // Initialize the face recognition manager if needed
    _faceManager.initialize().then((_) {
      // Reset and start liveness detection
      _faceManager.livenessService.reset();
      _faceManager.livenessService.startLivenessCheck();
      
      setState(() {
        _checkState = LivenessCheckState.inProgress;
        _statusMessage = _faceManager.livenessService.instructionText;
        _progressValue = 0.0;
        _blinkDetected = false;
        _headMovementDetected = false;
        _smileDetected = false;
        _eyeDirectionChangeDetected = false;
        _callbackCalled = false; // Reset callback flag
        _consecutiveNoFaceDetections = 0;
      });
      
      // Start processing
      _startProcessing();
    });
  }
  
  void _startProcessing() {
    // Process frames less frequently to reduce flashing
    // Much longer interval for iOS to reduce flashing significantly
    final processingInterval = Platform.isIOS ? 1500 : 800; // Milliseconds
    
    _processingTimer = Timer.periodic(Duration(milliseconds: processingInterval), (_) {
      _captureAndProcessFrame();
    });
  }
  
  Future<void> _captureAndProcessFrame() async {
    if (!mounted || 
        !_isCameraInitialized || 
        !_cameraController.value.isInitialized ||
        _isProcessing) {
      return;
    }
    
    setState(() {
      _isProcessing = true;
      // Use a subtle opacity change instead of completely hiding/showing the camera
      _cameraOpacity = 0.9;
    });
    
    try {
      // Track frame processing rate to avoid excessive processing
      final currentTime = DateTime.now().millisecondsSinceEpoch;
      bool shouldTakeFullPicture = true;
      
      // For iOS, limit full picture captures to reduce flashing
      if (Platform.isIOS) {
        if (_lastFullCapture != null && currentTime - _lastFullCapture! < 1500) {
          shouldTakeFullPicture = false;
        }
      }
      
      File? imageFile;
      
      if (shouldTakeFullPicture) {
        // Take a full resolution picture
        final image = await _cameraController.takePicture();
        imageFile = File(image.path);
        _lastFullCapture = currentTime;
        
        debugPrint("Frame captured: ${imageFile.path} on ${Platform.isIOS ? 'iOS' : 'Android'}");
        
        // Apply platform-specific image processing
        final processedFile = await ImageUtils.preprocessImageForPlatform(
          imageFile,
          isForFaceDetection: true
        );
        
        // Use the processed file if available, otherwise use original
        final fileToProcess = processedFile ?? imageFile;
        
        // Process the image for liveness detection
        final result = await _faceManager.livenessService.processImage(fileToProcess);
        
        if (mounted) {
          setState(() {
            // Parse the state enum from string
            final stateString = result['state'] as String;
            _checkState = LivenessCheckState.values.firstWhere(
              (e) => e.toString() == stateString,
              orElse: () => LivenessCheckState.notStarted
            );
            
            _statusMessage = result['message'] as String;
            _progressValue = result['progress'] as double;
            _blinkDetected = result['blinkDetected'] ?? false;
            _headMovementDetected = result['headMovementDetected'] ?? false;
            _smileDetected = result['smileDetected'] ?? false;
            _eyeDirectionChangeDetected = result['eyeDirectionChangeDetected'] ?? false;
            
            // Track consecutive no face detections to provide better guidance
            if (_statusMessage.contains('No face detected')) {
              _consecutiveNoFaceDetections++;
              
              // After several consecutive failures, provide more specific guidance
              if (_consecutiveNoFaceDetections >= 3) {
                _statusMessage = 'Please position your face clearly in the oval and ensure good lighting';
                
                // Reset counter to avoid repeating this message too often
                if (_consecutiveNoFaceDetections > 5) {
                  _consecutiveNoFaceDetections = 0;
                }
              }
            } else {
              _consecutiveNoFaceDetections = 0;
            }
            
            // If completed or failed, stop processing
            if (_checkState == LivenessCheckState.completed || 
                _checkState == LivenessCheckState.failed) {
              _pauseProcessing();
              
              // Only proceed if callback hasn't been called yet
              if (!_callbackCalled) {
                // If successful, capture a final image for verification
                if (_checkState == LivenessCheckState.completed) {
                  _captureAndReturnImage();
                } else {
                  // For failure, handle accordingly but don't bypass verification
                  _callbackCalled = true;
                  widget.onLivenessCheckComplete(false, null);
                }
              }
            }
          });
        }
        
        // If on iOS and no faces detected, try one more time with different settings
        if (Platform.isIOS && 
            _statusMessage.contains('No face detected') && 
            !_retryAttempted) {
          
          _retryAttempted = true;
          
          // Wait a moment before retry
          await Future.delayed(Duration(milliseconds: 500));
          
          // Try to adjust camera settings for better face detection
          try {
            // Adjust exposure if possible
            await _cameraController.setExposureMode(ExposureMode.auto);
            await _cameraController.setExposureOffset(0.5); // Slightly brighter
            
            // Capture a new image with adjusted settings
            final retryImage = await _cameraController.takePicture();
            final retryFile = File(retryImage.path);
            
            debugPrint("iOS retry capture: ${retryFile.path}");
            
            // Process this image
            final retryProcessed = await ImageUtils.preprocessImageForPlatform(
              retryFile,
              isForFaceDetection: true
            );
            
            // Process the retry image
            final retryResult = await _faceManager.livenessService.processImage(
              retryProcessed ?? retryFile
            );
            
            // Update with retry result if still mounted
            if (mounted) {
              setState(() {
                final stateString = retryResult['state'] as String;
                _checkState = LivenessCheckState.values.firstWhere(
                  (e) => e.toString() == stateString,
                  orElse: () => LivenessCheckState.notStarted
                );
                
                _statusMessage = retryResult['message'] as String;
                _progressValue = retryResult['progress'] as double;
                _blinkDetected = retryResult['blinkDetected'] ?? false;
                _headMovementDetected = retryResult['headMovementDetected'] ?? false;
                _smileDetected = retryResult['smileDetected'] ?? false;
                _eyeDirectionChangeDetected = retryResult['eyeDirectionChangeDetected'] ?? false;
              });
            }
          } catch (retryError) {
            debugPrint("iOS retry error: $retryError");
          } finally {
            _retryAttempted = false; // Reset for next frame
          }
        }
      } else {
        // For intermediate frames on iOS, just update UI state
        // without taking a full picture to reduce flashing
        if (mounted) {
          setState(() {
            // Just update progress indicators or animations
            // but don't change main status info
          });
        }
      }
    } catch (e) {
      debugPrint('Error processing frame: $e');
      
      if (mounted) {
        setState(() {
          _isProcessing = false;
          _cameraOpacity = 1.0;
        });
      }
    } finally {
      // Small delay before resetting processing state to reduce flickering
      if (Platform.isIOS) {
        await Future.delayed(Duration(milliseconds: 100));
      }
      
      if (mounted) {
        setState(() {
          _isProcessing = false;
          _cameraOpacity = 1.0;
        });
      }
    }
  }
  
  // Capture a final image and return it for verification
  Future<void> _captureAndReturnImage() async {
    if (_callbackCalled) return;
    
    _callbackCalled = true;
    
    try {
      // Take an actual picture for final verification
      if (_isCameraInitialized && _cameraController.value.isInitialized) {
        final image = await _cameraController.takePicture();
        _finalCapturedImage = File(image.path);
        
        // Process the image for better face recognition with enhanced iOS-specific handling
        if (Platform.isIOS) {
          debugPrint("iOS: Applying special processing to final verification image");
          try {
            // First enhance the image for face detection specifically
            final enhancedImage = await ImageUtils.enhanceImageForFaceDetection(_finalCapturedImage!);
            if (enhancedImage != null) {
              _finalCapturedImage = enhancedImage;
              debugPrint("iOS: Successfully enhanced final verification image");
            }
          } catch (e) {
            debugPrint("iOS: Error enhancing image: $e");
            // Continue with original image if enhancement fails
          }
        } else {
          // Standard processing for Android
          final processedImage = await ImageUtils.preprocessImageForPlatform(
            _finalCapturedImage!,
            isForFaceDetection: true
          );
          
          if (processedImage != null) {
            _finalCapturedImage = processedImage;
          }
        }
      }
      
      // Return based on actual completion
      final success = _checkState == LivenessCheckState.completed;
      widget.onLivenessCheckComplete(success, _finalCapturedImage);
      
    } catch (e) {
      debugPrint('Error capturing final image: $e');
      widget.onLivenessCheckComplete(false, null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<void>(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, void result) {
        if (!didPop && !_callbackCalled) {
          _callbackCalled = true;

          // Always treat back button as cancellation
          widget.onLivenessCheckComplete(false, null);
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Face Verification'),
          leading: IconButton(
            icon: const Icon(Icons.close),
            onPressed: () {
              // Only allow close if callback hasn't been called
              if (!_callbackCalled) {
                _callbackCalled = true;
                
                // Always treat manual close as cancellation
                widget.onLivenessCheckComplete(false, null);
              }
            },
          ),
        ),
        body: Column(
          children: [
            // Camera preview
            Expanded(
              flex: 3,
              child: FutureBuilder<void>(
                future: _initializeCameraFuture,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.done && _isCameraInitialized) {
                    return Stack(
                      fit: StackFit.expand,
                      children: [
                        // Camera preview with animated opacity to reduce flashing
                        AnimatedOpacity(
                          opacity: _cameraOpacity,
                          duration: Duration(milliseconds: 300),
                          child: CameraPreview(_cameraController),
                        ),
                        
                        // Face oval guide
                        CustomPaint(
                          painter: FaceOvalPainter(),
                        ),
                        
                        // Current action indicator
                        Positioned(
                          top: 20,
                          left: 0,
                          right: 0,
                          child: Container(
                            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            color: Colors.black54,
                            child: Text(
                              _getStatusText(),
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                        
                        // Show a subtle indicator when processing to prevent full screen flashing
                        if (_isProcessing)
                          Positioned(
                            top: 10,
                            right: 10,
                            child: Container(
                              padding: EdgeInsets.all(6),
                              decoration: BoxDecoration(
                                color: Colors.black54,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                ),
                              ),
                            ),
                          ),
                      ],
                    );
                  } else {
                    return Center(child: CircularProgressIndicator());
                  }
                },
              ),
            ),
            
            // Status and instructions
            Container(
              padding: EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Progress indicator
                  LinearProgressIndicator(
                    value: _progressValue,
                    backgroundColor: Colors.grey[300],
                    valueColor: AlwaysStoppedAnimation<Color>(
                      _progressValue == 1.0 ? Colors.green : Colors.blue,
                    ),
                  ),
                  SizedBox(height: 16),
                  
                  // Status message
                  Text(
                    _statusMessage,
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  SizedBox(height: 12),
                  
                  // Check list
                  _buildChecklistItem(
                    'Blink detection', 
                    _blinkDetected, 
                    'Please blink your eyes naturally'
                  ),
                  SizedBox(height: 8),
                  _buildChecklistItem(
                    'Head movement', 
                    _headMovementDetected, 
                    'Please move your head slightly from side to side'
                  ),
                  SizedBox(height: 8),
                  _buildChecklistItem(
                    'Smile', 
                    _smileDetected, 
                    'Please smile when instructed'
                  ),
                  
                  SizedBox(height: 16),
                  
                  // Conditional buttons based on verification state
                  if (_checkState == LivenessCheckState.completed)
                    // Show Continue button when verification is complete
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () {
                          if (!_callbackCalled) {
                            _captureAndReturnImage();
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          foregroundColor: Colors.white,
                          padding: EdgeInsets.symmetric(vertical: 12),
                        ),
                        child: Text('Continue'),
                      ),
                    )
                  else if (_checkState == LivenessCheckState.failed ||
                      _checkState == LivenessCheckState.notStarted)
                    // Show Retry button if failed or not started
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _startLivenessCheck,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.blue,
                          foregroundColor: Colors.white,
                          padding: EdgeInsets.symmetric(vertical: 12),
                        ),
                        child: Text('Retry Verification'),
                      ),
                    )
                  else 
                    // Add a cancel button for when checks are in progress
                    Padding(
                      padding: const EdgeInsets.only(top: 8.0),
                      child: SizedBox(
                        width: double.infinity,
                        child: TextButton(
                          onPressed: () {
                            if (!_callbackCalled) {
                              _callbackCalled = true;
                              widget.onLivenessCheckComplete(false, null);
                            }
                          },
                          style: TextButton.styleFrom(
                            padding: EdgeInsets.symmetric(vertical: 12),
                          ),
                          child: Text('Cancel'),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
  
  // Build checklist item widget
  Widget _buildChecklistItem(String title, bool isComplete, String instruction) {
    return Row(
      children: [
        // Check icon or circle
        Container(
          width: 24,
          height: 24,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isComplete ? Colors.green : Colors.grey[300],
          ),
          child: Center(
            child: isComplete
                ? Icon(Icons.check, color: Colors.white, size: 16)
                : null,
          ),
        ),
        SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: isComplete ? Colors.green : Colors.black,
                ),
              ),
              if (!isComplete)
                Text(
                  instruction,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[700],
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
  
  // Get the text for the current action
  String _getStatusText() {
    switch (_checkState) {
      case LivenessCheckState.inProgress:
        return 'Face Verification In Progress';
      case LivenessCheckState.actionRequired:
        return 'Action Required';
      case LivenessCheckState.completed:
        return 'Verification Complete!';
      case LivenessCheckState.failed:
        return 'Verification Failed';
      default:
        return 'Getting Ready...';
    }
  }

  @override
  void dispose() {
    debugPrint('Disposing enhanced liveness detection screen');
    WidgetsBinding.instance.removeObserver(this);
    _pauseProcessing();
    _autoCompleteTimer?.cancel();
    if (_isCameraInitialized) {
      _cameraController.dispose();
    }
    super.dispose();
  }
}

// Face oval guide painter
class FaceOvalPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.blue.withOpacity(0.5)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.0;
    
    // Calculate oval dimensions
    final double centerX = size.width / 2;
    final double centerY = size.height * 0.4; // Position slightly above center
    final double radiusX = size.width * 0.35;
    final double radiusY = size.height * 0.3;
    
    // Draw oval
    final rect = Rect.fromCenter(
      center: Offset(centerX, centerY),
      width: radiusX * 2,
      height: radiusY * 2,
    );
    
    canvas.drawOval(rect, paint);
    
    // Add semi-transparent overlay outside the oval
    final Path path = Path()
      ..addRect(Rect.fromLTWH(0, 0, size.width, size.height))
      ..addOval(rect)
      ..fillType = PathFillType.evenOdd;
    
    final Paint overlayPaint = Paint()
      ..color = Colors.black.withOpacity(0.5)
      ..style = PaintingStyle.fill;
    
    canvas.drawPath(path, overlayPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}