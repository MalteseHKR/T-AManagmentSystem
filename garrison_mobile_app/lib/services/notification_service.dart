// lib/services/notification_service.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'timezone_service.dart';

class NotificationService {
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();
  final TimezoneService _timezoneService = TimezoneService();

  static const int punchOutReminderId = 1;
  // Start leave reminder IDs at 1000 to avoid conflicts
  static const int leaveReminderBaseId = 1000;
  
  // Initialize notification service
  Future<void> initialize() async {
    // Initialize timezone
    tz.initializeTimeZones();
    final String currentTimeZone = await FlutterTimezone.getLocalTimezone();
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
          ?.requestNotificationsPermission();
    }
    
    debugPrint('NotificationService initialized successfully');
  }

  // Schedule a punch out reminder notification
  Future<void> schedulePunchOutReminder({required DateTime punchInTime}) async {
    // Apply timezone offset consistently
    final adjustedPunchInTime = punchInTime;
    debugPrint('Scheduling punch out reminder for 5 minutes after: $adjustedPunchInTime');
    
    // Save punch-in time in shared preferences
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('last_punch_in_time', adjustedPunchInTime.toIso8601String());
    
    // Calculate notification time (5 minutes after punch in for testing)
    final reminderTime = tz.TZDateTime.from(
      adjustedPunchInTime.add(const Duration(minutes: 5)),
      tz.local,
    );

    // Configure notification details for Android
    const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'punch_out_reminder_channel',
      'Punch Out Reminders',
      channelDescription: 'Reminds you to punch out after work',
      importance: Importance.high,
      priority: Priority.high,
      largeIcon: DrawableResourceAndroidBitmap('@mipmap/ic_launcher'),
      icon: '@mipmap/ic_lancher',
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
        final now = _timezoneService.getNow(); // Use timezone service
        
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
  
  // Schedule a notification at a specific date and time for leave reminders
  Future<void> scheduleNotification({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledDate,
    String? payload,
  }) async {
    // Configure notification details for Android
    const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'leave_reminder_channel',
      'Leave Reminders',
      channelDescription: 'Reminders for leave requests and medical certificates',
      importance: Importance.high,
      priority: Priority.high,
      enableVibration: true,
    );

    // Configure notification details for iOS
    const DarwinNotificationDetails iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    // Combine platform-specific details
    const NotificationDetails notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );
    
    final reminderTime = tz.TZDateTime.from(scheduledDate, tz.local);

    await flutterLocalNotificationsPlugin.zonedSchedule(
      id,
      title,
      body,
      reminderTime,
      notificationDetails,
      androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
      uiLocalNotificationDateInterpretation:
          UILocalNotificationDateInterpretation.absoluteTime,
      payload: payload,
    );
    
    debugPrint('Scheduled leave reminder notification for $scheduledDate');
  }
  
  // Show an immediate notification
  Future<void> showNotification({
    required int id,
    required String title,
    required String body,
    String? payload,
  }) async {
    // Configure notification details for Android
    const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'leave_reminder_channel',
      'Leave Reminders',
      channelDescription: 'Reminders for leave requests and medical certificates',
      importance: Importance.high,
      priority: Priority.high,
      enableVibration: true,
    );

    // Configure notification details for iOS
    const DarwinNotificationDetails iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    // Combine platform-specific details
    const NotificationDetails notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );
    
    await flutterLocalNotificationsPlugin.show(
      id,
      title,
      body,
      notificationDetails,
      payload: payload,
    );
    
    debugPrint('Showed immediate notification: $title');
  }
  
  // Cancel a specific notification
  Future<void> cancelNotification(int id) async {
    await flutterLocalNotificationsPlugin.cancel(id);
    debugPrint('Cancelled notification with id: $id');
  }
  
  // Cancel all notifications
  Future<void> cancelAllNotifications() async {
    await flutterLocalNotificationsPlugin.cancelAll();
    debugPrint('Cancelled all notifications');
  }
  
  // Schedule daily reminders for medical certificate upload
  Future<void> scheduleMedicalCertificateReminders(int requestId) async {
    final now = DateTime.now();
    
    // Schedule reminders for the next 10 days
    for (int i = 1; i <= 10; i++) {
      final reminderDate = now.add(Duration(days: i));
      // Set reminder for 9:00 AM
      final scheduledTime = DateTime(
        reminderDate.year, 
        reminderDate.month, 
        reminderDate.day, 
        9, 0, 0
      );
      
      await scheduleNotification(
        id: leaveReminderBaseId + requestId + i, // Unique ID using request ID and day
        title: 'Medical Certificate Required',
        body: 'Please upload your medical certificate and complete your sick leave request.',
        scheduledDate: scheduledTime,
        payload: 'medical_certificate_reminder_$requestId',
      );
    }
    
    debugPrint('Scheduled 10 daily reminders for medical certificate upload');
  }
  
  // Cancel all medical certificate reminders for a request
  Future<void> cancelMedicalCertificateReminders(int requestId) async {
    for (int i = 1; i <= 10; i++) {
      await cancelNotification(leaveReminderBaseId + requestId + i);
    }
    
    debugPrint('Cancelled all medical certificate reminders for request $requestId');
  }
}