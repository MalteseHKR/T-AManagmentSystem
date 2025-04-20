// lib/screens/leave_screen.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/timezone_service.dart';
import '../services/session_service.dart';
import '../services/notification_service.dart';
import '../widgets/leave_request_widget.dart';
import '../widgets/pending_certificates_widget.dart';

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

  Widget _buildLeaveRequestItem(Map<String, dynamic> request) {
    // Check if it's a sick leave with pending certificate
    final bool isPendingCertificate = 
        request['leave_type'] == 'Sick' && 
        request['status'] == 'Pending Certificate';
        
    final bool isFullDay = request['is_full_day'] ?? true;

    return ListTile(
      title: Row(
        children: [
          Expanded(
            child: Text(request['leave_type']?.toString() ?? 'Unknown'),
          ),
          if (!isPendingCertificate && request['status']?.toLowerCase() != 'rejected')
            IconButton(
              icon: const Icon(Icons.edit, size: 20),
              onPressed: () {
                _sessionService.userActivity();
                _showLeaveRequestDialog(leaveRequestToEdit: request);
              },
              tooltip: 'Edit request',
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
            
          if (request['medical_certificate_url'] != null)
            TextButton.icon(
              icon: const Icon(Icons.medical_services, size: 16),
              label: const Text('View Medical Certificate', style: TextStyle(fontSize: 12)),
              onPressed: () {
                _sessionService.userActivity();
                // Implement view certificate functionality
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