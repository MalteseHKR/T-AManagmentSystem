// lib/services/api_service.dart
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

class ApiService {
  static const String baseUrl = 'http://195.158.75.66:3000/api';
  String? _token;

  // Login
  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/login'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _token = data['token'];
        return data;
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Login failed with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Login error: $e');
    }
  }

  // Get auth headers
  Map<String, String> get _headers => {
    'Authorization': 'Bearer $_token',
    'Content-Type': 'application/json',
  };

  // Record Attendance with photo
  Future<Map<String, dynamic>> recordAttendance({
    required int userId,
    required String punchType,
    required File photoFile,
    required double latitude,
    required double longitude,
  }) async {
    try {
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/attendance'),
      );

      request.headers.addAll({
        'Authorization': 'Bearer $_token',
      });

      request.fields.addAll({
        'user_id': userId.toString(),
        'punch_type': punchType,
        'latitude': latitude.toString(),
        'longitude': longitude.toString(),
      });

      request.files.add(await http.MultipartFile.fromPath(
        'photo',
        photoFile.path,
        contentType: MediaType('image', 'jpeg'),
      ));

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to record attendance with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error recording attendance: $e');
    }
  }

  Future<Map<String, dynamic>> getAttendanceStatus(int userId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/attendance/status/$userId'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to get attendance status with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error getting attendance status: $e');
    }
  }

  // Submit Leave Request
  Future<void> submitLeaveRequest({
    required int userId,
    required String leaveType,
    required DateTime startDate,
    required DateTime endDate,
    String? reason,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/leave'),
        headers: _headers,
        body: jsonEncode({
          'user_id': userId,
          'leave_type': leaveType,
          'start_date': startDate.toIso8601String(),
          'end_date': endDate.toIso8601String(),
          'reason': reason,
        }),
      );

      if (response.statusCode != 200) {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to submit leave request with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error submitting leave request: $e');
    }
  }

  // Get Leave Balance
  Future<Map<String, dynamic>> getLeaveBalance(int userId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/leave-balance/$userId'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to get leave balance with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error getting leave balance: $e');
    }
  }

  // Get User Profile
  Future<Map<String, dynamic>> getUserProfile(int userId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/user/$userId'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to get user profile with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error getting user profile: $e');
    }
  }

  // Get Leave History
  Future<List<Map<String, dynamic>>> getLeaveRequests(int userId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/leave-requests/$userId'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to load leave requests with status ${response.statusCode}');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error loading leave requests: $e');
    }
  }
}