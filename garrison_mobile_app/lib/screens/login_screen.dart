// lib/screens/login_screen.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'dart:async';
import '../services/api_service.dart';
import '../services/session_service.dart';
import '../screens/admin/admin_dashboard.dart';
import '../main.dart';

class LoginScreen extends StatefulWidget {
  final CameraDescription camera;
  final CameraDescription? rearCamera;

  const LoginScreen({
    Key? key,
    required this.camera,
    this.rearCamera,
  }) : super(key: key);

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _apiService = ApiService();
  final _sessionService = SessionService();
  bool _isLoading = false;
  bool _obscurePassword = true;
  
  // Add state for login attempts and lockout
  bool _isLockedOut = false;
  int _remainingAttempts = 4; // Default max attempts
  int _lockoutRemainingSeconds = 0;
  Timer? _lockoutTimer;

  @override
  void initState() {
    super.initState();
    // Ensure any existing session timer is stopped
    _sessionService.stopSessionTimer();
  }

  void _startLockoutTimer(int lockoutSeconds) {
    setState(() {
      _isLockedOut = true;
      _lockoutRemainingSeconds = lockoutSeconds;
    });

    _lockoutTimer?.cancel(); // Cancel any existing timer
    _lockoutTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      setState(() {
        if (_lockoutRemainingSeconds > 0) {
          _lockoutRemainingSeconds--;
        } else {
          _isLockedOut = false;
          timer.cancel();
        }
      });
    });
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    if (_isLockedOut) return; // Prevent login if locked out

    setState(() {
      _isLoading = true;
    });

    try {
      final response = await _apiService.login(
        _emailController.text.trim(),
        _passwordController.text.trim(),
      );

      if (!mounted) return;
      
      // Check if MFA is required
      final bool mfaRequired = response['mfa_required'] ?? false;
      
      if (mfaRequired) {
        // If you want to implement MFA support, you would navigate to an MFA verification screen here
        // For now, just show a message that MFA is required
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Multi-factor authentication is required. This feature is not yet implemented.'),
            backgroundColor: Colors.orange,
          ),
        );
        
        setState(() {
          _isLoading = false;
        });
        
        return;
      }

      // Check if user is in admin departments or has admin roles
      // Stricter admin access check
      final int roleId = response['user']['role_id'] ?? 0;
      
      // Explicitly defined admin access roles
      final List<int> adminRoleIds = [1, 2, 3, 6, 7, 13, 14]; // HR Manager, HR, IT Manager, CEO, General Manager, Software Developer, Cyber Security Manager

      bool hasAdminAccess = 
        adminRoleIds.contains(roleId);

      if (hasAdminAccess) {
        // Navigate to admin dashboard
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (context) => AdminDashboard(
              userDetails: response['user'],
              camera: widget.camera,
              rearCamera: widget.rearCamera,
            ),
          ),
        );
      } else {
        // Standard user login
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (context) => MainScreen(
              camera: widget.camera,
              userDetails: response['user'],
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      
      // Parse the error to check if it contains attempt information
      final errorMessage = e.toString();
      
      if (errorMessage.contains('429')) {
        // Account is locked out
        final lockoutRegExp = RegExp(r'lockout_remaining: (\d+)');
        final match = lockoutRegExp.firstMatch(errorMessage);
        
        if (match != null && match.groupCount >= 1) {
          final lockoutMinutes = int.tryParse(match.group(1) ?? '5') ?? 5;
          _startLockoutTimer(lockoutMinutes * 60); // Convert minutes to seconds
        } else {
          // Default 5 minute lockout if we can't parse
          _startLockoutTimer(300);
        }
        
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Account locked. Too many failed attempts.'),
            backgroundColor: Colors.red,
            duration: Duration(seconds: 3),
          ),
        );
      } else if (errorMessage.contains('remaining_attempts')) {
        // Extract remaining attempts
        final attemptsRegExp = RegExp(r'remaining_attempts: (\d+)');
        final match = attemptsRegExp.firstMatch(errorMessage);
        
        if (match != null && match.groupCount >= 1) {
          setState(() {
            _remainingAttempts = int.tryParse(match.group(1) ?? '4') ?? 4;
          });
        }
        
        // Clear password field to help user retry
        _passwordController.clear();
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Incorrect email or password. Attempts remaining: $_remainingAttempts'),
            backgroundColor: Colors.orange,
            duration: const Duration(seconds: 3),
          ),
        );
      } else {
        // Generic error
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Login failed: $errorMessage'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
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

  String? _validateEmail(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please enter your email';
    }
    if (!value.contains('@')) {
      return 'Please enter a valid email';
    }
    return null;
  }

  String? _validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please enter your password';
    }
    if (value.length < 6) {
      return 'Password must be at least 6 characters';
    }
    return null;
  }

  String _formatTime(int seconds) {
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '$minutes:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Colors.blue[300]!, Colors.blue[900]!],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Form(
                key: _formKey,
                child: Card(
                  elevation: 8,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(15),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Image.asset(
                          'assets/icon/icon.png',
                          height: 80,
                          width: 80,
                        ),
                        const SizedBox(height: 16),
                        const Text(
                          'Garrison App',
                          style: TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                            color: Colors.blue,
                          ),
                        ),
                        const SizedBox(height: 24),
                        TextFormField(
                          controller: _emailController,
                          decoration: InputDecoration(
                            labelText: 'Email',
                            prefixIcon: const Icon(Icons.email),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                            filled: true,
                            fillColor: Colors.grey[50],
                          ),
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          validator: _validateEmail,
                          enabled: !_isLoading && !_isLockedOut,
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _passwordController,
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon: const Icon(Icons.lock),
                            suffixIcon: IconButton(
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_off
                                    : Icons.visibility,
                              ),
                              onPressed: () {
                                setState(() {
                                  _obscurePassword = !_obscurePassword;
                                });
                              },
                            ),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                            filled: true,
                            fillColor: Colors.grey[50],
                          ),
                          obscureText: _obscurePassword,
                          textInputAction: TextInputAction.done,
                          onFieldSubmitted: (_) => _isLockedOut ? null : _login(),
                          validator: _validatePassword,
                          enabled: !_isLoading && !_isLockedOut,
                        ),
                        const SizedBox(height: 16),
                        
                        // Lockout timer indicator
                        if (_isLockedOut) 
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.red[50],
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.red[300]!),
                            ),
                            child: Column(
                              children: [
                                const Text(
                                  'Account temporarily locked',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Colors.red,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Please try again in ${_formatTime(_lockoutRemainingSeconds)}',
                                  style: const TextStyle(color: Colors.red),
                                ),
                              ],
                            ),
                          ),
                          
                        // Show remaining attempts if not locked out and attempts remaining
                        if (!_isLockedOut && _remainingAttempts < 4)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              'Attempts remaining: $_remainingAttempts',
                              style: TextStyle(
                                color: _remainingAttempts > 2 ? Colors.orange : Colors.red,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        
                        const SizedBox(height: 24),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: FilledButton(
                            onPressed: (_isLoading || _isLockedOut) ? null : _login,
                            style: FilledButton.styleFrom(
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                              // Change button color based on lockout status
                              backgroundColor: _isLockedOut ? Colors.grey : null,
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      color: Colors.white,
                                      strokeWidth: 2,
                                    ),
                                  )
                                : Text(
                                    _isLockedOut ? 'Locked' : 'Login',
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _lockoutTimer?.cancel();
    super.dispose();
  }
}