// lib/screens/admin/leave_management_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../services/session_service.dart';
import '../../services/timezone_service.dart';

class LeaveManagementScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;

  const LeaveManagementScreen({
    Key? key,
    required this.userDetails,
  }) : super(key: key);

  @override
  State<LeaveManagementScreen> createState() => _LeaveManagementScreenState();
}

class _LeaveManagementScreenState extends State<LeaveManagementScreen> {
  final ApiService _apiService = ApiService();
  final SessionService _sessionService = SessionService();
  final TimezoneService _timezoneService = TimezoneService();
  
  List<Map<String, dynamic>> _pendingLeaveRequests = [];
  List<Map<String, dynamic>> _allLeaveRequests = [];
  List<Map<String, dynamic>> _employees = [];
  bool _isLoading = true;
  
  @override
  void initState() {
    super.initState();
    _loadData();
  }
  
  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
    });
    
    try {
      // First get all employees
      await _loadEmployees();
      
      // Then load all leave requests
      await _loadLeaveRequests();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading data: $e')),
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
  
  Future<void> _loadEmployees() async {
    try {
      // This would need a new API endpoint to get all employees
      final response = await _apiService.getAllEmployees();
      
      setState(() {
        _employees = response;
      });
    } catch (e) {
      print('Error loading employees: $e');
      // Show error but continue loading leave requests
    }
  }
  
  Future<void> _loadLeaveRequests() async {
    try {
      // This would need a new API endpoint to get all leave requests for admin
      final response = await _apiService.getAllLeaveRequests();
      
      // Add employee names to leave requests by joining with _employees list
      for (var request in response) {
        final employee = _employees.firstWhere(
          (emp) => emp['user_id'] == request['user_id'],
          orElse: () => {'name': 'Unknown', 'surname': 'Employee'},
        );
        
        request['employee_name'] = '${employee['name']} ${employee['surname']}';
      }
      
      setState(() {
        _allLeaveRequests = response;
        // Filter pending requests
        _pendingLeaveRequests = response.where((req) => req['status'] == 'pending').toList();
      });
    } catch (e) {
      print('Error loading leave requests: $e');
      rethrow;
    }
  }
  
  Future<void> _approveLeaveRequest(int requestId) async {
    _sessionService.userActivity();
    
    setState(() {
      _isLoading = true;
    });
    
    try {
      await _apiService.updateLeaveRequestStatus(
        requestId: requestId,
        newStatus: 'approved',
        adminId: widget.userDetails['id'],
      );
      
      // Reload data after update
      await _loadLeaveRequests();
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Leave request approved'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error approving request: $e'),
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
  
  Future<void> _rejectLeaveRequest(int requestId) async {
    _sessionService.userActivity();
    
    // Show dialog to enter rejection reason
    final TextEditingController reasonController = TextEditingController();
    
    if (!mounted) return;
    
    final bool? result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reject Leave Request'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Please provide a reason for rejection:'),
            const SizedBox(height: 16),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                labelText: 'Reason',
                border: OutlineInputBorder(),
              ),
              maxLines: 3,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: Colors.red,
            ),
            child: const Text('Reject'),
          ),
        ],
      ),
    );
    
    if (result != true) return;
    
    setState(() {
      _isLoading = true;
    });
    
    try {
      await _apiService.updateLeaveRequestStatus(
        requestId: requestId,
        newStatus: 'rejected',
        adminId: widget.userDetails['id'],
        reason: reasonController.text.trim(),
      );
      
      // Reload data after update
      await _loadLeaveRequests();
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Leave request rejected'),
            backgroundColor: Colors.orange,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error rejecting request: $e'),
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
  
  // Helper methods for formatting date and status
  String _formatDate(String? dateStr) {
    return _timezoneService.formatDateWithOffset(dateStr, format: 'dd/MM/yyyy');
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
      case 'rejected':
        chipColor = Colors.red;
        break;
      default:
        chipColor = Colors.grey;
    }

    return Chip(
      label: Text(
        status?.toString() ?? 'Pending',
        style: const TextStyle(color: Colors.white),
      ),
      backgroundColor: chipColor,
    );
  }
  
  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Leave Management'),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Pending Approvals'),
              Tab(text: 'All Requests'),
            ],
          ),
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(
                children: [
                  // Pending Leave Requests Tab
                  _buildPendingRequestsTab(),
                  
                  // All Leave Requests Tab
                  _buildAllRequestsTab(),
                ],
              ),
        floatingActionButton: FloatingActionButton(
          onPressed: _loadData,
          tooltip: 'Refresh',
          child: const Icon(Icons.refresh),
        ),
      ),
    );
  }
  
  Widget _buildPendingRequestsTab() {
    if (_pendingLeaveRequests.isEmpty) {
      return const Center(
        child: Text('No pending leave requests'),
      );
    }
    
    return ListView.builder(
      itemCount: _pendingLeaveRequests.length,
      itemBuilder: (context, index) {
        final request = _pendingLeaveRequests[index];
        
        return Card(
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.person, color: Colors.blue),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        request['employee_name'] ?? 'Unknown Employee',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 18,
                        ),
                      ),
                    ),
                    _buildStatusChip(request['status']),
                  ],
                ),
                const Divider(),
                
                // Leave details
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Leave Type'),
                          Text(
                            request['leave_type'] ?? 'Unknown',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Date Range'),
                          Text(
                            '${_formatDate(request['start_date'])} - ${_formatDate(request['end_date'])}',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                
                const SizedBox(height: 12),
                if (request['reason'] != null && request['reason'].toString().isNotEmpty)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Reason:'),
                      Text(request['reason']),
                    ],
                  ),
                
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    OutlinedButton.icon(
                      onPressed: () => _rejectLeaveRequest(request['request_id']),
                      icon: const Icon(Icons.close),
                      label: const Text('Reject'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                      ),
                    ),
                    const SizedBox(width: 12),
                    FilledButton.icon(
                      onPressed: () => _approveLeaveRequest(request['request_id']),
                      icon: const Icon(Icons.check),
                      label: const Text('Approve'),
                      style: FilledButton.styleFrom(
                        backgroundColor: Colors.green,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
  
  Widget _buildAllRequestsTab() {
    if (_allLeaveRequests.isEmpty) {
      return const Center(
        child: Text('No leave requests found'),
      );
    }
    
    return ListView.builder(
      itemCount: _allLeaveRequests.length,
      itemBuilder: (context, index) {
        final request = _allLeaveRequests[index];
        final bool isPending = request['status'] == 'pending';
        
        return Card(
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.person, color: Colors.blue),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        request['employee_name'] ?? 'Unknown Employee',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 18,
                        ),
                      ),
                    ),
                    _buildStatusChip(request['status']),
                  ],
                ),
                const Divider(),
                
                // Leave details
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Leave Type'),
                          Text(
                            request['leave_type'] ?? 'Unknown',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Date Range'),
                          Text(
                            '${_formatDate(request['start_date'])} - ${_formatDate(request['end_date'])}',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                
                const SizedBox(height: 12),
                if (request['reason'] != null && request['reason'].toString().isNotEmpty)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Reason:'),
                      Text(request['reason']),
                    ],
                  ),
                
                // Only show action buttons for pending requests
                if (isPending) ...[
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      OutlinedButton.icon(
                        onPressed: () => _rejectLeaveRequest(request['request_id']),
                        icon: const Icon(Icons.close),
                        label: const Text('Reject'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.red,
                        ),
                      ),
                      const SizedBox(width: 12),
                      FilledButton.icon(
                        onPressed: () => _approveLeaveRequest(request['request_id']),
                        icon: const Icon(Icons.check),
                        label: const Text('Approve'),
                        style: FilledButton.styleFrom(
                          backgroundColor: Colors.green,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}