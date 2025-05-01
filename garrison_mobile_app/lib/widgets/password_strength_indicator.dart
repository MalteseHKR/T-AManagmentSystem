// lib/widgets/password_strength_indicator.dart
import 'package:flutter/material.dart';

class PasswordStrengthIndicator extends StatelessWidget {
  final String strength;
  
  const PasswordStrengthIndicator({
    Key? key,
    required this.strength,
  }) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    Color barColor;
    double strength1, strength2, strength3;
    String strengthText;
    
    switch (strength) {
      case 'strong':
        barColor = Colors.green;
        strength1 = 1.0;
        strength2 = 1.0;
        strength3 = 1.0;
        strengthText = 'Strong';
        break;
      case 'medium':
        barColor = Colors.orange;
        strength1 = 1.0;
        strength2 = 1.0;
        strength3 = 0.0;
        strengthText = 'Medium';
        break;
      case 'weak':
      default:
        barColor = Colors.red;
        strength1 = 1.0;
        strength2 = 0.0;
        strength3 = 0.0;
        strengthText = 'Weak';
        break;
    }
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              flex: 1,
              child: Container(
                height: 4,
                decoration: BoxDecoration(
                  color: barColor.withOpacity(strength1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(width: 2),
            Expanded(
              flex: 1,
              child: Container(
                height: 4,
                decoration: BoxDecoration(
                  color: barColor.withOpacity(strength2),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(width: 2),
            Expanded(
              flex: 1,
              child: Container(
                height: 4,
                decoration: BoxDecoration(
                  color: barColor.withOpacity(strength3),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Text(
              'Password Strength: $strengthText',
              style: TextStyle(
                fontSize: 12,
                color: barColor,
                fontWeight: FontWeight.w500,
              ),
            ),
            const Spacer(),
            const Text(
              'Min 6 chars: 1 uppercase, 1 lowercase, 1 number, 1 special char',
              style: TextStyle(
                fontSize: 10,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      ],
    );
  }
}