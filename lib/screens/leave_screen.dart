// lib/screens/leave_screen.dart
import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import '../services/api_service.dart';

class LeaveScreen extends StatefulWidget {
  final Map<String, dynamic> userDetails;

  const LeaveScreen({
    Key? key,
    required this.userDetails,
  }) : super(key: key);

  @override
  _LeaveScreenState createState() => _LeaveScreenState();
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

  @override
  void initState() {
    super.initState();
    _loadLeaveBalance();
    _loadLeaveRequests();
  }

  Future<void> _loadLeaveBalance() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final balance = await _apiService.getLeaveBalance(widget.userDetails['id']);
      setState(() {
        _leaveBalance = balance;
      });
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading leave balance: $e')),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _loadLeaveRequests() async {
    try {
      final requests = await _apiService.getLeaveRequests(widget.userDetails['id']);
      setState(() {
        _leaveRequests = requests;
      });
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading leave requests: $e')),
      );
    }
  }

  Future<void> _submitLeaveRequest() async {
    if (_rangeStart == null || _rangeEnd == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select date range')),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      await _apiService.submitLeaveRequest(
        userId: widget.userDetails['id'],
        leaveType: _selectedLeaveType,
        startDate: _rangeStart!,
        endDate: _rangeEnd!,
      );

      // Refresh data
      await _loadLeaveBalance();
      await _loadLeaveRequests();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Leave request submitted successfully')),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error submitting leave request: $e')),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Leave Management'),
        elevation: 0,
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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildLeaveBalanceCard(),
                    const SizedBox(height: 16),
                    _buildCalendarCard(),
                    const SizedBox(height: 16),
                    _buildRequestButton(),
                    const SizedBox(height: 16),
                    _buildLeaveRequestHistory(),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildLeaveBalanceCard() {
    return Card(
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
                _buildLeaveBalanceItem('Annual', _leaveBalance['annual'] ?? 0),
                _buildLeaveBalanceItem('Sick', _leaveBalance['sick'] ?? 0),
                _buildLeaveBalanceItem('Personal', _leaveBalance['personal'] ?? 0),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLeaveBalanceItem(String type, int days) {
    return Column(
      children: [
        CircleAvatar(
          radius: 25,
          backgroundColor: Colors.blue.withOpacity(0.2),
          child: Text(
            days.toString(),
            style: const TextStyle(
              color: Colors.blue,
              fontWeight: FontWeight.bold,
              fontSize: 18,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(type),
      ],
    );
  }

  Widget _buildCalendarCard() {
    return Card(
      child: TableCalendar(
        firstDay: DateTime.now(),
        lastDay: DateTime.now().add(const Duration(days: 365)),
        focusedDay: _focusedDay,
        selectedDayPredicate: (day) => isSameDay(_selectedDay, day),
        rangeStartDay: _rangeStart,
        rangeEndDay: _rangeEnd,
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
      ),
    );
  }

  Widget _buildRequestButton() {
    return ElevatedButton(
      onPressed: _isLoading ? null : _showLeaveRequestDialog,
      child: const Text('Request Leave'),
      style: ElevatedButton.styleFrom(
        padding: const EdgeInsets.symmetric(vertical: 16),
      ),
    );
  }

  Widget _buildLeaveRequestHistory() {
    if (_leaveRequests.isEmpty) {
      return Card(
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
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
            physics: NeverScrollableScrollPhysics(),
            itemCount: _leaveRequests.length,
            separatorBuilder: (context, index) => Divider(),
            itemBuilder: (context, index) {
              final request = _leaveRequests[index];
              return ListTile(
                title: Text(request['leave_type']),
                subtitle: Text(
                  '${request['start_date']} to ${request['end_date']}',
                ),
                trailing: _buildStatusChip(request['status']),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildStatusChip(String status) {
    Color chipColor;
    switch (status.toLowerCase()) {
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
        status,
        style: TextStyle(color: Colors.white),
      ),
      backgroundColor: chipColor,
    );
  }

  void _showLeaveRequestDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Request Leave'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
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
              decoration: const InputDecoration(labelText: 'Leave Type'),
            ),
            const SizedBox(height: 16),
            Text(
              'Selected Range: ${_rangeStart?.toString().split(' ')[0] ?? 'Start Date'} - ${_rangeEnd?.toString().split(' ')[0] ?? 'End Date'}',
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              if (_rangeStart != null && _rangeEnd != null) {
                Navigator.pop(context);
                await _submitLeaveRequest();
              } else {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Please select date range')),
                );
              }
            },
            child: const Text('Submit'),
          ),
        ],
      ),
    );
  }
}