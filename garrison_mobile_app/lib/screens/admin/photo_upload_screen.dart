// lib/screens/admin/photo_upload_screen.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as path;
import '../../services/api_service.dart';
import '../../services/session_service.dart';
import '../../services/face_recognition_service.dart';

class PhotoUploadScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;
  final CameraDescription camera;

  const PhotoUploadScreen({
    Key? key,
    required this.userDetails,
    required this.camera,
  }) : super(key: key);

  @override
  State<PhotoUploadScreen> createState() => _PhotoUploadScreenState();
}

class _PhotoUploadScreenState extends State<PhotoUploadScreen> with WidgetsBindingObserver {
  final ApiService _apiService = ApiService();
  final SessionService _sessionService = SessionService();
  final FaceRecognitionService _faceRecognitionService = FaceRecognitionService();
  
  late CameraController _cameraController;
  late Future<void> _initializeCameraFuture;
  bool _isLoading = true;
  bool _isCameraInitialized = false;
  File? _capturedImage;
  String? _faceValidationMessage;
  bool _isFaceValid = false;
  Rect? _faceBounds;
  
  // Selected user data
  int? _selectedUserId;
  Map<String, dynamic>? _selectedUserData;
  
  // All users list
  List<Map<String, dynamic>> _allUsers = [];
  String _searchQuery = '';
  TextEditingController _searchController = TextEditingController();
  
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initializeCamera();
    _loadUsers();
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
    
    setState(() {
      _isLoading = true;
    });
    
    // Platform-specific settings - using rear camera for better quality photos
    final ResolutionPreset resolution = ResolutionPreset.high;
    
