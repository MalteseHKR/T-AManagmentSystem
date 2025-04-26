// lib/screens/profile_screen.dart
import 'dart:math' as Math;

import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/cache_service.dart';
import 'package:intl/intl.dart';

class ProfileScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;
  final VoidCallback onLogout;

  const ProfileScreen({
    Key? key,
    required this.userDetails,
    required this.onLogout,
  }) : super(key: key);

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _apiService = ApiService();
  final _cacheService = CacheService();
  Map<String, dynamic>? _profileData;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile({bool forceRefresh = false}) async {
    setState(() {
      _isLoading = true;
    });

    try {
      // Add token validation and logging
      if (_apiService.token == null) {
        print('No token available in profile screen');
        throw Exception('Token not available');
      }
      
      print('Profile screen using token: ${_apiService.token?.substring(0, Math.min(20, _apiService.token?.length ?? 0))}...');
      
      // Check if user profile is already in cache
      if (!forceRefresh) {
        final cachedProfile = await _cacheService.getCachedUserProfile(widget.userDetails['id']);
        if (cachedProfile != null) {
          print('Loading profile from cache for user ID: ${widget.userDetails['id']}');
          setState(() {
            _profileData = cachedProfile;
            _isLoading = false;
          });
          return;
        }
      }
      
      // If not in cache or forcing refresh, load from API
      print('Loading profile from API for user ID: ${widget.userDetails['id']}');
      final profile = await _apiService.getUserProfile(widget.userDetails['id']);
      
      // Cache the result
      await _cacheService.cacheUserProfile(widget.userDetails['id'], profile);
      
      setState(() {
        _profileData = profile;
      });
    } catch (e) {
      print('Complete profile loading error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error loading profile: $e'),
          ),
        );
      }
    } finally {
      setState(() {
        _isLoading = false;
      });
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

  void _confirmLogout() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm Logout'),
        content: const Text('Are you sure you want to logout?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(context);
              // Clear cache when logging out
              _cacheService.clearAllCache();
              widget.onLogout();
            },
            style: FilledButton.styleFrom(
              backgroundColor: Colors.red,
            ),
            child: const Text('Logout'),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileHeader() {
    if (_profileData == null) return const SizedBox.shrink();

    // Get the correct URL for the profile photo
    String? profilePhotoUrl;
    if (_profileData!['profile_photo'] != null && _profileData!['profile_photo'].toString().isNotEmpty) {
      final String photoPath = _profileData!['profile_photo']!;
      
      // Handle different profile photo URL formats
      if (photoPath.startsWith('http://') || photoPath.startsWith('https://')) {
        profilePhotoUrl = photoPath;
      } else if (photoPath.startsWith('/profile-pictures/')) {
        profilePhotoUrl = 'https://api.garrisonta.org$photoPath';
      } else {
        profilePhotoUrl = 'https://api.garrisonta.org/profile-pictures/$photoPath';
      }
    }

    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            if (profilePhotoUrl != null && profilePhotoUrl.isNotEmpty)
              CircleAvatar(
                radius: 50,
                backgroundColor: Colors.grey[200],
                child: ClipOval(
                  child: SizedBox(
                    width: 100,
                    height: 100,
                    child: Image.network(
                      profilePhotoUrl,
                      fit: BoxFit.cover,
                      headers: {
                        'Authorization': 'Bearer ${_apiService.token}'
                      },
                      errorBuilder: (context, error, stackTrace) {
                        print('Error loading profile image: $error');
                        // Fallback to initials avatar if image fails to load
                        return Container(
                          color: Colors.blue.shade100,
                          child: Center(
                            child: Text(
                              _getInitials(_profileData!['full_name']),
                              style: const TextStyle(
                                fontSize: 32,
                                fontWeight: FontWeight.bold,
                                color: Colors.blue,
                              ),
                            ),
                          ),
                        );
                      },
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
                    ),
                  ),
                ),
              )
            else
              CircleAvatar(
                radius: 50,
                backgroundColor: Colors.blue.shade100,
                child: Text(
                  _getInitials(_profileData!['full_name']),
                  style: const TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: Colors.blue,
                  ),
                ),
              ),
            const SizedBox(height: 16),
            Text(
              _profileData!['full_name'],
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _profileData!['email'],
              style: TextStyle(
                fontSize: 16,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 8),
            // Display department and role below the name
            Text(
              '${_profileData!['department']} • ${_profileData!['role']}',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey[700],
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPersonalInfo() {
    if (_profileData == null) return const SizedBox.shrink();

    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              'Personal Information',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.phone),
            title: const Text('Phone'),
            subtitle: Text(_profileData!['phone'] ?? 'N/A'),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.email_outlined),
            title: const Text('Email'),
            subtitle: Text(_profileData!['email']),
          ),
        ],
      ),
    );
  }

  Widget _buildWorkInfo() {
    if (_profileData == null) return const SizedBox.shrink();

    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              'Work Information',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.business),
            title: const Text('Department'),
            subtitle: Text(_profileData!['department'] ?? 'N/A'),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.badge),
            title: const Text('Role'),
            subtitle: Text(_profileData!['role'] ?? 'N/A'),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.calendar_today),
            title: const Text('Join Date'),
            subtitle: Text(_formatDate(_profileData!['join_date'])),
          ),
        ],
      ),
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => _loadProfile(forceRefresh: true),
            tooltip: 'Refresh',
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _confirmLogout,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _profileData == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text('Error loading profile'),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () => _loadProfile(forceRefresh: true),
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () => _loadProfile(forceRefresh: true),
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        _buildProfileHeader(),
                        const SizedBox(height: 24),
                        _buildPersonalInfo(),
                        const SizedBox(height: 24),
                        _buildWorkInfo(),
                      ],
                    ),
                  ),
                ),
    );
  }
}