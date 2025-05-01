// lib/screens/attendance_screen.dart
import 'dart:io';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/face_recognition_manager.dart' show FaceRecognitionManager, FaceRegistrationStatus;
import 'enhanced_liveness_detection_screen.dart';
import '../util/image_utils.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../services/notification_service.dart';
import '../services/timezone_service.dart';
import '../services/session_service.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

final NotificationService _notificationService = NotificationService();

class AttendanceScreen extends StatefulWidget {
  final CameraDescription camera;
  final Map<String, dynamic> userDetails;

  const AttendanceScreen({
    Key? key,
    required this.camera,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> with WidgetsBindingObserver {
  late CameraController _cameraController;
  late Future<void> _initializeCameraFuture;
  Position? _currentPosition;
  File? _capturedImage;
  bool _isPunchedIn = false;
  bool _isLoading = false;
  bool _showCamera = true;
  final _apiService = ApiService();
  final _sessionService = SessionService();
  final _timezoneService = TimezoneService();
  final _faceManager = FaceRecognitionManager();
  String? _lastPunchDate;
  String? _lastPunchTime;
  String? _faceValidationMessage;
  bool _isFaceValid = false;
  final MapController _mapController = MapController();
  bool _isCameraInitialized = false;
  Rect? _faceBounds;

  // New properties for clock
  late Timer _clockTimer;
  bool _isCameraError = false;
  
  // For screen refresh
  int _screenRefreshCounter = 0;

  // Log verification details method:
  void _logVerificationDetails(Map<String, dynamic> result) {
    debugPrint('---------- VERIFICATION DETAILS ----------');
    debugPrint('Platform: ${Platform.isIOS ? "iOS" : "Android"}');
    debugPrint('Result: ${result['isVerified'] ? "VERIFIED" : "REJECTED"}');
    debugPrint('Confidence: ${result['confidence']}');
    debugPrint('Message: ${result['message']}');
    debugPrint('-----------------------------------------');
  }

  // Improved face size validation method to add to the _AttendanceScreenState class
  bool _isValidFaceSize(Rect? faceBounds, {required Size imageSize}) {
    if (faceBounds == null) return false;
    
    // Get image dimensions
    final double imageWidth = imageSize.width;
    final double imageHeight = imageSize.height;
    
    // Calculate face dimensions relative to image size
    final double faceWidth = faceBounds.width;
    final double faceHeight = faceBounds.height;
    
    // Calculate face size as percentage of image dimensions
    final double faceWidthPercent = faceWidth / imageWidth;
    final double faceHeightPercent = faceHeight / imageHeight;
    
    // Define more tolerant acceptable size ranges
    // These values are deliberately more permissive
    const double minAcceptablePercent = 0.12; // Face should take up at least 12% of width/height
    const double maxAcceptablePercent = 0.80; // Face should not take up more than 80% of width/height
    
    final bool isWidthValid = faceWidthPercent >= minAcceptablePercent && faceWidthPercent <= maxAcceptablePercent;
    final bool isHeightValid = faceHeightPercent >= minAcceptablePercent && faceHeightPercent <= maxAcceptablePercent;
    
    debugPrint('Face width: ${(faceWidthPercent * 100).toStringAsFixed(1)}% of frame (${isWidthValid ? 'VALID' : 'INVALID'})');
    debugPrint('Face height: ${(faceHeightPercent * 100).toStringAsFixed(1)}% of frame (${isHeightValid ? 'VALID' : 'INVALID'})');
    
    return isWidthValid && isHeightValid;
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    
    // Start the clock timer
    _startClockTimer();
    
    _initializeCamera();
    _checkAttendanceStatus();
    _getCurrentLocation();
  }

  // Force screen refresh
  void _forceScreenRefresh() {
    setState(() {
      _screenRefreshCounter++;
      debugPrint('Forced screen refresh. Counter: $_screenRefreshCounter');
    });
  }

  void _startClockTimer() {
    _clockTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() {
        });
      }
    });
  }

  Future<void> _checkAttendanceStatus() async {
    try {
      final status = await _apiService.getAttendanceStatus(widget.userDetails['id']);
      if (mounted) {
        setState(() {
          _isPunchedIn = status['is_punched_in'];
          if (status['last_punch'] != null) {
            _lastPunchDate = status['last_punch']['date'];
            _lastPunchTime = status['last_punch']['time'];
          }
        });
        
        // If user is already punched in, schedule a reminder notification
        if (_isPunchedIn && _lastPunchDate != null && _lastPunchTime != null) {
          try {
            // Parse the last punch date and time
            final punchDateStr = _lastPunchDate!;
            final punchTimeStr = _lastPunchTime!;
            
            debugPrint('Found existing punch-in - Date: $punchDateStr, Time: $punchTimeStr');
            
            try {
              // Create a DateTime object from the punch date and time
              final punchDate = DateTime.parse(punchDateStr);
              final timeParts = punchTimeStr.split(':');
              
              if (timeParts.length >= 2) {
                final punchDateTime = DateTime(
                  punchDate.year,
                  punchDate.month,
                  punchDate.day,
                  int.parse(timeParts[0]),
                  int.parse(timeParts[1]),
                );
                
                // If punch-in time is less than 12 hours ago, schedule a reminder
                final now = _timezoneService.getNow();
                if (now.difference(punchDateTime).inHours < 12) {
                  debugPrint('Scheduling reminder for existing punch-in');
                  try {
                    await _notificationService.schedulePunchOutReminder(
                      punchInTime: punchDateTime,
                    );
                  } catch (notificationError) {
                    debugPrint('Notification error (handled): $notificationError');
                  }
                }
              }
            } catch (parseError) {
              debugPrint('Error parsing punch date/time: $parseError');
            }
          } catch (e) {
            debugPrint('Error scheduling reminder on app start: $e');
          }
        }
      }
    } catch (e) {
      debugPrint('Error checking attendance status: $e');
    }
  }
  Future<void> _getCurrentLocation() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Location services are disabled.')),
        );
      }
      return;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Location permissions are denied.')),
          );
        }
        return;
      }
    }

    try {
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      if (mounted) {
        setState(() {
          _currentPosition = position;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error getting location: $e')),
        );
      }
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;
    
    // Handle app lifecycle changes
    if (state == AppLifecycleState.inactive || state == AppLifecycleState.paused) {
      // Release camera resources when app goes to background
      if (_isCameraInitialized && _cameraController.value.isInitialized) {
        _cameraController.dispose();
        _isCameraInitialized = false;
      }
    } else if (state == AppLifecycleState.resumed) {
      // Reinitialize camera when app is resumed
      if (!_isCameraInitialized) {
        _initializeCamera();
      }
    }
  }

  Future<void> _initializeCamera() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    debugPrint("Initializing camera on ${Platform.isIOS ? 'iOS' : 'Android'}");
    
    // Platform-specific resolution settings
    final ResolutionPreset resolution = Platform.isIOS 
        ? ResolutionPreset.medium
        : ResolutionPreset.high; // Use medium for Android too for better compatibility
        
    // Platform-specific image format
    final ImageFormatGroup formatGroup = Platform.isIOS
        ? ImageFormatGroup.yuv420
        : ImageFormatGroup.jpeg; // Use jpeg instead of bgra8888 for Android
    
    try {
      // First dispose any existing camera controller
      if (_cameraController.value.isInitialized) {
        await _cameraController.dispose();
        debugPrint("Disposed existing camera controller");
      }
    } catch (e) {
      // Ignore errors during disposal
      debugPrint('Camera disposal error (can be ignored): $e');
    }
    
    // Create a new controller
    _cameraController = CameraController(
      widget.camera,
      resolution,
      enableAudio: false,
      imageFormatGroup: formatGroup,
    );
      
    try {
      debugPrint("Starting camera initialization");
      _initializeCameraFuture = _cameraController.initialize();
      await _initializeCameraFuture;
      _isCameraInitialized = true;
      _isCameraError = false;
      
      if (Platform.isAndroid) {
        // For Android, set flash mode and exposure to improve camera stability
        await _cameraController.setFlashMode(FlashMode.auto);
        await _cameraController.setExposureMode(ExposureMode.auto);
      }
      
      debugPrint("Camera initialized successfully");
      
      if (mounted) setState(() {});
    } catch (e) {
      debugPrint('Camera initialization error: $e');
      setState(() {
        _isCameraError = true;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error initializing camera: $e')),
        );
      }
    }
  }

  // iOS-specific image quality improvements
  Future<void> _takePhoto() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    if (!_isCameraInitialized || !_cameraController.value.isInitialized) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Camera is not ready. Please wait or restart the app.')),
      );
      return;
    }

    setState(() {
      _faceValidationMessage = null;
      _isFaceValid = false;
      _faceBounds = null;
      _isLoading = true;
    });

    try {
      // Take the photo
      final XFile photo = await _cameraController.takePicture();
      final File originalPhotoFile = File(photo.path);
      
      debugPrint("Photo captured: ${originalPhotoFile.path} on ${Platform.isIOS ? 'iOS' : 'Android'}");
      
      // Apply platform-specific image processing using existing ImageUtils
      final File? processedPhotoFile = await ImageUtils.preprocessImageForPlatform(
        originalPhotoFile,
        isForFaceDetection: true
      );
      
      // Use the processed or original file
      var fileToUse = processedPhotoFile ?? originalPhotoFile;
      debugPrint("Using processed photo: ${fileToUse.path}");
      
      // Initialize the face recognition manager
      final faceManager = FaceRecognitionManager();
      await faceManager.initialize();
      
      // Get the user ID string from user details
      final String userId = widget.userDetails['id'].toString();
      
      // UNIFIED APPROACH: Use the same validation flow for both platforms
      debugPrint('Validating face in image: ${fileToUse.path}');
      final validationResult = await faceManager.mlService.validateFace(fileToUse);
      
      final bool isBasicValid = validationResult['isValid'];
      debugPrint('Face validation result: $isBasicValid (${validationResult["message"]})');

      // Add face size validation
      if (isBasicValid) {
        // Get photo dimensions
        final bytes = await fileToUse.readAsBytes();
        final image = await decodeImageFromList(bytes);
        final Size imageSize = Size(image.width.toDouble(), image.height.toDouble());
        
        // Check face bounds
        final Rect? faceBounds = validationResult['faceBounds'];
        final bool isFaceSizeValid = _isValidFaceSize(faceBounds, imageSize: imageSize);
        
        debugPrint('Face size validation: $isFaceSizeValid');
        
        if (!isFaceSizeValid) {
          // Face was detected but size is invalid
          setState(() {
            _isLoading = false;
            _capturedImage = fileToUse;
            _faceBounds = faceBounds;
            
            // Create size-specific feedback
            final String feedback = faceBounds != null 
                ? (faceBounds.width / imageSize.width < 0.15)
                    ? 'Please move closer to the camera'
                    : 'Please move further from the camera' 
                : 'Face size is not valid';
            
            _faceValidationMessage = feedback;
            _isFaceValid = false;
          });
          
          // Auto-reset after 3 seconds
          Future.delayed(const Duration(seconds: 3), () {
            if (mounted && _capturedImage != null) {
              _retakePhoto();
            }
          });
          return;
        }
      }
      
      // If basic validation fails, return early
      if (!isBasicValid) {
        setState(() {
          _isLoading = false;
          _capturedImage = fileToUse;
          _faceBounds = validationResult['faceBounds'];
          _faceValidationMessage = validationResult['message'];
          _isFaceValid = false;
        });
        
        // If basic validation fails, auto-reset after 3 seconds
        Future.delayed(const Duration(seconds: 3), () {
          if (mounted && _capturedImage != null) {
            _retakePhoto();
          }
        });
        return;
      }
      
      // ADDED: Explicit check for multiple faces on iOS
      if (Platform.isIOS) {
        try {
          // Create a detector specifically to check for multiple faces on iOS
          final multipleFaceDetector = FaceDetector(
            options: FaceDetectorOptions(
              enableLandmarks: false, // No need for landmarks
              enableClassification: false, // No need for classification
              enableTracking: false,
              minFaceSize: 0.05, // Low threshold for iOS
              performanceMode: FaceDetectorMode.fast, // Fast mode for this check
            ),
          );
          
          final inputImage = InputImage.fromFilePath(fileToUse.path);
          final faces = await multipleFaceDetector.processImage(inputImage);
          multipleFaceDetector.close();
          
          if (faces.length > 1) {
            debugPrint('iOS: Multiple faces detected: ${faces.length}');
            setState(() {
              _isLoading = false;
              _capturedImage = fileToUse;
              _faceValidationMessage = 'Multiple faces detected. Please ensure only your face is in the frame.';
              _isFaceValid = false;
            });
            
            // Auto-reset after 3 seconds
            Future.delayed(const Duration(seconds: 3), () {
              if (mounted && _capturedImage != null) {
                _retakePhoto();
              }
            });
            return;
          }
        } catch (e) {
          debugPrint('Error checking for multiple faces on iOS: $e');
          // Continue even if this check fails
        }
      }
      
      setState(() {
        _isLoading = false;
        _capturedImage = fileToUse;
        _faceBounds = validationResult['faceBounds'];
      });
      
      // Check if face is registered
      final registrationStatus = await faceManager.checkFaceRegistrationStatus(userId);
      
      if (registrationStatus == FaceRegistrationStatus.notRegistered || 
          registrationStatus == FaceRegistrationStatus.registrationInProgress) {
        // Show dialog for registration
        if (mounted) {
          showDialog(
            context: context,
            barrierDismissible: false,
            builder: (BuildContext context) {
              return AlertDialog(
                title: const Text('Face Registration Required'),
                content: const Text(
                  'You need to register your face for authentication. '
                  'Would you like to register your face now?'
                ),
                actions: <Widget>[
                  TextButton(
                    child: const Text('Cancel'),
                    onPressed: () {
                      Navigator.of(context).pop();
                      _retakePhoto();
                    },
                  ),
                  TextButton(
                    child: const Text('Register'),
                    onPressed: () async {
                      Navigator.of(context).pop();
                      _startFaceRegistration(fileToUse, userId);
                    },
                  ),
                ],
              );
            },
          );
        }
        return;
      }
      
      // Navigate to liveness detection screen - pass the original photo to use for verification
      final File originalVerificationPhoto = fileToUse;
      
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EnhancedLivenessDetectionScreen(
            camera: widget.camera,
            userDetails: widget.userDetails,
            onLivenessCheckComplete: (success, capturedImage) async {
              // Pop the liveness screen
              if (Navigator.canPop(context)) {
                Navigator.pop(context);
              }
              
              if (!success) {
                // Liveness check failed
                setState(() {
                  _faceValidationMessage = 'Face verification failed. Please try again.';
                  _isFaceValid = false;
                });
                
                // Make sure camera is reinitialized after liveness failure
                _reinitializeCamera().then((_) {
                  // Auto-reset after delay and camera reinitialization
                  Future.delayed(const Duration(seconds: 1), () {
                    if (mounted) {
                      _retakePhoto();
                    }
                  });
                });
                return;
              }
              
              // Always use the original photo for verification, ignoring liveness captured image
              debugPrint('Liveness check passed, using original photo for verification');
              _processVerificationAfterLiveness(originalVerificationPhoto, userId);
            },
          ),
        ),
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to take photo: $e')),
        );
      }
    }
  }

  // New helper method to safely reinitialize the camera
  Future<void> _reinitializeCamera() async {
    debugPrint("Reinitializing camera after liveness check");
    if (_isCameraInitialized) {
      try {
        // Dispose old controller
        await _cameraController.dispose();
        _isCameraInitialized = false;
      } catch (e) {
        debugPrint("Error disposing camera: $e");
      }
    }
    
    // Platform-specific delay
    if (Platform.isIOS) {
      // iOS needs a longer delay
      await Future.delayed(Duration(milliseconds: 1500));
    } else {
      await Future.delayed(Duration(milliseconds: 500));
    }
    
    // Initialize with proper resolution for the platform
    return _initializeCamera();
  }

  // Add new method for face registration
  Future<void> _startFaceRegistration(File photoFile, String userId) async {
    setState(() {
      _isLoading = true;
      _faceValidationMessage = 'Registering face...';
    });
    
    try {
      final faceManager = FaceRecognitionManager();
      await faceManager.initialize();
      
      // First try to sync from server
      bool syncSuccess = await faceManager.syncUserFaceData(userId);
      
      // If sync failed, register with the current photo
      if (!syncSuccess) {
        bool registrationSuccess = await faceManager.registerFace(photoFile, userId);
        
        if (!registrationSuccess) {
          setState(() {
            _isLoading = false;
            _faceValidationMessage = 'Face registration failed. Please try again.';
            _isFaceValid = false;
          });
          
          Future.delayed(const Duration(seconds: 3), () {
            if (mounted) {
              _retakePhoto();
            }
          });
          return;
        }
      }
      
      // Registration successful, proceed with liveness detection
      setState(() {
        _isLoading = false;
        _faceValidationMessage = 'Face registered successfully. Now proceed with verification.';
      });
      
      // Short delay before proceeding with liveness detection
      Future.delayed(const Duration(seconds: 1), () {
        if (mounted) {
          // Navigate to liveness detection
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => EnhancedLivenessDetectionScreen(
                camera: widget.camera,
                userDetails: widget.userDetails,
                onLivenessCheckComplete: (success, capturedImage) {
                  // Handle liveness completion (same as in _takePhoto)
                  // Implementation similar to the callback in _takePhoto
                  if (Navigator.canPop(context)) {
                    Navigator.pop(context);
                  }
                  
                  if (!success) {
                    setState(() {
                      _faceValidationMessage = 'Face verification failed. Please try again.';
                      _isFaceValid = false;
                    });
                    
                    _reinitializeCamera().then((_) {
                      Future.delayed(const Duration(seconds: 1), () {
                        if (mounted) {
                          _retakePhoto();
                        }
                      });
                    });
                    return;
                  }
                  
                  // Process the verification as in the original method
                  _processVerificationAfterLiveness(capturedImage ?? photoFile, userId);
                },
              ),
            ),
          );
        }
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _faceValidationMessage = 'Error during registration: $e';
        _isFaceValid = false;
      });
      
      Future.delayed(const Duration(seconds: 3), () {
        if (mounted) {
          _retakePhoto();
        }
      });
    }
  }

  // Helper method to process verification after liveness
  Future<void> _processVerificationAfterLiveness(File imageFile, String userId) async {
    setState(() {
      _isLoading = true;
      _faceValidationMessage = 'Verifying face...';
    });
    
    // Debug the image file
    debugPrint('Verification image path: ${imageFile.path}');
    debugPrint('Verification image exists: ${await imageFile.exists()}');
    debugPrint('Verification image size: ${await imageFile.length()} bytes');
    
    try {
      final faceManager = FaceRecognitionManager();
      
      // Add iOS-specific image processing
      final File fileToVerify = Platform.isIOS 
          ? await ImageUtils.enhanceImageForFaceDetection(imageFile) ?? imageFile
          : imageFile;
      
      // Debug enhanced image file
      debugPrint('Enhanced verification image path: ${fileToVerify.path}');
      debugPrint('Enhanced verification image exists: ${await fileToVerify.exists()}');
      debugPrint('Enhanced verification image size: ${await fileToVerify.length()} bytes');
      
      // Verify face with on-device recognition
      final verificationResult = await faceManager.verifyFaceWithLiveness(
        fileToVerify,
        userId,
        requireLiveness: false // Already passed liveness check
      );

      // Add the logging call right after getting the result
      _logVerificationDetails(verificationResult);
      
      setState(() {
        _isLoading = false;
        _showCamera = false;
        _isFaceValid = verificationResult['isVerified'];
        _faceValidationMessage = _isFaceValid 
            ? 'Face verification successful'
            : 'Face verification failed: ${verificationResult['message']}';
        
        // Update the displayed image
        _capturedImage = fileToVerify;
        
        // Debug the display state
        debugPrint('Setting _capturedImage to: ${fileToVerify.path}');
        debugPrint('_showCamera set to: $_showCamera');
        debugPrint('_isFaceValid set to: $_isFaceValid');
      });

      if (!_isFaceValid) {
        _reinitializeCamera().then((_) {
          Future.delayed(const Duration(seconds: 3), () {
            if (mounted) {
              _retakePhoto();
            }
          });
        });
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
        _faceValidationMessage = 'Error: $e';
        _isFaceValid = false;
      });
      
      _reinitializeCamera().then((_) {
        Future.delayed(const Duration(seconds: 3), () {
          if (mounted) {
            _retakePhoto();
          }
        });
      });
    }
  }
  
  Future<void> _punchInOut() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    if (_currentPosition == null || _capturedImage == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please take photo and enable location')),
      );
      return;
    }

    if (!_isFaceValid) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Face verification failed. Please retake photo.')),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      // Debug log before API call
      debugPrint('STARTING PUNCH OPERATION at ${DateTime.now()}');
      debugPrint('Current status: ${_isPunchedIn ? 'IN' : 'OUT'}, Current time: $_lastPunchTime');
      
      final response = await _apiService.recordAttendance(
        punchType: _isPunchedIn ? 'OUT' : 'IN',
        photoFile: _capturedImage!,
        latitude: _currentPosition!.latitude,
        longitude: _currentPosition!.longitude,
      );

      // Log the entire response for detailed inspection
      debugPrint('FULL API RESPONSE: ${response.toString()}');
      
      // Log specific time values
      debugPrint('API PUNCH TIME: ${response['punch_time']}');
      debugPrint('API PUNCH DATE: ${response['punch_date']}');
      
      // Compare with local time
      final localTime = DateFormat('HH:mm:ss').format(DateTime.now());
      debugPrint('LOCAL DEVICE TIME: $localTime');
      
      // Check time difference
      try {
        if (response['punch_time'] != null) {
          final timeParts = response['punch_time'].split(':');
          if (timeParts.length >= 2) {
            final apiHour = int.parse(timeParts[0]);
            final apiMinute = int.parse(timeParts[1]);
            
            final now = DateTime.now();
            final localHour = now.hour;
            final localMinute = now.minute;
            
            debugPrint('TIME COMPARISON: API time [${apiHour}:${apiMinute}], Local time [${localHour}:${localMinute}]');
            debugPrint('HOUR DIFFERENCE: ${localHour - apiHour}');
          }
        }
      } catch (timeError) {
        debugPrint('Error analyzing time difference: $timeError');
      }
      
      // Store the new punch status
      final bool newPunchStatus = !_isPunchedIn;
      final String newPunchTime = response['punch_time'];
      final String newPunchDate = response['punch_date'];
      
      debugPrint('New status: ${newPunchStatus ? 'IN' : 'OUT'}, New time: $newPunchTime');
      
      // Update the state with new data
      setState(() {
        _isPunchedIn = newPunchStatus;
        _lastPunchDate = newPunchDate;
        _lastPunchTime = newPunchTime;
        _showCamera = true;
        _capturedImage = null;
        _isCameraInitialized = false;
      });
      
      // Force screen refresh
      _forceScreenRefresh();
      debugPrint('Screen refreshed. Refresh counter: $_screenRefreshCounter');

      // Handle notifications with error catching
      try {
        if (newPunchStatus) {
          // User punched in
          await _notificationService.schedulePunchOutReminder(
            punchInTime: _timezoneService.getNow(),
          );
          debugPrint('Punch-out reminder scheduled');
        } else {
          // User punched out
          await _notificationService.cancelPunchOutReminder();
          debugPrint('Punch-out reminder cancelled');
        }
      } catch (notificationError) {
        // Just log the error but continue with app flow
        debugPrint('Notification operation failed: $notificationError');
      }

      // After a short delay, check attendance status again
      Future.delayed(const Duration(milliseconds: 500), () async {
        if (mounted) {
          try {
            // Re-fetch attendance status from server
            await _checkAttendanceStatus();
            
            // Force another refresh
            _forceScreenRefresh();
            debugPrint('Second refresh after status check. Counter: $_screenRefreshCounter');

            // Properly reinitialize camera after punch operation
            await _reinitializeCamera();
            
          } catch (e) {
            debugPrint('Error refreshing attendance: $e');
          }
        }
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Successfully ${_isPunchedIn ? 'Punched In' : 'Punched Out'} at $newPunchTime'),
          backgroundColor: _isPunchedIn ? Colors.green : Colors.red,
        ),
      );
    } catch (e) {
      debugPrint('Error during punch operation: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
  // Method to retake photo
  void _retakePhoto() {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    debugPrint("Retaking photo on ${Platform.isIOS ? 'iOS' : 'Android'}");
    
    // First reset UI state
    setState(() {
      _capturedImage = null;
      _showCamera = true;
      _faceValidationMessage = null;
      _isFaceValid = false;
      _faceBounds = null;
      // Set to false during reinitialization
      _isCameraInitialized = false;
    });
    
    // Platform-specific camera handling
    if (Platform.isAndroid) {
      // Android needs more careful camera disposal
      if (_cameraController.value.isInitialized) {
        debugPrint("Android: Properly disposing camera");
        _cameraController.dispose().then((_) {
          debugPrint("Android: Camera disposed successfully");
          // Add slightly longer delay for Android
          Future.delayed(Duration(milliseconds: 500), () {
            debugPrint("Android: Reinitializing camera after delay");
            _initializeCamera().then((_) {
              debugPrint("Android: Camera reinitialized");
              if (mounted) {
                setState(() {
                  // Force UI refresh after camera is reinitialized
                });
              }
            });
          });
        }).catchError((error) {
          debugPrint("Android: Error disposing camera: $error");
          // Even if disposal fails, try to reinitialize
          Future.delayed(Duration(milliseconds: 500), () {
            _initializeCamera();
          });
        });
      } else {
        // If camera was never initialized, just initialize it
        _initializeCamera();
      }
    } else {
      // iOS handling (already working correctly)
      Future.delayed(Duration(milliseconds: 200), () {
        if (_cameraController.value.isInitialized) {
          try {
            _cameraController.dispose();
          } catch (e) {
            debugPrint("iOS: Error disposing camera: $e");
          }
        }
        
        _initializeCamera();
      });
    }
  }

  // Use timezone service for formatting time

  // Build Camera Card with clock
  Widget _buildCameraCard() {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // Add Clock at the top of the camera card
            Row(
              children: [
                const Icon(Icons.access_time, color: Colors.blue),
                const SizedBox(width: 8),
                StreamBuilder(
                  stream: Stream.periodic(const Duration(seconds: 1)),
                  builder: (context, snapshot) {
                    return Text(
                      DateFormat('EEEE, dd MMM yyyy HH:mm:ss').format(DateTime.now()),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    );
                  },
                ),
              ],
            ),
            
            const SizedBox(height: 12),
            
            // Camera Preview Container
            Container(
              height: 350,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.shade300),
                color: Colors.black, // Background color for areas not covered by the preview
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: FutureBuilder<void>(
                  future: _initializeCameraFuture,
                  builder: (context, snapshot) {
                    // Camera initialization checks
                    if (snapshot.connectionState == ConnectionState.done && 
                        _isCameraInitialized && 
                        _cameraController.value.isInitialized) {
                      return Stack(
                        children: [
                          // Center the camera preview
                          Center(
                            child: CameraPreview(_cameraController),
                          ),
                          
                          // Simple face guide overlay - centered in the view
                          Center(
                            child: Container(
                              width: 150,  // Fixed width for the guide
                              height: 180, // Fixed height for the guide
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(30),
                                border: Border.all(
                                  color: Colors.green.withOpacity(0.7),
                                  width: 3,
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    } else {
                      // Loading indicator - unchanged
                      return const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            CircularProgressIndicator(),
                            SizedBox(height: 16),
                            Text(
                              'Initializing Camera',
                              style: TextStyle(
                                color: Colors.grey,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      );
                    }
                  },
                ),
              ),
            ),
            
            // Spacer
            const SizedBox(height: 16),
            
            // Take Photo Button
            ElevatedButton.icon(
              onPressed: _isCameraInitialized ? _takePhoto : null,
              icon: const Icon(Icons.camera_alt),
              label: const Text('Take Photo'),
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(50),
                backgroundColor: _isCameraInitialized ? Colors.blue : Colors.grey,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Build preview card with face highlighting
  Widget _buildPreviewCard() {
    // Debug the image being used for preview
    debugPrint('Building preview card with image: ${_capturedImage?.path}');
    debugPrint('Image exists: ${_capturedImage?.existsSync()}');
    debugPrint('Image size: ${_capturedImage?.lengthSync()} bytes');
      
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Preview',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: () {
                    debugPrint("Manual camera refresh requested");
                    _retakePhoto();
                  },
                  tooltip: 'Refresh Camera',
                ),
              ],
            ),
          ),
          // Add clock to photo preview as well
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Row(
              children: [
                const Icon(Icons.access_time, color: Colors.blue, size: 18),
                const SizedBox(width: 8),
                StreamBuilder(
                  stream: Stream.periodic(const Duration(seconds: 1)),
                  builder: (context, snapshot) {
                    return Text(
                      DateFormat('EEEE, dd MMM yyyy HH:mm:ss').format(DateTime.now()),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
          // Face validation message
          if (_faceValidationMessage != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              color: _isFaceValid ? Colors.green.withOpacity(0.1) : Colors.red.withOpacity(0.1),
              child: Row(
                children: [
                  Icon(
                    _isFaceValid ? Icons.check_circle : Icons.error,
                    color: _isFaceValid ? Colors.green : Colors.red,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _faceValidationMessage!,
                      style: TextStyle(
                        color: _isFaceValid ? Colors.green : Colors.red,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          
          // Enhanced face preview with highlight - MODIFIED to avoid excessive cropping
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            width: double.infinity,
            height: 300,
            child: Stack(
              children: [
                // Base image - MODIFIED to use "contain" fit rather than "cover"
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.file(
                    _capturedImage!,
                    height: 280,
                    width: double.infinity,
                    fit: BoxFit.contain, // Changed from "cover" to "contain" to avoid excessive cropping
                  ),
                ),
                
                // Face highlight overlay if a face is detected - MODIFIED to adjust with contain fitting
                if (_faceBounds != null && _isFaceValid)
                  Positioned.fill(
                    child: CustomPaint(
                      painter: FaceHighlightOverlay(
                        faceBounds: _faceBounds!,
                        imageSize: Size(
                          _cameraController.value.previewSize?.height ?? 300,
                          _cameraController.value.previewSize?.width ?? 400,
                        ),
                        useFillFit: false, // New parameter to indicate we're using "contain" instead of "cover"
                      ),
                    ),
                  ),
                
                // "Verified" badge when face is valid
                if (_isFaceValid)
                  Positioned(
                    top: 10,
                    right: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.green,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.verified_user, color: Colors.white, size: 16),
                          SizedBox(width: 4),
                          Text(
                            'Verified',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  // Build punch button
  Widget _buildPunchButton() {
    return ElevatedButton.icon(
      onPressed: _isLoading ? null : _punchInOut,
      icon: Icon(_isPunchedIn ? Icons.logout : Icons.login),
      label: Text(_isPunchedIn ? 'Punch Out' : 'Punch In'),
      style: ElevatedButton.styleFrom(
        minimumSize: const Size.fromHeight(50),
        backgroundColor: _isPunchedIn ? Colors.red : Colors.green,
        foregroundColor: Colors.white,
      ),
    );
  }

  // Build location card
  Widget _buildLocationCard() {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Location',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                ElevatedButton.icon(
                  onPressed: _getCurrentLocation,
                  icon: const Icon(Icons.my_location, size: 18),
                  label: const Text('Update'),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              height: 200,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.shade300),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: _currentPosition == null
                  ? const Center(child: Text('Getting location...'))
                  : FlutterMap(
                      mapController: _mapController,
                      options: MapOptions(
                        initialCenter: LatLng(_currentPosition!.latitude, _currentPosition!.longitude),
                        initialZoom: 15,
                      ),
                      children: [
                        TileLayer(
                          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'com.your.app.package',
                        ),
                        MarkerLayer(
                          markers: [
                            Marker(
                              point: LatLng(_currentPosition!.latitude, _currentPosition!.longitude),
                              child: const Icon(
                                Icons.location_on,
                                color: Colors.red,
                                size: 40,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
              ),
            ),
            if (_currentPosition != null) ...[
              const SizedBox(height: 8),
              Text(
                'Coordinates: ${_currentPosition!.latitude.toStringAsFixed(6)}, ${_currentPosition!.longitude.toStringAsFixed(6)}',
                style: const TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ],
        ),
      ),
    );
  }
  // Build last punch card
  Widget _buildLastPunchCard() {
    return Card(
      key: ValueKey('lastPunch-$_screenRefreshCounter-${_lastPunchDate}-${_lastPunchTime}'),
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Last Punch Details',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.refresh, size: 20),
                  onPressed: () async {
                    await _checkAttendanceStatus();
                    _forceScreenRefresh();
                  },
                  tooltip: 'Refresh',
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text('Date: ${_timezoneService.formatDateWithOffset(_lastPunchDate)}'),
            // Display the raw time string directly, without formatting
            Text('Time: ${_lastPunchTime ?? 'N/A'}'),
            Text(
              'Status: ${_isPunchedIn ? 'Punched In' : 'Punched Out'}',
              style: TextStyle(
                color: _isPunchedIn ? Colors.green : Colors.red,
                fontWeight: FontWeight.bold,
              ),
            ),
            // Add a small indicator showing when the card was last refreshed
            Text(
              'Refreshed: ${DateFormat('HH:mm:ss').format(DateTime.now())} (#$_screenRefreshCounter)',
              style: const TextStyle(fontSize: 10, color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return KeyedSubtree(
      key: ValueKey('attendance-screen-$_screenRefreshCounter'),
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Attendance'),
          // Removed all debugging buttons from the actions array
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [

                    // Camera error handling
                    if (_isCameraError) 
                      Card(
                        color: Colors.red[50],
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            children: [
                              const Icon(Icons.camera_alt, color: Colors.red, size: 50),
                              const SizedBox(height: 16),
                              const Text(
                                'Camera Unavailable',
                                style: TextStyle(
                                  color: Colors.red,
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold
                                ),
                              ),
                              const SizedBox(height: 8),
                              ElevatedButton(
                                onPressed: _initializeCamera,
                                child: const Text('Retry Camera'),
                              )
                            ],
                          ),
                        ),
                      ),

                    // Camera section
                    if (!_isCameraError && _showCamera) ...[
                      _buildCameraCard(),
                    ],
                    
                    // Preview section
                    if (_capturedImage != null) ...[
                      const SizedBox(height: 16),
                      _buildPreviewCard(),
                    ],
                    
                    const SizedBox(height: 24),
                    _buildPunchButton(),
                    const SizedBox(height: 16),
                    _buildLocationCard(),
                    
                    // Last punch details
                    if (_lastPunchDate != null && _lastPunchTime != null) ...[
                      const SizedBox(height: 16),
                      _buildLastPunchCard(),
                    ],
                  ],
                ),
              ),
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    
    // Cancel the clock timer
    _clockTimer.cancel();
    
    // Safely dispose camera
    if (_isCameraInitialized && _cameraController.value.isInitialized) {
      _cameraController.dispose();
    }
    
    // Dispose the face recognition service
    _faceManager.dispose();
    
    super.dispose();
  }
}

// Class for face highlighting
class FaceHighlightOverlay extends CustomPainter {
  final Rect faceBounds;
  final Size imageSize;
  final bool useFillFit;
  
  FaceHighlightOverlay({
    required this.faceBounds,
    required this.imageSize,
    this.useFillFit = true, // Default to the original behavior (cover)
  });
  
  @override
  void paint(Canvas canvas, Size size) {
    // Create scaling factors - modified to handle contain vs cover fitting
    double scaleX, scaleY;
    double offsetX = 0, offsetY = 0;
    
    if (useFillFit) {
      // Original "cover" behavior - fill the entire area
      scaleX = size.width / imageSize.width;
      scaleY = size.height / imageSize.height;
    } else {
      // "Contain" behavior - maintain aspect ratio, calculate centering offsets
      final double imageAspectRatio = imageSize.width / imageSize.height;
      final double containerAspectRatio = size.width / size.height;
      
      if (imageAspectRatio > containerAspectRatio) {
        // Image is wider than container - fit to width, center vertically
        scaleX = scaleY = size.width / imageSize.width;
        offsetY = (size.height - (imageSize.height * scaleY)) / 2;
      } else {
        // Image is taller than container - fit to height, center horizontally
        scaleX = scaleY = size.height / imageSize.height;
        offsetX = (size.width - (imageSize.width * scaleX)) / 2;
      }
    }
    
    // Calculate the face rectangle in the scaled image, accounting for offsets
    final Rect scaledFaceRect = Rect.fromLTRB(
      faceBounds.left * scaleX + offsetX,
      faceBounds.top * scaleY + offsetY,
      faceBounds.right * scaleX + offsetX,
      faceBounds.bottom * scaleY + offsetY,
    );
    
    // Calculate enlarged face rectangle that covers more of the head
    // Make it taller by adding more padding to top and bottom
    // Original faceBounds usually cuts off forehead and chin
    final double extraHeightFactor = 0.9; // 90% height addition
    final double extraWidthFactor = 0.1; // 10% width addition
    
    final double originalHeight = scaledFaceRect.height;
    final double originalWidth = scaledFaceRect.width;
    final double extraHeight = originalHeight * extraHeightFactor;
    final double extraWidth = originalWidth * extraWidthFactor;
    
    // Create an enlarged rectangle that better encompasses the whole head
    Rect enlargedFaceRect = Rect.fromLTRB(
      scaledFaceRect.left - (extraWidth / 2), // Add width on both sides
      scaledFaceRect.top - (extraHeight * 0.1), // Add more space for forehead
      scaledFaceRect.right + (extraWidth / 2),
      scaledFaceRect.bottom + (extraHeight * 0.1) // Add some space for chin
    );
    
    // Ensure the rectangle stays within the preview bounds
    // with a small margin (5 pixels) from the edges
    final double margin = 5.0;
    final Rect bounds = Rect.fromLTRB(
      margin, 
      margin, 
      size.width - margin, 
      size.height - margin
    );
    
    // Constrain the enlarged rectangle to stay within bounds
    enlargedFaceRect = Rect.fromLTRB(
      enlargedFaceRect.left.clamp(bounds.left, bounds.right),
      enlargedFaceRect.top.clamp(bounds.top, bounds.bottom),
      enlargedFaceRect.right.clamp(bounds.left, bounds.right),
      enlargedFaceRect.bottom.clamp(bounds.top, bounds.bottom)
    );
    
    // Add rounded corners to the square
    final RRect roundedRect = RRect.fromRectAndRadius(
      enlargedFaceRect,
      Radius.circular(12), // Adjust corner radius as needed
    );
    
    // Draw semi-transparent overlay
    final Paint overlayPaint = Paint()
      ..color = Colors.black.withOpacity(0.3)
      ..style = PaintingStyle.fill;
    
    // Draw green border around face
    final Paint borderPaint = Paint()
      ..color = Colors.green
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    
    // Create a "hole" effect using layers
    
    // First, save the current canvas state
    canvas.saveLayer(Rect.fromLTWH(0, 0, size.width, size.height), Paint());
    
    // Draw the background overlay
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, size.height), overlayPaint);
    
    // Use destination-out blend mode to create transparent hole
    final Paint holePaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill
      ..blendMode = BlendMode.dstOut;
    
    // Draw the "cutout" rectangle (square with rounded corners)
    canvas.drawRRect(roundedRect, holePaint);
    
    // Restore the canvas state to apply the blend
    canvas.restore();
    
    // Draw the green border around the face (this will be on top)
    canvas.drawRRect(roundedRect, borderPaint);
  }
  
  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}

// Add this simpler face guide overlay class
class SimpleFaceGuideOverlay extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final double width = size.width;
    final double height = size.height;
    
    // Calculate a more appropriate face guide rectangle
    // Make it wider and more rectangular (less oval)
    final double guideWidth = width * 0.1; // 10% of width
    final double guideHeight = height * 0.3; // 30% of height
    
    // Position the guide in the center
    final double left = (width - guideWidth) / 2;
    final double top = (height - guideHeight) / 2;
    final double right = left + guideWidth;
    final double bottom = top + guideHeight;
    
    final Rect guideRect = Rect.fromLTRB(left, top, right, bottom);
    
    // Create a rounded rectangle with less rounding
    final RRect roundedRect = RRect.fromRectAndRadius(
      guideRect,
      Radius.circular(20), // Less rounded corners
    );
    
    // Just draw a border for the guide - no transparency overlay
    final Paint borderPaint = Paint()
      ..color = Colors.green.withOpacity(0.7)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    
    // Draw the border
    canvas.drawRRect(roundedRect, borderPaint);
  }
  
  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}