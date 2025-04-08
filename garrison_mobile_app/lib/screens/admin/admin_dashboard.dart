// lib/screens/admin/admin_dashboard.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';
import 'leave_management_screen.dart';
import 'user_management_screen.dart';
import 'photo_upload_screen.dart';
import '../attendance_screen.dart';
import '../leave_screen.dart';
import '../profile_screen.dart';
import '../login_screen.dart';

class AdminDashboard extends StatefulWidget {
  final Map<String, dynamic> userDetails;
  final CameraDescription camera;
  final CameraDescription? rearCamera;

  const AdminDashboard({
    Key? key,
    required this.userDetails,
    required this.camera,
    this.rearCamera,
  }) : super(key: key);

  @override
  State<AdminDashboard> createState() => _AdminDashboardState();
}

class _AdminDashboardState extends State<AdminDashboard> {
  final SessionService _sessionService = SessionService();
  
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
              Navigator.of(context).pushAndRemoveUntil(
                MaterialPageRoute(
                  builder: (context) => LoginScreen(camera: widget.camera),
                ),
                (Route<dynamic> route) => false,
              );
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

  @override
  Widget build(BuildContext context) {
    final bool isAdminDepartment = 
        widget.userDetails['department'] == 'HR' ||
        widget.userDetails['department'] == 'IT' ||
        widget.userDetails['department'] == 'Administration';
        
    final bool isAdminRole = 
        widget.userDetails['role'] == 'HR Manager' || 
        widget.userDetails['role'] == 'HR' ||
        widget.userDetails['role'] == 'IT Manager' ||
        widget.userDetails['role'] == 'CEO' ||
        widget.userDetails['role'] == 'General Manager' ||
        widget.userDetails['role'] == 'Software Developer' ||
        widget.userDetails['role'] == 'Cyber Security Manager';
        
    final bool hasAdminAccess = isAdminDepartment || isAdminRole;
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('Admin Dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              // Refresh dashboard data
              _sessionService.userActivity();
            },
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _confirmLogout,
            tooltip: 'Logout',
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Welcome card with user info
            Card(
              elevation: 3,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Welcome, ${widget.userDetails['full_name']}',
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${widget.userDetails['department']} • ${widget.userDetails['role']}',
                      style: TextStyle(
                        fontSize: 16,
                        color: Colors.grey[700],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            
            const SizedBox(height: 24),
            const Text(
              'Personal Tools',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            
            const SizedBox(height: 16),
            
            // Grid of personal features
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              childAspectRatio: 1.2,
              mainAxisSpacing: 16,
              crossAxisSpacing: 16,
              children: [
                // Attendance Card
                _buildFeatureCard(
                  title: 'Attendance Punch',
                  icon: Icons.fingerprint,
                  color: Colors.teal,
                  onTap: () {
                    _sessionService.userActivity();
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => AttendanceScreen(
                          camera: widget.camera,
                          userDetails: widget.userDetails,
                        ),
                      ),
                    );
                  },
                ),
                
                // Request Leave Card
                _buildFeatureCard(
                  title: 'Request Leave',
                  icon: Icons.event_available,
                  color: Colors.amber,
                  onTap: () {
                    _sessionService.userActivity();
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => LeaveScreen(
                          userDetails: widget.userDetails,
                        ),
                      ),
                    );
                  },
                ),
                
                // Profile Card
                _buildFeatureCard(
                  title: 'My Profile',
                  icon: Icons.person,
                  color: Colors.indigo,
                  onTap: () {
                    _sessionService.userActivity();
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ProfileScreen(
                          userDetails: widget.userDetails,
                          onLogout: _confirmLogout,
                        ),
                      ),
                    );
                  },
                ),
              ],
            ),
            
            const SizedBox(height: 24),
            const Text(
              'Administrative Tools',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            
            const SizedBox(height: 16),
            
            // Grid of admin features
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              childAspectRatio: 1.2,
              mainAxisSpacing: 16,
              crossAxisSpacing: 16,
              children: [
                // Leave Management Card
                if (hasAdminAccess) 
                  _buildFeatureCard(
                    title: 'Leave Management',
                    icon: Icons.calendar_today,
                    color: Colors.green,
                    onTap: () {
                      _sessionService.userActivity();
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => LeaveManagementScreen(
                            userDetails: widget.userDetails,
                          ),
                        ),
                      );
                    },
                  ),
                
                // User Management Card
                if (hasAdminAccess)
                  _buildFeatureCard(
                    title: 'User Management',
                    icon: Icons.people,
                    color: Colors.blue,
                    onTap: () {
                      _sessionService.userActivity();
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => UserManagementScreen(
                            userDetails: widget.userDetails,
                          ),
                        ),
                      );
                    },
                  ),
                
                // Photo Upload Card
                _buildFeatureCard(
                  title: 'Upload Face Photos',
                  icon: Icons.face,
                  color: Colors.purple,
                  onTap: () {
                    _sessionService.userActivity();
                    // Check if rearCamera is available
                    if (widget.rearCamera != null) {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => PhotoUploadScreen(
                            userDetails: widget.userDetails,
                            camera: widget.rearCamera!,
                          ),
                        ),
                      );
                    } else {
                      // Show a message if no rear camera is available
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Rear camera not available for face photo upload'),
                          backgroundColor: Colors.orange,
                        ),
                      );
                    }
                  },
                ),
                
                // Reports Card
                _buildFeatureCard(
                  title: 'Attendance Reports',
                  icon: Icons.bar_chart,
                  color: Colors.orange,
                  onTap: () {
                    _sessionService.userActivity();
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Reports feature coming soon')),
                    );
                  },
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
  
  Widget _buildFeatureCard({
    required String title,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 48,
                color: color,
              ),
              const SizedBox(height: 16),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}