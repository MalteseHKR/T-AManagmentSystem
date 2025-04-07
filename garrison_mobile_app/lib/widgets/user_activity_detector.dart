// lib/widgets/user_activity_detector.dart
import 'package:flutter/material.dart';
import '../services/session_service.dart';

class UserActivityDetector extends StatelessWidget {
  final Widget child;
  final SessionService sessionService;

  const UserActivityDetector({
    Key? key,
    required this.child,
    required this.sessionService,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Listener(
      onPointerDown: (_) => sessionService.userActivity(),
      onPointerMove: (_) => sessionService.userActivity(),
      onPointerUp: (_) => sessionService.userActivity(),
      child: child,
    );
  }
}