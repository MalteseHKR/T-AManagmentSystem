// lib/services/background_service.dart
import 'package:flutter/foundation.dart';
import 'package:workmanager/workmanager.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'notification_service.dart';

// This callback MUST be a top-level function outside of any class
@pragma('vm:entry-point') // Ensure this top-level function is not removed by tree-shaking
void callbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    debugPrint('Background task $taskName started');
    
    try {
      if (taskName == BackgroundService.checkPunchStatusTask) {
        // Get the last punch in time from shared preferences
        final prefs = await SharedPreferences.getInstance();
        final lastPunchInTimeStr = prefs.getString('last_punch_in_time');
        
        if (lastPunchInTimeStr != null) {
          try {
            final lastPunchInTime = DateTime.parse(lastPunchInTimeStr);
            final now = DateTime.now();
            
            // If it's been less than 12 hours since punch in, schedule a reminder
            if (now.difference(lastPunchInTime).inHours < 12) {
              // Initialize notification service
              final notificationService = NotificationService();
              await notificationService.initialize();
              
              // Schedule the reminder
              await notificationService.schedulePunchOutReminder(
                punchInTime: lastPunchInTime,
              );
              
              debugPrint('Successfully scheduled reminder in background task');
            } else {
              // Clear old punch-in data
              await prefs.remove('last_punch_in_time');
              debugPrint('Removed outdated punch-in record');
            }
          } catch (e) {
            debugPrint('Error processing punch-in in background: $e');
            // Clean up invalid data
            await prefs.remove('last_punch_in_time');
          }
        } else {
          debugPrint('No pending punch-in found in background task');
        }
      }
      return true;
    } catch (e) {
      debugPrint('Error executing background task: $e');
      return false;
    }
  });
}

class BackgroundService {
  static const String checkPunchStatusTask = 'com.garrison.checkPunchStatus';
  
  // Initialize background service
  static Future<void> initialize() async {
    await Workmanager().initialize(
      callbackDispatcher, // Make sure this is the top-level function
      isInDebugMode: true, // Set to false in production
    );
    
    debugPrint('Background service initialized');
  }
  
  // Register periodic background task
  static Future<void> registerPeriodicTask() async {
    await Workmanager().registerPeriodicTask(
      checkPunchStatusTask,
      checkPunchStatusTask,
      frequency: const Duration(minutes: 15),
      existingWorkPolicy: ExistingWorkPolicy.replace,
      backoffPolicy: BackoffPolicy.linear,
      backoffPolicyDelay: const Duration(minutes: 5),
      constraints: Constraints(
        networkType: NetworkType.not_required,
        requiresBatteryNotLow: false,
      ),
    );
    
    debugPrint('Periodic punch status check task registered');
  }
  
  // Cancel periodic background task
  static Future<void> cancelPeriodicTask() async {
    await Workmanager().cancelByUniqueName(checkPunchStatusTask);
    debugPrint('Periodic punch status check task cancelled');
  }
}