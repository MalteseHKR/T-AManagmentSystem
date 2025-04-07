// lib/services/api_service.dart
import 'dart:convert';
import 'dart:io';
import 'dart:math' as Math;
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'timezone_service.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  static const String baseUrl = 'http://195.158.75.66:3000/api';
  String? _token;

  String? get token => _token;

  // New method to properly set token
  void setToken(String? newToken) {
    if (newToken != null) {
      // Trim whitespace and ensure clean token format
      _token = newToken.trim();
      print('Token set: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
      // Add a clear debug message showing the token
      print('COMPLETE TOKEN: $_token');
    } else {
      _token = null;
      print('Token cleared');
    }
  }

  
  // Add timezone service
  final TimezoneService _timezoneService = TimezoneService();

  // Login with improved error handling for attempts and lockout
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

      // Success case
      if (response.statusCode == 200) {
        final Map<String, dynamic> data = jsonDecode(response.body);
        print('Decoded data: $data');
        
        if (data.containsKey('token')) {
          setToken(data['token']);
          print('Auth header value: Bearer $_token');
        } else {
          print('No token found in response data');
          setToken(null);
        }
        
        // Check if MFA is enabled for the user
        final bool mfaEnabled = data['user']?['mfa_enabled'] ?? false;
        if (mfaEnabled) {
          // In a real implementation, you would handle the MFA flow here
          // For now, we'll just pass this information to the caller
          data['mfa_required'] = true;
        }

        return data;
      } 
      // Handle different error cases
      else {
        final errorBody = jsonDecode(response.body);
        
        // Handle lockout (429 status)
        if (response.statusCode == 429) {
          final int lockoutRemaining = errorBody['lockout_remaining'] ?? 5;
          throw Exception('429: Account locked. lockout_remaining: $lockoutRemaining');
        }
        // Handle invalid credentials with remaining attempts
        else if (response.statusCode == 401 && errorBody.containsKey('remaining_attempts')) {
          final int remainingAttempts = errorBody['remaining_attempts'];
          throw Exception('401: Incorrect email or password. remaining_attempts: $remainingAttempts');
        }
        // Generic error case
        else {
          throw Exception(errorBody['message'] ?? 'Login failed');
        }
      }
    } on SocketException {
      throw Exception('Network error: Unable to connect to the server');
    } catch (e) {
      print('Login error caught: $e');
      rethrow; // Rethrow to preserve error message with attempt info
    }
  }

  // Get auth headers
  Map<String, String> get _headers {
    if (_token == null || _token!.isEmpty) {
      print('WARNING: Trying to generate headers with null or empty token');
      return {'Content-Type': 'application/json'};
    }
    
    print('Generating headers with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
    return {
      'Authorization': 'Bearer $_token',
      'Content-Type': 'application/json',
    };
  }

  // For multipart requests, add this helper method
  Map<String, String> get _authHeaders {
    if (_token == null) {
      print('WARNING: Trying to generate auth headers with null token');
      return {};
    }
    
    print('Generating auth headers with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
    return {
      'Authorization': 'Bearer $_token',
    };
  }

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

      // Let the server handle the timestamp - don't send any time information
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
      // Add token validation
      if (_token == null || _token!.isEmpty) {
        throw Exception('No token provided');
      }
      
      print('Calling getAttendanceStatus with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
      
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
      if (_token == null || _token!.isEmpty) {
        throw Exception('No token provided');
      }
      
      print('Calling getUserProfile with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
      
      final response = await http.get(
        Uri.parse('$baseUrl/user/$userId'),
        headers: _headers,
      );

      print('getUserProfile response status: ${response.statusCode}');
      print('getUserProfile response body: ${response.body}');

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        // Process the profile photo path - the path may need adjusting based on server configuration
        String? profilePhotoPath = data['profile_photo'];
        if (profilePhotoPath != null && profilePhotoPath.isNotEmpty) {
          // If the path is a full server path, make it relative
          if (profilePhotoPath.startsWith('/home/softwaredev/profile_pictures/')) {
            profilePhotoPath = '/profile_pictures/${profilePhotoPath.split('/').last}';
          }
        }
        
        return {
          'full_name': '${data['name']} ${data['surname']}',
          'email': data['email'],
          'department': data['department'],
          'department_id': data['department_id'],
          'role': data['role'],
          'role_id': data['role_id'],
          'phone': data['phone'],
          'position': data['role'],
          'join_date': data['user_job_start'],
          'profile_photo': profilePhotoPath
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
    // Add token validation
    if (_token == null || _token!.isEmpty) {
      throw Exception('No token provided');
    }
    
    print('Calling getLeaveBalance with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
    
    // Use the correct endpoint path
    final response = await http.get(
      Uri.parse('$baseUrl/leave-balance/$userId'),
      headers: _headers,
    );

    // Print response for debugging
    print('getLeaveBalance response status: ${response.statusCode}');
    print('getLeaveBalance response body: ${response.body}');

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load leave balance');
    }
  }

  // Submit Leave Request with adjusted dates
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
    print('Start Date (with timezone offset): $cleanStartDate');
    print('End Date (with timezone offset): $cleanEndDate');
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
    // Add token validation
    if (_token == null || _token!.isEmpty) {
      throw Exception('No token provided');
    }
    
    print('Calling getLeaveRequests with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
    
    // Use the correct endpoint path
    final response = await http.get(
      Uri.parse('$baseUrl/leave-requests/$userId'),
      headers: _headers,
    );

    // Print response for debugging
    print('getLeaveRequests response status: ${response.statusCode}');
    print('getLeaveRequests response body: ${response.body}');

    if (response.statusCode == 200) {
      final List<dynamic> data = json.decode(response.body);
      return data.cast<Map<String, dynamic>>();
    } else {
      throw Exception('Failed to load leave requests');
    }
  }
  
  // Get Attendance History
  Future<List<Map<String, dynamic>>> getAttendanceHistory(String userId) async {
    // Add token validation
    if (_token == null || _token!.isEmpty) {
      throw Exception('No token provided');
    }
    
    print('Calling getAttendanceHistory with token: ${_token?.substring(0, Math.min(20, _token?.length ?? 0))}...');
    
    final response = await http.get(
      Uri.parse('$baseUrl/attendance-history/$userId'), // FIXED: Changed from attendance/status to attendance-history endpoint
      headers: _headers,
    );

    if (response.statusCode == 200) {
      final List<dynamic> data = json.decode(response.body);
      return data.cast<Map<String, dynamic>>();
    } else {
      throw Exception('Failed to load attendance history');
    }
  }
}