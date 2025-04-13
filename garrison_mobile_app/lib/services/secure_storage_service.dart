// lib/services/secure_storage_service.dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {
  // Create storage
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  // Keys for storing credentials
  static const String _emailKey = 'user_email';
  static const String _passwordKey = 'user_password';
  static const String _biometricEnabledKey = 'biometric_login_enabled';

  // Save user credentials
  Future<void> saveCredentials({
    required String email, 
    required String password
  }) async {
    try {
      await _storage.write(key: _emailKey, value: email);
      await _storage.write(key: _passwordKey, value: password);
    } catch (e) {
      print('Error saving credentials: $e');
      throw Exception('Failed to save credentials');
    }
  }

  // Retrieve stored email
  Future<String?> getStoredEmail() async {
    try {
      return await _storage.read(key: _emailKey);
    } catch (e) {
      print('Error retrieving email: $e');
      return null;
    }
  }

  // Retrieve stored password
  Future<String?> getStoredPassword() async {
    try {
      return await _storage.read(key: _passwordKey);
    } catch (e) {
      print('Error retrieving password: $e');
      return null;
    }
  }

  // Clear stored credentials
  Future<void> clearCredentials() async {
    try {
      await _storage.delete(key: _emailKey);
      await _storage.delete(key: _passwordKey);
    } catch (e) {
      print('Error clearing credentials: $e');
    }
  }

  // Enable biometric login
  Future<void> enableBiometricLogin() async {
    try {
      await _storage.write(key: _biometricEnabledKey, value: 'true');
    } catch (e) {
      print('Error enabling biometric login: $e');
    }
  }

  // Disable biometric login
  Future<void> disableBiometricLogin() async {
    try {
      await _storage.write(key: _biometricEnabledKey, value: 'false');
    } catch (e) {
      print('Error disabling biometric login: $e');
    }
  }

  // Check if biometric login is enabled
  Future<bool> isBiometricLoginEnabled() async {
    try {
      final value = await _storage.read(key: _biometricEnabledKey);
      return value == 'true';
    } catch (e) {
      print('Error checking biometric login: $e');
      return false;
    }
  }

  static const String _authInProgressKey = 'auth_in_progress';

  Future<void> setAuthInProgress(bool inProgress) async {
    try {
      await _storage.write(key: _authInProgressKey, value: inProgress ? 'true' : 'false');
    } catch (e) {
      print('Error setting auth in progress: $e');
    }
  }

  Future<bool> isAuthInProgress() async {
    try {
      final value = await _storage.read(key: _authInProgressKey);
      return value == 'true';
    } catch (e) {
      print('Error checking auth in progress: $e');
      return false;
    }
  }
}