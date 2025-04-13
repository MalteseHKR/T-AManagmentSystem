// lib/services/api_service.dart
import 'dart:convert';
import 'dart:io';
import 'dart:math' as Math;
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:path_provider/path_provider.dart';
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

  // Get all employees
  Future<List<Map<String, dynamic>>> getAllEmployees() async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.get(
        Uri.parse('$baseUrl/admin/employees'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        throw Exception('Failed to load employees');
      }
    } catch (e) {
      print('Error loading employees: $e');
      rethrow;
    }
  }

  // Get all active users (for face registration)
  Future<List<Map<String, dynamic>>> getAllActiveUsers() async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.get(
        Uri.parse('$baseUrl/admin/active-users'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        throw Exception('Failed to load active users');
      }
    } catch (e) {
      print('Error loading active users: $e');
      rethrow;
    }
  }

  // Get all departments
  Future<List<Map<String, dynamic>>> getAllDepartments() async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.get(
        Uri.parse('$baseUrl/admin/departments'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        throw Exception('Failed to load departments');
      }
    } catch (e) {
      print('Error loading departments: $e');
      rethrow;
    }
  }

  // Get all roles
  Future<List<Map<String, dynamic>>> getAllRoles() async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.get(
        Uri.parse('$baseUrl/admin/roles'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        throw Exception('Failed to load roles');
      }
    } catch (e) {
      print('Error loading roles: $e');
      rethrow;
    }
  }

  // Get all leave requests (for HR/admin)
  Future<List<Map<String, dynamic>>> getAllLeaveRequests() async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.get(
        Uri.parse('$baseUrl/admin/leave-requests'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        throw Exception('Failed to load leave requests');
      }
    } catch (e) {
      print('Error loading leave requests: $e');
      rethrow;
    }
  }

  // Upload face photo for registration
  Future<Map<String, dynamic>> uploadFacePhoto(File photoFile, String userId) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Uploading face photo for user $userId');
      
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/register-face'),
      );

      request.headers.addAll(_authHeaders);
      
      request.fields.addAll({
        'user_id': userId,
      });

      request.files.add(await http.MultipartFile.fromPath(
        'face_photo', // Make sure this matches the field name expected by your server
        photoFile.path,
        contentType: MediaType('image', 'jpeg'),
      ));

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      print('Face photo upload response: ${response.statusCode} - ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to upload face photo');
      }
    } catch (e) {
      print('Error uploading face photo: $e');
      throw Exception('Error uploading face photo: $e');
    }
  }

  // Create a new user with password
  Future<Map<String, dynamic>> createUser(Map<String, dynamic> userData) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.post(
        Uri.parse('$baseUrl/admin/create-user'),
        headers: _headers,
        body: jsonEncode(userData),
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to create user');
      }
    } catch (e) {
      print('Error creating user: $e');
      rethrow;
    }
  }

  // Upload Profile Photo 
  Future<Map<String, dynamic>> uploadProfilePhoto(File photoFile, String userId) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Uploading profile photo for user $userId');
      
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/upload-profile-photo'),
      );

      request.headers.addAll(_authHeaders);
      
      // Add user_id to the request
      request.fields.addAll({
        'user_id': userId,
      });

      // Add the file with the field name the server expects
      request.files.add(await http.MultipartFile.fromPath(
        'profile_photo', // Changed from 'photo' to 'profile_photo'
        photoFile.path,
        contentType: MediaType('image', 'jpeg'),
      ));

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      print('Profile photo upload response: ${response.statusCode} - ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to upload profile photo');
      }
    } catch (e) {
      print('Error uploading profile photo: $e');
      throw Exception('Error uploading profile photo: $e');
    }
  }

  // Update user active status
  Future<void> updateUserStatus({
    required int userId,
    required bool active,
    required int adminId,
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.put(
        Uri.parse('$baseUrl/admin/update-user-status'),
        headers: _headers,
        body: jsonEncode({
          'user_id': userId,
          'active': active,
          'admin_id': adminId,
        }),
      );

      if (response.statusCode != 200) {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to update user status');
      }
    } catch (e) {
      print('Error updating user status: $e');
      rethrow;
    }
  }

  // Reset user password
  Future<String> resetUserPassword({
    required int userId,
    required int adminId,
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.post(
        Uri.parse('$baseUrl/admin/reset-password'),
        headers: _headers,
        body: jsonEncode({
          'user_id': userId,
          'admin_id': adminId,
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['password'];
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to reset password');
      }
    } catch (e) {
      print('Error resetting password: $e');
      rethrow;
    }
  }

  // Get all employees
  Future<List<Map<String, dynamic>>> getAllUsers() async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.get(
        Uri.parse('$baseUrl/admin/employees'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      } else {
        throw Exception('Failed to load employees');
      }
    } catch (e) {
      print('Error loading employees: $e');
      rethrow;
    }
  }

  // Update leave request status
  Future<void> updateLeaveRequestStatus({
    required int requestId,
    required String newStatus,
    required int adminId,
    String? reason,
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      final response = await http.put(
        Uri.parse('$baseUrl/admin/update-leave-status'),
        headers: _headers,
        body: jsonEncode({
          'request_id': requestId,
          'status': newStatus,
          'admin_id': adminId,
          'reason': reason,
        }),
      );

      if (response.statusCode != 200) {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to update leave status');
      }
    } catch (e) {
      print('Error updating leave status: $e');
      rethrow;
    }
  }

  // Helper method to get photo URL
  String getFacePhotoUrl(String photoPath) {
    if (photoPath.startsWith('/api/face-photo/')) {
      // Already in the correct format
      return 'http://195.158.75.66:3000$photoPath';
    } else if (photoPath.contains('_')) {
      // It's probably a filename like "UserName_UserSurname_PhotoNum_UserID.jpg"
      // Extract the user ID from the filename
      final parts = photoPath.split('_');
      if (parts.length >= 2) {
        final lastPart = parts.last;
        final userId = lastPart.split('.')[0]; // Remove extension
        return 'http://195.158.75.66:3000/api/face-photo/$userId/${photoPath.split('/').last}';
      }
    }
  
  // Default fallback - just append to base URL
  return 'http://195.158.75.66:3000$photoPath';
}

  // Get user face photos for model training
  Future<List<String>> getUserFacePhotos(String userId) async {
    if (token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Fetching face photos for user $userId');
      final response = await http.get(
        Uri.parse('$baseUrl/user-face-photos/$userId'),
        headers: _headers,
      );

      print('Face photos response: ${response.statusCode} - ${response.body}');

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final List<dynamic> photoUrls = data['photos'] ?? [];
        return photoUrls.cast<String>();
      } else {
        throw Exception('Failed to load user face photos');
      }
    } catch (e) {
      print('Error loading face photos: $e');
      rethrow;
    }
  }

  // Download a face photo by URL
  Future<File?> downloadFacePhoto(String photoUrl) async {
    if (token == null) {
      throw Exception('Not authenticated');
    }

    try {
      // Remove leading "/api" if already present to avoid duplication
      String correctedUrl = photoUrl.startsWith('/api')
          ? photoUrl.replaceFirst('/api', '')
          : photoUrl;

      final fullUrl = '$baseUrl$correctedUrl';

      print('Attempting to download face photo from URL: $fullUrl');
      print('Using authorization token: ${token?.substring(0, 10)}...');

      final response = await http.get(
        Uri.parse(fullUrl),
        headers: {
          'Authorization': 'Bearer $token',
        },
      );

      print('Face photo download response status: ${response.statusCode}');

      if (response.statusCode == 200) {
        final tempDir = await getTemporaryDirectory();
        final filename = correctedUrl.split('/').last;
        final file = File('${tempDir.path}/$filename');
        await file.writeAsBytes(response.bodyBytes);
        print('Successfully saved photo to: ${file.path}');
        return file;
      } else {
        print('Download failed with status ${response.statusCode}: ${response.body}');
        throw Exception('Failed to download face photo');
      }
    } catch (e) {
      print('Complete error details for face photo download: $e');
      return null;
    }
  }


  // Download all face photos for a user and return the files
  Future<List<File>> downloadAllUserFacePhotos(String userId) async {
    try {
      // Get photo URLs
      final photoUrls = await getUserFacePhotos(userId);
      
      if (photoUrls.isEmpty) {
        print('No face photos found for user $userId');
        return [];
      }
      
      // Download each photo
      final List<File> files = [];
      for (final url in photoUrls) {
        final file = await downloadFacePhoto(url);
        if (file != null) {
          files.add(file);
        }
      }
      
      print('Downloaded ${files.length} face photos for user $userId');
      return files;
    } catch (e) {
      print('Error downloading user face photos: $e');
      return [];
    }
  }

  // Check if user has registered face
  Future<Map<String, dynamic>> checkFaceRegistration(String userId) async {
    if (token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Checking face registration for user $userId');
      final response = await http.get(
        Uri.parse('$baseUrl/face-status/$userId'),
        headers: _headers,
      );

      print('Face registration check response: ${response.statusCode} - ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('Failed to check face registration');
      }
    } catch (e) {
      print('Error checking face registration: $e');
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
  
  Future<Map<String, String>> getUserProfilePhotos() async {
    if (token == null) {
      throw Exception('Not authenticated');
    }

    try {
      // This would be a new endpoint you'd need to add to your server
      final response = await http.get(
        Uri.parse('$baseUrl/user-profile-photos'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final Map<String, dynamic> photosMap = data['photos'] ?? {};
        return photosMap.map((key, value) => MapEntry(key, value.toString()));
      } else {
        throw Exception('Failed to load profile photos mapping');
      }
    } catch (e) {
      print('Error loading profile photos mapping: $e');
      return {}; // Return empty map on error
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