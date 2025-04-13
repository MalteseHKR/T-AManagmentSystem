// lib/services/biometric_service.dart
import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import '../services/secure_storage_service.dart';

class BiometricService {
  final LocalAuthentication _localAuthentication = LocalAuthentication();
  final SecureStorageService _secureStorageService = SecureStorageService();
  
  // Last authentication timestamp to prevent rapid prompts
  DateTime? _lastAuthAttempt;
  static const _minAuthInterval = Duration(milliseconds: 1500);
  
  // Track if authentication is currently in progress
  static bool _isAuthInProgress = false;
  
  // Reset authentication state
  void resetAuthTimeout() {
    _lastAuthAttempt = null;
    _isAuthInProgress = false;
  }
  
  // Check if biometric authentication is available on the device
  Future<bool> isDeviceBiometricsAvailable() async {
    try {
      bool canCheckBiometrics = await _localAuthentication.canCheckBiometrics;
      
      if (canCheckBiometrics) {
        List<BiometricType> availableBiometrics = 
          await _localAuthentication.getAvailableBiometrics();
        
        return availableBiometrics.isNotEmpty;
      }
      
      return false;
    } on PlatformException {
      return false;
    }
  }
  
  // Enable biometric login - delegate to secure storage
  Future<void> enableBiometricLogin() async {
    await _secureStorageService.enableBiometricLogin();
  }
  
  // Disable biometric login - delegate to secure storage
  Future<void> disableBiometricLogin() async {
    await _secureStorageService.disableBiometricLogin();
  }
  
  // Check if biometric login is enabled - delegate to secure storage
  Future<bool> isBiometricLoginEnabled() async {
    return await _secureStorageService.isBiometricLoginEnabled();
  }
  
  // Authenticate using biometrics
  Future<bool> authenticate() async {
    try {
      print("BiometricService.authenticate called");
      
      // Check if authentication is already in progress
      if (_isAuthInProgress) {
        print("BiometricService: Authentication already in progress, skipping");
        return false;
      }
      
      // Check if we've authenticated too recently
      final now = DateTime.now();
      if (_lastAuthAttempt != null && 
          now.difference(_lastAuthAttempt!) < _minAuthInterval) {
        print("BiometricService: Authentication attempted too soon after previous attempt");
        return false;
      }
      
      // Record this attempt time
      _lastAuthAttempt = now;
      
      // Set auth in progress flag
      _isAuthInProgress = true;
      
      // Check device biometrics availability
      bool canCheckBiometrics = await _localAuthentication.canCheckBiometrics;
      print("BiometricService: Can check biometrics: $canCheckBiometrics");
      if (!canCheckBiometrics) {
        _isAuthInProgress = false;
        return false;
      }
      
      List<BiometricType> availableBiometrics = 
        await _localAuthentication.getAvailableBiometrics();
      print("BiometricService: Available biometrics: $availableBiometrics");
      if (availableBiometrics.isEmpty) {
        _isAuthInProgress = false;
        return false;
      }
      
      // Perform authentication
      print("BiometricService: Attempting to authenticate with biometrics");
      try {
        bool result = await _localAuthentication.authenticate(
          localizedReason: 'Please authenticate to access the app',
          options: const AuthenticationOptions(
            stickyAuth: true,
            biometricOnly: true,
          ),
        );
        print("BiometricService: Authentication result: $result");
        return result;
      } on PlatformException catch (e) {
        // Handle specific error codes
        print("BiometricService: Authentication error: ${e.code} - ${e.message}");
        return false;
      }
    } catch (e) {
      print("BiometricService: Error during authentication: $e");
      return false;
    } finally {
      // Always reset the auth in progress flag
      _isAuthInProgress = false;
    }
  }
}