    try {
      // First dispose any existing camera controller
      if (_cameraController != null && _cameraController.value.isInitialized) {
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
    );
      
    try {
      debugPrint("Starting camera initialization");
      _initializeCameraFuture = _cameraController.initialize();
      await _initializeCameraFuture;
      _isCameraInitialized = true;
      
      if (Platform.isAndroid) {
        // For Android, set flash mode and exposure to improve camera stability
        await _cameraController.setFlashMode(FlashMode.off);
        await _cameraController.setExposureMode(ExposureMode.auto);
      }
      
      debugPrint("Camera initialized successfully");
      
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('Camera initialization error: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error initializing camera: $e')),
        );
      }
    }
  }
  
  Future<void> _loadUsers() async {
    try {
      // Use a new endpoint to get all active users for face registration
      final users = await _apiService.getAllActiveUsers();
      
      if (mounted) {
        setState(() {
          _allUsers = users;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading users: $e')),
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
    
    if (_selectedUserId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a user first')),
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
      final XFile photo = await _cameraController.takePicture();
      final File photoFile = File(photo.path);
      
      // First validate basic face requirements
      final validationResult = await _faceRecognitionService.validateFace(photoFile);
      final bool isBasicValid = validationResult['isValid'];
      
      if (!isBasicValid) {
        setState(() {
          _isLoading = false;
          _capturedImage = photoFile;
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
      
      // If validation passes, set the captured image and validate
      setState(() {
        _isLoading = false;
        _capturedImage = photoFile;
        _faceBounds = validationResult['faceBounds'];
        _faceValidationMessage = 'Face validated successfully. Ready to register.';
        _isFaceValid = true;
      });
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
  
  void _retakePhoto() {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    setState(() {
      _capturedImage = null;
      _faceValidationMessage = null;
      _isFaceValid = false;
      _faceBounds = null;
    });
  }
  
  Future<void> _registerFace() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    if (_capturedImage == null || !_isFaceValid || _selectedUserId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please take a valid photo and select a user')),
      );
      return;
    }
    
    setState(() {
      _isLoading = true;
    });
    
    try {
      // Register the face photo
      final result = await _faceRecognitionService.registerFacePhoto(
        _capturedImage!,
        _selectedUserId.toString(),
        _apiService.token!,
      );
      
      if (result['success']) {
        // Success!
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Face registered successfully for ${_selectedUserData!['name']} ${_selectedUserData!['surname']}'),
              backgroundColor: Colors.green,
            ),
          );
          
          // Reset the form for next photo
          setState(() {
            _capturedImage = null;
            _faceValidationMessage = null;
            _isFaceValid = false;
            _faceBounds = null;
          });
        }
      } else {
        // Registration failed
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Face registration failed: ${result['message']}'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error registering face: $e'),
            backgroundColor: Colors.red,
          ),
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
  
  void _selectUser(Map<String, dynamic> user) {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    setState(() {
      _selectedUserId = user['user_id'];
      _selectedUserData = user;
    });
    
    // Close the bottom sheet
    Navigator.pop(context);
  }
  
  void _showUserSelectionSheet() {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    final filteredUsers = _allUsers.where((user) {
      final String fullName = '${user['name']} ${user['surname']}'.toLowerCase();
      final String email = (user['email'] ?? '').toLowerCase();
      final String searchLower = _searchQuery.toLowerCase();
      
      return _searchQuery.isEmpty || 
             fullName.contains(searchLower) || 
             email.contains(searchLower);
    }).toList();
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (BuildContext context) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setState) {
            return Container(
              padding: const EdgeInsets.all(16),
              height: MediaQuery.of(context).size.height * 0.8,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Select User',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _searchController,
                    decoration: InputDecoration(
                      hintText: 'Search by name or email',
                      prefixIcon: const Icon(Icons.search),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    onChanged: (value) {
                      setState(() {
                        _searchQuery = value;
                      });
                    },
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Showing ${filteredUsers.length} of ${_allUsers.length} users',
                    style: TextStyle(
                      color: Colors.grey[700],
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Expanded(
                    child: filteredUsers.isEmpty
                        ? const Center(child: Text('No users found'))
                        : ListView.builder(
                            itemCount: filteredUsers.length,
                            itemBuilder: (context, index) {
                              final user = filteredUsers[index];
                              final bool isSelected = user['user_id'] == _selectedUserId;
                              
                              return Card(
                                margin: const EdgeInsets.only(bottom: 8),
                                color: isSelected ? Colors.blue.shade50 : null,
                                child: ListTile(
                                  leading: CircleAvatar(
                                    backgroundColor: isSelected ? Colors.blue : Colors.blue.shade100,
                                    child: Text(
                                      _getInitials('${user['name']} ${user['surname']}'),
                                      style: TextStyle(
                                        color: isSelected ? Colors.white : Colors.blue,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  title: Text(
                                    '${user['name']} ${user['surname']}',
                                    style: const TextStyle(fontWeight: FontWeight.bold),
                                  ),
                                  subtitle: Text(
                                    '${user['email']} • ${user['department']}',
                                  ),
                                  trailing: isSelected ? const Icon(Icons.check_circle, color: Colors.blue) : null,
                                  onTap: () => _selectUser(user),
                                ),
                              );
                            },
                          ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
  
  String _getInitials(String fullName) {
    List<String> names = fullName.split(' ');
    String initials = '';
    for (var name in names) {
      if (name.isNotEmpty) {
        initials += name[0];
      }
    }
    return initials.toUpperCase();
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Upload Face Photo'),
        actions: [
          if (_capturedImage != null && _isFaceValid)
            IconButton(
              icon: const Icon(Icons.check),
              onPressed: _registerFace,
              tooltip: 'Register Face',
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
                  // Selected user info or user selection button
                  Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: _selectedUserId == null
                          ? Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Select User',
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                const Text(
                                  'Select a user to upload a face photo for',
                                ),
                                const SizedBox(height: 16),
                                FilledButton.icon(
                                  onPressed: _showUserSelectionSheet,
                                  icon: const Icon(Icons.person_search),
                                  label: const Text('Select User'),
                                ),
                              ],
                            )
                          : Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          const Text(
                                            'Selected User',
                                            style: TextStyle(
                                              fontSize: 18,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          const SizedBox(height: 8),
                                          Text(
                                            '${_selectedUserData!['name']} ${_selectedUserData!['surname']}',
                                            style: const TextStyle(
                                              fontSize: 16,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          Text(
                                            '${_selectedUserData!['email']}',
                                          ),
                                          Text(
                                            '${_selectedUserData!['department']} • ${_selectedUserData!['role']}',
                                            style: TextStyle(
                                              color: Colors.grey[700],
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    TextButton.icon(
                                      onPressed: _showUserSelectionSheet,
                                      icon: const Icon(Icons.edit),
                                      label: const Text('Change'),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                    ),
                  ),
                  
                  // Camera preview or captured image
                  if (_capturedImage == null) ...[
                    const Text(
                      'Take Photo',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Position face in the center of the frame and ensure good lighting',
                    ),
                    const SizedBox(height: 16),
                    Container(
                      height: 350,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey.shade300),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: CameraPreview(_cameraController),
                      ),
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _selectedUserId == null ? null : _takePhoto,
                      icon: const Icon(Icons.camera_alt),
                      label: const Text('Take Photo'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(50),
                      ),
                    ),
                  ] else ...[
                    // Captured image display with face validation
                    const Text(
                      'Review Photo',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (_faceValidationMessage != null)
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: _isFaceValid ? Colors.green.shade50 : Colors.red.shade50,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: _isFaceValid ? Colors.green : Colors.red,
                          ),
                        ),
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
                    const SizedBox(height: 16),
                    Container(
                      height: 350,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey.shade300),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: Image.file(
                              _capturedImage!,
                              fit: BoxFit.cover,
                            ),
                          ),
                          if (_faceBounds != null && _isFaceValid)
                            Positioned.fill(
                              child: CustomPaint(
                                painter: FaceHighlightOverlay(
                                  faceBounds: _faceBounds!,
                                  isValid: _isFaceValid,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _retakePhoto,
                            icon: const Icon(Icons.refresh),
                            label: const Text('Retake'),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size.fromHeight(50),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: _isFaceValid ? _registerFace : null,
                            icon: const Icon(Icons.save),
                            label: const Text('Save'),
                            style: FilledButton.styleFrom(
                              minimumSize: const Size.fromHeight(50),
                              backgroundColor: Colors.green,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                  
                  // Instructions Card
                  const SizedBox(height: 24),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.info_outline, color: Colors.blue),
                              SizedBox(width: 8),
                              Text(
                                'Photo Guidelines',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          const Text('• Ensure the face is clearly visible'),
                          const Text('• Use good lighting conditions'),
                          const Text('• Face should be centered in the frame'),
                          const Text('• Eyes should be open and visible'),
                          const Text('• Avoid extreme facial expressions'),
                          const Text('• Photo will be used for facial recognition'),
                        ],
                      ),
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
    if (_isCameraInitialized) {
      _cameraController.dispose();
    }
    _searchController.dispose();
    super.dispose();
  }
}

// Face highlighting painter
class FaceHighlightOverlay extends CustomPainter {
  final Rect faceBounds;
  final bool isValid;
  
  FaceHighlightOverlay({
    required this.faceBounds,
    required this.isValid,
  });
  
  @override
  void paint(Canvas canvas, Size size) {
    final double scaleX = size.width / 100;
    final double scaleY = size.height / 100;
    
    // Scale the face bounds to the canvas size
    final Rect scaledFaceBounds = Rect.fromLTRB(
      faceBounds.left * scaleX,
      faceBounds.top * scaleY,
      faceBounds.right * scaleX,
      faceBounds.bottom * scaleY,
    );
    
    // Create a rounded rectangle with padding
    final double padding = 10;
    final RRect faceRect = RRect.fromRectAndRadius(
      Rect.fromLTRB(
        scaledFaceBounds.left - padding,
        scaledFaceBounds.top - padding,
        scaledFaceBounds.right + padding,
        scaledFaceBounds.bottom + padding,
      ),
      const Radius.circular(16),
    );
    
    // Draw a semi-transparent overlay over the whole image
    canvas.drawRect(
      Rect.fromLTWH(0, 0, size.width, size.height),
      Paint()..color = Colors.black.withOpacity(0.3),
    );
    
    // Cut out a hole for the face using BlendMode.dstOut
    canvas.drawRRect(
      faceRect,
      Paint()
        ..color = Colors.white
        ..blendMode = BlendMode.dstOut,
    );
    
    // Draw a border around the face
    canvas.drawRRect(
      faceRect,
      Paint()
        ..color = isValid ? Colors.green : Colors.red
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3,
    );
  }
  
  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}