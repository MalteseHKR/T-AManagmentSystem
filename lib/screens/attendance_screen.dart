// lib/screens/attendance_screen.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:geolocator/geolocator.dart';
import '../services/api_service.dart';
import '../services/face_recognition_service.dart';
import 'package:intl/intl.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../services/notification_service.dart';

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
  String? _lastPunchDate;
  String? _lastPunchTime;
  String? _lastPhotoUrl;
  final FaceRecognitionService _faceRecognitionService = FaceRecognitionService();
  String? _faceValidationMessage;
  bool _isFaceValid = false;
  final MapController _mapController = MapController();
  bool _isCameraInitialized = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initializeCamera();
    _checkAttendanceStatus();
    _getCurrentLocation();
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
      print('Camera disposal error (can be ignored): $e');
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
      if (mounted) setState(() {});
    } catch (e) {
      print('Camera initialization error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error initializing camera: $e')),
        );
      }
    }
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
              final now = DateTime.now();
              if (now.difference(punchDateTime).inHours < 12) {
                debugPrint('Scheduling reminder for existing punch-in');
                await _notificationService.schedulePunchOutReminder(
                  punchInTime: punchDateTime,
                );
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

  String _formatDate(String? dateStr) {
    if (dateStr == null) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      return DateFormat('MMM dd, yyyy').format(date);
    } catch (e) {
      return dateStr;
    }
  }

  String _formatTime(String? timeStr) {
    if (timeStr == null) return 'N/A';
    try {
      return DateFormat('hh:mm a').format(DateFormat('HH:mm:ss').parse(timeStr));
    } catch (e) {
      return timeStr;
    }
  }

  void _retakePhoto() {
    setState(() {
      _capturedImage = null;
      _showCamera = true;
      _faceValidationMessage = null;
      _isFaceValid = false;
    });
  }

  Future<void> _takePhoto() async {
    if (!_isCameraInitialized || !_cameraController.value.isInitialized) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Camera is not ready. Please wait or restart the app.')),
      );
      return;
    }
    
    setState(() {
      _faceValidationMessage = null;
      _isFaceValid = false;
    });

    try {
      final XFile photo = await _cameraController.takePicture();
      final File photoFile = File(photo.path);
      
      // Validate face before showing preview
      final bool isFaceValid = await _faceRecognitionService.validateFace(photoFile);
      
      if (mounted) {
        setState(() {
          _capturedImage = photoFile;
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
    final response = await _apiService.recordAttendance(
      punchType: _isPunchedIn ? 'OUT' : 'IN',
      photoFile: _capturedImage!,
      latitude: _currentPosition!.latitude,
      longitude: _currentPosition!.longitude,
    );

    // Store the new punch status
    final bool newPunchStatus = !_isPunchedIn;
    
    setState(() {
      _isPunchedIn = newPunchStatus;
      _lastPunchDate = response['punch_date'];
      _lastPunchTime = response['punch_time'];
      _lastPhotoUrl = response['photo_url'];
      _showCamera = true;
      _capturedImage = null;
    });

    // If the user is punching in, schedule a notification reminder
    if (newPunchStatus) {
      debugPrint('User punched in at ${DateTime.now()}, scheduling punch-out reminder');
      try {
        await _notificationService.schedulePunchOutReminder(
          punchInTime: DateTime.now(),
        );
        debugPrint('Punch-out reminder scheduled successfully');
      } catch (notificationError) {
        debugPrint('Failed to schedule punch-out reminder: $notificationError');
        // Continue with normal flow even if notification scheduling fails
      }
    } else {
      // If the user is punching out, cancel any pending reminders
      debugPrint('User punched out, cancelling punch-out reminder');
      try {
        await _notificationService.cancelPunchOutReminder();
        debugPrint('Punch-out reminder cancelled successfully');
      } catch (cancelError) {
        debugPrint('Failed to cancel punch-out reminder: $cancelError');
        // Continue with normal flow even if notification cancellation fails
      }
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Successfully ${_isPunchedIn ? 'Punched In' : 'Punched Out'} at ${_formatTime(_lastPunchTime)}'),
        backgroundColor: _isPunchedIn ? Colors.green : Colors.red,
      ),
    );
  } catch (e) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Error: $e')),
    );
  } finally {
    setState(() {
      _isLoading = false;
    });
  }
}

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Attendance'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_showCamera) ...[
                    _buildCameraCard(),
                  ],
                  if (_capturedImage != null) ...[
                    const SizedBox(height: 16),
                    _buildPreviewCard(),
                  ],
                  const SizedBox(height: 24),
                  _buildPunchButton(),
                  const SizedBox(height: 16),
                  _buildLocationCard(),
                  if (_lastPunchDate != null && _lastPunchTime != null) ...[
                    const SizedBox(height: 16),
                    _buildLastPunchCard(),
                  ],
                ],
              ),
            ),
    );
  }

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
                    if (snapshot.connectionState == ConnectionState.done && 
                        _isCameraInitialized && 
                        _cameraController.value.isInitialized) {
                      return CameraPreview(_cameraController);
                    } else {
                      return const Center(child: CircularProgressIndicator());
                    }
                  },
                ),
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: _isCameraInitialized ? _takePhoto : null,
              icon: const Icon(Icons.camera_alt),
              label: const Text('Take Photo'),
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(50),
              ),
            ),
          ],
        ),
      ),
    );
  }

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
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Image.file(
              _capturedImage!,
              height: 350,
              width: double.infinity,
              fit: BoxFit.cover,
            ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

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


  Widget _buildLastPunchCard() {
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
            const Text(
              'Last Punch Details',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text('Date: ${_formatDate(_lastPunchDate)}'),
            Text('Time: ${_formatTime(_lastPunchTime)}'),
            Text(
              'Status: ${_isPunchedIn ? 'Punched In' : 'Punched Out'}',
              style: TextStyle(
                color: _isPunchedIn ? Colors.green : Colors.red,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    
    // Safely dispose camera
    if (_isCameraInitialized && _cameraController.value.isInitialized) {
      _cameraController.dispose();
    }
    
    // Dispose the face recognition service
    _faceRecognitionService.dispose();
    
    super.dispose();
  }
} // End of _AttendanceScreenState class