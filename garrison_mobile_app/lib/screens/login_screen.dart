// Modified login_screen.dart with "Signing in" loading animation instead of face sync message
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'dart:async';
import '../services/api_service.dart';
import '../services/session_service.dart';
import '../services/biometric_service.dart';
import '../services/secure_storage_service.dart';
import '../screens/admin/admin_dashboard.dart';
import '../services/face_recognition_manager.dart';
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
  final _biometricService = BiometricService();
  final _secureStorageService = SecureStorageService();
  
  bool _isLoading = false;
  bool _obscurePassword = true;
  bool _rememberMe = false;
  
  // Biometrics
  bool _isBiometricsAvailable = false;
  bool _isBiometricsEnabled = false;
  
  // Login attempts and lockout
  bool _isLockedOut = false;
  int _remainingAttempts = 4;
  int _lockoutRemainingSeconds = 0;
  Timer? _lockoutTimer;

  @override
  void initState() {
    super.initState();
    // Stop any existing session timer
    _sessionService.stopSessionTimer();
    
    // Check biometrics availability
    _checkBiometricStatus();
    
    // Check if credentials are already saved
    _checkSavedCredentials();
  }

  // Check if biometrics is available and enabled
  Future<void> _checkBiometricStatus() async {
    final bool isAvailable = await _biometricService.isDeviceBiometricsAvailable();
    final bool isEnabled = await _secureStorageService.isBiometricLoginEnabled();
    
    setState(() {
      _isBiometricsAvailable = isAvailable;
      _isBiometricsEnabled = isEnabled;
    });
  }

  // Check if credentials are already saved
  Future<void> _checkSavedCredentials() async {
    final email = await _secureStorageService.getStoredEmail();
    final isBiometricsEnabled = await _secureStorageService.isBiometricLoginEnabled();
    
    if (email != null && isBiometricsEnabled) {
      setState(() {
        _emailController.text = email;
        _rememberMe = true;
      });
    }
  }

  // Biometric login handler
  Future<void> _handleBiometricLogin() async {
    setState(() {
      _isLoading = true;
    });

    try {
      // Authenticate using biometrics
      final bool isAuthenticated = await _biometricService.authenticate();
      
      if (isAuthenticated) {
        // Perform login with stored credentials
        final loginSuccess = await _performBiometricLogin();
        
        if (!loginSuccess && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Biometric login failed. Please login manually.'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Biometric authentication error: $e'),
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
  // Login with stored credentials
  Future<bool> _performBiometricLogin() async {
    try {
      final storedEmail = await _secureStorageService.getStoredEmail();
      final storedPassword = await _secureStorageService.getStoredPassword();
      
      if (storedEmail == null || storedPassword == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No saved credentials found.'),
            backgroundColor: Colors.red,
          ),
        );
        return false;
      }
      
      final response = await _apiService.login(storedEmail, storedPassword);
      
      if (!mounted) return false;
      
      // Check MFA requirement
      final bool mfaRequired = response['mfa_required'] ?? false;
      
      if (mfaRequired) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Multi-factor authentication is required.'),
            backgroundColor: Colors.orange,
          ),
        );
        return false;
      }

      // Face Recognition Sync - Run in background but don't show message to user
      try {
        final userId = response['user']['id'].toString();
        
        // Background face sync without notification
        Future.microtask(() async {
          final faceManager = FaceRecognitionManager();
          await faceManager.initialize();
          await faceManager.syncUserFaceData(userId);
          print('Face data sync completed for user: $userId');
        });
      } catch (faceError) {
        print('Face sync error (non-fatal): $faceError');
        // Continue with login even if face sync fails
      }

      // Navigate based on user role
      final int roleId = response['user']['role_id'] ?? 0;
      final List<int> adminRoleIds = [1, 2, 3, 6, 7, 13, 14];
      final bool hasAdminAccess = adminRoleIds.contains(roleId);

      if (hasAdminAccess) {
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
      
      return true;
    } catch (e) {
      print('Biometric login error: $e');
      return false;
    }
  }

  // Main login method
  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    
    // Check if account is locked out
    if (_isLockedOut) return;

    setState(() {
      _isLoading = true;
    });

    try {
      final response = await _apiService.login(
        _emailController.text.trim(),
        _passwordController.text.trim(),
      );

      // Handle MFA requirement
      final bool mfaRequired = response['mfa_required'] ?? false;
      
      if (mfaRequired) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Multi-factor authentication is required.'),
            backgroundColor: Colors.orange,
          ),
        );
        return;
      }

      // Save credentials if "Remember Me" is checked
      if (_rememberMe) {
        await _secureStorageService.saveCredentials(
          email: _emailController.text.trim(), 
          password: _passwordController.text.trim()
        );
        await _secureStorageService.enableBiometricLogin();
      }

      // Face Recognition Sync - Run in background without notification
      try {
        final userId = response['user']['id'].toString();
        
        // Run face sync in background
        Future.microtask(() async {
          final faceManager = FaceRecognitionManager();
          await faceManager.initialize();
          final syncSuccess = await faceManager.syncUserFaceData(userId);
          print('Face data sync completed for user: $userId with result: $syncSuccess');
        });
      } catch (faceError) {
        print('Face sync error (non-fatal): $faceError');
        // Continue with login even if face sync fails
      }

      // Determine user access
      final int roleId = response['user']['role_id'] ?? 0;
      final List<int> adminRoleIds = [1, 2, 3, 6, 7, 13, 14];
      final bool hasAdminAccess = adminRoleIds.contains(roleId);

      if (hasAdminAccess) {
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
      // Handle login errors
      final errorMessage = e.toString();
      
      if (errorMessage.contains('429')) {
        // Account is locked out
        final lockoutRegExp = RegExp(r'lockout_remaining: (\d+)');
        final match = lockoutRegExp.firstMatch(errorMessage);
        
        if (match != null && match.groupCount >= 1) {
          final lockoutMinutes = int.tryParse(match.group(1) ?? '5') ?? 5;
          _startLockoutTimer(lockoutMinutes * 60);
        } else {
          _startLockoutTimer(300); // Default 5-minute lockout
        }
        
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Account locked. Too many failed attempts.'),
            backgroundColor: Colors.red,
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
        
        // Clear password field
        _passwordController.clear();
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Incorrect email or password. Attempts remaining: $_remainingAttempts'),
            backgroundColor: Colors.orange,
          ),
        );
      } else {
        // Generic error
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Login failed: $errorMessage'),
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

  // Start lockout timer
  void _startLockoutTimer(int lockoutSeconds) {
    setState(() {
      _isLockedOut = true;
      _lockoutRemainingSeconds = lockoutSeconds;
    });

    _lockoutTimer?.cancel();
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

  // Format lockout time
  String _formatTime(int seconds) {
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '$minutes:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  // Validation methods
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
          child: Stack(
            children: [
              Center(
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
                            
                            // Lockout indicator
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
                            
                            if (!_isLockedOut) ...[
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
                                enabled: !_isLoading,
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
                              
                              // Remember me checkbox
                              Row(
                                children: [
                                  Checkbox(
                                    value: _rememberMe,
                                    onChanged: (value) {
                                      setState(() {
                                        _rememberMe = value ?? false;
                                      });
                                    },
                                  ),
                                  const Text('Remember me'),
                                ],
                              ),
                              
                              // Show remaining attempts if not locked out and attempts remaining
                              if (_remainingAttempts < 4)
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
                              
                              // Regular login button
                              SizedBox(
                                width: double.infinity,
                                height: 50,
                                child: FilledButton(
                                  onPressed: (_isLoading || _isLockedOut) ? null : _login,
                                  style: FilledButton.styleFrom(
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                    ),
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
                              
                              // Add biometric login button
                              if (_isBiometricsAvailable && _isBiometricsEnabled) ...[
                                const SizedBox(height: 16),
                                SizedBox(
                                  width: double.infinity,
                                  height: 50,
                                  child: OutlinedButton.icon(
                                    onPressed: _isLoading ? null : _handleBiometricLogin,
                                    icon: const Icon(Icons.fingerprint),
                                    label: const Text('Login with Biometrics'),
                                    style: OutlinedButton.styleFrom(
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ],
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              
              // Overlay loading indicator with "Signing in" message
              if (_isLoading)
                Container(
                  color: Colors.black54,
                  child: Center(
                    child: Card(
                      elevation: 8,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 30),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const CircularProgressIndicator(),
                            const SizedBox(height: 16),
                            const Text(
                              "Signing in...",
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
            ],
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