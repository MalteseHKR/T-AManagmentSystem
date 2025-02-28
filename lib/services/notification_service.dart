// lib/services/notification_service.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;
import 'package:flutter_native_timezone/flutter_native_timezone.dart';
import 'package:shared_preferences/shared_preferences.dart';

class NotificationService {
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  static const int punchOutReminderId = 1;
  
  // Initialize notification service
  Future<void> initialize() async {
    // Initialize timezone
    tz.initializeTimeZones();
    final String currentTimeZone = await FlutterNativeTimezone.getLocalTimezone();
    tz.setLocalLocation(tz.getLocation(currentTimeZone));
    
    // For Android 
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    
    // For iOS
    final DarwinInitializationSettings initializationSettingsDarwin =
        DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
      onDidReceiveLocalNotification: (int id, String? title, String? body, String? payload) {
        debugPrint('Received iOS notification: $title');
      },
    );
    
    // Initialize settings
    final InitializationSettings initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsDarwin,
    );
    
    // Initialize plugin
    await flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: (NotificationResponse notificationResponse) {
        debugPrint('Notification clicked: ${notificationResponse.payload}');
      },
    );

    // Request permissions for iOS
    if (Platform.isIOS) {
      await flutterLocalNotificationsPlugin
          .resolvePlatformSpecificImplementation<
              IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions(
            alert: true,
            badge: true,
            sound: true,
          );
    }
    
    // Request permissions for Android
    if (Platform.isAndroid) {
      await flutterLocalNotificationsPlugin
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.requestPermission();
    }
    
    debugPrint('NotificationService initialized successfully');
  }

  // Schedule a punch out reminder notification
  Future<void> schedulePunchOutReminder({required DateTime punchInTime}) async {
    debugPrint('Scheduling punch out reminder for 5 minutes after: $punchInTime');
    
    // Save punch-in time in shared preferences
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('last_punch_in_time', punchInTime.toIso8601String());
    
    // Calculate notification time (5 minutes after punch in for testing)
    final reminderTime = tz.TZDateTime.from(
      punchInTime.add(const Duration(minutes: 5)),
      tz.local,
    );

    // Configure notification details for Android
    const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'punch_out_reminder_channel',
      'Punch Out Reminders',
      channelDescription: 'Reminds you to punch out after work',
      importance: Importance.high,
      priority: Priority.high,
      icon: '@mipmap/ic_launcher',
    );

    // Configure notification details for iOS
    const DarwinNotificationDetails iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
      sound: 'default',
    );

    // Combine platform-specific details
    const NotificationDetails notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    // Cancel any existing reminders before scheduling new one
    await flutterLocalNotificationsPlugin.cancel(punchOutReminderId);

    // Schedule the notification
    await flutterLocalNotificationsPlugin.zonedSchedule(
      punchOutReminderId,
      'Time to Punch Out',
      'Don\'t forget to punch out before you leave!',
      reminderTime,
      notificationDetails,
      androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
      uiLocalNotificationDateInterpretation:
          UILocalNotificationDateInterpretation.absoluteTime,
      payload: 'punch_out_reminder',
    );

    debugPrint('Punch out reminder scheduled for: $reminderTime');
  }

  // Cancel punch out reminder
  Future<void> cancelPunchOutReminder() async {
    await flutterLocalNotificationsPlugin.cancel(punchOutReminderId);
    debugPrint('Punch out reminder cancelled');
    
    // Clear punch-in time from shared preferences
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('last_punch_in_time');
  }

  // Check if there's a previous punch-in that needs a reminder
  Future<void> checkForPendingPunchIn() async {
    final prefs = await SharedPreferences.getInstance();
    final lastPunchInTimeStr = prefs.getString('last_punch_in_time');
    
    if (lastPunchInTimeStr != null) {
      try {
        final lastPunchInTime = DateTime.parse(lastPunchInTimeStr);
        final now = DateTime.now();
        
        // If it's been less than 12 hours since punch in, schedule a reminder
        if (now.difference(lastPunchInTime).inHours < 12) {
          debugPrint('Found pending punch-in from: $lastPunchInTime');
          await schedulePunchOutReminder(punchInTime: lastPunchInTime);
        } else {
          // Clear old punch-in data
          await prefs.remove('last_punch_in_time');
        }
      } catch (e) {
        debugPrint('Error checking pending punch-in: $e');
        await prefs.remove('last_punch_in_time');
      }
    }
  }
}