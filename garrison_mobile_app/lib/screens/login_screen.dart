// lib/screens/login_screen.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'dart:async';
import '../services/api_service.dart';
import '../services/session_service.dart';
import '../services/biometric_service.dart';
import '../services/secure_storage_service.dart';
import '../screens/admin/admin_dashboard.dart';
import '../services/face_recognition_manager.dart';
import '../services/cache_service.dart';
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

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  
  final _apiService = ApiService();
  final _sessionService = SessionService();
  final _biometricService = BiometricService();
  final _secureStorageService = SecureStorageService();
  final _cacheService = CacheService();
  
  bool _isLoading = false;
  bool _obscurePassword = true;
  bool _rememberMe = false;
  
  // Improved loading animation controls
  bool _isSyncingFace = false;
  bool _isLoadingAdminData = false;
  String _loadingText = "Signing in...";
  late AnimationController _loadingAnimationController;
  
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
    
    // Initialize loading animation controller
    _loadingAnimationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat();
    
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

      // Show face synchronization in progress
      setState(() {
        _isSyncingFace = true;
        _loadingText = "Syncing face data...";
      });
      
      // Face Recognition Sync - Run and show status to user
      try {
        final userId = response['user']['id'].toString();
        
        // Initialize the face manager
        final faceManager = FaceRecognitionManager();
        await faceManager.initialize();
        
        // Perform face sync
        await faceManager.syncUserFaceData(userId);
        print('Face data sync completed for user: $userId');
      } catch (faceError) {
        print('Face sync error (non-fatal): $faceError');
        // Continue with login even if face sync fails
      } finally {
        if (mounted) {
          setState(() {
            _isSyncingFace = false;
          });
        }
      }
      
      // Determine user access
      final int roleId = response['user']['role_id'] ?? 0;
      final List<int> adminRoleIds = [1, 2, 3, 6, 7, 13, 14];
      final bool hasAdminAccess = adminRoleIds.contains(roleId);
      
      // For admin users, preload all user data
      if (hasAdminAccess) {
        await _preloadAdminData(response['user']['id']);
      } else {
        // For regular users, just preload their own profile
        await _preloadUserProfile(response['user']['id']);
      }

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
      _loadingText = "Signing in...";
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

      // Show face synchronization in progress
      setState(() {
        _isSyncingFace = true;
        _loadingText = "Syncing face data...";
      });
      
      // Face Recognition Sync - Run visibly with progress indicator
      try {
        final userId = response['user']['id'].toString();
        
        // Initialize the face manager
        final faceManager = FaceRecognitionManager();
        await faceManager.initialize();
        
        // Perform face sync
        final syncSuccess = await faceManager.syncUserFaceData(userId);
        print('Face data sync completed for user: $userId with result: $syncSuccess');
      } catch (faceError) {
        print('Face sync error (non-fatal): $faceError');
        // Continue with login even if face sync fails
      } finally {
        if (mounted) {
          setState(() {
            _isSyncingFace = false;
          });
        }
      }
      
      // Determine user access
      final int roleId = response['user']['role_id'] ?? 0;
      final List<int> adminRoleIds = [1, 2, 3, 6, 7, 13, 14];
      final bool hasAdminAccess = adminRoleIds.contains(roleId);
      
      // For admin users, preload all user data
      if (hasAdminAccess) {
        await _preloadAdminData(response['user']['id']);
      } else {
        // For regular users, just preload their own profile
        await _preloadUserProfile(response['user']['id']);
      }

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
  
  // Preload admin data (all users, departments, roles, etc)
  Future<void> _preloadAdminData(int adminId) async {
    if (!mounted) return;
    
    setState(() {
      _isLoadingAdminData = true;
      _loadingText = "Loading user data...";
    });
    
    try {
      // For admin users, preload all necessary data
      final apiService = ApiService();
      
      // Fetch all users and cache them
      final allUsers = await apiService.getAllUsers();
      await _cacheService.cacheAllUsers(allUsers);
      
      // Fetch departments and roles and cache them
      final allDepartments = await apiService.getAllDepartments();
      await _cacheService.cacheDepartments(allDepartments);
      
      final allRoles = await apiService.getAllRoles();
      await _cacheService.cacheRoles(allRoles);
      
      print('Admin data preloaded and cached successfully');
    } catch (e) {
      print('Error preloading admin data: $e');
      // Continue with login anyway - data will be fetched when needed
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingAdminData = false;
        });
      }
    }
  }
  
  // Preload current user's profile
  Future<void> _preloadUserProfile(int userId) async {
    try {
      // Just load the user's own profile
      final userProfile = await _apiService.getUserProfile(userId);
      await _cacheService.cacheUserProfile(userId, userProfile);
      print('User profile preloaded successfully');
    } catch (e) {
      print('Error preloading user profile: $e');
      // Continue with login anyway
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
              
              // Enhanced loading overlay with animation and dynamic state indicators
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
                            // Animated loading indicator
                            AnimatedBuilder(
                              animation: _loadingAnimationController,
                              builder: (context, child) {
                                return SizedBox(
                                  width: 50,
                                  height: 50,
                                  child: CircularProgressIndicator(
                                    value: (_isSyncingFace || _isLoadingAdminData) ? null : _loadingAnimationController.value,
                                    strokeWidth: 3,
                                    color: Colors.blue,
                                  ),
                                );
                              },
                            ),
                            const SizedBox(height: 16),
                            
                            // Dynamic loading message based on current operation
                            Text(
                              _loadingText,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            
                            // Show progress indicator for specific operations
                            if (_isSyncingFace || _isLoadingAdminData) ...[
                              const SizedBox(height: 12),
                              SizedBox(
                                width: 200,
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(4),
                                  child: LinearProgressIndicator(
                                    minHeight: 6,
                                    valueColor: AlwaysStoppedAnimation<Color>(Colors.blue[400]!),
                                    backgroundColor: Colors.blue[100],
                                  ),
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _isSyncingFace 
                                  ? "This helps improve facial recognition"
                                  : _isLoadingAdminData 
                                    ? "Preparing admin dashboard data" 
                                    : "",
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Colors.grey,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ],
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
    _loadingAnimationController.dispose();
    super.dispose();
  }
}