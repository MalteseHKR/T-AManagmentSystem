// lib/utils/password_generator.dart
import 'dart:math';

class PasswordGenerator {
  static final Random _random = Random.secure();
  
  static String generateStrongPassword({int length = 8}) {
    const String uppercaseChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // No I, O
    const String lowercaseChars = 'abcdefghijkmnpqrstuvwxyz'; // No l, o
    const String digitChars = '23456789'; // No 0, 1
    const String specialChars = '!@#\$%^&*()_-+=<>?';
    
    // Ensure at least one of each character type
    String password = '';
    password += uppercaseChars[_random.nextInt(uppercaseChars.length)];
    password += lowercaseChars[_random.nextInt(lowercaseChars.length)];
    password += digitChars[_random.nextInt(digitChars.length)];
    password += specialChars[_random.nextInt(specialChars.length)];
    
    // Fill the rest of the password
    const String allChars = uppercaseChars + lowercaseChars + digitChars + specialChars;
    for (int i = 4; i < length; i++) {
      password += allChars[_random.nextInt(allChars.length)];
    }
    
    // Shuffle the password
    final List<String> passwordChars = password.split('');
    passwordChars.shuffle(_random);
    
    return passwordChars.join();
  }
  
  // Validate if a password meets the requirements
  static bool isPasswordValid(String password) {
    if (password.length < 6) return false;
    
    bool hasUppercase = password.contains(RegExp(r'[A-Z]'));
    bool hasLowercase = password.contains(RegExp(r'[a-z]'));
    bool hasDigit = password.contains(RegExp(r'[0-9]'));
    bool hasSpecialChar = password.contains(RegExp(r'[!@#$%^&*(),.?":{}|<>]'));
    
    return hasUppercase && hasLowercase && hasDigit && hasSpecialChar;
  }
  
  // Check which requirements are met for a password
  static Map<String, bool> checkPasswordRequirements(String password) {
    return {
      'length': password.length >= 6,
      'uppercase': password.contains(RegExp(r'[A-Z]')),
      'lowercase': password.contains(RegExp(r'[a-z]')),
      'digit': password.contains(RegExp(r'[0-9]')),
      'specialChar': password.contains(RegExp(r'[!@#$%^&*(),.?":{}|<>]')),
    };
  }
}