// lib/screens/admin/create_user_screen.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';
import '../../widgets/password_strength_indicator.dart';
import '../../util/password_generator.dart';

class CreateUserScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;
  final List<Map<String, dynamic>> departments;
  final List<Map<String, dynamic>> roles;

  const CreateUserScreen({
    Key? key,
    required this.userDetails,
    required this.departments,
    required this.roles,
  }) : super(key: key);

  @override
  State<CreateUserScreen> createState() => _CreateUserScreenState();
}

class _CreateUserScreenState extends State<CreateUserScreen> {
  final _formKey = GlobalKey<FormState>();
  final _sessionService = SessionService();
  final _apiService = ApiService();
  final _passwordController = TextEditingController();
  final _imagePicker = ImagePicker();
  
  // Form controllers
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  
  // Form values
  int? _selectedDepartmentId;
  int? _selectedRoleId;
  DateTime _selectedDateOfBirth = DateTime.now().subtract(const Duration(days: 365 * 25)); // Default to 25 years ago
  DateTime _selectedStartDate = DateTime.now();
  
  bool _isLoading = false;
  bool _autoGeneratePassword = true;
  bool _showPassword = false;
  String _passwordStrength = 'weak';
  
  // Photos
  File? _profilePhoto;
  File? _faceRecognitionPhoto;
  bool _isUploadingProfilePhoto = false;
  bool _isUploadingFacePhoto = false;
  
