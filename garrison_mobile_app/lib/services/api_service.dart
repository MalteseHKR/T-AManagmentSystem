// lib/services/api_service.dart
import 'dart:convert';
import 'dart:io';
import 'dart:math' as Math;
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:path_provider/path_provider.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  static const String baseUrl = 'https://api.garrisonta.org/api';
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
  
  // Standardize certificate URL without relying on external helper
  String standardizeCertificateUrl(String? certificateUrl) {
    if (certificateUrl == null || certificateUrl.toString().trim().isEmpty) {
      return '';
    }
    
    String url = certificateUrl.toString().trim();
    
    // Already a full URL
    if (url.startsWith('http')) return url;
    
    // Handle various path formats - return just the path without the base URL
    if (url.startsWith('/uploads/')) {
      return url; // Already a path, return as is
    }
    
    // Just a filename - add the /uploads/certificates/ path prefix
    return '/uploads/certificates/$url';
  }

  // Get the full URL for viewing a certificate
  String getCertificateViewUrl(String certificatePath) {
    if (certificatePath.isEmpty) {
      return '';
    }
    
    // Make sure we're using a standardized path
    String path = standardizeCertificateUrl(certificatePath);
    
    // Add the base URL for viewing
    return 'https://api.garrisonta.org$path';
  }

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

  // Medical Certificate Upload - FIXED to save path only, not full URL
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
        final String? fileUrl = jsonData['fileUrl'];
        
        // FIXED: Return the path only, not the full URL
        if (fileUrl != null) {
          print('Received file URL: $fileUrl');
          // Ensure it's just the path
          String path = fileUrl;
          
          // If the server returns a full URL, extract just the path
          if (path.startsWith('http')) {
            // Try to extract just the path portion
            try {
              final uri = Uri.parse(path);
              path = uri.path;
              print('Extracted path from URL: $path');
            } catch (e) {
              print('Error parsing URL: $e');
              // If URI parsing fails, just strip off standard API URL prefix
              if (path.startsWith('https://api.garrisonta.org')) {
                path = path.replaceFirst('https://api.garrisonta.org', '');
                print('Stripped domain to get path: $path');
              }
            }
          }
          
          // Make sure path starts with /uploads/
          if (!path.startsWith('/uploads/')) {
            path = '/uploads/certificates/$path';
            print('Added prefix to path: $path');
          }
          
          return path;
        }
        return null;
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
        
        // Log certificate data for debugging
        for (var request in data) {
          print('Processing admin leave request ID: ${request['request_id']}');
          print('Original certificate data: ${request['medical_certificate']}');
        }
        
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
        
        // Process each user and fetch individual profile photos if needed
        List<Map<String, dynamic>> processedUsers = [];
        
        for (var user in data) {
          final userId = user['user_id'];
          Map<String, dynamic> userWithPhoto = {...user};
          
          // Check if profile photo is already included
          if (user['profile_photo'] == null || user['profile_photo'].toString().isEmpty) {
            try {
              // Get additional user info including profile photo
              final userProfile = await getUserProfile(userId);
              if (userProfile['profile_photo'] != null && userProfile['profile_photo'].toString().isNotEmpty) {
                userWithPhoto['profile_photo'] = userProfile['profile_photo'];
                print('Added profile photo for user ${user['name']}: ${userProfile['profile_photo']}');
              }
            } catch (e) {
              print('Error fetching user profile for ID $userId: $e');
              // Continue without profile photo
            }
          }
          
          processedUsers.add(userWithPhoto);
        }
        
        return processedUsers;
      } else {
        throw Exception('Failed to load employees');
      }
    } catch (e) {
      print('Error loading employees: $e');
      rethrow;
    }
  }

  // Submit preliminary sick leave request
  Future<Map<String, dynamic>> submitPreliminarySickLeave({
    required String reason,
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Submitting preliminary sick leave with reason: $reason');
      
      final response = await http.post(
        Uri.parse('$baseUrl/leave/preliminary-sick'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $_token',
        },
        body: jsonEncode({
          'reason': reason,
          'status': 'Pending Certificate',
        }),
      );

      print('Preliminary sick leave response status: ${response.statusCode}');
      print('Preliminary sick leave response body: ${response.body}');

      if (response.statusCode == 200 || response.statusCode == 201) {
        return jsonDecode(response.body);
      } else {
        // Parse error details when available
        final errorBody = jsonDecode(response.body);
        final errorMessage = errorBody['message'] ?? errorBody['error'] ?? 'Failed to submit preliminary sick leave request';
        final errorDetails = errorBody['details'];
        
        // Log detailed error information
        print('Error details: $errorDetails');
        
        throw Exception(errorMessage);
      }
    } catch (e) {
      print('Error submitting preliminary sick leave: $e');
      rethrow;
    }
  }

  // Complete sick leave request with dates and certificate
  Future<Map<String, dynamic>> completeSickLeaveRequest({
    required int requestId,
    required DateTime startDate,
    required DateTime endDate,
    required String? certificateUrl,
    required bool isFullDay,
    TimeOfDay? startTime,
    TimeOfDay? endTime,
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    // Create dates with time set to midnight
    final cleanStartDate = DateTime(startDate.year, startDate.month, startDate.day);
    final cleanEndDate = DateTime(endDate.year, endDate.month, endDate.day);

    // Format start and end times if provided
    String? formattedStartTime;
    String? formattedEndTime;
    
    if (!isFullDay && startTime != null && endTime != null) {
      formattedStartTime = '${startTime.hour.toString().padLeft(2, '0')}:${startTime.minute.toString().padLeft(2, '0')}';
      formattedEndTime = '${endTime.hour.toString().padLeft(2, '0')}:${endTime.minute.toString().padLeft(2, '0')}';
    }

    // Generate a standardized reason message for completed sick leave
    String updatedReason = "Completed sick leave with medical certificate provided.";

    print('Completing sick leave request:');
    print('Request ID: $requestId');
    print('Start Date: ${cleanStartDate.toString()}');
    print('End Date: ${cleanEndDate.toString()}');
    print('Certificate URL: $certificateUrl');
    print('Is Full Day: $isFullDay');
    print('Start Time: $formattedStartTime');
    print('End Time: $formattedEndTime');
    print('Updated Reason: $updatedReason');

    try {
      final response = await http.put(
        Uri.parse('$baseUrl/leave/complete-sick/$requestId'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $_token',
        },
        body: jsonEncode({
          'start_date': '${cleanStartDate.year}-${cleanStartDate.month.toString().padLeft(2, '0')}-${cleanStartDate.day.toString().padLeft(2, '0')}',
          'end_date': '${cleanEndDate.year}-${cleanEndDate.month.toString().padLeft(2, '0')}-${cleanEndDate.day.toString().padLeft(2, '0')}',
          // FIXED: Ensure certificate URL is just the path
          'medical_certificate': certificateUrl != null ? standardizeCertificateUrl(certificateUrl) : null,
          'status': 'Pending',
          'is_full_day': isFullDay,
          'start_time': formattedStartTime,
          'end_time': formattedEndTime,
          'reason': updatedReason,  // Add the updated reason
        }),
      );

      print('Complete sick leave response status: ${response.statusCode}');
      print('Complete sick leave response body: ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to complete sick leave request');
      }
    } catch (e) {
      print('Error completing sick leave request: $e');
      rethrow;
    }
  }

  // Get pending certificate requests
  Future<List<Map<String, dynamic>>> getPendingCertificateRequests(String userId) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Fetching pending certificate requests for user $userId');
      
      final response = await http.get(
        Uri.parse('$baseUrl/leave/pending-certificates/$userId'),
        headers: {
          'Authorization': 'Bearer $_token',
        },
      );

      print('Pending certificates response status: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        print('Pending certificate requests: ${data['pendingRequests']}');
        return (data['pendingRequests'] as List<dynamic>).cast<Map<String, dynamic>>();
      } else {
        // Parse error details when available
        final errorBody = jsonDecode(response.body);
        final errorMessage = errorBody['message'] ?? errorBody['error'] ?? 'Failed to fetch pending certificate requests';
        throw Exception(errorMessage);
      }
    } catch (e) {
      print('Error fetching pending certificate requests: $e');
      rethrow;
    }
  }

  // Update an existing leave request
  Future<Map<String, dynamic>> updateLeaveRequest({
    required int requestId,
    required String leaveType,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
    required bool isFullDay,
    TimeOfDay? startTime,
    TimeOfDay? endTime,
    String? certificateUrl, // Add this parameter
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    // Create dates with time set to midnight
    final cleanStartDate = DateTime(startDate.year, startDate.month, startDate.day);
    final cleanEndDate = DateTime(endDate.year, endDate.month, endDate.day);
    
    // Format start and end times if provided
    String? formattedStartTime;
    String? formattedEndTime;
    
    if (!isFullDay && startTime != null && endTime != null) {
      formattedStartTime = '${startTime.hour.toString().padLeft(2, '0')}:${startTime.minute.toString().padLeft(2, '0')}';
      formattedEndTime = '${endTime.hour.toString().padLeft(2, '0')}:${endTime.minute.toString().padLeft(2, '0')}';
    }

    // Standardize certificate URL if provided
    String? standardizedCertificateUrl = certificateUrl != null ? 
                                      standardizeCertificateUrl(certificateUrl) : 
                                      null;

    try {
      final response = await http.put(
        Uri.parse('$baseUrl/leave/$requestId'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $_token',
        },
        body: jsonEncode({
          'leave_type': leaveType,
          'start_date': '${cleanStartDate.year}-${cleanStartDate.month.toString().padLeft(2, '0')}-${cleanStartDate.day.toString().padLeft(2, '0')}',
          'end_date': '${cleanEndDate.year}-${cleanEndDate.month.toString().padLeft(2, '0')}-${cleanEndDate.day.toString().padLeft(2, '0')}',
          'reason': reason,
          'is_full_day': isFullDay,
          'start_time': formattedStartTime,
          'end_time': formattedEndTime,
          'medical_certificate': standardizedCertificateUrl, // Add this line
        }),
      );

      print('Update leave request response status: ${response.statusCode}');
      print('Update leave request response body: ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to update leave request');
      }
    } catch (e) {
      print('Error updating leave request: $e');
      rethrow;
    }
  }

  // Updated method to handle leave request cancellation
  Future<Map<String, dynamic>> cancelLeaveRequest(int requestId) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    try {
      print('Cancelling leave request with ID: $requestId');
      
      final response = await http.put(
        Uri.parse('$baseUrl/leave/cancel/$requestId'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $_token',
        },
        body: jsonEncode({
          'status': 'cancelled',
        }),
      );

      print('Cancel leave request response status: ${response.statusCode}');
      print('Cancel leave request response body: ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final errorBody = jsonDecode(response.body);
        throw Exception(errorBody['message'] ?? 'Failed to cancel leave request');
      }
    } catch (e) {
      print('Error cancelling leave request: $e');
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
      return 'https://api.garrisonta.org$photoPath';
    } else if (photoPath.contains('_')) {
      // It's probably a filename like "UserName_UserSurname_PhotoNum_UserID.jpg"
      // Extract the user ID from the filename
      final parts = photoPath.split('_');
      if (parts.length >= 2) {
        final lastPart = parts.last;
        final userId = lastPart.split('.')[0]; // Remove extension
        return 'https://api.garrisonta.org/api/face-photo/$userId/${photoPath.split('/').last}';
      }
    }

    // Default fallback - just append to base URL
    return 'https://api.garrisonta.org$photoPath';
  }

  // Updated method to check if a leave request is editable based on status and dates
  bool isLeaveRequestEditable(Map<String, dynamic> request) {
    final status = request['status']?.toString().toLowerCase() ?? '';
    
    // Pending certificate requests are managed separately
    if (status == 'pending certificate') {
      return false;
    }
    
    // Check if the leave dates are in the future
    final startDate = DateTime.parse(request['start_date']?.toString() ?? DateTime.now().toString());
    final today = DateTime.now();
    final isPastDate = startDate.isBefore(DateTime(today.year, today.month, today.day));
    
    // Rule 1: Pending requests are always editable
    if (status == 'pending') {
      return true;
    }
    
    // Rule 2: Approved requests that haven't started yet are editable
    if (status == 'approved' && !isPastDate) {
      return true;
    }
    
    // All other cases are not editable
    return false;
  }

  // Updated method to check if a leave request is cancelable
  bool isLeaveRequestCancelable(Map<String, dynamic> request) {
    final status = request['status']?.toString().toLowerCase() ?? '';
    
    // Check if the leave dates are in the future
    final startDate = DateTime.parse(request['start_date']?.toString() ?? DateTime.now().toString());
    final today = DateTime.now();
    final isPastDate = startDate.isBefore(DateTime(today.year, today.month, today.day));
    
    // Rule: Pending or approved requests that haven't started yet can be cancelled
    if ((status == 'pending' || status == 'approved') && !isPastDate) {
      return true;
    }
    
    return false;
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

  // Check if user has face registered for face recognition
  Future<bool> userHasRegisteredFacePhotos(String userId) async {
    if (token == null) {
      print('No authentication token available for checking face photos');
      return false;
    }

    try {
      print('Checking if user $userId has registered face photos');
      
      // Use the direct face-status endpoint instead of getUserFacePhotos
      final response = await http.get(
        Uri.parse('$baseUrl/face-status/$userId'),
        headers: _headers,
      );
      
      print('Face status response: ${response.statusCode} - ${response.body}');
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final hasRegisteredFace = data['has_registered_face'] ?? false;
        
        print('User $userId has registered face: $hasRegisteredFace (photo count: ${data['photo_count'] ?? 0})');
        return hasRegisteredFace;
      } else {
        throw Exception('Failed to check face registration status');
      }
    } catch (e) {
      print('Error checking face photos: $e');
      return false; // Assume no registration on error
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
    
    if (response.statusCode == 200) {
      final List<dynamic> data = json.decode(response.body);
      
      // Enhanced debug logging for certificate data
      for (var request in data) {
        print('Processing leave request ID: ${request['request_id']}');
        print('Original certificate data: ${request['medical_certificate']}');
      }
      
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
  
  // Submit Leave Request with adjusted dates
  Future<void> submitLeaveRequest({
    required String leaveType,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
    String? certificateUrl,
    bool isFullDay = true,
    TimeOfDay? startTime,
    TimeOfDay? endTime,
  }) async {
    if (_token == null) {
      throw Exception('Not authenticated');
    }

    // Create dates with time set to midnight
    final cleanStartDate = DateTime(startDate.year, startDate.month, startDate.day);
    final cleanEndDate = DateTime(endDate.year, endDate.month, endDate.day);
    
    // Format start and end times if provided
    String? formattedStartTime;
    String? formattedEndTime;
    
    if (!isFullDay && startTime != null && endTime != null) {
      formattedStartTime = '${startTime.hour.toString().padLeft(2, '0')}:${startTime.minute.toString().padLeft(2, '0')}';
      formattedEndTime = '${endTime.hour.toString().padLeft(2, '0')}:${endTime.minute.toString().padLeft(2, '0')}';
    }

    // FIXED: Standardize certificate path format if present
    String? standardizedCertificateUrl = certificateUrl != null ? 
                                        standardizeCertificateUrl(certificateUrl) : 
                                        null;
    
    print('API Submit Leave Request:');
    print('Leave Type: $leaveType');
    print('Start Date: $cleanStartDate');
    print('End Date: $cleanEndDate');
    print('Is Full Day: $isFullDay');
    print('Start Time: $formattedStartTime');
    print('End Time: $formattedEndTime');
    print('Reason: $reason');
    print('Original Certificate URL: $certificateUrl');
    print('Standardized Certificate URL: $standardizedCertificateUrl');

    final response = await http.post(
      Uri.parse('$baseUrl/leave'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $_token',
      },
      body: jsonEncode({
        'leave_type': leaveType,
        'start_date': '${cleanStartDate.year}-${cleanStartDate.month.toString().padLeft(2, '0')}-${cleanStartDate.day.toString().padLeft(2, '0')}',
        'end_date': '${cleanEndDate.year}-${cleanEndDate.month.toString().padLeft(2, '0')}-${cleanEndDate.day.toString().padLeft(2, '0')}',
        'reason': reason,
        'medical_certificate': standardizedCertificateUrl,  // Use standardized path
        'is_full_day': isFullDay,
        'start_time': formattedStartTime,
        'end_time': formattedEndTime,
      }),
    );

    print('Leave Request Response Status: ${response.statusCode}');
    print('Leave Request Response Body: ${response.body}');

    if (response.statusCode != 200) {
      final errorBody = jsonDecode(response.body);
      throw Exception(errorBody['message'] ?? 'Failed to submit leave request');
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

  // Helper method to properly format profile photo URLs
  String getProfilePhotoUrl(String? photoPath) {
    if (photoPath == null || photoPath.isEmpty) {
      return '';
    }
    
    // Full URL already
    if (photoPath.startsWith('http://') || photoPath.startsWith('https://')) {
      return photoPath;
    }
    
    // Relative path starting with /profile-pictures
    if (photoPath.startsWith('/profile-pictures/')) {
      return 'https://api.garrisonta.org$photoPath';
    }
    
    // Just the filename
    return 'https://api.garrisonta.org/profile-pictures/$photoPath';
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
}