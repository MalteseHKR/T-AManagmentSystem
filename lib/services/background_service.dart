// lib/services/background_service.dart
import 'package:flutter/foundation.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_background_service_android/flutter_background_service_android.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'notification_service.dart';

class BackgroundService {
  static const String checkPunchStatusTask = 'com.garrison.checkPunchStatus';
  static final FlutterLocalNotificationsPlugin _flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();
  
  // Initialize background service
  static Future<void> initialize() async {
    final service = FlutterBackgroundService();
    
    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'garrison_track_background',
      'Garrison Track Background Service',
      description: 'This channel is used for the background service notification',
      importance: Importance.low,
    );

    // Create notification channel for Android
    await _flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    // Configure the service
    await service.configure(
      androidConfiguration: AndroidConfiguration(
        onStart: onStart,
        autoStart: true,
        isForegroundMode: true,
        notificationChannelId: 'garrison_track_background',
        initialNotificationTitle: 'Garrison Track',
        initialNotificationContent: 'Monitoring attendance status',
        foregroundServiceNotificationId: 888,
      ),
      iosConfiguration: IosConfiguration(
        autoStart: true,
        onForeground: onStart,
        onBackground: onIosBackground,
      ),
    );
    
    debugPrint('Background service initialized');
  }
  
  // iOS background handler - required by the plugin
  @pragma('vm:entry-point')
  static Future<bool> onIosBackground(ServiceInstance service) async {
    debugPrint('iOS background service started');
    return true;
  }
  
  // Main background service handler
  @pragma('vm:entry-point')
  static void onStart(ServiceInstance service) async {
    debugPrint('Background service started');
    
    // Run initial check
    await _checkPunchStatus();
    
    // For Android, we need to periodically check using a timer
    service.on('checkPunchStatus').listen((event) async {
      await _checkPunchStatus();
    });
    
    // Set up periodic check (every 15 minutes)
    Stream.periodic(
      const Duration(minutes: 15), 
      (i) => i
    ).listen((_) async {
      await _checkPunchStatus();
    });
    
    // Register for foreground service (Android) to keep the service running
    if (service is AndroidServiceInstance) {
      service.setAsForegroundService();
    }
  }
  
  // Register the background task
  static Future<void> registerPeriodicTask() async {
    final service = FlutterBackgroundService();
    bool isRunning = await service.isRunning();
    
    if (!isRunning) {
      await service.startService();
    }
    
    // Trigger a check
    service.invoke('checkPunchStatus');
    
    debugPrint('Periodic punch status check task registered');
  }
  
  // Cancel the background task
  static Future<void> cancelPeriodicTask() async {
    final service = FlutterBackgroundService();
    service.invoke('stopService');
    debugPrint('Background service stopped');
  }
  
  // Check punch status logic (extracted from the callback)
  static Future<void> _checkPunchStatus() async {
    debugPrint('Checking punch status in background');
    
    try {
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
    } catch (e) {
      debugPrint('Error executing background task: $e');
    }
  }
}