// lib/screens/attendance_screen.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:geolocator/geolocator.dart';
import '../services/api_service.dart';
import 'package:intl/intl.dart';

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

class _AttendanceScreenState extends State<AttendanceScreen> {
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

  @override
  void initState() {
    super.initState();
    _initializeCamera();
    _checkAttendanceStatus();
    _getCurrentLocation();
  }

  Future<void> _initializeCamera() async {
    _cameraController = CameraController(
      widget.camera,
      ResolutionPreset.medium,
      enableAudio: false,
    );
    
    try {
      _initializeCameraFuture = _cameraController.initialize();
      await _initializeCameraFuture;
      if (mounted) setState(() {});
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error initializing camera: $e')),
        );
      }
    }
  }

  @override
  void dispose() {
    _cameraController.dispose();
    super.dispose();
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
      }
    } catch (e) {
      print('Error checking attendance status: $e');
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

  Future<void> _takePhoto() async {
    try {
      final XFile photo = await _cameraController.takePicture();
      setState(() {
        _capturedImage = File(photo.path);
        _showCamera = false;
      });
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to take photo: $e')),
      );
    }
  }

  void _retakePhoto() {
    setState(() {
      _capturedImage = null;
      _showCamera = true;
    });
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

  Future<void> _punchInOut() async {
    if (_currentPosition == null || _capturedImage == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please take photo and enable location')),
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

      setState(() {
        _isPunchedIn = !_isPunchedIn;
        _lastPunchDate = response['punch_date'];
        _lastPunchTime = response['punch_time'];
        _lastPhotoUrl = response['photo_url'];
        _showCamera = true;
        _capturedImage = null;
      });

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
                  const SizedBox(height: 16),
                  _buildLocationCard(),
                  if (_lastPunchDate != null && _lastPunchTime != null) ...[
                    const SizedBox(height: 16),
                    _buildLastPunchCard(),
                  ],
                  const SizedBox(height: 24),
                  _buildPunchButton(),
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
                    if (snapshot.connectionState == ConnectionState.done) {
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
              onPressed: _takePhoto,
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
            const Text(
              'Location',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            if (_currentPosition != null)
              Text(
                'Lat: ${_currentPosition!.latitude.toStringAsFixed(6)}\nLong: ${_currentPosition!.longitude.toStringAsFixed(6)}',
              ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: _getCurrentLocation,
              icon: const Icon(Icons.location_on),
              label: const Text('Update Location'),
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(50),
              ),
            ),
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
            if (_lastPhotoUrl != null) ...[
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(
                  'http://195.158.75.66:3000${_lastPhotoUrl}',
                  height: 100,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) {
                    return Container(
                      height: 100,
                      color: Colors.grey[200],
                      child: const Center(
                        child: Icon(Icons.error_outline),
                      ),
                    );
                  },
                ),
              ),
            ],
          ],
        ),
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
}