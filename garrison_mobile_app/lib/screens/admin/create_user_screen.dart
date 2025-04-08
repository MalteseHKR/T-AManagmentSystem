// lib/screens/admin/create_user_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';

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
  
  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
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
      // Call the API to create the user
      final result = await _apiService.createUser({
        'name': _firstNameController.text.trim(),
        'surname': _lastNameController.text.trim(),
        'email': _emailController.text.trim(),
        'phone': _phoneController.text.trim(),
        'department_id': _selectedDepartmentId,
        'role_id': _selectedRoleId,
        'dob': DateFormat('yyyy-MM-dd').format(_selectedDateOfBirth),
        'job_start': DateFormat('yyyy-MM-dd').format(_selectedStartDate),
        'admin_id': widget.userDetails['id'],
      });
      
      if (mounted) {
        // Show the generated password
        final String newPassword = result['password'];
        
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
                  'Temporary Password:',
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
                    newPassword,
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
                  ],
                ),
              ),
            ),
    );
  }
}