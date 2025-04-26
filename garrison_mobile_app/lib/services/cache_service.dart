// lib/services/cache_service.dart
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class CacheService {
  static final CacheService _instance = CacheService._internal();
  factory CacheService() => _instance;
  CacheService._internal();

  // Cache keys
  static const String USER_PROFILES_KEY = "user_profiles";
  static const String ALL_USERS_KEY = "all_users";
  static const String ALL_DEPARTMENTS_KEY = "all_departments";
  static const String ALL_ROLES_KEY = "all_roles";
  static const String LEAVE_REQUESTS_PREFIX = "leave_requests_";
  static const String ATTENDANCE_HISTORY_PREFIX = "attendance_history_";

  // Cache user profile
  Future<void> cacheUserProfile(int userId, Map<String, dynamic> profileData) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final existingDataStr = prefs.getString(USER_PROFILES_KEY) ?? '{}';
      final Map<String, dynamic> existingData = json.decode(existingDataStr);
      
      existingData[userId.toString()] = profileData;
      await prefs.setString(USER_PROFILES_KEY, json.encode(existingData));
      
      print('Cached user profile for user $userId');
    } catch (e) {
      print('Error caching user profile: $e');
    }
  }

  // Get cached user profile
  Future<Map<String, dynamic>?> getCachedUserProfile(int userId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final existingDataStr = prefs.getString(USER_PROFILES_KEY) ?? '{}';
      final Map<String, dynamic> existingData = json.decode(existingDataStr);
      
      if (existingData.containsKey(userId.toString())) {
        print('Retrieved user profile from cache for user $userId');
        return Map<String, dynamic>.from(existingData[userId.toString()]);
      }
      
      return null;
    } catch (e) {
      print('Error getting cached user profile: $e');
      return null;
    }
  }

  // Cache all users
  Future<void> cacheAllUsers(List<Map<String, dynamic>> users) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(ALL_USERS_KEY, json.encode(users));
      print('Cached ${users.length} users');
    } catch (e) {
      print('Error caching all users: $e');
    }
  }

  // Get all cached users
  Future<List<Map<String, dynamic>>?> getCachedAllUsers() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final dataStr = prefs.getString(ALL_USERS_KEY);
      
      if (dataStr != null) {
        final List<dynamic> data = json.decode(dataStr);
        print('Retrieved ${data.length} users from cache');
        return data.map((item) => Map<String, dynamic>.from(item)).toList();
      }
      
      return null;
    } catch (e) {
      print('Error getting cached users: $e');
      return null;
    }
  }

  // Cache departments
  Future<void> cacheDepartments(List<Map<String, dynamic>> departments) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(ALL_DEPARTMENTS_KEY, json.encode(departments));
      print('Cached ${departments.length} departments');
    } catch (e) {
      print('Error caching departments: $e');
    }
  }

  // Get cached departments
  Future<List<Map<String, dynamic>>?> getCachedDepartments() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final dataStr = prefs.getString(ALL_DEPARTMENTS_KEY);
      
      if (dataStr != null) {
        final List<dynamic> data = json.decode(dataStr);
        print('Retrieved ${data.length} departments from cache');
        return data.map((item) => Map<String, dynamic>.from(item)).toList();
      }
      
      return null;
    } catch (e) {
      print('Error getting cached departments: $e');
      return null;
    }
  }

  // Cache roles
  Future<void> cacheRoles(List<Map<String, dynamic>> roles) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(ALL_ROLES_KEY, json.encode(roles));
      print('Cached ${roles.length} roles');
    } catch (e) {
      print('Error caching roles: $e');
    }
  }

  // Get cached roles
  Future<List<Map<String, dynamic>>?> getCachedRoles() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final dataStr = prefs.getString(ALL_ROLES_KEY);
      
      if (dataStr != null) {
        final List<dynamic> data = json.decode(dataStr);
        print('Retrieved ${data.length} roles from cache');
        return data.map((item) => Map<String, dynamic>.from(item)).toList();
      }
      
      return null;
    } catch (e) {
      print('Error getting cached roles: $e');
      return null;
    }
  }

  // Cache leave requests
  Future<void> cacheLeaveRequests(String userId, List<Map<String, dynamic>> requests) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(LEAVE_REQUESTS_PREFIX + userId, json.encode(requests));
      print('Cached ${requests.length} leave requests for user $userId');
    } catch (e) {
      print('Error caching leave requests: $e');
    }
  }

  // Get cached leave requests
  Future<List<Map<String, dynamic>>?> getCachedLeaveRequests(String userId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final dataStr = prefs.getString(LEAVE_REQUESTS_PREFIX + userId);
      
      if (dataStr != null) {
        final List<dynamic> data = json.decode(dataStr);
        print('Retrieved ${data.length} leave requests from cache for user $userId');
        return data.map((item) => Map<String, dynamic>.from(item)).toList();
      }
      
      return null;
    } catch (e) {
      print('Error getting cached leave requests: $e');
      return null;
    }
  }

  // Cache attendance history
  Future<void> cacheAttendanceHistory(String userId, List<Map<String, dynamic>> history) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(ATTENDANCE_HISTORY_PREFIX + userId, json.encode(history));
      print('Cached ${history.length} attendance records for user $userId');
    } catch (e) {
      print('Error caching attendance history: $e');
    }
  }

  // Get cached attendance history
  Future<List<Map<String, dynamic>>?> getCachedAttendanceHistory(String userId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final dataStr = prefs.getString(ATTENDANCE_HISTORY_PREFIX + userId);
      
      if (dataStr != null) {
        final List<dynamic> data = json.decode(dataStr);
        print('Retrieved ${data.length} attendance records from cache for user $userId');
        return data.map((item) => Map<String, dynamic>.from(item)).toList();
      }
      
      return null;
    } catch (e) {
      print('Error getting cached attendance history: $e');
      return null;
    }
  }

  // Clear all cache
  Future<void> clearAllCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.clear();
      print('Cleared all cache');
    } catch (e) {
      print('Error clearing cache: $e');
    }
  }
}