  @override
  void initState() {
    super.initState();
    _generatePassword();
  }
  
  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }
  
  // Generate a strong password
  void _generatePassword() {
    if (_autoGeneratePassword) {
      final newPassword = PasswordGenerator.generateStrongPassword(length: 8);
      _passwordController.text = newPassword;
      _checkPasswordStrength(newPassword);
    }
  }
  
  // Check password strength
  void _checkPasswordStrength(String password) {
    if (password.isEmpty) {
      setState(() => _passwordStrength = 'weak');
      return;
    }
    
    final requirements = PasswordGenerator.checkPasswordRequirements(password);
    final bool allRequirementsMet = requirements.values.every((met) => met);
    
    if (password.length >= 8 && allRequirementsMet) {
      setState(() => _passwordStrength = 'strong');
    } else if (password.length >= 6 && allRequirementsMet) {
      setState(() => _passwordStrength = 'medium');
    } else {
      setState(() => _passwordStrength = 'weak');
    }
  }
  
  // Show date picker for DOB
  Future<void> _selectDateOfBirth() async {
    _sessionService.userActivity();
    
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDateOfBirth,
      firstDate: DateTime(1950),
      lastDate: DateTime.now(),
    );
    
    if (picked != null && picked != _selectedDateOfBirth) {
      setState(() {
        _selectedDateOfBirth = picked;
      });
    }
  }
  
  // Show date picker for start date
  Future<void> _selectStartDate() async {
    _sessionService.userActivity();
    
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedStartDate,
      firstDate: DateTime(2010),
      lastDate: DateTime.now().add(const Duration(days: 365)), // Allow up to 1 year in the future
    );
    
    if (picked != null && picked != _selectedStartDate) {
      setState(() {
        _selectedStartDate = picked;
      });
    }
  }
  
  // Format a date as a string
  String _formatDate(DateTime date) {
    return DateFormat('dd/MM/yyyy').format(date);
  }
  
  // Pick profile photo
  Future<void> _pickProfilePhoto() async {
    _sessionService.userActivity();
    
    try {
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 85,
      );
      
      if (pickedFile != null) {
        setState(() {
          _profilePhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error selecting profile photo: $e')),
      );
    }
  }
  
  // Take profile photo with camera
  Future<void> _takeProfilePhoto() async {
    _sessionService.userActivity();
    
    try {
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 85,
      );
      
      if (pickedFile != null) {
        setState(() {
          _profilePhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error taking profile photo: $e')),
      );
    }
  }
  
  // Pick face recognition photo
  Future<void> _pickFaceRecognitionPhoto() async {
    _sessionService.userActivity();
    
    try {
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 90, // Higher quality for face recognition
      );
      
      if (pickedFile != null) {
        setState(() {
          _faceRecognitionPhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error selecting face recognition photo: $e')),
      );
    }
  }
  
  // Take face recognition photo with camera
  Future<void> _takeFaceRecognitionPhoto() async {
    _sessionService.userActivity();
    
    try {
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 90, // Higher quality for face recognition
      );
      
      if (pickedFile != null) {
        setState(() {
          _faceRecognitionPhoto = File(pickedFile.path);
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error taking face recognition photo: $e')),
      );
    }
  }
  
  // Create user submission
  Future<void> _createUser() async {
    if (!_formKey.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please correct the errors in the form')),
      );
      return;
    }
    
    if (_selectedDepartmentId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a department')),
      );
      return;
    }
    
    if (_selectedRoleId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a role')),
      );
      return;
    }
    
    setState(() {
      _isLoading = true;
    });
    
    try {
      // Create the request data
      final Map<String, dynamic> userData = {
        'name': _firstNameController.text.trim(),
        'surname': _lastNameController.text.trim(),
        'email': _emailController.text.trim(),
        'phone': _phoneController.text.trim(),
        'department_id': _selectedDepartmentId,
        'role_id': _selectedRoleId,
        'dob': DateFormat('yyyy-MM-dd').format(_selectedDateOfBirth),
        'job_start': DateFormat('yyyy-MM-dd').format(_selectedStartDate),
        'admin_id': widget.userDetails['id'],
        'password': _passwordController.text,
      };
      
      // Call the API to create the user
      final result = await _apiService.createUser(userData);
      
      // Get the user ID from the response
      final String userId = result['user_id'].toString();
      
      // If we have a profile photo and the user was created successfully
      if (_profilePhoto != null && userId.isNotEmpty) {
        setState(() {
          _isUploadingProfilePhoto = true;
        });
        
        try {
          await _apiService.uploadProfilePhoto(_profilePhoto!, userId);
        } catch (e) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Failed to upload profile photo: $e')),
            );
          }
          // Continue even if profile photo upload fails
        } finally {
          if (mounted) {
            setState(() {
              _isUploadingProfilePhoto = false;
            });
          }
        }
      }
      
      // If we have a face recognition photo and the user was created successfully
      if (_faceRecognitionPhoto != null && userId.isNotEmpty) {
        setState(() {
          _isUploadingFacePhoto = true;
        });
        
        try {
          await _apiService.uploadFacePhoto(_faceRecognitionPhoto!, userId);
        } catch (e) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Failed to upload face recognition photo: $e')),
            );
          }
          // Continue even if face photo upload fails
        } finally {
          if (mounted) {
            setState(() {
              _isUploadingFacePhoto = false;
            });
          }
        }
      }
      
      if (mounted) {
        // Show success dialog
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (context) => AlertDialog(
            title: const Text('User Created Successfully'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('User ${_firstNameController.text} ${_lastNameController.text} has been created.'),
                const SizedBox(height: 16),
                const Text(
                  'Password:',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey),
                    borderRadius: BorderRadius.circular(4),
                    color: Colors.grey[200],
                  ),
                  child: SelectableText(
                    _passwordController.text,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Please share this password with the user. They will be prompted to change it on first login.',
                  style: TextStyle(fontStyle: FontStyle.italic),
                ),
              ],
            ),
            actions: [
              FilledButton(
                onPressed: () {
                  Navigator.pop(context);
                  Navigator.pop(context);
                },
                child: const Text('Done'),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error creating user: $e'),
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
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create New User'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Form Title
                    const Text(
                      'User Information',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    // Personal Information
                    const Text(
                      'Personal Information',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // First Name
                    TextFormField(
                      controller: _firstNameController,
                      decoration: const InputDecoration(
                        labelText: 'First Name',
                        prefixIcon: Icon(Icons.person),
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter a first name';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    
                    // Last Name
                    TextFormField(
                      controller: _lastNameController,
                      decoration: const InputDecoration(
                        labelText: 'Last Name',
                        prefixIcon: Icon(Icons.person),
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter a last name';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    
                    // Date of Birth
                    GestureDetector(
                      onTap: _selectDateOfBirth,
                      child: AbsorbPointer(
                        child: TextFormField(
                          decoration: const InputDecoration(
                            labelText: 'Date of Birth',
                            prefixIcon: Icon(Icons.calendar_today),
                            border: OutlineInputBorder(),
                          ),
                          controller: TextEditingController(
                            text: _formatDate(_selectedDateOfBirth),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    // Contact Information
                    const Text(
                      'Contact Information',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // Email
                    TextFormField(
                      controller: _emailController,
                      decoration: const InputDecoration(
                        labelText: 'Email Address',
                        prefixIcon: Icon(Icons.email),
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: TextInputType.emailAddress,
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter an email address';
                        }
                        if (!value.contains('@') || !value.contains('.')) {
                          return 'Please enter a valid email address';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    
                    // Phone
                    TextFormField(
                      controller: _phoneController,
                      decoration: const InputDecoration(
                        labelText: 'Phone Number',
                        prefixIcon: Icon(Icons.phone),
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: TextInputType.phone,
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter a phone number';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),
                    
                    // Employment Information
                    const Text(
                      'Employment Information',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // Department
                    DropdownButtonFormField<int>(
                      decoration: const InputDecoration(
                        labelText: 'Department',
                        prefixIcon: Icon(Icons.business),
                        border: OutlineInputBorder(),
                      ),
                      items: widget.departments.map((department) {
                        return DropdownMenuItem<int>(
                          value: department['department_id'],
                          child: Text(department['department']),
                        );
                      }).toList(),
                      onChanged: (value) {
                        setState(() {
                          _selectedDepartmentId = value;
                        });
                      },
                      validator: (value) {
                        if (value == null) {
                          return 'Please select a department';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    
                    // Role
                    DropdownButtonFormField<int>(
                      decoration: const InputDecoration(
                        labelText: 'Role',
                        prefixIcon: Icon(Icons.work),
                        border: OutlineInputBorder(),
                      ),
                      items: widget.roles.map((role) {
                        return DropdownMenuItem<int>(
                          value: role['role_id'],
                          child: Text(role['role']),
                        );
                      }).toList(),
                      onChanged: (value) {
                        setState(() {
                          _selectedRoleId = value;
                        });
                      },
                      validator: (value) {
                        if (value == null) {
                          return 'Please select a role';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    
                    // Start Date
                    GestureDetector(
                      onTap: _selectStartDate,
                      child: AbsorbPointer(
                        child: TextFormField(
                          decoration: const InputDecoration(
                            labelText: 'Start Date',
                            prefixIcon: Icon(Icons.event),
                            border: OutlineInputBorder(),
                          ),
                          controller: TextEditingController(
                            text: _formatDate(_selectedStartDate),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    // Password Section
                    const Text(
                      'Account Security',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // Auto-generate password checkbox
                    Row(
                      children: [
                        Checkbox(
                          value: _autoGeneratePassword,
                          onChanged: (value) {
                            setState(() {
                              _autoGeneratePassword = value ?? true;
                              if (_autoGeneratePassword) {
                                _generatePassword();
                              } else {
                                // Clear password field when switching to manual mode
                                _passwordController.clear();
                                _checkPasswordStrength('');
                              }
                            });
                          },
                        ),
                        const Text('Auto-generate password'),
                        const Spacer(),
                        if (_autoGeneratePassword)
                          IconButton(
                            icon: const Icon(Icons.refresh),
                            tooltip: 'Generate new password',
                            onPressed: _generatePassword,
                          ),
                      ],
                    ),
                    
                    // Password field
                    TextFormField(
                      controller: _passwordController,
                      obscureText: !_showPassword,
                      enabled: !_autoGeneratePassword,
                      decoration: InputDecoration(
                        labelText: 'Password',
                        hintText: _autoGeneratePassword ? null : 'Enter password...',
                        helperText: _autoGeneratePassword ? 'Using auto-generated password' : 'Create your own password',
                        prefixIcon: const Icon(Icons.lock),
                        suffixIcon: IconButton(
                          icon: Icon(_showPassword ? Icons.visibility_off : Icons.visibility),
                          onPressed: () {
                            setState(() {
                              _showPassword = !_showPassword;
                            });
                          },
                        ),
                        border: const OutlineInputBorder(),
                        enabledBorder: OutlineInputBorder(
                          borderSide: BorderSide(
                            color: _autoGeneratePassword ? Colors.green : Colors.grey,
                          ),
                        ),
                      ),
                      onChanged: _checkPasswordStrength,
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter a password';
                        }
                        
                        final requirements = PasswordGenerator.checkPasswordRequirements(value);
                        
                        if (!requirements['length']!) {
                          return 'Password must be at least 6 characters';
                        }
                        if (!requirements['uppercase']!) {
                          return 'Password must contain at least one uppercase letter';
                        }
                        if (!requirements['lowercase']!) {
                          return 'Password must contain at least one lowercase letter';
                        }
                        if (!requirements['digit']!) {
                          return 'Password must contain at least one number';
                        }
                        if (!requirements['specialChar']!) {
                          return 'Password must contain at least one special character';
                        }
                        
                        return null;
                      },
                    ),
                    const SizedBox(height: 8),
                    
                    // Password strength indicator
                    PasswordStrengthIndicator(strength: _passwordStrength),
                    const SizedBox(height: 24),
                    
                    // Photo Upload Section
                    const Text(
                      'Profile Photo',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // Profile Photo Preview
                    Center(
                      child: Column(
                        children: [
                          Container(
                            width: 150,
                            height: 150,
                            decoration: BoxDecoration(
                              color: Colors.grey[200],
                              borderRadius: BorderRadius.circular(75),
                              border: Border.all(color: Colors.grey),
                            ),
                            child: _isUploadingProfilePhoto
                                ? const Center(child: CircularProgressIndicator())
                                : _profilePhoto != null
                                    ? ClipRRect(
                                        borderRadius: BorderRadius.circular(75),
                                        child: Image.file(
                                          _profilePhoto!,
                                          width: 150,
                                          height: 150,
                                          fit: BoxFit.cover,
                                        ),
                                      )
                                    : const Icon(
                                        Icons.person,
                                        size: 80,
                                        color: Colors.grey,
                                      ),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              ElevatedButton.icon(
                                onPressed: _pickProfilePhoto,
                                icon: const Icon(Icons.photo_library),
                                label: const Text('Gallery'),
                              ),
                              const SizedBox(width: 16),
                              ElevatedButton.icon(
                                onPressed: _takeProfilePhoto,
                                icon: const Icon(Icons.camera_alt),
                                label: const Text('Camera'),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    // Face Recognition Photo
                    const Text(
                      'Face Recognition Photo',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Upload a clear front-facing photo for face recognition (optional)',
                      style: TextStyle(
                        fontSize: 14,
                        color: Colors.grey,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // Face Recognition Photo Preview
                    Center(
                      child: Column(
                        children: [
                          Container(
                            width: 150,
                            height: 150,
                            decoration: BoxDecoration(
                              color: Colors.grey[200],
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: Colors.grey),
                            ),
                            child: _isUploadingFacePhoto
                                ? const Center(child: CircularProgressIndicator())
                                : _faceRecognitionPhoto != null
                                    ? ClipRRect(
                                        borderRadius: BorderRadius.circular(10),
                                        child: Image.file(
                                          _faceRecognitionPhoto!,
                                          width: 150,
                                          height: 150,
                                          fit: BoxFit.cover,
                                        ),
                                      )
                                    : const Center(
                                        child: Column(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Icon(
                                              Icons.face,
                                              size: 60,
                                              color: Colors.grey,
                                            ),
                                            Text(
                                              'Face Photo',
                                              style: TextStyle(color: Colors.grey),
                                            ),
                                          ],
                                        ),
                                      ),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              ElevatedButton.icon(
                                onPressed: _pickFaceRecognitionPhoto,
                                icon: const Icon(Icons.photo_library),
                                label: const Text('Gallery'),
                              ),
                              const SizedBox(width: 16),
                              ElevatedButton.icon(
                                onPressed: _takeFaceRecognitionPhoto,
                                icon: const Icon(Icons.camera_alt),
                                label: const Text('Camera'),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 32),
                    
                    // Submit Button
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: FilledButton(
                        onPressed: _createUser,
                        child: const Text('Create User'),
                      ),
                    ),
                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ),
    );
  }
}