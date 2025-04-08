// lib/main.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'screens/login_screen.dart';
import 'screens/attendance_screen.dart';
import 'screens/leave_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/admin/admin_dashboard.dart';
import 'services/notification_service.dart';
import 'services/background_service.dart';
import 'services/session_service.dart';

void main() async {
  // Ensure Flutter is initialized
  WidgetsFlutterBinding.ensureInitialized();
  
  try {
    // Initialize notification service
    final notificationService = NotificationService();
    await notificationService.initialize();
    
    // Initialize background service and register background task
    await BackgroundService.initialize();
    await BackgroundService.registerPeriodicTask();
    
    // Check for pending punch-in from previous sessions
    await notificationService.checkForPendingPunchIn();
    
    // Initialize cameras - both front and rear if available
    final cameras = await availableCameras();
    CameraDescription? selectedFrontCamera;
    CameraDescription? selectedRearCamera;
    
    if (cameras.isNotEmpty) {
      // Look for front and rear cameras
      for (var camera in cameras) {
        debugPrint('Found camera: ${camera.name}, lens direction: ${camera.lensDirection}');
        if (camera.lensDirection == CameraLensDirection.front) {
          selectedFrontCamera = camera;
          debugPrint('Selected front camera: ${camera.name}');
        } else if (camera.lensDirection == CameraLensDirection.back) {
          selectedRearCamera = camera;
          debugPrint('Selected rear camera: ${camera.name}');
        }
      }
      
      // If no front camera was found, fall back to the first camera
      if (selectedFrontCamera == null) {
        debugPrint('No front camera found, using first available camera');
        selectedFrontCamera = cameras.first;
      }
    }
    
    if (selectedFrontCamera == null) {
      debugPrint("No cameras available");
      return;
    }
    
    runApp(TimeAttendanceApp(
      frontCamera: selectedFrontCamera,
      rearCamera: selectedRearCamera,
    ));
  } catch (e) {
    debugPrint('Error during app initialization: $e');
    // Fall back to basic initialization if services fail
    final cameras = await availableCameras();
    CameraDescription? selectedFrontCamera;
    
    if (cameras.isNotEmpty) {
      // Try to find front camera even in the fallback path
      selectedFrontCamera = cameras.firstWhere(
        (camera) => camera.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );
    }
    
    if (selectedFrontCamera == null) {
      debugPrint("No cameras available");
      return;
    }
    
    runApp(TimeAttendanceApp(
      frontCamera: selectedFrontCamera,
      rearCamera: null,
    ));
  }
}

class TimeAttendanceApp extends StatelessWidget {
  final CameraDescription frontCamera;
  final CameraDescription? rearCamera;

  const TimeAttendanceApp({
    Key? key, 
    required this.frontCamera,
    this.rearCamera,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Garrison Track',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
        scaffoldBackgroundColor: Colors.grey[100],
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.blue,
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      home: LoginScreen(
        camera: frontCamera,
        rearCamera: rearCamera,
      ),
    );
  }
}

class MainScreen extends StatefulWidget {
  final CameraDescription camera;
  final Map<String, dynamic> userDetails;

  const MainScreen({
    Key? key, 
    required this.camera,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _selectedIndex = 0;
  late PageController _pageController;
  final SessionService _sessionService = SessionService();
  bool _showingTimeoutWarning = false;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    
    // Start the session timeout timer
    _startSessionTimer();
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
          builder: (context) => LoginScreen(camera: widget.camera),
        ),
        (Route<dynamic> route) => false,
      );
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    _sessionService.dispose();
    super.dispose();
  }

  void _onItemTapped(int index) {
    _sessionService.userActivity(); // Reset session timer on user interaction
    setState(() {
      _selectedIndex = index;
      _pageController.animateToPage(
        index,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    });
  }

  void _onPageChanged(int index) {
    _sessionService.userActivity(); // Reset session timer on user interaction
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    // Wrap with GestureDetector to detect user activity
    return GestureDetector(
      onTap: () => _sessionService.userActivity(),
      onPanDown: (_) => _sessionService.userActivity(),
      onScaleStart: (_) => _sessionService.userActivity(),
      child: Scaffold(
        body: PageView(
          controller: _pageController,
          onPageChanged: _onPageChanged,
          physics: const NeverScrollableScrollPhysics(),
          children: [
            AttendanceScreen(
              camera: widget.camera,
              userDetails: widget.userDetails,
            ),
            LeaveScreen(userDetails: widget.userDetails),
            ProfileScreen(
              userDetails: widget.userDetails,
              onLogout: _performLogout,
            ),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _selectedIndex,
          onDestinationSelected: _onItemTapped,
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.fingerprint),
              label: 'Attendance',
            ),
            NavigationDestination(
              icon: Icon(Icons.calendar_today),
              label: 'Leave',
            ),
            NavigationDestination(
              icon: Icon(Icons.person),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}