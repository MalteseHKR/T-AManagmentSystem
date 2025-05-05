// lib/widgets/leave_request_widget.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:intl/intl.dart';
import 'package:time_attendance_app/util/simplified_certificate_helper.dart'; // FIXED: Using simplified helper
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../services/session_service.dart';
import '../widgets/medical_certificate_uploader.dart';

class LeaveRequestWidget extends StatefulWidget {
  final Function onLeaveRequested;
  final Map<String, dynamic>? leaveRequestToEdit;
  
  const LeaveRequestWidget({
    Key? key,
    required this.onLeaveRequested,
    this.leaveRequestToEdit,
  }) : super(key: key);

  @override
  State<LeaveRequestWidget> createState() => _LeaveRequestWidgetState();
}

class _LeaveRequestWidgetState extends State<LeaveRequestWidget> {
  final _apiService = ApiService();
  final _sessionService = SessionService();
  final _notificationService = NotificationService();
  
  String _selectedLeaveType = '';
  DateTime? _rangeStart;
  DateTime? _rangeEnd;
  final _reasonController = TextEditingController();
  File? _medicalCertificate;
  String? _existingCertificateUrl;
  bool _isFullDay = true;
  TimeOfDay _startTime = const TimeOfDay(hour: 9, minute: 0);
  TimeOfDay _endTime = const TimeOfDay(hour: 17, minute: 0);
  bool _isSubmitting = false;
  DateTime _focusedDay = DateTime.now();
  CalendarFormat _calendarFormat = CalendarFormat.month;
  
  // Add state variable for single day vs date range
  bool _isSingleDaySelection = true;
  
  // For sick leave temporary request
  bool _isSickLeaveRequested = false;
  int? _sickLeaveRequestId;
  DateTime? _sickLeaveRequestDate;
  
  @override
  void initState() {
    super.initState();
    
    // If editing an existing request, populate the fields
    if (widget.leaveRequestToEdit != null) {
      _populateFields();
    }
  }
  
