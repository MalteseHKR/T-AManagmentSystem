// lib/screens/admin/user_detail_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';
import '../../services/cache_service.dart';
import 'package:cached_network_image/cached_network_image.dart';

class UserDetailScreen extends StatefulWidget {
  final int userId;
  final Map<String, dynamic> userDetails;

  const UserDetailScreen({
    Key? key,
    required this.userId,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<UserDetailScreen> createState() => _UserDetailScreenState();
}

class _UserDetailScreenState extends State<UserDetailScreen> {
  final ApiService _apiService = ApiService();
  final SessionService _sessionService = SessionService();
  final CacheService _cacheService = CacheService();
  
  Map<String, dynamic>? _userData;
  List<Map<String, dynamic>> _leaveHistory = [];
  List<Map<String, dynamic>> _attendanceHistory = [];
  bool _isLoading = true;
  bool _isFaceRegistered = false;
  
  @override
  void initState() {
    super.initState();
    _loadUserData();
  }
  
  Future<void> _loadUserData({bool forceRefresh = false}) async {
    setState(() {
      _isLoading = true;
    });
    
    try {
      // Load all user data in parallel
      await Future.wait([
        _loadUserProfile(forceRefresh),
        _loadLeaveHistory(forceRefresh),
        _loadAttendanceHistory(forceRefresh),
        _checkFaceRegistration(),
      ]);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading user data: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
  
  Future<void> _loadUserProfile(bool forceRefresh) async {
    try {
      // Check cache first if not forcing refresh
      if (!forceRefresh) {
        final cachedProfile = await _cacheService.getCachedUserProfile(widget.userId);
        if (cachedProfile != null) {
          print('Loading user profile from cache for user ${widget.userId}');
          setState(() {
            _userData = cachedProfile;
          });
          return;
        }
      }
      
      // If not in cache or forcing refresh, load from API
      print('Loading user profile from API for user ${widget.userId}');
      final profile = await _apiService.getUserProfile(widget.userId);
      
      // Cache the result
      await _cacheService.cacheUserProfile(widget.userId, profile);
      
      if (mounted) {
        setState(() {
          _userData = profile;
        });
      }
    } catch (e) {
      print('Error loading user profile: $e');
      rethrow;
    }
  }
  
  Future<void> _loadLeaveHistory(bool forceRefresh) async {
    try {
      final userId = widget.userId.toString();
      
      // Check cache first if not forcing refresh
      if (!forceRefresh) {
        final cachedLeaveHistory = await _cacheService.getCachedLeaveRequests(userId);
        if (cachedLeaveHistory != null && cachedLeaveHistory.isNotEmpty) {
          print('Loading leave history from cache for user $userId');
          setState(() {
            _leaveHistory = cachedLeaveHistory;
          });
          return;
        }
      }
      
      // If not in cache or forcing refresh, load from API
      print('Loading leave history from API for user $userId');
      final history = await _apiService.getLeaveRequests(userId);
      
      // Cache the result
      await _cacheService.cacheLeaveRequests(userId, history);
      
      if (mounted) {
        setState(() {
          _leaveHistory = history;
        });
      }
    } catch (e) {
      print('Error loading leave history: $e');
      // Continue without leave history
    }
  }
  
  Future<void> _loadAttendanceHistory(bool forceRefresh) async {
    try {
      final userId = widget.userId.toString();
      
      // Check cache first if not forcing refresh
      if (!forceRefresh) {
        final cachedAttendanceHistory = await _cacheService.getCachedAttendanceHistory(userId);
        if (cachedAttendanceHistory != null && cachedAttendanceHistory.isNotEmpty) {
          print('Loading attendance history from cache for user $userId');
          
          // Limit to latest 10 records
          final latestRecords = cachedAttendanceHistory.length > 10 
              ? cachedAttendanceHistory.sublist(0, 10) 
              : cachedAttendanceHistory;
          
          setState(() {
            _attendanceHistory = latestRecords;
          });
          return;
        }
      }
      
      // If not in cache or forcing refresh, load from API
      print('Loading attendance history from API for user $userId');
      final history = await _apiService.getAttendanceHistory(userId);
      
      // Cache all records
      await _cacheService.cacheAttendanceHistory(userId, history);
      
      // Limit to latest 10 records for display
      final latestRecords = history.length > 10 ? history.sublist(0, 10) : history;
      
      if (mounted) {
        setState(() {
          _attendanceHistory = latestRecords;
        });
      }
    } catch (e) {
      print('Error loading attendance history: $e');
      // Continue without attendance history
    }
  }
  
  Future<void> _checkFaceRegistration() async {
    try {
      final result = await _apiService.checkFaceRegistration(widget.userId.toString());
      
      if (mounted) {
        setState(() {
          _isFaceRegistered = result['has_registered_face'] ?? false;
        });
      }
    } catch (e) {
      print('Error checking face registration: $e');
      // Continue without face registration status
    }
  }
  
  String _formatDate(String? dateStr) {
    if (dateStr == null) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      return DateFormat('MMM dd, yyyy').format(date);
    } catch (e) {
      return dateStr;
    }
  }
  
  String _formatDateTime(String? dateStr, String? timeStr) {
    if (dateStr == null || timeStr == null) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      final timeParts = timeStr.split(':');
      final time = TimeOfDay(
        hour: int.parse(timeParts[0]),
        minute: int.parse(timeParts[1]),
      );
      
      return '${DateFormat('MMM dd, yyyy').format(date)} at ${time.format(context)}';
    } catch (e) {
      return '$dateStr $timeStr';
    }
  }
  
  Widget _buildStatusChip(String? status) {
    Color chipColor;
    switch (status?.toLowerCase() ?? 'pending') {
      case 'approved':
        chipColor = Colors.green;
        break;
      case 'pending':
        chipColor = Colors.orange;
        break;
      case 'rejected':
        chipColor = Colors.red;
        break;
      default:
        chipColor = Colors.grey;
    }

    return Chip(
      label: Text(
        status?.toString() ?? 'Pending',
        style: const TextStyle(color: Colors.white),
      ),
      backgroundColor: chipColor,
    );
  }
  
  String _getProfilePhotoUrl(String? photoPath) {
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
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_userData != null 
            ? '${_userData!['full_name']}' 
            : 'User Details'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => _loadUserData(forceRefresh: true),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _userData == null
              ? const Center(child: Text('User not found'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // User Profile Card
                      Card(
                        margin: const EdgeInsets.only(bottom: 24),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Profile Photo or Avatar
                                  if (_userData!['profile_photo'] != null && _userData!['profile_photo'].toString().isNotEmpty)
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(50),
                                      child: CachedNetworkImage(
                                        imageUrl: _getProfilePhotoUrl(_userData!['profile_photo']),
                                        width: 100,
                                        height: 100,
                                        fit: BoxFit.cover,
                                        httpHeaders: {
                                          'Authorization': 'Bearer ${_apiService.token}',
                                        },
                                        placeholder: (context, url) => const Center(
                                          child: CircularProgressIndicator(strokeWidth: 2),
                                        ),
                                        errorWidget: (context, url, error) {
                                          print('Error loading profile photo: $error, URL: $url');
                                          // Fallback to avatar with initials
                                          return CircleAvatar(
                                            radius: 50,
                                            backgroundColor: Colors.blue.shade100,
                                            child: Text(
                                              _getInitials(_userData!['full_name']),
                                              style: const TextStyle(
                                                fontSize: 32,
                                                fontWeight: FontWeight.bold,
                                                color: Colors.blue,
                                              ),
                                            ),
                                          );
                                        },
                                      ),
                                    )
                                  else
                                    CircleAvatar(
                                      radius: 50,
                                      backgroundColor: Colors.blue.shade100,
                                      child: Text(
                                        _getInitials(_userData!['full_name']),
                                        style: const TextStyle(
                                          fontSize: 32,
                                          fontWeight: FontWeight.bold,
                                          color: Colors.blue,
                                        ),
                                      ),
                                    ),
                                  const SizedBox(width: 16),
                                  
                                  // User Info
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          _userData!['full_name'],
                                          style: const TextStyle(
                                            fontSize: 24,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          _userData!['email'],
                                          style: TextStyle(
                                            fontSize: 16,
                                            color: Colors.grey[700],
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          '${_userData!['department']} • ${_userData!['role']}',
                                          style: const TextStyle(
                                            fontSize: 16,
                                          ),
                                        ),
                                        const SizedBox(height: 8),
                                        Row(
                                          children: [
                                            Icon(
                                              _isFaceRegistered ? Icons.face : Icons.face_outlined,
                                              color: _isFaceRegistered ? Colors.green : Colors.red,
                                              size: 20,
                                            ),
                                            const SizedBox(width: 4),
                                            Text(
                                              _isFaceRegistered ? 'Face Registered' : 'No Face Registered',
                                              style: TextStyle(
                                                color: _isFaceRegistered ? Colors.green : Colors.red,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const Divider(height: 32),
                              
                              // Contact & Employment Information
                              Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text(
                                          'Phone',
                                          style: TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 16,
                                          ),
                                        ),
                                        Text(_userData!['phone'] ?? 'N/A'),
                                      ],
                                    ),
                                  ),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text(
                                          'Join Date',
                                          style: TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 16,
                                          ),
                                        ),
                                        Text(_formatDate(_userData!['join_date'])),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      
                      // Attendance History
                      _buildSection(
                        title: 'Recent Attendance',
                        child: _attendanceHistory.isEmpty 
                            ? const Text('No attendance records found')
                            : Column(
                                children: _attendanceHistory.map((record) {
                                  final isPunchIn = record['punch_type'] == 'IN';
                                  return ListTile(
                                    leading: Icon(
                                      isPunchIn ? Icons.login : Icons.logout,
                                      color: isPunchIn ? Colors.green : Colors.red,
                                    ),
                                    title: Text(
                                      isPunchIn ? 'Punch In' : 'Punch Out',
                                      style: TextStyle(
                                        color: isPunchIn ? Colors.green : Colors.red,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    subtitle: Text(
                                      _formatDateTime(record['punch_date'], record['punch_time']),
                                    ),
                                    trailing: record['photo_url'] != null && record['photo_url'].toString().isNotEmpty
                                        ? IconButton(
                                            icon: const Icon(Icons.photo),
                                            onPressed: () {
                                              _sessionService.userActivity();
                                              // Show photo in dialog
                                              showDialog(
                                                context: context,
                                                builder: (context) => Dialog(
                                                  child: Column(
                                                    mainAxisSize: MainAxisSize.min,
                                                    children: [
                                                      AppBar(
                                                        title: Text(
                                                          '${isPunchIn ? 'Punch In' : 'Punch Out'} Photo',
                                                        ),
                                                        automaticallyImplyLeading: false,
                                                        actions: [
                                                          IconButton(
                                                            icon: const Icon(Icons.close),
                                                            onPressed: () => Navigator.pop(context),
                                                          ),
                                                        ],
                                                      ),
                                                      Image.network(
                                                        'https://api.garrisonta.org${record['photo_url']}',
                                                        fit: BoxFit.contain,
                                                        height: 300,
                                                        loadingBuilder: (context, child, loadingProgress) {
                                                          if (loadingProgress == null) return child;
                                                          return Center(
                                                            child: CircularProgressIndicator(
                                                              value: loadingProgress.expectedTotalBytes != null
                                                                  ? loadingProgress.cumulativeBytesLoaded / 
                                                                      loadingProgress.expectedTotalBytes!
                                                                  : null,
                                                            ),
                                                          );
                                                        },
                                                        errorBuilder: (context, error, stackTrace) {
                                                          return const Padding(
                                                            padding: EdgeInsets.all(16),
                                                            child: Text('Error loading image'),
                                                          );
                                                        },
                                                      ),
                                                      const SizedBox(height: 16),
                                                    ],
                                                  ),
                                                ),
                                              );
                                            },
                                          )
                                        : null,
                                  );
                                }).toList(),
                              ),
                      ),
                      
                      const SizedBox(height: 24),
                      
                      // Leave History
                      _buildSection(
                        title: 'Leave Requests',
                        child: _leaveHistory.isEmpty 
                            ? const Text('No leave requests found')
                            : Column(
                                children: _leaveHistory.map((leave) {
                                  return Card(
                                    margin: const EdgeInsets.only(bottom: 8),
                                    child: Padding(
                                      padding: const EdgeInsets.all(12),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                            children: [
                                              Text(
                                                leave['leave_type'] ?? 'Unknown',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 16,
                                                ),
                                              ),
                                              _buildStatusChip(leave['status']),
                                            ],
                                          ),
                                          const SizedBox(height: 8),
                                          Text(
                                            'From ${_formatDate(leave['start_date'])} to ${_formatDate(leave['end_date'])}',
                                          ),
                                          if (leave['reason'] != null && leave['reason'].toString().isNotEmpty) ...[
                                            const SizedBox(height: 8),
                                            Text(
                                              'Reason: ${leave['reason']}',
                                              style: TextStyle(
                                                fontStyle: FontStyle.italic,
                                                color: Colors.grey[700],
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                  );
                                }).toList(),
                              ),
                      ),
                    ],
                  ),
                ),
    );
  }
  
  Widget _buildSection({required String title, required Widget child}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: child,
          ),
        ),
      ],
    );
  }

  String _getInitials(String fullName) {
    List<String> names = fullName.split(' ');
    String initials = '';
    for (var name in names) {
      if (name.isNotEmpty) {
        initials += name[0];
      }
    }
    return initials.toUpperCase();
  }
}