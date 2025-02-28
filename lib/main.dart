// lib/main.dart
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'screens/login_screen.dart';
import 'screens/attendance_screen.dart';
import 'screens/leave_screen.dart';
import 'screens/profile_screen.dart';
import 'services/notification_service.dart';
import 'services/background_service.dart';

// The callback dispatcher function must be imported wherever it's used,
// but is defined in background_service.dart as a top-level function

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
    
    // Initialize camera
    final cameras = await availableCameras();
    CameraDescription? selectedCamera;
    
    if (cameras.isNotEmpty) {
      // Specifically look for the front camera
      for (var camera in cameras) {
        debugPrint('Found camera: ${camera.name}, lens direction: ${camera.lensDirection}');
        if (camera.lensDirection == CameraLensDirection.front) {
          selectedCamera = camera;
          debugPrint('Selected front camera: ${camera.name}');
          break;
        }
      }
      
      // If no front camera was found, fall back to the first camera
      if (selectedCamera == null) {
        debugPrint('No front camera found, using first available camera');
        selectedCamera = cameras.first;
      }
    }
    
    if (selectedCamera == null) {
      debugPrint("No cameras available");
      return;
    }
    
    runApp(TimeAttendanceApp(camera: selectedCamera));
  } catch (e) {
    debugPrint('Error during app initialization: $e');
    // Fall back to basic initialization if services fail
    final cameras = await availableCameras();
    CameraDescription? selectedCamera;
    
    if (cameras.isNotEmpty) {
      // Try to find front camera even in the fallback path
      selectedCamera = cameras.firstWhere(
        (camera) => camera.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );
    }
    
    if (selectedCamera == null) {
      debugPrint("No cameras available");
      return;
    }
    
    runApp(TimeAttendanceApp(camera: selectedCamera));
  }
}

class TimeAttendanceApp extends StatelessWidget {
  final CameraDescription camera;

  const TimeAttendanceApp({Key? key, required this.camera}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Garrison Track',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
        scaffoldBackgroundColor: Colors.grey[100],
        appBarTheme: AppBarTheme(
          backgroundColor: Colors.blue,
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      home: LoginScreen(camera: camera),
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

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _onItemTapped(int index) {
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
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
            onLogout: () {
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(
                  builder: (context) => LoginScreen(camera: widget.camera),
                ),
              );
            },
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
    );
  }
}