// lib/util/simplified_certificate_helper.dart
import 'package:flutter/material.dart';
import '../services/api_service.dart';

class CertificateViewer {
  // Simple function to show certificate for sick leave
  static void viewSickLeaveDocument(BuildContext context, Map<String, dynamic> leaveRequest, ApiService apiService) {
    // Ensure we have auth token
    if (apiService.token == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Not authenticated. Please login again.')),
      );
      return;
    }
    
    // Get the request ID for debugging
    final requestId = leaveRequest['request_id'] ?? 'unknown';
    print('Attempting to view certificate for request ID: $requestId');
    
    // Extract the certificate filename/path
    final certificatePath = leaveRequest['medical_certificate'];
    print('Certificate data from API: $certificatePath');
    
    // If no certificate data, show error
    if (certificatePath == null || certificatePath.toString().trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No certificate available for this request')),
      );
      return;
    }
    
    // Get the full URL using the API service's method
    String fullUrl = apiService.getCertificateViewUrl(certificatePath.toString());
    
    print('Constructed URL: $fullUrl');
    
    // Show the certificate in a dialog
    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppBar(
              title: const Text('Medical Certificate'),
              automaticallyImplyLeading: false,
              actions: [
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            Flexible(
              child: Container(
                padding: const EdgeInsets.all(16),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Add debug info
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8.0),
                      child: Text(
                        'Loading certificate from: $fullUrl',
                        style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    
                    Flexible(
                      child: Image.network(
                        fullUrl,
                        headers: {'Authorization': 'Bearer ${apiService.token}'},
                        fit: BoxFit.contain,
                        loadingBuilder: (context, child, loadingProgress) {
                          if (loadingProgress == null) return child;
                          return Center(
                            child: CircularProgressIndicator(
                              value: loadingProgress.expectedTotalBytes != null
                                  ? loadingProgress.cumulativeBytesLoaded / loadingProgress.expectedTotalBytes!
                                  : null,
                            ),
                          );
                        },
                        errorBuilder: (context, error, stackTrace) {
                          print('Error loading image: $error');
                          
                          // Try alternative URL as fallback
                          final String certificateFilename = certificatePath.toString().split('/').last;
                          final String altUrl = 'https://api.garrisonta.org/uploads/certificates/$certificateFilename';
                          
                          print('Trying alternative URL: $altUrl');
                          
                          return Image.network(
                            altUrl,
                            headers: {'Authorization': 'Bearer ${apiService.token}'},
                            fit: BoxFit.contain,
                            errorBuilder: (context, error, stackTrace) {
                              return Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.error_outline, color: Colors.red, size: 48),
                                  const SizedBox(height: 16),
                                  const Text('Could not load certificate'),
                                  const SizedBox(height: 8),
                                  Text('Original path: $certificatePath', style: const TextStyle(fontSize: 12)),
                                  Text('First URL: $fullUrl', style: const TextStyle(fontSize: 12)),
                                  Text('Alt URL: $altUrl', style: const TextStyle(fontSize: 12)),
                                  const SizedBox(height: 16),
                                  FilledButton(
                                    onPressed: () => Navigator.pop(context),
                                    child: const Text('Close'),
                                  ),
                                ],
                              );
                            },
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
  
  // Simple check if a leave request should have a certificate
  static bool shouldHaveCertificate(Map<String, dynamic> leaveRequest) {
    // Check if it's a sick leave
    bool isSickLeave = false;
    
    // Try different approaches to identify sick leave
    if (leaveRequest.containsKey('leave_type')) {
      isSickLeave = leaveRequest['leave_type'] == 'Sick';
    } else if (leaveRequest.containsKey('leave_type_id')) {
      isSickLeave = leaveRequest['leave_type_id'] == 2;
    }
    
    // For debugging
    print('Leave request ID: ${leaveRequest['request_id']}');
    print('Is sick leave: $isSickLeave');
    
    // Always show certificate button for sick leave, regardless of whether there's certificate data
    // This allows the user to attempt to view the certificate even if not showing in the data
    return isSickLeave;
  }
}