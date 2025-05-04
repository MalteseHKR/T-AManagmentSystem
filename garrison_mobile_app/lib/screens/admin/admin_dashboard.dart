// lib/screens/admin/admin_dashboard.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
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
  bool _showingTimeoutWarning = false;
  
  @override
  void initState() {
    super.initState();
    // Start the session timeout timer
    _startSessionTimer();
  }
  
  @override
  void dispose() {
    // Stop the session timer when disposing
    _sessionService.dispose();
    super.dispose();
  }
  
  void _startSessionTimer() {
    _sessionService.startSessionTimer(
      onWarningTimeout: () {
        _showLogoutWarning();
      },
      onFinalTimeout: () {
        _performLogout();
      }
    );
  }
  
  // Show a warning dialog before auto-logout
  void _showLogoutWarning() {
    if (_showingTimeoutWarning || !mounted) return;
    
    setState(() {
      _showingTimeoutWarning = true;
    });
    
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Session Timeout Warning'),
        content: const Text('Your session will expire in 1 minute due to inactivity. Would you like to stay logged in?'),
        actions: [
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(
                onPressed: () {
                  Navigator.pop(context);
                  setState(() {
                    _showingTimeoutWarning = false;
                  });
                  _sessionService.userActivity(); // Reset both timers
                },
                child: const Text('Stay Logged In'),
              ),
              const SizedBox(width: 8),
              FilledButton(
                onPressed: () {
                  Navigator.pop(context);
                  _performLogout();
                },
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.red,
                ),
                child: const Text('Logout Now'),
              ),
            ],
          ),
        ],
      ),
    ).then((_) {
      // If dialog is dismissed by system (like on a phone call), continue with timeout
      if (_showingTimeoutWarning && mounted) {
        setState(() {
          _showingTimeoutWarning = false;
        });
        // Let the final timer continue running
        _sessionService.acknowledgeWarning();
      }
    });
  }
  
  void _performLogout() {
    // Stop any existing timers
    _sessionService.stopSessionTimer();
    
    // Ensure any existing dialogs are dismissed
    try {
      // Try to dismiss any open dialogs
      Navigator.of(context, rootNavigator: true).popUntil((route) => route.isFirst);
    } catch (e) {
      print('Error dismissing dialogs: $e');
    }
    
    // Ensure we're on the main thread
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Clear any existing routes and push login screen
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (context) => LoginScreen(
            camera: widget.camera,
            rearCamera: widget.rearCamera,
          ),
        ),
        (Route<dynamic> route) => false,
      );
    });
  }
  
  void _confirmLogout() {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
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
              _performLogout();
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
    
    // Wrap with GestureDetector to detect user activity
    return GestureDetector(
      onTap: () => _sessionService.userActivity(),
      onPanDown: (_) => _sessionService.userActivity(),
      onScaleStart: (_) => _sessionService.userActivity(),
      child: Scaffold(
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
        body: SafeArea(
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints constraints) {
              // Small welcome banner instead of card
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Enhanced welcome header
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                    child: Row(
                      children: [
                        const Icon(Icons.person_outline, size: 24),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Welcome, ${widget.userDetails['full_name']}',
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        Text(
                          '${widget.userDetails['department']} • ${widget.userDetails['role']}',
                          style: TextStyle(
                            fontSize: 14,
                            color: Colors.grey[700],
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  
                  const Divider(height: 8),
                  
                  // Main content with section headers and grid layout
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Personal tools header - larger text
                          const Padding(
                            padding: EdgeInsets.only(left: 6, top: 6, bottom: 6),
                            child: Text(
                              'Personal Tools',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          
                          // Personal tools grid - now with 2 cards per row instead of 3
                          SizedBox(
                            height: constraints.maxHeight * 0.40, // Increased height allocation
                            child: GridView.count(
                              crossAxisCount: 2, // Changed from 3 to 2 cards per row
                              childAspectRatio: 1.5, // Increased to make cards wider relative to height
                              mainAxisSpacing: 12, // Increased spacing
                              crossAxisSpacing: 12, // Increased spacing
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              children: [
                                // Attendance Card
                                _buildFeatureCard(
                                  title: 'Attendance',
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
                                  title: 'Leave Requests',
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
                                  title: 'User Profile',
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
                          ),
                          
                          // Add more space between Personal Tools and Administrative Tools sections
                          const SizedBox(height: 16),
                          
                          const Padding(
                            padding: EdgeInsets.only(left: 6, top: 0, bottom: 6),
                            child: Text(
                              'Administrative Tools',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          
                          // Admin tools grid - also 2 cards per row
                          Expanded(
                            child: GridView.count(
                              crossAxisCount: 2, // Changed from 3 to 2 cards per row
                              childAspectRatio: 1.5, // Increased to make cards wider
                              mainAxisSpacing: 12, // Increased spacing
                              crossAxisSpacing: 12, // Increased spacing
                              shrinkWrap: true,
                              physics: const ClampingScrollPhysics(), // Changed to allow scrolling if needed
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
                                  title: 'Face Photo Upload',
                                  icon: Icons.face,
                                  color: Colors.purple,
                                  onTap: () {
                                    _sessionService.userActivity();
                                    Navigator.push(
                                      context,
                                      MaterialPageRoute(
                                        builder: (context) => PhotoUploadScreen(
                                          userDetails: widget.userDetails,
                                        ),
                                      ),
                                    );
                                  },
                                ),
                                
                                // Reports Card
                                _buildFeatureCard(
                                  title: 'Analytics Reports',
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
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
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
      elevation: 3, // Increased elevation for more prominence
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10), // Slightly larger radius
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.all(14), // Increased padding
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 36, // Larger icon
                color: color,
              ),
              const SizedBox(height: 10), // Increased spacing
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 14, // Larger text
                  fontWeight: FontWeight.bold,
                ),
                maxLines: 2, // Allow up to 2 lines for longer titles
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}