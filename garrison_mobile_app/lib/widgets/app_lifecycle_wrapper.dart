// lib/widgets/app_lifecycle_wrapper.dart
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'dart:async';
import '../services/biometric_service.dart';
import '../services/secure_storage_service.dart';
import '../screens/login_screen.dart';
// Import main.dart to access the global navigator key
import '../main.dart'; // Make sure this points to your main.dart file

class AppLifecycleWrapper extends StatefulWidget {
  final Widget child;
  final CameraDescription camera;
  final CameraDescription? rearCamera;
  
  // Time in seconds that app must be in background before requiring auth
  final int minBackgroundTimeForAuth;

  const AppLifecycleWrapper({
    Key? key,
    required this.child,
    required this.camera,
    this.rearCamera,
    this.minBackgroundTimeForAuth = 1, // Set default to 1 second
  }) : super(key: key);

  @override
  State<AppLifecycleWrapper> createState() => _AppLifecycleWrapperState();
}

class _AppLifecycleWrapperState extends State<AppLifecycleWrapper> 
    with WidgetsBindingObserver {
  final BiometricService _biometricService = BiometricService();
  final SecureStorageService _secureStorageService = SecureStorageService();
  bool _isAuthenticated = true;
  bool _isAuthenticating = false;
  DateTime? _appPausedTime;
  bool _isOnLoginScreen = false;
  
  // Track if back button authentication is in progress
  bool _isBackButtonAuthenticating = false;

  @override
  void initState() {
    super.initState();
    print("AppLifecycleWrapper: initState called");
    WidgetsBinding.instance.addObserver(this);
    
    // Reset the biometric service state
    _biometricService.resetAuthTimeout();
  }

  @override
  void dispose() {
    print("AppLifecycleWrapper: dispose called");
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // Check if we're on the login screen
  void _checkIfOnLoginScreen(BuildContext context) {
    try {
      final currentRoute = ModalRoute.of(context);
      final routeName = currentRoute?.settings.name;
      _isOnLoginScreen = routeName == '/login' || 
                        routeName == 'login' || 
                        (routeName?.contains('login') ?? false);
      print('AppLifecycleWrapper: Is on login screen: $_isOnLoginScreen');
    } catch (e) {
      print('AppLifecycleWrapper: Error checking login screen: $e');
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) async {
    print('AppLifecycleWrapper: App lifecycle state changed to: $state');
    
    if (state == AppLifecycleState.paused) {
      // App is definitely going to background - record the time
      _appPausedTime = DateTime.now();
      _isAuthenticated = false;
      print('AppLifecycleWrapper: App is paused at $_appPausedTime');
    } 
    else if (state == AppLifecycleState.resumed) {
      // Skip if we're authenticating (likely resuming from biometric prompt)
      if (_isAuthenticating) {
        print('AppLifecycleWrapper: Resuming during authentication, skipping');
        return;
      }
      
      // Only consider it a "resume" if we have a recorded pause time
      if (_appPausedTime != null) {
        print('AppLifecycleWrapper: App is resuming after being paused');
        
        // Calculate time in background
        final timeInBackground = DateTime.now().difference(_appPausedTime!).inSeconds;
        print('AppLifecycleWrapper: Time in background: ${timeInBackground}s');
        
        // Clear the paused time
        _appPausedTime = null;
        
        // Only authenticate if background time exceeds minimum
        if (timeInBackground >= widget.minBackgroundTimeForAuth) {
          print('AppLifecycleWrapper: Sufficient time in background, will check auth');
          
          // Delay authentication slightly to let UI settle
          Future.delayed(const Duration(milliseconds: 300), () {
            if (!mounted) return;
            
            // Check if we're on login screen
            if (context.mounted) {
              _checkIfOnLoginScreen(context);
              if (_isOnLoginScreen) {
                print('AppLifecycleWrapper: On login screen, skipping auth');
                return;
              }
            }
            
            // Check if biometrics is enabled
            _secureStorageService.isBiometricLoginEnabled().then((bool isEnabled) {
              if (isEnabled && !_isAuthenticating && mounted) {
                _authenticateUser(context);
              }
            });
          });
        } else {
          print('AppLifecycleWrapper: Background time too short, skipping auth');
        }
      }
    }
  }
  
  // Main authentication method
  Future<bool> _authenticateUser(BuildContext context) async {
    // Don't authenticate if already authenticating
    if (_isAuthenticating) {
      print('AppLifecycleWrapper: Already authenticating, skipping');
      return false;
    }
    
    // Set flags
    _isAuthenticating = true;
    print('AppLifecycleWrapper: Starting biometric authentication');
    
    try {
      // Perform authentication
      final bool isAuthenticated = await _biometricService.authenticate();
      
      if (isAuthenticated) {
        print('AppLifecycleWrapper: Authentication successful');
        if (mounted) {
          setState(() {
            _isAuthenticated = true;
          });
        }
        return true;
      } else {
        print('AppLifecycleWrapper: Authentication failed, will navigate to login');
        
        // Check if we're on login screen before navigating
        if (context.mounted) {
          _checkIfOnLoginScreen(context);
          if (_isOnLoginScreen) {
            print('AppLifecycleWrapper: Already on login screen, skipping navigation');
            return false;
          }
        }
        
        // Navigate to login
        _navigateToLogin();
        return false;
      }
    } catch (e) {
      print('AppLifecycleWrapper: Authentication error: $e');
      return false;
    } finally {
      _isAuthenticating = false;
    }
  }
  
  // Special authentication for back button
  Future<bool> _authenticateForBackButton(BuildContext context) async {
    // Don't authenticate if already authenticating
    if (_isBackButtonAuthenticating) {
      print('AppLifecycleWrapper: Back button auth already in progress, blocking');
      return false;
    }
    
    // Set flag
    _isBackButtonAuthenticating = true;
    print('AppLifecycleWrapper: Starting back button authentication');
    
    try {
      // Perform authentication with direct prompts, no delay
      final bool isAuthenticated = await _biometricService.authenticate();
      
      if (isAuthenticated) {
        print('AppLifecycleWrapper: Back button authentication successful');
        if (mounted) {
          setState(() {
            _isAuthenticated = true;
          });
        }
        return true;
      } else {
        print('AppLifecycleWrapper: Back button authentication failed');
        
        // Navigate to login using global navigator key
        _navigateToLogin();
        return false;
      }
    } catch (e) {
      print('AppLifecycleWrapper: Back button authentication error: $e');
      return false;
    } finally {
      _isBackButtonAuthenticating = false;
    }
  }
  
  // Navigation to login screen using global navigator key
  void _navigateToLogin() {
    print('AppLifecycleWrapper: Preparing to navigate to login');
    
    // Use the global navigator key from main.dart
    if (navigatorKey.currentState != null) {
      print('AppLifecycleWrapper: Using global navigator key for navigation');
      
      // Add a slight delay to ensure we're not in the middle of a build cycle
      Future.delayed(const Duration(milliseconds: 200), () {
        navigatorKey.currentState!.pushAndRemoveUntil(
          MaterialPageRoute(
            settings: const RouteSettings(name: '/login'),
            builder: (context) => LoginScreen(
              camera: widget.camera,
              rearCamera: widget.rearCamera,
            ),
          ),
          (Route<dynamic> route) => false,
        );
      });
    } else {
      print('AppLifecycleWrapper: Global navigator key not available');
    }
  }

  // Handle back button press via PopScope
  Future<bool> _handleBackButton() async {
    print('AppLifecycleWrapper: Back button pressed');
    
    // Check if we're on login screen
    _checkIfOnLoginScreen(context);
    if (_isOnLoginScreen) {
      print('AppLifecycleWrapper: On login screen, allowing back button');
      return true;
    }
    
    // If already authenticated, allow back button
    if (_isAuthenticated) {
      print('AppLifecycleWrapper: Already authenticated, allowing back button');
      return true;
    }
    
    print('AppLifecycleWrapper: Not authenticated, requiring authentication for back button');
    
    // Check if biometrics is enabled
    final bool isBiometricsEnabled = await _secureStorageService.isBiometricLoginEnabled();
    if (!isBiometricsEnabled) {
      print('AppLifecycleWrapper: Biometrics not enabled, allowing back button');
      return true;
    }
    
    // Perform authentication for back button - use specialized method
    final bool authenticated = await _authenticateForBackButton(context);
    
    // Only allow back navigation if authentication succeeded
    return authenticated;
  }

  @override
  Widget build(BuildContext context) {
    print('AppLifecycleWrapper: build method called');
    
    // Use PopScope (the modern replacement for WillPopScope)
    return PopScope(
      canPop: _isAuthenticated || _isOnLoginScreen,
      onPopInvoked: (didPop) async {
        print('AppLifecycleWrapper: PopScope onPopInvoked, didPop: $didPop');
        
        // If we already popped, do nothing
        if (didPop) return;
        
        // We need to check authentication since canPop was false
        final bool canPopNow = await _handleBackButton();
        
        // If authentication succeeded and we can now pop, do it manually
        if (canPopNow && mounted && context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: widget.child,
    );
  }
}