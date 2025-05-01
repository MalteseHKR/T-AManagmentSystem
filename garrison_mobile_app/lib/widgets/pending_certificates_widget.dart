// lib/widgets/pending_certificates_widget.dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_service.dart';
import '../services/session_service.dart';
import '../services/timezone_service.dart';

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
  final TimezoneService _timezoneService = TimezoneService();
  final ImagePicker _imagePicker = ImagePicker();
  
  List<Map<String, dynamic>> _pendingCertificates = [];
  bool _isLoading = true;
  
  @override
  void initState() {
    super.initState();
    _loadPendingCertificates();
  }
  
  Future<void> _loadPendingCertificates() async {
    try {
      final userId = widget.userDetails['id'].toString();
      final pendingRequests = await _apiService.getPendingCertificateRequests(userId);
      
      if (mounted) {
        setState(() {
          _pendingCertificates = pendingRequests;
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Error loading pending certificates: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
  
  Future<void> _completeSickLeave(Map<String, dynamic> request) async {
    _sessionService.userActivity();
    
    // Date pickers
    final DateTime now = DateTime.now();
    
    DateTime? startDate;
    DateTime? endDate;
    bool isFullDay = true;
    // Add a new variable for single day vs range selection
    bool isSingleDaySelection = true;
    TimeOfDay? startTime;
    TimeOfDay? endTime;
    File? certificateFile;
    String? certificateUrl;
    
    // Show complete sick leave form dialog
    if (!mounted) return;
    
    final bool? completed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: const Text('Complete Sick Leave'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Please provide the required information:'),
                    const SizedBox(height: 16),
                    
                    // NEW: Add Leave Duration selector (Single Day vs Date Range)
                    Card(
                      elevation: 4,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(12.0),
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
                            // Single Day vs Date Range radio buttons
                            Row(
                              children: [
                                Expanded(
                                  child: RadioListTile<bool>(
                                    title: const Text('Single Day'),
                                    value: true,
                                    groupValue: isSingleDaySelection,
                                    onChanged: (value) {
                                      _sessionService.userActivity();
                                      setState(() {
                                        isSingleDaySelection = value!;
                                        // If switching to single day with a date range, set end date to start date
                                        if (isSingleDaySelection && startDate != null) {
                                          endDate = startDate;
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
                                    groupValue: isSingleDaySelection,
                                    onChanged: (value) {
                                      _sessionService.userActivity();
                                      setState(() {
                                        isSingleDaySelection = value!;
                                        // If switching to date range with a single date, reset end date
                                        if (!isSingleDaySelection && startDate != null) {
                                          endDate = null;
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
                            
                            // Full Day vs Partial Day radio buttons
                            Row(
                              children: [
                                Expanded(
                                  child: RadioListTile<bool>(
                                    title: const Text('Full Day'),
                                    value: true,
                                    groupValue: isFullDay,
                                    onChanged: (value) {
                                      _sessionService.userActivity();
                                      setState(() {
                                        isFullDay = value!;
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
                                    groupValue: isFullDay,
                                    onChanged: (value) {
                                      _sessionService.userActivity();
                                      setState(() {
                                        isFullDay = value!;
                                        
                                        // For partial days, force single day selection
                                        if (value == false) {
                                          isSingleDaySelection = true;
                                          if (startDate != null) {
                                            endDate = startDate;
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
                          ],
                        ),
                      ),
                    ),
                    
                    const SizedBox(height: 16),

                    // Date range selection - modified for single/range
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(isSingleDaySelection ? 'Date:' : 'Start Date:'),
                              OutlinedButton.icon(
                                icon: const Icon(Icons.calendar_today),
                                label: Text(startDate != null 
                                  ? DateFormat('dd/MM/yyyy').format(startDate!)
                                  : 'Select'),
                                onPressed: () async {
                                  _sessionService.userActivity();
                                  final date = await showDatePicker(
                                    context: context,
                                    initialDate: now,
                                    firstDate: now.subtract(const Duration(days: 30)),
                                    lastDate: now.add(const Duration(days: 365)), // Allow future dates up to a year ahead
                                  );
                                  if (date != null) {
                                    setState(() {
                                      startDate = date;
                                      // Set end date to start date if single day selection
                                      if (isSingleDaySelection) {
                                        endDate = date;
                                      } else if (endDate == null || endDate!.isBefore(startDate!)) {
                                        // For range selection, adjust end date if necessary
                                        endDate = date;
                                      }
                                    });
                                  }
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('End Date:'),
                              OutlinedButton.icon(
                                icon: const Icon(Icons.calendar_today),
                                label: Text(endDate != null 
                                  ? DateFormat('dd/MM/yyyy').format(endDate!)
                                  : 'Select'),
                                // Disable end date button for single day selection
                                onPressed: isSingleDaySelection ? null : () async {
                                  _sessionService.userActivity();
                                  // End date should be no earlier than start date
                                  final minDate = startDate ?? now.subtract(const Duration(days: 30));
                                  final date = await showDatePicker(
                                    context: context,
                                    initialDate: endDate ?? minDate,
                                    firstDate: minDate,
                                    lastDate: now.add(const Duration(days: 365)), // Allow future dates up to a year ahead
                                  );
                                  if (date != null) {
                                    setState(() {
                                      endDate = date;
                                    });
                                  }
                                },
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    
                    // Show time pickers for partial day leave
                    if (!isFullDay) ...[
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Start Time:'),
                                OutlinedButton.icon(
                                  icon: const Icon(Icons.access_time),
                                  label: Text(startTime != null 
                                    ? startTime!.format(context)
                                    : 'Select'),
                                  onPressed: () async {
                                    _sessionService.userActivity();
                                    final time = await showTimePicker(
                                      context: context,
                                      initialTime: startTime ?? const TimeOfDay(hour: 8, minute: 0),
                                    );
                                    if (time != null) {
                                      setState(() {
                                        startTime = time;
                                        // Set end time if not set
                                        if (endTime == null) {
                                          // Default to 4 hours later
                                          endTime = TimeOfDay(
                                            hour: (time.hour + 4) % 24,
                                            minute: time.minute,
                                          );
                                        }
                                      });
                                    }
                                  },
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('End Time:'),
                                OutlinedButton.icon(
                                  icon: const Icon(Icons.access_time),
                                  label: Text(endTime != null 
                                    ? endTime!.format(context)
                                    : 'Select'),
                                  onPressed: () async {
                                    _sessionService.userActivity();
                                    final time = await showTimePicker(
                                      context: context,
                                      initialTime: endTime ?? const TimeOfDay(hour: 17, minute: 0),
                                    );
                                    if (time != null) {
                                      setState(() {
                                        endTime = time;
                                      });
                                    }
                                  },
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ],
                    
                    const SizedBox(height: 16),
                    // Medical certificate upload
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Medical Certificate:'),
                        const SizedBox(height: 8),
                        certificateFile != null
                          ? Padding(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              child: Stack(
                                alignment: Alignment.topRight,
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(8),
                                    child: Image.file(
                                      certificateFile!,
                                      height: 200, // Increased height from 100 to 200
                                      width: double.infinity,
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.close, color: Colors.red),
                                    onPressed: () {
                                      _sessionService.userActivity();
                                      setState(() {
                                        certificateFile = null;
                                      });
                                    },
                                  ),
                                ],
                              ),
                            )
                          : Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton.icon(
                                    icon: const Icon(Icons.photo_camera),
                                    label: const Text('Take Photo'),
                                    onPressed: () async {
                                      _sessionService.userActivity();
                                      final XFile? image = await _imagePicker.pickImage(
                                        source: ImageSource.camera,
                                        imageQuality: 70,
                                        preferredCameraDevice: CameraDevice.rear,
                                      );
                                      if (image != null) {
                                        setState(() {
                                          certificateFile = File(image.path);
                                        });
                                      }
                                    },
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: OutlinedButton.icon(
                                    icon: const Icon(Icons.file_upload),
                                    label: const Text('Upload'),
                                    onPressed: () async {
                                      _sessionService.userActivity();
                                      final XFile? image = await _imagePicker.pickImage(
                                        source: ImageSource.gallery,
                                        imageQuality: 70,
                                      );
                                      if (image != null) {
                                        setState(() {
                                          certificateFile = File(image.path);
                                        });
                                      }
                                    },
                                  ),
                                ),
                              ],
                            ),
                      ],
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context, false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () async {
                    // Validate form
                    if (startDate == null || endDate == null) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please select dates')),
                      );
                      return;
                    }
                    if (!isFullDay && (startTime == null || endTime == null)) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please select start and end times for partial day leave')),
                      );
                      return;
                    }
                    if (certificateFile == null) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please upload a medical certificate')),
                      );
                      return;
                    }
                    
                    // Show loading
                    showDialog(
                      context: context,
                      barrierDismissible: false,
                      builder: (BuildContext context) {
                        return const Dialog(
                          child: Padding(
                            padding: EdgeInsets.all(16),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                CircularProgressIndicator(),
                                SizedBox(height: 16),
                                Text('Uploading certificate and completing leave request...'),
                              ],
                            ),
                          ),
                        );
                      },
                    );
                    
                    try {
                      // 1. Upload the certificate
                      certificateUrl = await _apiService.uploadMedicalCertificate(certificateFile!);
                      
                      // 2. Complete the sick leave request
                      await _apiService.completeSickLeaveRequest(
                        requestId: int.parse(request['request_id'].toString()),
                        startDate: startDate!,
                        endDate: endDate!,
                        certificateUrl: certificateUrl,
                        isFullDay: isFullDay,
                        startTime: startTime,
                        endTime: endTime,
                      );
                      
                      // Close the loading dialog
                      if (mounted && Navigator.of(context).canPop()) {
                        Navigator.pop(context);  // Close loading dialog
                      }
                      
                      // Close the form dialog with success
                      Navigator.pop(context, true);
                    } catch (e) {
                      // Close the loading dialog
                      if (mounted && Navigator.of(context).canPop()) {
                        Navigator.pop(context);  // Close loading dialog
                      }
                      
                      // Show error message
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Error: $e')),
                        );
                      }
                    }
                  },
                  child: const Text('Submit'),
                ),
              ],
            );
          }
        );
      },
    );
    
    if (completed == true) {
      // Reload pending certificates
      await _loadPendingCertificates();
      
      // Call the callback to refresh parent screen
      widget.onCertificateCompleted();
      
      // Show success message
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Sick leave request completed successfully'),
            backgroundColor: Colors.green,
          ),
        );
      }
    }
  }
  
  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const SizedBox(
        height: 100,
        child: Center(child: CircularProgressIndicator()),
      );
    }
    
    // If no pending certificates, don't show anything
    if (_pendingCertificates.isEmpty) {
      return const SizedBox.shrink();
    }
    
    // Show the pending certificates card
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      color: Colors.orange.shade50,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.warning, color: Colors.orange),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text(
                    'Pending Medical Certificates',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: Colors.deepOrange,
                    ),
                  ),
                ),
                Text('${_pendingCertificates.length}'),
              ],
            ),
            const Divider(),
            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _pendingCertificates.length,
              itemBuilder: (context, index) {
                final request = _pendingCertificates[index];
                final requestDate = _formatDate(request['request_date']);
                
                return ListTile(
                  title: Text('Sick Leave (${request['leave_type']})'),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Requested on $requestDate'),
                      if (request['reason'] != null && request['reason'].toString().isNotEmpty)
                        Text(
                          'Reason: ${request['reason']}',
                          style: TextStyle(
                            color: Colors.grey[600],
                            fontSize: 12,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                    ],
                  ),
                  trailing: OutlinedButton(
                    onPressed: () => _completeSickLeave(request),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.deepOrange,
                    ),
                    child: const Text('Complete'),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
  
  String _formatDate(String? dateString) {
    return _timezoneService.formatDateWithOffset(dateString, format: 'dd/MM/yyyy');
  }
}