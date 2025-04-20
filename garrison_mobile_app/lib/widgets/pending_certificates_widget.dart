// lib/widgets/pending_certificates_widget.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/session_service.dart';
import 'leave_request_widget.dart';

class PendingCertificatesWidget extends StatefulWidget {
  final Map<String, dynamic> userDetails;
  final Function onCertificateCompleted;

  const PendingCertificatesWidget({
    Key? key,
    required this.userDetails,
    required this.onCertificateCompleted,
  }) : super(key: key);

  @override
  State<PendingCertificatesWidget> createState() => _PendingCertificatesWidgetState();
}

class _PendingCertificatesWidgetState extends State<PendingCertificatesWidget> {
  final ApiService _apiService = ApiService();
  final SessionService _sessionService = SessionService();
  List<Map<String, dynamic>> _pendingRequests = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPendingRequests();
  }

  Future<void> _loadPendingRequests() async {
    setState(() {
      _isLoading = true;
    });

    try {
      // Make sure we're using id as a string
      final userId = widget.userDetails['id'].toString();
      final requests = await _apiService.getPendingCertificateRequests(userId);
      
      if (mounted) {
        setState(() {
          _pendingRequests = requests;
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Error loading pending certificate requests: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading pending requests: $e')),
        );
      }
    }
  }

  void _showCompletionForm(Map<String, dynamic> request) {
    _sessionService.userActivity();
    
    // Format the request to match the expected structure for LeaveRequestWidget
    final formattedRequest = {
      'request_id': request['request_id'],
      'id': request['request_id'],
      'leave_type': 'Sick',
      'status': 'Pending Certificate',
      'reason': request['reason'],
      'request_date': request['request_date'],
    };
    
    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Container(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.8,
            maxWidth: MediaQuery.of(context).size.width * 0.9,
          ),
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Complete Sick Leave Request',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  LeaveRequestWidget(
                    onLeaveRequested: () async {
                      // Reload data and close dialog
                      await _loadPendingRequests();
                      widget.onCertificateCompleted();
                      Navigator.pop(context);
                    },
                    leaveRequestToEdit: formattedRequest,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      return DateFormat('dd/MM/yyyy').format(date);
    } catch (e) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_pendingRequests.isEmpty) {
      return const SizedBox.shrink(); // No pending requests, don't show anything
    }

    return Card(
      elevation: 4,
      color: Colors.amber.shade50,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.warning_amber_rounded, color: Colors.amber),
                const SizedBox(width: 8),
                const Text(
                  'Pending Medical Certificates',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: _loadPendingRequests,
                  tooltip: 'Refresh',
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Text(
              'You have sick leave requests that require medical certificates:',
              style: TextStyle(
                fontSize: 14,
              ),
            ),
            const SizedBox(height: 16),
            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _pendingRequests.length,
              itemBuilder: (context, index) {
                final request = _pendingRequests[index];
                final requestDate = request['request_date'] ?? request['created_at'];
                final daysElapsed = requestDate != null 
                    ? DateTime.now().difference(DateTime.parse(requestDate)).inDays 
                    : 0;
                final daysRemaining = 10 - daysElapsed;
                
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: const Icon(Icons.medical_services, color: Colors.red),
                    title: Text('Sick Leave Request'),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Requested on: ${_formatDate(requestDate)}'),
                        Text(
                          'Complete within $daysRemaining days',
                          style: TextStyle(
                            color: daysRemaining <= 3 ? Colors.red : Colors.black87,
                            fontWeight: daysRemaining <= 3 ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                        if (request['reason'] != null && request['reason'].toString().isNotEmpty)
                          Text('Reason: ${request['reason']}'),
                      ],
                    ),
                    trailing: ElevatedButton(
                      onPressed: () => _showCompletionForm(request),
                      child: const Text('Complete'),
                    ),
                    isThreeLine: true,
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}