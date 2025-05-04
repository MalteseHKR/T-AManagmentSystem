// lib/services/session_service.dart
import 'dart:async';
import 'package:flutter/material.dart';

class SessionService {
  static final SessionService _instance = SessionService._internal();
  factory SessionService() => _instance;
  SessionService._internal();

  // First warning after 10 minutes
  static const warningTimeoutDuration = Duration(minutes: 10);
  
  // Final logout after 15 minutes total (5 minute after warning)
  static const finalTimeoutDuration = Duration(minutes: 15);
  
  Timer? _warningTimer;
  Timer? _finalLogoutTimer;
  VoidCallback? _onWarningTimeout;
  VoidCallback? _onFinalTimeout;
  
  // Initialize session timer
  void startSessionTimer({
    required VoidCallback onWarningTimeout, 
    required VoidCallback onFinalTimeout
  }) {
    _onWarningTimeout = onWarningTimeout;
    _onFinalTimeout = onFinalTimeout;
    _resetSessionTimer();
  }
  
  // Reset the session timer
  void _resetSessionTimer() {
    // Cancel both timers
    _warningTimer?.cancel();
    _finalLogoutTimer?.cancel();
    _finalLogoutTimer = null;
    
    // Start the warning timer
    _warningTimer = Timer(warningTimeoutDuration, () {
      if (_onWarningTimeout != null) {
        _onWarningTimeout!();
        
        // Start the final logout timer after warning is shown
        _finalLogoutTimer = Timer(
          Duration(minutes: 5), // 5 minute after warning
          () {
            if (_onFinalTimeout != null) {
              _onFinalTimeout!();
            }
          }
        );
        debugPrint('Final logout timer started. Will logout in 5 minute');
      }
    });
    
    debugPrint('Session timer reset. Warning in ${warningTimeoutDuration.inMinutes} minutes, logout in ${finalTimeoutDuration.inMinutes} minutes');
  }
  
  // Reset timer on user activity
  void userActivity() {
    _resetSessionTimer();
  }
  
  // Cancel warning but keep final timer running
  void acknowledgeWarning() {
    _warningTimer?.cancel();
    _warningTimer = null;
    
    // Make sure final timer is running
    if (_finalLogoutTimer == null && _onFinalTimeout != null) {
      _finalLogoutTimer = Timer(
        Duration(minutes: 5), // 5 minute after warning
        () {
          if (_onFinalTimeout != null) {
            _onFinalTimeout!();
          }
        }
      );
      debugPrint('Guaranteed final logout timer started. Will logout in 5 minute.');
    }
  }
  
  // Stop tracking session timeout
  void stopSessionTimer() {
    _warningTimer?.cancel();
    _finalLogoutTimer?.cancel();
    _warningTimer = null;
    _finalLogoutTimer = null;
    _onWarningTimeout = null;
    _onFinalTimeout = null;
    debugPrint('Session timer stopped');
  }
  
  // Dispose resources
  void dispose() {
    stopSessionTimer();
  }
}