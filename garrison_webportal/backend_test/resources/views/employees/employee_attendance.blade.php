<!-- filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/resources/views/employee_attendance.blade.php -->
@extends('layouts.app')

@section('title', 'Employee Attendance - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="attendance-header mb-1">Employee Attendance</h1>
            <p class="employee-name mb-0">
                <i class="fas fa-user me-2"></i>{{ $employee->first_name }} {{ $employee->surname }}
            </p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="javascript:history.back()" class="btn btn-secondary attendance-btn">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
            <button id="printReport" class="btn btn-primary attendance-btn">
                <i class="fas fa-print me-2"></i> Print Report
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Attendance Calendar -->
            <div class="card attendance-card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Attendance Calendar</h5>
                    <div class="calendar-controls">
                        <button id="prevMonth" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="currentMonthDisplay" class="mx-2"></span>
                        <button id="nextMonth" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body calendar-container p-0">
                    <div id="attendanceCalendar"></div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex flex-wrap calendar-legend">
                        <div class="legend-item me-3 mb-2">
                            <span class="legend-color" style="background-color: #28a745;"></span>
                            <span class="legend-text">Clock In</span>
                        </div>
                        <div class="legend-item me-3 mb-2">
                            <span class="legend-color" style="background-color: #dc3545;"></span>
                            <span class="legend-text">Clock Out</span>
                        </div>
                        <div class="legend-item me-3 mb-2">
                            <span class="legend-color" style="background-color: #ffc107;"></span>
                            <span class="legend-text">Partial Day</span>
                        </div>
                        <div class="legend-item mb-2">
                            <span class="legend-color" style="background-color: #17a2b8;"></span>
                            <span class="legend-text">Weekend</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Statistics Card -->
            <div class="card attendance-card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Attendance Stats</h5>
                </div>
                <div class="card-body">
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-icon bg-success">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">{{ $attendanceStats['totalDays'] ?? 0 }}</span>
                                <span class="stat-label">Total Days</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon bg-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">{{ $attendanceStats['onTime'] ?? 0 }}</span>
                                <span class="stat-label">On Time</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon bg-warning">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">{{ $attendanceStats['late'] ?? 0 }}</span>
                                <span class="stat-label">Late Arrivals</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon bg-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">{{ $attendanceStats['absent'] ?? 0 }}</span>
                                <span class="stat-label">Absences</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="card attendance-card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="activity-list">
                        @if(count($attendanceRecords) > 0)
                            @foreach($attendanceRecords->sortByDesc('punch_date')->take(5) as $record)
                                <div class="activity-item">
                                    <div class="activity-icon {{ $record->punch_type == 'In' ? 'bg-success' : 'bg-danger' }}">
                                        <i class="fas {{ $record->punch_type == 'In' ? 'fa-sign-in-alt' : 'fa-sign-out-alt' }}"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-time">
                                            {{ \Carbon\Carbon::parse($record->punch_date)->format('M d, Y') }}
                                            <span class="badge {{ $record->punch_type == 'In' ? 'bg-success' : 'bg-danger' }}">
                                                {{ $record->punch_type }}
                                            </span>
                                        </div>
                                        <div class="activity-info">
                                            {{ $record->punch_time ?? 'N/A' }}
                                            @if(isset($record->device_name))
                                            <span class="activity-device">
                                                <i class="fas fa-tablet-alt me-1"></i> {{ $record->device_name }}
                                            </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="activity-empty p-4 text-center">
                                <div class="empty-icon mb-2">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <p class="mb-0">No recent activity found</p>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <button id="viewAll" class="btn btn-sm btn-outline-primary w-100">
                        <i class="fas fa-list-ul me-1"></i> View All Records
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table Modal -->
    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-labelledby="attendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceModalLabel">
                        Complete Attendance Records
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attendanceRecords->sortByDesc('punch_date') as $record)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($record->punch_date)->format('M d, Y') }}</td>
                                    <td>
                                        <span class="badge {{ $record->punch_type == 'In' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $record->punch_type }}
                                        </span>
                                    </td>
                                    <td>{{ $record->punch_time ?? 'N/A' }}</td>
                                    <td>{{ $record->device_name ?? 'Unknown Device' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printTable">
                        <i class="fas fa-print me-1"></i> Print Records
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.10.1/main.min.css" rel="stylesheet">
@endpush

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- FullCalendar JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.10.1/main.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Display SweetAlert notifications for session messages
        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonColor: '#2563eb',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                title: 'Error!',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        @endif

        // Initialize FullCalendar
        const calendarEl = document.getElementById('attendanceCalendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            plugins: ['dayGrid'],
            initialView: 'dayGridMonth',
            headerToolbar: false, // Custom header
            height: 'auto',
            events: [
                @foreach($attendanceRecords as $record)
                {
                    title: '{{ $record->punch_type }}',
                    start: '{{ \Carbon\Carbon::parse($record->punch_date)->toIso8601String() }}',
                    color: '{{ $record->punch_type == "In" ? "#28a745" : "#dc3545" }}'
                },
                @endforeach
            ],
            eventDidMount: function(info) {
                // Add tooltip with more details
                $(info.el).tooltip({
                    title: `${info.event.title} on ${info.event.start.toLocaleDateString()}`,
                    placement: 'top',
                    trigger: 'hover',
                    container: 'body'
                });
            }
        });
        calendar.render();

        // Update current month display
        function updateCurrentMonthDisplay() {
            const currentDate = calendar.getDate();
            document.getElementById('currentMonthDisplay').textContent = 
                new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentDate);
        }
        updateCurrentMonthDisplay();

        // Previous month button
        document.getElementById('prevMonth').addEventListener('click', function() {
            calendar.prev();
            updateCurrentMonthDisplay();
        });

        // Next month button
        document.getElementById('nextMonth').addEventListener('click', function() {
            calendar.next();
            updateCurrentMonthDisplay();
        });

        // View all records button
        document.getElementById('viewAll').addEventListener('click', function() {
            const attendanceModal = new bootstrap.Modal(document.getElementById('attendanceModal'));
            attendanceModal.show();
        });

        // Print report button
        document.getElementById('printReport').addEventListener('click', function() {
            Swal.fire({
                title: 'Generating Report',
                text: 'Please wait while we prepare the attendance report...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    setTimeout(() => {
                        Swal.close();
                        printAttendanceReport();
                    }, 1500);
                }
            });
        });

        // Print table button
        document.getElementById('printTable').addEventListener('click', function() {
            printAttendanceTable();
        });

        // Print attendance report function
        function printAttendanceReport() {
            const employeeName = '{{ $employee->first_name }} {{ $employee->surname }}';
            const printWindow = window.open('', '_blank');

            let printContent = `
                <html>
                <head>
                    <title>Attendance Report for ${employeeName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #2563eb; }
                        .report-header { margin-bottom: 20px; }
                        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .report-table th, .report-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .report-table th { background-color: #f2f2f2; }
                        .clock-in { color: #28a745; }
                        .clock-out { color: #dc3545; }
                        .footer { margin-top: 30px; font-size: 12px; text-align: center; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Attendance Report</h1>
                        <p><strong>Employee:</strong> ${employeeName}</p>
                        <p><strong>Generated on:</strong> ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Time</th>
                                <th>Device</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            @foreach($attendanceRecords->sortByDesc('punch_date') as $record)
                printContent += `
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($record->punch_date)->format('M d, Y') }}</td>
                        <td class="{{ $record->punch_type == 'In' ? 'clock-in' : 'clock-out' }}">
                            {{ $record->punch_type }}
                        </td>
                        <td>{{ $record->punch_time ?? 'N/A' }}</td>
                        <td>{{ $record->device_name ?? 'Unknown Device' }}</td>
                    </tr>
                `;
            @endforeach

            printContent += `
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p>This is an automatically generated report from Garrison Time and Attendance System.</p>
                    </div>
                </body>
                </html>
            `;

            printWindow.document.open();
            printWindow.document.write(printContent);
            printWindow.document.close();

            // Wait for content to load
            printWindow.onload = function() {
                printWindow.print();
            };
        }

        // Print attendance table function
        function printAttendanceTable() {
            window.print();
        }
    });
</script>
@endpush