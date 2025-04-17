// lib/screens/admin/user_management_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';
import 'user_detail_screen.dart';
import 'create_user_screen.dart';
import 'package:cached_network_image/cached_network_image.dart';

// Extension to capitalize strings
extension StringExtension on String {
  String capitalize() {
    return "${this[0].toUpperCase()}${this.substring(1)}";
  }
}

class UserManagementScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;

  const UserManagementScreen({
    Key? key,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<UserManagementScreen> createState() => _UserManagementScreenState();
}

class _UserManagementScreenState extends State<UserManagementScreen> {
  // Helper method to get initials from a name
  String _getInitials(String fullName) {
    List<String> names = fullName.split(" ");
    String initials = "";
    for (var name in names) {
      if (name.isNotEmpty) {
        initials += name[0];
      }
    }
    return initials.toUpperCase();
  }

  // Add helper method from UserDetailScreen
  String _buildProfilePhotoUrl(String photoPath) {
    if (photoPath.isEmpty) {
      return '';
    }
    
    // If it's already a full URL, return it
    if (photoPath.startsWith('http://') || photoPath.startsWith('https://')) {
      return photoPath;
    }
    
    // If it's just a filename without path
    if (!photoPath.contains('/')) {
      return 'http://195.158.75.66:3000/profile-pictures/$photoPath';
    }
    
    // If it has a path but doesn't start with /profile-pictures
    if (!photoPath.startsWith('/profile-pictures')) {
      // Extract the filename from the path
      final filename = photoPath.split('/').last;
      return 'http://195.158.75.66:3000/profile-pictures/$filename';
    }
    
    // If it starts with /profile-pictures, just add the base URL
    return 'http://195.158.75.66:3000$photoPath';
  }

  Widget _buildUserAvatar(Map<String, dynamic> user, bool isActive) {
    final String fullName = '${user['name']} ${user['surname']}';
    final String initials = _getInitials(fullName);
    final String? profilePhoto = user['profile_photo'];
    String? imageUrl;

    // Use the same method that works in UserDetailScreen
    if (profilePhoto != null && profilePhoto.isNotEmpty) {
      imageUrl = _buildProfilePhotoUrl(profilePhoto);
      print('Avatar image URL for ${user['name']}: $imageUrl'); // Debug log
    }

    return CircleAvatar(
      radius: 20,
      backgroundColor: isActive ? Colors.blue.shade100 : Colors.grey.shade300,
      child: ClipOval(
        child: SizedBox(
          width: 40,
          height: 40,
          child: imageUrl != null && imageUrl.isNotEmpty
              ? CachedNetworkImage(
                  imageUrl: imageUrl,
                  fit: BoxFit.cover,
                  // No auth headers needed for profile photos
                  placeholder: (context, url) => Center(
                    child: Text(
                      initials,
                      style: TextStyle(
                        color: isActive ? Colors.blue : Colors.grey.shade700,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  errorWidget: (context, url, error) {
                    print('Error loading avatar: $error, URL: $imageUrl');
                    return Center(
                      child: Text(
                        initials,
                        style: TextStyle(
                          color: isActive ? Colors.blue : Colors.grey.shade700,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    );
                  },
                )
              : Center(
                  child: Text(
                    initials,
                    style: TextStyle(
                      color: isActive ? Colors.blue : Colors.grey.shade700,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
        ),
      ),
    );
  }

  final ApiService _apiService = ApiService();
  final SessionService _sessionService = SessionService();
  
  List<Map<String, dynamic>> _allUsers = [];
  List<Map<String, dynamic>> _departments = [];
  List<Map<String, dynamic>> _roles = [];
  bool _isLoading = true;
  String _searchQuery = '';
  
  // Filters
  String? _selectedDepartment;
  bool _showInactiveUsers = false;
  
  final _searchController = TextEditingController();
  
  @override
  void initState() {
    super.initState();
    _loadData();
  }
  
  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }
  
  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
    });
    
    try {
      // Load all lookup data in parallel
      await Future.wait([
        _loadUsers(),
        _loadDepartments(),
        _loadRoles(),
      ]);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading data: $e')),
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
  
  Future<void> _loadUsers() async {
    try {
      // This would need a new API endpoint to get all users with their department and role info
      final response = await _apiService.getAllUsers();
      
      setState(() {
        _allUsers = response;
      });
      
      // Debug: Check if profile photos are present
      int photosFound = 0;
      for (var user in _allUsers) {
        if (user['profile_photo'] != null && user['profile_photo'].toString().isNotEmpty) {
          photosFound++;
          print('User ${user['name']} has profile photo: ${user['profile_photo']}');
        }
      }
      print('Found $photosFound users with profile photos out of ${_allUsers.length} total users');
    } catch (e) {
      print('Error loading users: $e');
      rethrow;
    }
  }
  
  Future<void> _loadDepartments() async {
    try {
      // This would need a new API endpoint to get all departments
      final response = await _apiService.getAllDepartments();
      
      setState(() {
        _departments = response;
      });
    } catch (e) {
      print('Error loading departments: $e');
      // Show error but continue loading other data
    }
  }
  
  Future<void> _loadRoles() async {
    try {
      // This would need a new API endpoint to get all roles
      final response = await _apiService.getAllRoles();
      
      setState(() {
        _roles = response;
      });
    } catch (e) {
      print('Error loading roles: $e');
      // Show error but continue loading other data
    }
  }
  
  Future<void> _toggleUserStatus(int userId, bool currentActive) async {
    _sessionService.userActivity();
    
    final bool newStatus = !currentActive;
    final String action = newStatus ? 'activate' : 'deactivate';
    
    // Show confirmation dialog
    final bool? confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('${action.capitalize()} User'),
        content: Text('Are you sure you want to $action this user?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: newStatus ? Colors.green : Colors.red,
            ),
            child: Text(action.capitalize()),
          ),
        ],
      ),
    );
    
    if (confirm != true) return;
    
    setState(() {
      _isLoading = true;
    });
    
    try {
      await _apiService.updateUserStatus(
        userId: userId,
        active: newStatus,
        adminId: widget.userDetails['id'],
      );
      
      // Update local list
      setState(() {
        for (var user in _allUsers) {
          if (user['user_id'] == userId) {
            user['active'] = newStatus;
            break;
          }
        }
      });
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('User ${newStatus ? 'activated' : 'deactivated'} successfully'),
            backgroundColor: newStatus ? Colors.green : Colors.orange,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error updating user status: $e'),
            backgroundColor: Colors.red,
          ),
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
  
  // Apply filters to user list
  List<Map<String, dynamic>> _getFilteredUsers() {
    return _allUsers.where((user) {
      // Apply search filter
      final String fullName = '${user['name']} ${user['surname']}'.toLowerCase();
      final String email = (user['email'] ?? '').toLowerCase();
      final String searchLower = _searchQuery.toLowerCase();
      
      final bool matchesSearch = _searchQuery.isEmpty || 
                               fullName.contains(searchLower) || 
                               email.contains(searchLower);
      
      // Apply department filter
      final bool matchesDepartment = _selectedDepartment == null || 
                                   user['department'] == _selectedDepartment;
      
      // Apply active status filter
      final bool matchesActiveStatus = _showInactiveUsers || user['active'] == 1;
      
      return matchesSearch && matchesDepartment && matchesActiveStatus;
    }).toList();
  }
  
  @override
  Widget build(BuildContext context) {
    final filteredUsers = _getFilteredUsers();
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('User Management'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                // Search and filters
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Search field
                      TextField(
                        controller: _searchController,
                        decoration: InputDecoration(
                          hintText: 'Search by name or email',
                          prefixIcon: const Icon(Icons.search),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                          contentPadding: const EdgeInsets.symmetric(vertical: 12),
                        ),
                        onChanged: (value) {
                          setState(() {
                            _searchQuery = value;
                          });
                        },
                      ),
                      
                      const SizedBox(height: 12),
                      
                      // Filter options
                      Row(
                        children: [
                          // Department filter
                          Expanded(
                            child: DropdownButtonFormField<String?>(
                              decoration: const InputDecoration(
                                labelText: 'Department',
                                border: OutlineInputBorder(),
                                contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                              ),
                              value: _selectedDepartment,
                              items: [
                                const DropdownMenuItem<String?>(
                                  value: null,
                                  child: Text('All Departments'),
                                ),
                                ..._departments.map((dept) => DropdownMenuItem<String?>(
                                  value: dept['department'],
                                  child: Text(dept['department']),
                                )),
                              ],
                              onChanged: (value) {
                                setState(() {
                                  _selectedDepartment = value;
                                });
                              },
                            ),
                          ),
                          
                          const SizedBox(width: 16),
                          
                          // Show inactive users filter
                          FilterChip(
                            label: const Text('Show Inactive'),
                            selected: _showInactiveUsers,
                            onSelected: (value) {
                              setState(() {
                                _showInactiveUsers = value;
                              });
                            },
                            checkmarkColor: Colors.white,
                            selectedColor: Colors.blue,
                            labelStyle: TextStyle(
                              color: _showInactiveUsers ? Colors.white : Colors.black,
                            ),
                          ),
                        ],
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // Summary of filtered users
                      Text(
                        'Showing ${filteredUsers.length} of ${_allUsers.length} users',
                        style: TextStyle(
                          color: Colors.grey[700],
                          fontStyle: FontStyle.italic,
                        ),
                      ),
                    ],
                  ),
                ),
                
                // User list
                Expanded(
                  child: filteredUsers.isEmpty 
                  ? const Center(
                      child: Text('No users found'),
                    )
                  : ListView.builder(
                      itemCount: filteredUsers.length,
                      itemBuilder: (context, index) {
                        final user = filteredUsers[index];
                        final bool isActive = user['active'] == 1;
                        final int userId = user['user_id'];
                        final String fullName = '${user['name']} ${user['surname']}';
                        
                        // This approach prioritizes displaying the initials and treats the image as optional
                        return Card(
                          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          child: ListTile(
                            leading: _buildUserAvatar(user, isActive),
                            title: Text(
                              '${user['name']} ${user['surname']}',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: isActive ? Colors.black : Colors.grey,
                              ),
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(user['email'] ?? 'No email'),
                                Text('${user['department']} • ${user['role']}'),
                              ],
                            ),
                            trailing: PopupMenuButton<String>(
                              onSelected: (value) async {
                                _sessionService.userActivity();
                                
                                switch (value) {
                                  case 'view':
                                    await Navigator.push(
                                      context,
                                      MaterialPageRoute(
                                        builder: (context) => UserDetailScreen(
                                          userId: user['user_id'],
                                          userDetails: widget.userDetails,
                                        ),
                                      ),
                                    );
                                    _loadData(); // Refresh after returning
                                    break;
                                    
                                  case 'toggle':
                                    await _toggleUserStatus(user['user_id'], isActive);
                                    break;
                                    
                                  case 'reset':
                                    // Show reset password confirmation
                                    final bool? confirm = await showDialog<bool>(
                                      context: context,
                                      builder: (context) => AlertDialog(
                                        title: const Text('Reset Password'),
                                        content: const Text('Are you sure you want to reset this user\'s password?'),
                                        actions: [
                                          TextButton(
                                            onPressed: () => Navigator.of(context).pop(false),
                                            child: const Text('Cancel'),
                                          ),
                                          FilledButton(
                                            onPressed: () => Navigator.of(context).pop(true),
                                            style: FilledButton.styleFrom(
                                              backgroundColor: Colors.orange,
                                            ),
                                            child: const Text('Reset Password'),
                                          ),
                                        ],
                                      ),
                                    );
                                    
                                    if (confirm == true) {
                                      try {
                                        setState(() {
                                          _isLoading = true;
                                        });
                                        
                                        final newPassword = await _apiService.resetUserPassword(
                                          userId: user['user_id'],
                                          adminId: widget.userDetails['id'],
                                        );
                                        
                                        setState(() {
                                          _isLoading = false;
                                        });
                                        
                                        // Show password in dialog
                                        if (mounted) {
                                          showDialog(
                                            context: context,
                                            builder: (context) => AlertDialog(
                                              title: const Text('New Password Generated'),
                                              content: Column(
                                                mainAxisSize: MainAxisSize.min,
                                                children: [
                                                  const Text('Please share this password with the user:'),
                                                  const SizedBox(height: 16),
                                                  Container(
                                                    padding: const EdgeInsets.all(12),
                                                    decoration: BoxDecoration(
                                                      border: Border.all(color: Colors.grey),
                                                      borderRadius: BorderRadius.circular(4),
                                                      color: Colors.grey[100],
                                                    ),
                                                    child: SelectableText(
                                                      newPassword,
                                                      style: const TextStyle(
                                                        fontFamily: 'monospace',
                                                        fontWeight: FontWeight.bold,
                                                      ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                              actions: [
                                                TextButton(
                                                  onPressed: () => Navigator.of(context).pop(),
                                                  child: const Text('OK'),
                                                ),
                                              ],
                                            ),
                                          );
                                        }
                                      } catch (e) {
                                        setState(() {
                                          _isLoading = false;
                                        });
                                        
                                        if (mounted) {
                                          ScaffoldMessenger.of(context).showSnackBar(
                                            SnackBar(
                                              content: Text('Error resetting password: $e'),
                                              backgroundColor: Colors.red,
                                            ),
                                          );
                                        }
                                      }
                                    }
                                    break;
                                }
                              },
                              itemBuilder: (context) => [
                                const PopupMenuItem<String>(
                                  value: 'view',
                                  child: Row(
                                    children: [
                                      Icon(Icons.visibility),
                                      SizedBox(width: 8),
                                      Text('View Details'),
                                    ],
                                  ),
                                ),
                                PopupMenuItem<String>(
                                  value: 'toggle',
                                  child: Row(
                                    children: [
                                      Icon(isActive ? Icons.person_off : Icons.person),
                                      SizedBox(width: 8),
                                      Text(isActive ? 'Deactivate' : 'Activate'),
                                    ],
                                  ),
                                ),
                                const PopupMenuItem<String>(
                                  value: 'reset',
                                  child: Row(
                                    children: [
                                      Icon(Icons.password),
                                      SizedBox(width: 8),
                                      Text('Reset Password'),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            isThreeLine: true,
                            onTap: () async {
                              _sessionService.userActivity();
                              await Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => UserDetailScreen(
                                    userId: user['user_id'],
                                    userDetails: widget.userDetails,
                                  ),
                                ),
                              );
                              _loadData(); // Refresh after returning
                            },
                          ),
                        );
                      },
                    ),
                ),
              ],
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          _sessionService.userActivity();
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => CreateUserScreen(
                userDetails: widget.userDetails,
                departments: _departments,
                roles: _roles,
              ),
            ),
          );
          _loadData(); // Refresh after creating a new user
        },
        tooltip: 'Add User',
        child: const Icon(Icons.person_add),
      ),
    );
  }
}