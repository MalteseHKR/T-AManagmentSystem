import 'dart:io';
import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../widgets/medical_certificate_uploader.dart';

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
  Map<String, dynamic> _leaveBalance = {};
  List<Map<String, dynamic>> _leaveRequests = [];
  bool _isLoading = false;
  DateTime _selectedDay = DateTime.now();
  DateTime _focusedDay = DateTime.now();
  String _selectedLeaveType = 'Annual';
  DateTime? _rangeStart;
  DateTime? _rangeEnd;
  final _reasonController = TextEditingController();
  File? _medicalCertificate;

  @override
  void initState() {
    super.initState();
    _loadLeaveBalance();
    _loadLeaveRequests();
  }

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _loadLeaveBalance() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final balance = await _apiService.getLeaveBalance(widget.userDetails['id'].toString());
      if (mounted) {
        setState(() {
          _leaveBalance = balance;
        });
      }
    } catch (e) {
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
    try {
      final requests = await _apiService.getLeaveRequests(widget.userDetails['id'].toString());
      if (mounted) {
        setState(() {
          _leaveRequests = requests;
        });
      }
    } catch (e) {
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

  Future<void> _submitLeaveRequest() async {
    if (_rangeStart == null || _rangeEnd == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select date range')),
      );
      return;
    }

    if (_selectedLeaveType == 'Sick' && _medicalCertificate == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please upload a medical certificate')),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      String? certificateUrl;
      // Add more detailed logging
      print('Leave Type: $_selectedLeaveType');
      print('Medical Certificate: $_medicalCertificate');
      print('Start Date: $_rangeStart');
      print('End Date: $_rangeEnd');
      print('Reason: ${_reasonController.text.trim()}');

      if (_medicalCertificate != null && _selectedLeaveType == 'Sick') {
        try {
          certificateUrl = await _apiService.uploadMedicalCertificate(_medicalCertificate!);
          print('Uploaded Certificate URL: $certificateUrl');
        } catch (uploadError) {
          print('Medical Certificate Upload Error: $uploadError');
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Failed to upload medical certificate: $uploadError'),
              backgroundColor: Colors.red,
            ),
          );
          setState(() {
            _isLoading = false;
          });
          return;
        }
      }

      print('Submitting Leave Request:');
      print('Certificate URL: $certificateUrl');

      await _apiService.submitLeaveRequest(
        leaveType: _selectedLeaveType,
        startDate: _rangeStart!,
        endDate: _rangeEnd!,
        reason: _reasonController.text.trim(),
        certificateUrl: certificateUrl,
      );

      if (mounted) {
        // Refresh data
        await _loadLeaveBalance();
        await _loadLeaveRequests();

        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Leave request submitted successfully'),
            backgroundColor: Colors.green,
          ),
        );

        // Reset form
        setState(() {
          _rangeStart = null;
          _rangeEnd = null;
          _reasonController.clear();
          _medicalCertificate = null;
        });
      }
    } catch (e) {
      print('Complete Leave Request Submission Error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error submitting leave request: $e'),
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

  Widget _buildCalendarCard() {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: TableCalendar(
        firstDay: DateTime.now(),
        lastDay: DateTime.now().add(const Duration(days: 365)),
        focusedDay: _focusedDay,
        selectedDayPredicate: (day) => isSameDay(_selectedDay, day),
        rangeStartDay: _rangeStart,
        rangeEndDay: _rangeEnd,
        calendarFormat: CalendarFormat.month,
        rangeSelectionMode: RangeSelectionMode.enforced,
        onDaySelected: (selectedDay, focusedDay) {
          setState(() {
            _selectedDay = selectedDay;
            _focusedDay = focusedDay;
          });
        },
        onRangeSelected: (start, end, focusedDay) {
          setState(() {
            _rangeStart = start;
            _rangeEnd = end;
            _focusedDay = focusedDay;
          });
        },
        calendarStyle: CalendarStyle(
          rangeHighlightColor: Colors.blue.withOpacity(0.2),
          rangeStartDecoration: const BoxDecoration(
            color: Colors.blue,
            shape: BoxShape.circle,
          ),
          rangeEndDecoration: const BoxDecoration(
            color: Colors.blue,
            shape: BoxShape.circle,
          ),
          todayDecoration: BoxDecoration(
            color: Colors.blue.withOpacity(0.5),
            shape: BoxShape.circle,
          ),
          selectedDecoration: const BoxDecoration(
            color: Colors.blue,
            shape: BoxShape.circle,
          ),
        ),
        headerStyle: const HeaderStyle(
          formatButtonVisible: false,
        ),
      ),
    );
  }

  void _showLeaveRequestDialog() {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Container(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.8,
          ),
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Request Leave',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 16),
                  StatefulBuilder(
                    builder: (context, setState) => Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<String>(
                          value: _selectedLeaveType,
                          items: ['Annual', 'Sick', 'Personal']
                              .map((type) => DropdownMenuItem(
                                    value: type,
                                    child: Text(type),
                                  ))
                              .toList(),
                          onChanged: (value) {
                            setState(() => _selectedLeaveType = value!);
                          },
                          decoration: const InputDecoration(
                            labelText: 'Leave Type',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Selected Range: ${_rangeStart != null ? _formatDate(_rangeStart.toString()) : 'Start Date'} - ${_rangeEnd != null ? _formatDate(_rangeEnd.toString()) : 'End Date'}',
                        ),
                        const SizedBox(height: 16),
                        TextField(
                          controller: _reasonController,
                          decoration: const InputDecoration(
                            labelText: 'Reason',
                            border: OutlineInputBorder(),
                          ),
                          maxLines: 3,
                        ),
                        if (_selectedLeaveType == 'Sick') ...[
                          const SizedBox(height: 16),
                          MedicalCertificateUploader(
                            onFileSelected: (file) {
                              setState(() => _medicalCertificate = file);
                            },
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Cancel'),
                      ),
                      const SizedBox(width: 8),
                      FilledButton(
                        onPressed: () async {
                          Navigator.pop(context);
                          await _submitLeaveRequest();
                        },
                        child: const Text('Submit'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
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
              return ListTile(
                title: Text(request['leave_type']?.toString() ?? 'Unknown'),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${_formatDate(request['start_date']?.toString())} to ${_formatDate(request['end_date']?.toString())}',
                    ),
                    if (request['medical_certificate_url'] != null)
                      TextButton.icon(
                        icon: const Icon(Icons.medical_services),
                        label: const Text('View Medical Certificate'),
                        onPressed: () {
                          // Implement view certificate functionality
                          // You can open the image in a dialog or navigate to a new screen
                        },
                      ),
                  ],
                ),
                trailing: _buildStatusChip(request['status']?.toString()),
              );
            },
          ),
        ],
      ),
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

  String _formatDate(String? dateString) {
  if (dateString == null) return 'N/A';
  try {
    // Parse the date string
    DateTime date = DateTime.parse(dateString);
    // Format it in a readable format (e.g., dd/MM/yyyy)
    return DateFormat('dd/MM/yyyy').format(date);
  } catch (e) {
    return dateString; // Fallback if parsing fails
  }
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
                    _buildCalendarCard(),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _showLeaveRequestDialog,
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