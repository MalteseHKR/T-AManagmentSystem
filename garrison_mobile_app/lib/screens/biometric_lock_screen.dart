// lib/screens/biometric_lock_screen.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import '../services/biometric_service.dart';
import '../screens/login_screen.dart';

class BiometricLockScreen extends StatefulWidget {
  final Widget destinationScreen;
  final CameraDescription camera;
  final CameraDescription? rearCamera;

  const BiometricLockScreen({
    Key? key,
    required this.destinationScreen,
    required this.camera,
    this.rearCamera,
  }) : super(key: key);

  @override
  State<BiometricLockScreen> createState() => _BiometricLockScreenState();
}

class _BiometricLockScreenState extends State<BiometricLockScreen> {
  final BiometricService _biometricService = BiometricService();
  bool _isAuthenticating = false;

  @override
  void initState() {
    super.initState();
    print('BiometricLockScreen: initState called');
    // Try authentication as soon as screen loads
    WidgetsBinding.instance.addPostFrameCallback((_) {
      print('BiometricLockScreen: Post frame callback, triggering authentication');
      _authenticate();
    });
  }

  @override
  void dispose() {
    print('BiometricLockScreen: dispose called');
    super.dispose();
  }

  Future<void> _authenticate() async {
    print('BiometricLockScreen: _authenticate called');
    if (_isAuthenticating) {
      print('BiometricLockScreen: Already authenticating, skipping');
      return;
    }

    setState(() {
      _isAuthenticating = true;
    });

    try {
      print('BiometricLockScreen: Attempting biometric authentication');
      final bool isAuthenticated = await _biometricService.authenticate();
      print('BiometricLockScreen: Authentication result: $isAuthenticated');
      
      if (isAuthenticated) {
        if (mounted) {
          print('BiometricLockScreen: Authentication successful, navigating to destination');
          // Just close the modal sheet, don't navigate
          Navigator.of(context).pop(true);
        }
      } else {
        // Authentication failed
        if (mounted) {
          print('BiometricLockScreen: Authentication failed, showing error');
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Authentication failed. Please try again.'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      print('BiometricLockScreen: Authentication error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Authentication error: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isAuthenticating = false;
        });
      }
    }
  }

  void _returnToLogin() {
    print('BiometricLockScreen: Returning to login screen');
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (context) => LoginScreen(
          camera: widget.camera,
          rearCamera: widget.rearCamera,
        ),
      ),
      (Route<dynamic> route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    print('BiometricLockScreen: build called');
    return PopScope(
      canPop: false, // Prevent back button from working
      child: Container(
        height: MediaQuery.of(context).size.height,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Colors.blue[300]!, Colors.blue[900]!],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: Card(
              margin: const EdgeInsets.all(24),
              elevation: 8,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.lock_outline,
                      size: 80,
                      color: Colors.blue,
                    ),
                    const SizedBox(height: 24),
                    const Text(
                      'Authentication Required',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'Please authenticate to continue using the app',
                      style: TextStyle(
                        fontSize: 16,
                        color: Colors.black54,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: FilledButton.icon(
                        onPressed: _isAuthenticating ? null : _authenticate,
                        icon: const Icon(Icons.fingerprint),
                        label: Text(_isAuthenticating ? 'Authenticating...' : 'Authenticate'),
                        style: FilledButton.styleFrom(
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: OutlinedButton(
                        onPressed: _returnToLogin,
                        style: OutlinedButton.styleFrom(
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        child: const Text('Return to Login'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}