// lib/screens/admin/photo_upload_screen.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';

class PhotoUploadScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;

  const PhotoUploadScreen({
    Key? key,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<PhotoUploadScreen> createState() => _PhotoUploadScreenState();
}

class _PhotoUploadScreenState extends State<PhotoUploadScreen> {
  final ApiService _apiService = ApiService();
  final SessionService _sessionService = SessionService();
  final _imagePicker = ImagePicker();
  
  bool _isLoading = false;
  File? _facePhoto;
  
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
    _loadUsers();
  }
  
  Future<void> _loadUsers() async {
    setState(() {
      _isLoading = true;
    });
    
    try {
      // Use endpoint to get all active users for face registration
      final users = await _apiService.getAllActiveUsers();
      
      if (mounted) {
        setState(() {
          _allUsers = users;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading users: $e')),
        );
      }
    }
  }
  
  Future<void> _takePhotoWithCamera() async {
    _sessionService.userActivity();
    
    if (_selectedUserId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a user first')),
      );
      return;
    }
    
    try {
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1200,
        maxHeight: 1600,
        imageQuality: 90,
        preferredCameraDevice: CameraDevice.front, // Prefer front camera for face photos
      );
      
      if (pickedFile != null) {
        setState(() {
          _facePhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error taking photo: $e')),
      );
    }
  }
  
  Future<void> _pickPhotoFromGallery() async {
    _sessionService.userActivity();
    
    if (_selectedUserId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a user first')),
      );
      return;
    }
    
    try {
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1200,
        maxHeight: 1600,
        imageQuality: 90,
      );
      
      if (pickedFile != null) {
        setState(() {
          _facePhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error picking photo: $e')),
      );
    }
  }
  
  Future<void> _uploadFacePhoto() async {
    if (_facePhoto == null || _selectedUserId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a user and take/upload a photo')),
      );
      return;
    }
    
    setState(() {
      _isLoading = true;
    });
    
    try {
      // Upload face photo to server
      await _apiService.uploadFacePhoto(
        _facePhoto!,
        _selectedUserId.toString(),
      );
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Face photo uploaded successfully for ${_selectedUserData!['name']} ${_selectedUserData!['surname']}'),
            backgroundColor: Colors.green,
          ),
        );
        
        // Reset the photo for next upload
        setState(() {
          _facePhoto = null;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error uploading face photo: $e'),
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
    _sessionService.userActivity();
    
    setState(() {
      _selectedUserId = user['user_id'];
      _selectedUserData = user;
      _facePhoto = null; // Reset photo when changing user
    });
    
    // Close the bottom sheet
    Navigator.pop(context);
  }
  
  void _showUserSelectionSheet() {
    _sessionService.userActivity();
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (BuildContext context) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setState) {
            final filteredUsers = _allUsers.where((user) {
              final String fullName = '${user['name']} ${user['surname']}'.toLowerCase();
              final String email = (user['email'] ?? '').toLowerCase();
              final String searchLower = _searchQuery.toLowerCase();
              
              return _searchQuery.isEmpty || 
                    fullName.contains(searchLower) || 
                    email.contains(searchLower);
            }).toList();
            
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
                      // Update _searchQuery AND re-render with StateSetter
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
          if (_facePhoto != null)
            IconButton(
              icon: const Icon(Icons.check),
              onPressed: _uploadFacePhoto,
              tooltip: 'Upload Photo',
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
                  
                  // Face Photo Upload Section
                  Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Face Photo',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Take or select a clear photo of the user\'s face for face recognition',
                          ),
                          const SizedBox(height: 16),
                          
                          // Photo preview or placeholder
                          Center(
                            child: Container(
                              width: 200,
                              height: 200,
                              decoration: BoxDecoration(
                                color: Colors.grey.shade200,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: Colors.grey),
                              ),
                              child: _facePhoto != null
                                  ? ClipRRect(
                                      borderRadius: BorderRadius.circular(8),
                                      child: Image.file(
                                        _facePhoto!,
                                        width: 200,
                                        height: 200,
                                        fit: BoxFit.cover,
                                      ),
                                    )
                                  : const Icon(
                                      Icons.face,
                                      size: 80,
                                      color: Colors.grey,
                                    ),
                            ),
                          ),
                          const SizedBox(height: 16),
                          
                          // Photo actions
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton.icon(
                                  onPressed: _selectedUserId == null ? null : _takePhotoWithCamera,
                                  icon: const Icon(Icons.camera_alt),
                                  label: const Text('Take Photo'),
                                ),
                              ),
                              const SizedBox(width: 16),
                              Expanded(
                                child: OutlinedButton.icon(
                                  onPressed: _selectedUserId == null ? null : _pickPhotoFromGallery,
                                  icon: const Icon(Icons.photo_library),
                                  label: const Text('Gallery'),
                                ),
                              ),
                            ],
                          ),
                          if (_facePhoto != null) ...[
                            const SizedBox(height: 16),
                            FilledButton.icon(
                              onPressed: _uploadFacePhoto,
                              icon: const Icon(Icons.upload),
                              label: const Text('Upload Photo'),
                              style: FilledButton.styleFrom(
                                minimumSize: const Size.fromHeight(50),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                  
                  // Guidelines
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
    _searchController.dispose();
    super.dispose();
  }
}