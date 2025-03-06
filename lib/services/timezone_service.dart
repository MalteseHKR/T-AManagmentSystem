// lib/services/timezone_service.dart
import 'package:intl/intl.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;
import 'package:flutter/foundation.dart';

class TimezoneService {
  static final TimezoneService _instance = TimezoneService._internal();
  factory TimezoneService() => _instance;
  
  String? _currentTimezone;
  tz.Location? _localLocation;
  // Define _cetLocation as nullable
  tz.Location? _cetLocation;
  bool _verbose = true; // Debug logging flag - set to false to disable logs

  TimezoneService._internal() {
    _initializeTimezone();
  }

  Future<void> _initializeTimezone() async {
    // Initialize timezone database first
    tz.initializeTimeZones();
    
    // Then initialize CET location
    _cetLocation = tz.getLocation('Europe/Paris'); // Paris uses CET/CEST
    
    try {
      // Dynamically get the device's timezone
      _currentTimezone = await FlutterTimezone.getLocalTimezone();
      
      // Fallback to CET if timezone detection fails
      _localLocation = _currentTimezone != null 
        ? tz.getLocation(_currentTimezone!) 
        : _cetLocation;
      
      if (_verbose) debugPrint('Detected Timezone: $_currentTimezone');
    } catch (e) {
      if (_verbose) debugPrint('Timezone initialization error: $e');
      _localLocation = _cetLocation;
      _currentTimezone = 'Europe/Paris (CET)';
    }
  }

  // Get current timezone name
  String? get currentTimezone => _currentTimezone;

  // Get current time in detected timezone, fallback to CET
  DateTime getNow() {
    if (_localLocation != null) {
      return tz.TZDateTime.now(_localLocation!);
    } else if (_cetLocation != null) {
      return tz.TZDateTime.now(_cetLocation!);
    } else {
      // Ultimate fallback if something is wrong with timezone initialization
      return DateTime.now();
    }
  }
  
  String formatTimeWithOffset(String? timeStr, {String format = 'HH:mm', String serverTimezone = 'UTC'}) {
    if (timeStr == null) return 'N/A';
    try {
      final timeParts = timeStr.split(':');
      if (timeParts.length >= 2) {
        final hours = int.parse(timeParts[0]);
        final minutes = int.parse(timeParts[1]);
        
        // Get today's date components
        final now = DateTime.now();
        
        // Create a DateTime with the server's timezone
        tz.Location serverLocation;
        try {
          serverLocation = tz.getLocation(serverTimezone);
        } catch (e) {
          if (_verbose) debugPrint('Invalid server timezone: $e');
          // Default to CET if server timezone is invalid and CET is available
          serverLocation = _cetLocation ?? tz.UTC;
        }
        
        // Create time in the server's timezone
        final serverDateTime = tz.TZDateTime(
          serverLocation, now.year, now.month, now.day, hours, minutes);
        
        // Convert to the local timezone
        tz.Location targetLocation = _localLocation ?? (_cetLocation ?? tz.UTC);
        final localDateTime = tz.TZDateTime.from(serverDateTime, targetLocation);
        
        // Format using provided format
        return DateFormat(format).format(localDateTime);
      }
      return timeStr;
    } catch (e) {
      if (_verbose) debugPrint('Time formatting error: $e');
      return timeStr;
    }
  }

  // Format local time without timezone conversion
  String formatLocalTime(String? timeStr, {String format = 'HH:mm'}) {
    if (timeStr == null) return 'N/A';
    try {
      final timeParts = timeStr.split(':');
      if (timeParts.length >= 2) {
        final hours = int.parse(timeParts[0]);
        final minutes = int.parse(timeParts[1]);
        
        // Create a DateTime using the time directly without conversion
        final localDateTime = DateTime(2000, 1, 1, hours, minutes);
        
        // Format using provided format
        return DateFormat(format).format(localDateTime);
      }
      return timeStr;
    } catch (e) {
      if (_verbose) debugPrint('Time formatting error: $e');
      return timeStr;
    }
  }

  // Format date with offset
  String formatDateWithOffset(String? dateStr, {String format = 'MMM dd, yyyy'}) {
    if (dateStr == null) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      // Convert to local timezone if possible, fallback to CET
      tz.Location targetLocation = _localLocation ?? (_cetLocation ?? tz.UTC);
      final tzDate = tz.TZDateTime.from(date, targetLocation);
      return DateFormat(format).format(tzDate);
    } catch (e) {
      if (_verbose) debugPrint('Date formatting error: $e');
      return dateStr;
    }
  }
  
  // Format server timestamp
  String formatServerTimestamp(String? dateStr, String? timeStr, {String format = 'MMM dd, yyyy HH:mm'}) {
    if (dateStr == null || timeStr == null) return 'N/A';
    
    try {
      final dateTimeParts = dateStr.split('-');
      final timeParts = timeStr.split(':');
      
      if (dateTimeParts.length >= 3 && timeParts.length >= 2) {
        final year = int.parse(dateTimeParts[0]);
        final month = int.parse(dateTimeParts[1]);
        final day = int.parse(dateTimeParts[2]);
        
        final hours = int.parse(timeParts[0]);
        final minutes = int.parse(timeParts[1]);
        int seconds = 0;
        if (timeParts.length >= 3) {
          seconds = int.parse(timeParts[2].split('.')[0]);
        }
        
        // Create a datetime in local timezone or fallback to CET
        tz.Location targetLocation = _localLocation ?? (_cetLocation ?? tz.UTC);
        final serverTime = tz.TZDateTime(targetLocation, 
          year, month, day, hours, minutes, seconds);
        
        return DateFormat(format).format(serverTime);
      }
      
      return '$dateStr $timeStr';
    } catch (e) {
      if (_verbose) debugPrint('Server timestamp formatting error: $e');
      return '$dateStr $timeStr';
    }
  }
}