// lib/screens/attendance_screen.dart
import 'dart:io';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/face_recognition_service.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../services/notification_service.dart';
import '../services/timezone_service.dart';
import '../services/session_service.dart';

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
  String? _lastPunchDate;
  String? _lastPunchTime;
  String? _lastPhotoUrl;
  final FaceRecognitionService _faceRecognitionService = FaceRecognitionService();
  String? _faceValidationMessage;
  bool _isFaceValid = false;
  final MapController _mapController = MapController();
  bool _isCameraInitialized = false;
  Rect? _faceBounds;

  // New properties for clock
  late Timer _clockTimer;
  DateTime _currentTime = DateTime.now();
  bool _isCameraError = false;
  
  // For screen refresh
  int _screenRefreshCounter = 0;

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
          _currentTime = DateTime.now();
        });
      }
    });
  }

  // Method to format current time
  String _formatCurrentTime() {
    return DateFormat('EEEE, dd MMM yyyy HH:mm:ss').format(_currentTime);
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
            _lastPhotoUrl = status['last_punch']['photo_url'];
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
    
    // Use medium resolution on iOS and high on Android
    final ResolutionPreset resolution = Platform.isIOS 
        ? ResolutionPreset.medium
        : ResolutionPreset.high;
        
    // Use different image format for iOS
    final ImageFormatGroup formatGroup = Platform.isIOS
        ? ImageFormatGroup.yuv420
        : ImageFormatGroup.bgra8888;
    
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
      _isCameraError = false;
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
    });

    try {
      final XFile photo = await _cameraController.takePicture();
      final File photoFile = File(photo.path);
      
      // Validate face before showing preview
      final validationResult = await _faceRecognitionService.validateFace(photoFile);
      final bool isFaceValid = validationResult['isValid'];
      final Rect? faceBounds = validationResult['faceBounds'];
      
      if (mounted) {
        setState(() {
          _capturedImage = photoFile;
          _faceBounds = faceBounds;
          _showCamera = false;
          _isFaceValid = isFaceValid;
          _faceValidationMessage = isFaceValid 
              ? 'Face verification successful'
              : 'Face verification failed. Please try again and ensure:\n'
                '• Your face is clearly visible\n'
                '• You are looking directly at the camera\n'
                '• Your eyes are open\n'
                '• Only one face is in the frame';
        });

        if (!isFaceValid) {
          // Show error message and auto-reset after delay
          Future.delayed(const Duration(seconds: 3), () {
            if (mounted) {
              _retakePhoto();
            }
          });
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to take photo: $e')),
        );
      }
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
        _lastPhotoUrl = response['photo_url'];
        _showCamera = true;
        _capturedImage = null;
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

  void _retakePhoto() {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    setState(() {
      _capturedImage = null;
      _showCamera = true;
      _faceValidationMessage = null;
      _isFaceValid = false;
      _faceBounds = null;
    });
  }

  // Use timezone service for formatting time
  String _formatTime(String? timeStr) {
    return _timezoneService.formatTimeWithOffset(timeStr);
  }

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
              height: 250,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.shade300),
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
                      return CameraPreview(_cameraController);
                    } else {
                      // Loading indicator while camera is initializing
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
                  onPressed: _retakePhoto,
                  tooltip: 'Retake Photo',
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
          
          // Enhanced face preview with highlight
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            width: double.infinity,
            height: 300,
            child: Stack(
              children: [
                // Base image
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.file(
                    _capturedImage!,
                    height: 280,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  ),
                ),
                
                // Face highlight overlay if a face is detected
                if (_faceBounds != null && _isFaceValid)
                  Positioned.fill(
                    child: CustomPaint(
                      painter: FaceHighlightOverlay(
                        faceBounds: _faceBounds!,
                        imageSize: Size(
                          _cameraController.value.previewSize?.height ?? 300,
                          _cameraController.value.previewSize?.width ?? 400,
                        ),
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
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: () async {
                await _checkAttendanceStatus();
                _forceScreenRefresh();
              },
              tooltip: 'Refresh Status',
            ),
          ],
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
    _faceRecognitionService.dispose();
    
    super.dispose();
  }
}

// Class for face highlighting
class FaceHighlightOverlay extends CustomPainter {
  final Rect faceBounds;
  final Size imageSize;
  
  FaceHighlightOverlay({
    required this.faceBounds,
    required this.imageSize,
  });
  
  @override
  void paint(Canvas canvas, Size size) {
    // Create scaling factors
    final double scaleX = size.width / imageSize.width;
    final double scaleY = size.height / imageSize.height;
    
    // Calculate the face rectangle in the scaled image
    final Rect scaledFaceRect = Rect.fromLTRB(
      faceBounds.left * scaleX,
      faceBounds.top * scaleY,
      faceBounds.right * scaleX,
      faceBounds.bottom * scaleY,
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
      scaledFaceRect.top - (extraHeight * 0.6), // Add more space for forehead
      scaledFaceRect.right + (extraWidth / 2),
      scaledFaceRect.bottom + (extraHeight * 0.4) // Add some space for chin
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