  void _populateFields() {
    final request = widget.leaveRequestToEdit!;
    
    setState(() {
      _selectedLeaveType = request['leave_type'] ?? '';
      
      // Parse dates
      if (request['start_date'] != null) {
        _rangeStart = DateTime.parse(request['start_date']);
        _focusedDay = _rangeStart!;
      }
      if (request['end_date'] != null) {
        _rangeEnd = DateTime.parse(request['end_date']);
      }
      
      // Determine if it's a single day request
      if (_rangeStart != null && _rangeEnd != null) {
        final isSameDay = _rangeStart!.year == _rangeEnd!.year && 
                         _rangeStart!.month == _rangeEnd!.month && 
                         _rangeStart!.day == _rangeEnd!.day;
        _isSingleDaySelection = isSameDay;
      }
      
      // Parse reason
      _reasonController.text = request['reason'] ?? '';
      
      // Parse full day vs partial day
      _isFullDay = request['is_full_day'] ?? true;
      
      // If it's partial day, enforce single day selection
      if (!_isFullDay) {
        _isSingleDaySelection = true;
      }
      
      // Parse start and end times if partial day
      if (!_isFullDay && request['start_time'] != null) {
        final List<String> startTimeParts = request['start_time'].split(':');
        _startTime = TimeOfDay(
          hour: int.parse(startTimeParts[0]),
          minute: int.parse(startTimeParts[1]),
        );
      }
      
      if (!_isFullDay && request['end_time'] != null) {
        final List<String> endTimeParts = request['end_time'].split(':');
        _endTime = TimeOfDay(
          hour: int.parse(endTimeParts[0]),
          minute: int.parse(endTimeParts[1]),
        );
      }
      
      // For sick leave, check if it's in progress
      if (_selectedLeaveType == 'Sick' && request['status'] == 'Pending Certificate') {
        _isSickLeaveRequested = true;
        _sickLeaveRequestId = request['id'] ?? request['request_id'];
        _sickLeaveRequestDate = request['request_date'] != null 
            ? DateTime.parse(request['request_date']) 
            : DateTime.now().subtract(const Duration(days: 1));
      }
      
      // Check for existing certificate
      if (request['medical_certificate'] != null && 
          request['medical_certificate'].toString().isNotEmpty) {
        _existingCertificateUrl = request['medical_certificate'];
      }
    });
  }
  
  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }
  
  void _showLeaveTypeSelection() {
    _sessionService.userActivity();
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Select Leave Type'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _buildLeaveTypeOption('Annual'),
            _buildLeaveTypeOption('Sick'),
            _buildLeaveTypeOption('Personal'),
          ],
        ),
      ),
    );
  }
  
  Widget _buildLeaveTypeOption(String leaveType) {
    return ListTile(
      title: Text(leaveType),
      onTap: () {
        Navigator.pop(context);
        setState(() {
          _selectedLeaveType = leaveType;
          
          // Reset date range when changing leave type
          _rangeStart = null;
          _rangeEnd = null;
        });
        
        // For sick leave, ask if they want to request now and upload later
        if (leaveType == 'Sick') {
          _showSickLeaveOptions();
        }
      },
    );
  }
  
  void _showSickLeaveOptions() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sick Leave Request'),
        content: const Text(
          'Do you want to submit a preliminary sick leave request now and upload '
          'medical certificate and select dates within 10 days?'
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              // User wants to fill everything now
            },
            child: const Text('No, I\'ll fill everything now'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(context);
              _requestPreliminarySickLeave();
            },
            child: const Text('Yes, submit preliminary request'),
          ),
        ],
      ),
    );
  }
  
  Future<void> _requestPreliminarySickLeave() async {
    _sessionService.userActivity();
    
    setState(() {
      _isSubmitting = true;
    });
    
    try {
      // Call the API to create preliminary sick leave request
      final response = await _apiService.submitPreliminarySickLeave(
        reason: _reasonController.text.isNotEmpty 
            ? _reasonController.text
            : 'Sick leave - medical certificate to be provided',
      );
      
      // Get the request ID for notifications
      final int requestId = response['id'] ?? 0;
      
      // Schedule notifications for the next 10 days
      if (requestId > 0) {
        await _notificationService.scheduleMedicalCertificateReminders(requestId);
        
        // Update the state to show pending status
        setState(() {
          _isSickLeaveRequested = true;
          _sickLeaveRequestId = requestId;
          _sickLeaveRequestDate = DateTime.now();
          _isSubmitting = false;
        });
      }
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Sick leave request submitted. Please upload medical certificate and select dates within 10 days.'),
            backgroundColor: Colors.green,
          ),
        );
      }
      
      // Call the callback to refresh parent
      widget.onLeaveRequested();
      
    } catch (e) {
      print('Error submitting preliminary sick leave: $e');
      setState(() {
        _isSubmitting = false;
      });
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error submitting sick leave request: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
  
  // Method to view a medical certificate - MODIFIED to use simplified helper
  void _viewMedicalCertificate(String? certificateUrl) {
    _sessionService.userActivity();
    
    if (certificateUrl == null || certificateUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No certificate available to view')),
      );
      return;
    }
    
    // Create a "fake" leave request map with the certificate URL
    Map<String, dynamic> tempRequest = {
      'request_id': 'preview',
      'medical_certificate': certificateUrl
    };
    
    // Use the simplified helper
    CertificateViewer.viewSickLeaveDocument(context, tempRequest, _apiService);
  }
  
  Future<void> _completeSickLeaveRequest() async {
    _sessionService.userActivity();
    
    if (_rangeStart == null || _rangeEnd == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select date range')),
      );
      return;
    }
    
    if (_medicalCertificate == null && _existingCertificateUrl == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please upload a medical certificate')),
      );
      return;
    }
    
    setState(() {
      _isSubmitting = true;
    });
    
    try {
      String? certificateUrl = _existingCertificateUrl;
      
      // Upload the medical certificate if a new one is selected
      if (_medicalCertificate != null) {
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
            _isSubmitting = false;
          });
          return;
        }
      }
      
      // Set the reason to the standardized message
      _reasonController.text = "Completed sick leave with medical certificate provided.";
      
      // Complete the sick leave request with date range and certificate
      await _apiService.completeSickLeaveRequest(
        requestId: _sickLeaveRequestId!,
        startDate: _rangeStart!,
        endDate: _rangeEnd!,
        certificateUrl: certificateUrl,
        isFullDay: _isFullDay,
        startTime: _isFullDay ? null : _startTime,
        endTime: _isFullDay ? null : _endTime,
      );
      
      // Cancel the scheduled notifications since we've completed the request
      if (_sickLeaveRequestId != null) {
        await _notificationService.cancelMedicalCertificateReminders(_sickLeaveRequestId!);
      }
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Sick leave request completed successfully'),
            backgroundColor: Colors.green,
          ),
        );
      }
      
      // Call the callback to refresh parent
      widget.onLeaveRequested();
      
    } catch (e) {
      print('Error completing sick leave request: $e');
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error completing sick leave request: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  Future<void> _submitRegularLeaveRequest() async {
    _sessionService.userActivity();
    
    if (_selectedLeaveType.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select leave type')),
      );
      return;
    }
    
    if (_rangeStart == null || _rangeEnd == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select date range')),
      );
      return;
    }
    
    // For sick leave, medical certificate is required
    if (_selectedLeaveType == 'Sick' && _medicalCertificate == null && _existingCertificateUrl == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please upload a medical certificate for sick leave')),
      );
      return;
    }
    
    if (!_isFullDay && (_startTime == null || _endTime == null)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select start and end time')),
      );
      return;
    }
    
    setState(() {
      _isSubmitting = true;
    });
    
    try {
      String? certificateUrl = _existingCertificateUrl;
      
      // Upload medical certificate if provided
      if (_medicalCertificate != null) {
        try {
          certificateUrl = await _apiService.uploadMedicalCertificate(_medicalCertificate!);
          print('Uploaded Certificate URL: $certificateUrl');
          
          // For sick leave, update reason to indicate certificate was provided
          if (_selectedLeaveType == 'Sick') {
            _reasonController.text = "Completed sick leave with medical certificate provided.";
          }
        } catch (uploadError) {
          print('Medical Certificate Upload Error: $uploadError');
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Failed to upload medical certificate: $uploadError'),
              backgroundColor: Colors.red,
            ),
          );
          setState(() {
            _isSubmitting = false;
          });
          return;
        }
      }
      
      // For editing an existing request
      if (widget.leaveRequestToEdit != null) {
        String? certificateUrl = _existingCertificateUrl;
        
        // Upload medical certificate if provided
        if (_medicalCertificate != null) {
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
              _isSubmitting = false;
            });
            return;
          }
        }
        
        await _apiService.updateLeaveRequest(
          requestId: widget.leaveRequestToEdit!['id'] ?? widget.leaveRequestToEdit!['request_id'],
          leaveType: _selectedLeaveType,
          startDate: _rangeStart!,
          endDate: _rangeEnd!,
          reason: _reasonController.text.trim(),
          isFullDay: _isFullDay,
          startTime: _isFullDay ? null : _startTime,
          endTime: _isFullDay ? null : _endTime,
          certificateUrl: certificateUrl, // Add this parameter
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Leave request updated successfully'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        // For creating a new regular leave request
        await _apiService.submitLeaveRequest(
          leaveType: _selectedLeaveType,
          startDate: _rangeStart!,
          endDate: _rangeEnd!,
          reason: _reasonController.text.trim(),
          certificateUrl: certificateUrl,
          isFullDay: _isFullDay,
          startTime: _isFullDay ? null : _startTime,
          endTime: _isFullDay ? null : _endTime,
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Leave request submitted successfully'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
      
      // Call the callback to refresh parent
      widget.onLeaveRequested();
      
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
          _isSubmitting = false;
        });
      }
    }
  }
  
  Future<void> _selectTime(BuildContext context, bool isStartTime) async {
    _sessionService.userActivity();
    
    final TimeOfDay? picked = await showTimePicker(
      context: context,
      initialTime: isStartTime ? _startTime : _endTime,
    );
    
    if (picked != null) {
      setState(() {
        if (isStartTime) {
          _startTime = picked;
        } else {
          _endTime = picked;
        }
      });
    }
  }

  // Enhanced day type selector with duration type (single day vs date range)
  Widget _buildDayTypeSelector() {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Leave Duration',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),
            // First set of radio buttons for Single Day vs Date Range
            if (_isFullDay) ...[
              Row(
                children: [
                  Expanded(
                    child: RadioListTile<bool>(
                      title: const Text('Single Day'),
                      value: true,
                      groupValue: _isSingleDaySelection,
                      onChanged: (value) {
                        _sessionService.userActivity();
                        setState(() {
                          _isSingleDaySelection = value!;
                          // If switching to single day with a date range, set end date to start date
                          if (_isSingleDaySelection && _rangeStart != null) {
                            _rangeEnd = _rangeStart;
                          }
                        });
                      },
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                  Expanded(
                    child: RadioListTile<bool>(
                      title: const Text('Date Range'),
                      value: false,
                      groupValue: _isSingleDaySelection,
                      onChanged: (value) {
                        _sessionService.userActivity();
                        setState(() {
                          _isSingleDaySelection = value!;
                          // If switching to date range with a single date, reset end date
                          if (!_isSingleDaySelection && _rangeStart != null) {
                            _rangeEnd = null;
                          }
                        });
                      },
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                ],
              ),
              const Divider(),
            ],
            
            // Second set of radio buttons for Full Day vs Partial Day
            Row(
              children: [
                Expanded(
                  child: RadioListTile<bool>(
                    title: const Text('Full Day'),
                    value: true,
                    groupValue: _isFullDay,
                    onChanged: (value) {
                      _sessionService.userActivity();
                      setState(() {
                        _isFullDay = value!;
                      });
                    },
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
                Expanded(
                  child: RadioListTile<bool>(
                    title: const Text('Partial Day'),
                    value: false,
                    groupValue: _isFullDay,
                    onChanged: (value) {
                      _sessionService.userActivity();
                      setState(() {
                        _isFullDay = value!;
                        
                        // For partial days, force single day selection and set both dates the same
                        if (value == false) {
                          _isSingleDaySelection = true;
                          if (_rangeStart != null) {
                            _rangeEnd = _rangeStart;
                          }
                        }
                      });
                    },
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ],
            ),
            
            // Time selection for partial day
            if (!_isFullDay) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () => _selectTime(context, true),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Start Time',
                          border: OutlineInputBorder(),
                          contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                        child: Text(
                          '${_startTime.hour.toString().padLeft(2, '0')}:${_startTime.minute.toString().padLeft(2, '0')}',
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: InkWell(
                      onTap: () => _selectTime(context, false),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'End Time',
                          border: OutlineInputBorder(),
                          contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                        child: Text(
                          '${_endTime.hour.toString().padLeft(2, '0')}:${_endTime.minute.toString().padLeft(2, '0')}',
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
  
  Widget _buildCalendarCard() {
    // Set the correct range selection mode based on our selection type
    RangeSelectionMode selectionMode;
    
    if (!_isFullDay || _isSingleDaySelection) {
      // For partial day or single day selection, disable range selection
      selectionMode = RangeSelectionMode.disabled;
    } else {
      // For full day range selection, enforce range selection
      selectionMode = RangeSelectionMode.enforced;
    }
    
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  _isSingleDaySelection ? 'Select Date' : 'Select Date Range',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Spacer(),
                IconButton(
                  icon: Icon(Icons.calendar_view_month),
                  onPressed: () {
                    setState(() {
                      _calendarFormat = CalendarFormat.month;
                    });
                  },
                ),
                IconButton(
                  icon: Icon(Icons.calendar_view_week),
                  onPressed: () {
                    setState(() {
                      _calendarFormat = CalendarFormat.twoWeeks;
                    });
                  },
                ),
              ],
            ),
            Container(
              height: 360, // Fixed height for better scrolling
              child: SingleChildScrollView(
                child: TableCalendar(
                  firstDay: _selectedLeaveType == 'Sick' ? DateTime.now().subtract(const Duration(days: 30)) : DateTime.now(),
                  lastDay: DateTime.now().add(const Duration(days: 365)),
                  focusedDay: _focusedDay,
                  selectedDayPredicate: (day) {
                    return _rangeStart != null && isSameDay(day, _rangeStart);
                  },
                  rangeStartDay: _rangeStart,
                  rangeEndDay: _rangeEnd,
                  calendarFormat: _calendarFormat,
                  rangeSelectionMode: selectionMode,
                  onDaySelected: (selectedDay, focusedDay) {
                    _sessionService.userActivity();
                    
                    setState(() {
                      _rangeStart = selectedDay;
                      _focusedDay = focusedDay;
                      
                      // If single day selection or partial day, set end date same as start date
                      if (_isSingleDaySelection || !_isFullDay) {
                        _rangeEnd = selectedDay;
                      } else {
                        // For full day date range, reset end date when selecting a new start date
                        _rangeEnd = null;
                      }
                    });
                  },
                  onFormatChanged: (format) {
                    setState(() {
                      _calendarFormat = format;
                    });
                  },
                  onRangeSelected: (start, end, focusedDay) {
                    // Only allow range selection for full day date range
                    if (!_isSingleDaySelection && _isFullDay) {
                      _sessionService.userActivity();
                      
                      setState(() {
                        _rangeStart = start;
                        _rangeEnd = end;
                        _focusedDay = focusedDay;
                      });
                    }
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
                    titleCentered: true,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.blue.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: _isSingleDaySelection 
                ? Text(
                    'Selected Date: ${_rangeStart != null ? DateFormat('dd/MM/yyyy').format(_rangeStart!) : 'Select a date'}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                    textAlign: TextAlign.center,
                  )
                : Text(
                    'Selected Range: ${_rangeStart != null ? DateFormat('dd/MM/yyyy').format(_rangeStart!) : 'Start Date'} - ${_rangeEnd != null ? DateFormat('dd/MM/yyyy').format(_rangeEnd!) : 'End Date'}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                    textAlign: TextAlign.center,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSickLeaveCompletion() {
    // Calculate days remaining
    final daysElapsed = DateTime.now().difference(_sickLeaveRequestDate!).inDays;
    final daysRemaining = 10 - daysElapsed;
    
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            elevation: 4,
            color: Colors.amber.shade100,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                children: [
                  const Text(
                    'Complete Your Sick Leave Request',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'You have $daysRemaining days remaining to upload your medical certificate and select dates.',
                    style: TextStyle(
                      color: daysRemaining <= 3 ? Colors.red : Colors.black87,
                      fontWeight: daysRemaining <= 3 ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ],
              ),
            ),
          ),
          
          const SizedBox(height: 16),
          
          // Day Type selector moved above calendar
          _buildDayTypeSelector(),
          
          const SizedBox(height: 16),
          
          _buildCalendarCard(),
          
          const SizedBox(height: 16),
          
          // Medical Certificate Uploader
          Card(
            elevation: 4,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Medical Certificate',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  
                  // If there's an existing certificate, show view option
                  if (_existingCertificateUrl != null) ...[
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Certificate already uploaded',
                            style: TextStyle(color: Colors.green[700]),
                          ),
                        ),
                        OutlinedButton.icon(
                          icon: const Icon(Icons.visibility),
                          label: const Text('View'),
                          onPressed: () => _viewMedicalCertificate(_existingCertificateUrl),
                        ),
                        const SizedBox(width: 8),
                        OutlinedButton.icon(
                          icon: const Icon(Icons.file_upload),
                          label: const Text('Replace'),
                          onPressed: () {
                            setState(() {
                              _existingCertificateUrl = null;
                            });
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                  ] else if (_medicalCertificate != null) ...[
                    // Preview selected certificate
                    Text('Selected Certificate Preview:',
                      style: TextStyle(fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 8),
                    Stack(
                      alignment: Alignment.topRight,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.file(
                            _medicalCertificate!,
                            height: 150,
                            width: double.infinity,
                            fit: BoxFit.cover,
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.clear, color: Colors.red),
                          onPressed: () {
                            setState(() {
                              _medicalCertificate = null;
                            });
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                  ] else ...[
                    // Standard uploader when no certificate is selected
                    MedicalCertificateUploader(
                      onFileSelected: (file) {
                        _sessionService.userActivity();
                        setState(() => _medicalCertificate = file);
                      },
                    ),
                  ],
                ],
              ),
            ),
          ),
          
          const SizedBox(height: 16),
          
          // Notice that the reason will be automatically set
          Card(
            elevation: 4,
            color: Colors.blue.shade50,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Note:',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'When you complete this sick leave request, the reason will be set to "Completed sick leave with medical certificate provided."',
                    style: TextStyle(fontStyle: FontStyle.italic),
                  ),
                ],
              ),
            ),
          ),
          
          const SizedBox(height: 24),
          
          // Submit Button
          FilledButton(
            onPressed: _isSubmitting ? null : _completeSickLeaveRequest,
            style: FilledButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
            child: _isSubmitting
                ? const CircularProgressIndicator(color: Colors.white)
                : const Text('Complete Sick Leave Request'),
          ),
        ],
      ),
    );
  }
  
  @override
  Widget build(BuildContext context) {
    // If it's a sick leave that's already requested but not completed
    if (_isSickLeaveRequested && _selectedLeaveType == 'Sick') {
      return _buildSickLeaveCompletion();
    }
    
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Leave Type Selection
          Card(
            elevation: 4,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Leave Type',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  InkWell(
                    onTap: _showLeaveTypeSelection,
                    child: InputDecorator(
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      ),
                      child: Text(
                        _selectedLeaveType.isEmpty ? 'Select Leave Type' : _selectedLeaveType,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          
          if (_selectedLeaveType.isNotEmpty) ...[
            const SizedBox(height: 16),
            
            // Day Type selector moved above calendar
            _buildDayTypeSelector(),
            
            const SizedBox(height: 16),
            
            // Calendar for date selection
            _buildCalendarCard(),
            
            const SizedBox(height: 16),
            
            // Show Medical Certificate uploader for Sick leave
            if (_selectedLeaveType == 'Sick') ...[
              Card(
                elevation: 4,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Medical Certificate',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 12),
                      
                      // If there's an existing certificate, show view option
                      if (_existingCertificateUrl != null) ...[
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                'Certificate already uploaded',
                                style: TextStyle(color: Colors.green[700]),
                              ),
                            ),
                            OutlinedButton.icon(
                              icon: const Icon(Icons.visibility),
                              label: const Text('View'),
                              onPressed: () => _viewMedicalCertificate(_existingCertificateUrl),
                            ),
                            const SizedBox(width: 8),
                            OutlinedButton.icon(
                              icon: const Icon(Icons.file_upload),
                              label: const Text('Replace'),
                              onPressed: () {
                                setState(() {
                                  _existingCertificateUrl = null;
                                });
                              },
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                      ] else ...[
                        MedicalCertificateUploader(
                          onFileSelected: (file) {
                            _sessionService.userActivity();
                            setState(() => _medicalCertificate = file);
                          },
                        ),
                      ],
                      
                      // Preview selected certificate if available
                      if (_medicalCertificate != null) ...[
                        const SizedBox(height: 16),
                        Text('Selected Certificate Preview:',
                          style: TextStyle(fontWeight: FontWeight.w500),
                        ),
                        const SizedBox(height: 8),
                        Stack(
                          alignment: Alignment.topRight,
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.file(
                                _medicalCertificate!,
                                height: 150,
                                width: double.infinity,
                                fit: BoxFit.cover,
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.clear, color: Colors.red),
                              onPressed: () {
                                setState(() {
                                  _medicalCertificate = null;
                                });
                              },
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
            ],
            
            // Reason Text Field
            Card(
              elevation: 4,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Reason for Leave',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _reasonController,
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                        hintText: 'Enter reason for leave request',
                      ),
                      maxLines: 3,
                      onChanged: (_) {
                        _sessionService.userActivity();
                      },
                    ),
                  ],
                ),
              ),
            ),
            
            const SizedBox(height: 24),
            
            // Submit Button
            FilledButton(
              onPressed: _isSubmitting ? null : _submitRegularLeaveRequest,
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
              child: _isSubmitting
                  ? const CircularProgressIndicator(color: Colors.white)
                  : Text(widget.leaveRequestToEdit != null ? 'Update Leave Request' : 'Submit Leave Request'),
            ),
          ],
        ],
      ),
    );
  }
}