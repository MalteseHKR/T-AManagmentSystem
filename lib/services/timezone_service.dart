// lib/services/timezone_service.dart
import 'package:intl/intl.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

class TimezoneService {
  static final TimezoneService _instance = TimezoneService._internal();
  factory TimezoneService() => _instance;
  
  String? _currentTimezone;
  tz.Location? _localLocation;
  bool _verbose = false; // Debug logging flag - set to false to disable logs

  TimezoneService._internal() {
    _initializeTimezone();
  }

  Future<void> _initializeTimezone() async {
    tz.initializeTimeZones();
    try {
      // Dynamically get the device's timezone
      _currentTimezone = await FlutterTimezone.getLocalTimezone();
      
      // Fallback to UTC if timezone detection fails
      _localLocation = _currentTimezone != null 
        ? tz.getLocation(_currentTimezone!) 
        : tz.UTC;
      
      if (_verbose) print('Detected Timezone: $_currentTimezone');
    } catch (e) {
      if (_verbose) print('Timezone initialization error: $e');
      _localLocation = tz.UTC;
      _currentTimezone = 'UTC';
    }
  }

  // Get current timezone name
  String? get currentTimezone => _currentTimezone;

  // Get current time in detected timezone
  DateTime getNow() {
    return _localLocation != null 
      ? tz.TZDateTime.now(_localLocation!) 
      : DateTime.now().toUtc();
  }
  
  String formatTimeWithOffset(String? timeStr, {String format = 'HH:mm'}) {
  if (timeStr == null) return 'N/A';
  try {
    final timeParts = timeStr.split(':');
    if (timeParts.length >= 2) {
      final hours = int.parse(timeParts[0]);
      final minutes = int.parse(timeParts[1]);
      
      // Get the current device timezone offset in hours
      final now = DateTime.now();
      final localOffset = now.timeZoneOffset.inHours;
      
      // Create a DateTime with the parsed time (assuming server is in UTC)
      final serverDateTime = DateTime.utc(2000, 1, 1, hours, minutes);
      
      // Convert to local time
      final localDateTime = serverDateTime.toLocal();
      
      // Format using 24-hour format
      final formattedTime = DateFormat(format).format(localDateTime);
      
      return formattedTime;
    }
    return timeStr;
  } catch (e) {
    print('Time formatting error: $e');
    return timeStr;
  }
}

  // Format date with offset
  String formatDateWithOffset(String? dateStr, {String format = 'MMM dd, yyyy'}) {
    if (dateStr == null) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      // Convert to local timezone if possible
      final tzDate = _localLocation != null 
        ? tz.TZDateTime.from(date, _localLocation!)
        : date;
      return DateFormat(format).format(tzDate);
    } catch (e) {
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
        
        // Create a datetime in local timezone
        final serverTime = tz.TZDateTime(_localLocation ?? tz.UTC, 
          year, month, day, hours, minutes, seconds);
        
        return DateFormat(format).format(serverTime);
      }
      
      return '$dateStr $timeStr';
    } catch (e) {
      return '$dateStr $timeStr';
    }
  }
}