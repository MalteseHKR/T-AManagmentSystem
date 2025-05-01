// lib/screens/leave_screen.dart
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/timezone_service.dart';
import '../services/session_service.dart';
import '../services/notification_service.dart';
import '../widgets/leave_request_widget.dart';
import '../widgets/pending_certificates_widget.dart';
import '../util/simplified_certificate_helper.dart';

class LeaveScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;

  const LeaveScreen({
    Key? key,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<LeaveScreen> createState() => _LeaveScreenState();
}

class _LeaveScreenState extends State<LeaveScreen> {
  final _apiService = ApiService();
  final _timezoneService = TimezoneService();
  final _sessionService = SessionService();
  final _notificationService = NotificationService();
  
  Map<String, dynamic> _leaveBalance = {};
  List<Map<String, dynamic>> _leaveRequests = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadLeaveBalance();
    _loadLeaveRequests();
  }

  Future<void> _loadLeaveBalance() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    setState(() {
      _isLoading = true;
    });

    try {
      // Make sure we're using id as a string
      final userId = widget.userDetails['id'].toString();
      final balance = await _apiService.getLeaveBalance(userId);
      
      if (mounted) {
        setState(() {
          _leaveBalance = balance;
        });
      }
    } catch (e) {
      print('Complete leave balance error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error loading leave balance: $e'),
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

  Future<void> _loadLeaveRequests() async {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
    try {
      // Make sure we're using id as a string
      final userId = widget.userDetails['id'].toString();
      final requests = await _apiService.getLeaveRequests(userId);
      
      if (mounted) {
        setState(() {
          _leaveRequests = requests;
        });
      }
    } catch (e) {
      print('Complete leave requests error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error loading leave requests: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _showLeaveRequestDialog({Map<String, dynamic>? leaveRequestToEdit}) {
    // Reset session timer on user interaction
    _sessionService.userActivity();
    
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
                      Text(
                        leaveRequestToEdit != null ? 'Edit Leave Request' : 'New Leave Request',
                        style: const TextStyle(
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
                      // Refresh data before closing dialog
                      await _loadLeaveBalance();
                      await _loadLeaveRequests();
                      
                      if (mounted && Navigator.of(context).canPop()) {
                        Navigator.pop(context);
                      }
                    },
                    leaveRequestToEdit: leaveRequestToEdit,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // Method to cancel a leave request
  Future<void> _cancelLeaveRequest(Map<String, dynamic> request) async {
    try {
      // Reset session timer on user interaction
      _sessionService.userActivity();
      
      // Show confirmation dialog
      final bool confirm = await showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Cancel Leave Request'),
          content: const Text('Are you sure you want to cancel this leave request?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('No'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Yes, Cancel'),
            ),
          ],
        ),
      ) ?? false;
      
      if (!confirm) return;
      
      // Show loading indicator
      setState(() {
        _isLoading = true;
      });
      
      // Call the dedicated API to cancel the leave request
      await _apiService.cancelLeaveRequest(
        int.parse(request['request_id'].toString()),
      );
      
      // Refresh data
      await _loadLeaveBalance();
      await _loadLeaveRequests();
      
      // Show success message
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Leave request cancelled successfully'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      print('Error cancelling leave request: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error cancelling leave request: $e'),
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

  Widget _buildLeaveBalanceCard() {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Leave Balance',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildLeaveBalanceItem('Annual', _leaveBalance['annual']?['remaining']),
                _buildLeaveBalanceItem('Sick', _leaveBalance['sick']?['remaining']),
                _buildLeaveBalanceItem('Personal', _leaveBalance['personal']?['remaining']),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLeaveBalanceItem(String type, dynamic days) {
    num balanceDays = 0;
    if (days != null) {
      if (days is String) {
        balanceDays = num.tryParse(days) ?? 0;
      } else if (days is num) {
        balanceDays = days;
      }
    }

    return Column(
      children: [
        CircleAvatar(
          radius: 25,
          backgroundColor: Colors.blue.withOpacity(0.2),
          child: Text(
            balanceDays.toString(),
            style: const TextStyle(
              color: Colors.blue,
              fontWeight: FontWeight.bold,
              fontSize: 18,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          type,
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
      ],
    );
  }

  Widget _buildLeaveRequestHistory() {
    if (_leaveRequests.isEmpty) {
      return Card(
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Center(
            child: Text(
              'No leave requests',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ),
        ),
      );
    }

    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              'Leave Request History',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _leaveRequests.length,
            separatorBuilder: (context, index) => const Divider(),
            itemBuilder: (context, index) {
              final request = _leaveRequests[index];
              return _buildLeaveRequestItem(request);
            },
          ),
        ],
      ),
    );
  }

  // Helper method to check if a leave request should be editable
  bool _isLeaveRequestEditable(Map<String, dynamic> request) {
    final status = request['status']?.toString().toLowerCase() ?? '';
    
    // Pending certificate requests are managed separately
    if (status == 'pending certificate') {
      return false;
    }
    
    // Check if the leave dates are in the future
    final startDate = DateTime.parse(request['start_date']?.toString() ?? '');
    final today = DateTime.now();
    final isPastDate = startDate.isBefore(DateTime(today.year, today.month, today.day));
    
    // Rule 1: Pending requests are always editable
    if (status == 'pending') {
      return true;
    }
    
    // Rule 2: Approved requests that haven't started yet are editable
    if (status == 'approved' && !isPastDate) {
      return true;
    }
    
    // All other cases are not editable
    return false;
  }
  
  // Helper method to check if a leave request should be cancelable
  bool _isLeaveRequestCancelable(Map<String, dynamic> request) {
    final status = request['status']?.toString().toLowerCase() ?? '';
    
    // Check if the leave dates are in the future
    final startDate = DateTime.parse(request['start_date']?.toString() ?? '');
    final today = DateTime.now();
    final isPastDate = startDate.isBefore(DateTime(today.year, today.month, today.day));
    
    // Rule: Pending or approved requests that haven't started yet can be cancelled
    if ((status == 'pending' || status == 'approved') && !isPastDate) {
      return true;
    }
    
    return false;
  }

  Widget _buildLeaveRequestItem(Map<String, dynamic> request) {
    // Check if it's a sick leave with pending certificate
    final bool isPendingCertificate = 
        request['leave_type'] == 'Sick' && 
        request['status'] == 'Pending Certificate';
        
    final bool isFullDay = request['is_full_day'] ?? true;
    
    // Check if this leave request should show certificate button using our simplified helper
    final bool showCertificateButton = CertificateViewer.shouldHaveCertificate(request);
    
    // Check editability and cancelability
    final bool canEdit = _isLeaveRequestEditable(request);
    final bool canCancel = _isLeaveRequestCancelable(request);

    return ListTile(
      title: Row(
        children: [
          Expanded(
            child: Text(request['leave_type']?.toString() ?? 'Unknown'),
          ),
          // Show edit button if editable
          if (canEdit)
            IconButton(
              icon: const Icon(Icons.edit, size: 20),
              onPressed: () {
                _sessionService.userActivity();
                _showLeaveRequestDialog(leaveRequestToEdit: request);
              },
              tooltip: 'Edit request',
            ),
          // Show cancel button if cancelable
          if (canCancel)
            IconButton(
              icon: const Icon(Icons.cancel, size: 20),
              onPressed: () => _cancelLeaveRequest(request),
              tooltip: 'Cancel request',
            ),
        ],
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isPendingCertificate)
            Row(
              children: [
                const Icon(Icons.warning, color: Colors.orange, size: 16),
                const SizedBox(width: 4),
                const Expanded(
                  child: Text(
                    'Medical certificate and dates required',
                    style: TextStyle(color: Colors.orange, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            )
          else
            Text(
              '${_formatDate(request['start_date']?.toString())} to ${_formatDate(request['end_date']?.toString())}',
            ),
              
          // Show time details for partial day leave
          if (!isFullDay && request['start_time'] != null && request['end_time'] != null)
            Text(
              'Time: ${request['start_time']} - ${request['end_time']}',
              style: const TextStyle(fontSize: 12),
            ),
              
          if (request['reason'] != null && request['reason'].toString().isNotEmpty)
            Text(
              'Reason: ${request['reason']}',
              style: const TextStyle(fontSize: 12),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
              
          // Simplified: Show certificate button only for sick leave with certificate
          if (showCertificateButton) 
            TextButton.icon(
              icon: const Icon(Icons.medical_services, size: 16),
              label: const Text('View Medical Certificate', style: TextStyle(fontSize: 12)),
              onPressed: () {
                _sessionService.userActivity();
                // Use our new simplified viewer
                CertificateViewer.viewSickLeaveDocument(
                  context, 
                  request,
                  _apiService
                );
              },
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                minimumSize: const Size(0, 0),
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
            ),
        ],
      ),
      trailing: _buildStatusChip(request['status']?.toString()),
    );
  }

  Widget _buildStatusChip(String? status) {
    Color chipColor;
    switch (status?.toLowerCase() ?? 'pending') {
      case 'approved':
        chipColor = Colors.green;
        break;
      case 'pending':
        chipColor = Colors.orange;
        break;
      case 'pending certificate':
        chipColor = Colors.deepOrange;
        break;
      case 'rejected':
        chipColor = Colors.red;
        break;
      case 'cancelled':  // Added new status
        chipColor = Colors.grey;
        break;
      default:
        chipColor = Colors.grey;
    }

    return Chip(
      label: Text(
        status?.toString() ?? 'Pending',
        style: const TextStyle(color: Colors.white, fontSize: 11),
      ),
      backgroundColor: chipColor,
      padding: EdgeInsets.zero,
      labelPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: -2),
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );
  }

  String _formatDate(String? dateString) {
    // Use timezone service to format the date with timezone offset
    return _timezoneService.formatDateWithOffset(dateString, format: 'dd/MM/yyyy');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Leave Management'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () async {
                // Reset session timer on user interaction
                _sessionService.userActivity();
                await _loadLeaveBalance();
                await _loadLeaveRequests();
              },
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildLeaveBalanceCard(),
                    const SizedBox(height: 16),
                    
                    // Add the Pending Certificates Widget here
                    PendingCertificatesWidget(
                      userDetails: widget.userDetails,
                      onCertificateCompleted: () async {
                        await _loadLeaveBalance();
                        await _loadLeaveRequests();
                      },
                    ),
                    const SizedBox(height: 16),
                    
                    FilledButton.icon(
                      onPressed: () => _showLeaveRequestDialog(),
                      icon: const Icon(Icons.add),
                      label: const Text('Request Leave'),
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                    ),
                    const SizedBox(height: 16),
                    _buildLeaveRequestHistory(),
                  ],
                ),
              ),
            ),
    );
  }
}