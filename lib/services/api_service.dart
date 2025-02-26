// lib/services/api_service.dart
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:flutter/foundation.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  static const String baseUrl = 'http://195.158.75.66:3000/api';
  String? _token;

  // Login
  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      print('Attempting login with email: $email');
      
      final response = await http.post(
        Uri.parse('$baseUrl/login'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      print('Login response status: ${response.statusCode}');
      print('Raw response body: ${response.body}');

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = jsonDecode(response.body);
        print('Decoded data: $data');  // Let's see the full decoded data
        
        if (data.containsKey('token')) {
          _token = data['token'];
          print('Token extracted: $_token');
        } else {
          print('No token found in response data');
        }

        // Make sure we're returning the exact structure we receive
        return data;  // Return the raw data instead of restructuring it
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Login failed');
      }
    } catch (e) {
      print('Login error caught: $e');
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

      print('Token being sent: $_token');

      request.headers.addAll({
        'Authorization': 'Bearer $_token',
      });

      request.fields.addAll({
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
        throw Exception(errorBody['message'] ?? 'Failed to record attendance');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error recording attendance: $e');
    }
  }

  // Medical Certificate Upload
  Future<String?> uploadMedicalCertificate(File file) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Uploading medical certificate');
      print('File path: ${file.path}');
      
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/upload-medical-certificate'),
      );

      // Determine content type based on file extension
      String extension = file.path.split('.').last.toLowerCase();
      String? contentType;
      switch (extension) {
        case 'jpg':
        case 'jpeg':
          contentType = 'image/jpeg';
          break;
        case 'png':
          contentType = 'image/png';
          break;
        case 'pdf':
          contentType = 'application/pdf';
          break;
        default:
          throw Exception('Unsupported file type');
      }

      request.files.add(
        await http.MultipartFile.fromPath(
          'certificate',
          file.path,
          contentType: MediaType.parse(contentType!),
        ),
      );
      
      request.headers['Authorization'] = 'Bearer $_token';
      
      final response = await request.send();
      print('Upload Response Status: ${response.statusCode}');
      
      final responseBody = await response.stream.bytesToString();
      print('Upload Response Body: $responseBody');
      
      if (response.statusCode == 200) {
        final jsonData = json.decode(responseBody);
        return jsonData['fileUrl'];
      } else {
        throw Exception('Failed to upload medical certificate. Status: ${response.statusCode}, Body: $responseBody');
      }
    } catch (e) {
      print('Complete upload error details: $e');
      rethrow;
    }
}

  // Get Attendance Status
  Future<Map<String, dynamic>> getAttendanceStatus(int userId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/attendance/status/$userId'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return {
          'is_punched_in': data['lastPunchType'] == 'IN',
          'last_punch': data['lastPunchTime'] != null
              ? {
                  'date': data['lastPunchDate'],
                  'time': data['lastPunchTime'],
                  'photo_url': data['lastPhotoUrl'],
                }
              : null
        };
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to get attendance status');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error getting attendance status: $e');
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
        final data = jsonDecode(response.body);
        return {
          'full_name': '${data['name']} ${data['surname']}',
          'email': data['email'],
          'department': data['department'],
          'phone': data['phone'],
          'position': data['title'],
          'join_date': data['user_job_start'],
          'profile_photo': data['profile_photo']
        };
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to get user profile');
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      throw Exception('Error getting user profile: $e');
    }
  }

  // Get Leave Balance
  Future<Map<String, dynamic>> getLeaveBalance(String userId) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    final response = await http.get(
      Uri.parse('$baseUrl/leave-balance/$userId'),
      headers: {
        'Authorization': 'Bearer $_token',
      },
    );

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load leave balance');
    }
  }

  // Submit Leave Request
  Future<void> submitLeaveRequest({
  required String leaveType,
  required DateTime startDate,
  required DateTime endDate,
  required String reason,
  String? certificateUrl,
}) async {
  if (_token == null) {
    throw Exception('Not authenticated');
  }

  // Create dates with time set to midnight
  final cleanStartDate = DateTime(startDate.year, startDate.month, startDate.day);
  final cleanEndDate = DateTime(endDate.year, endDate.month, endDate.day);

  print('API Submit Leave Request:');
  print('Leave Type: $leaveType');
  print('Start Date (clean): $cleanStartDate');
  print('End Date (clean): $cleanEndDate');
  print('Reason: $reason');
  print('Certificate URL: $certificateUrl');

  final response = await http.post(
    Uri.parse('$baseUrl/leave'),
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $_token',
    },
    body: jsonEncode({
      'leave_type': leaveType,
      // Use formatted date string to remove time
      'start_date': '${cleanStartDate.year}-${cleanStartDate.month.toString().padLeft(2, '0')}-${cleanStartDate.day.toString().padLeft(2, '0')}',
      'end_date': '${cleanEndDate.year}-${cleanEndDate.month.toString().padLeft(2, '0')}-${cleanEndDate.day.toString().padLeft(2, '0')}',
      'reason': reason,
      'medical_certificate_url': certificateUrl,
    }),
  );

  print('Leave Request Response Status: ${response.statusCode}');
  print('Leave Request Response Body: ${response.body}');

  if (response.statusCode != 200) {
    final errorBody = jsonDecode(response.body);
    throw Exception(errorBody['message'] ?? 'Failed to submit leave request');
  }
}

  // Get Leave Requests
  Future<List<Map<String, dynamic>>> getLeaveRequests(String userId) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    final response = await http.get(
      Uri.parse('$baseUrl/leave-requests/$userId'),
      headers: {
        'Authorization': 'Bearer $_token',
      },
    );

    if (response.statusCode == 200) {
      final List<dynamic> data = json.decode(response.body);
      return data.cast<Map<String, dynamic>>();
    } else {
      throw Exception('Failed to load leave requests');
    }
  }
}