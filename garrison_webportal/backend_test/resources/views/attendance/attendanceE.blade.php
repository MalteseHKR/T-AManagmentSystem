@extends('layouts.app')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-md-12">
            <div class="card attendance-card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        Attendance for {{ $employee->first_name }} {{ $employee->last_name }}
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('employee.profile', $employee->id) }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Profile
                        </a>
                        <button id="printAttendance" class="btn btn-outline-primary d-none d-md-inline-flex">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    @if($attendances->count() > 0)
                        <!-- Summary stats -->
                        <div class="row attendance-summary mb-4">
                            <div class="col-6 col-md-3">
                                <div class="summary-item">
                                    <div class="summary-title">Total Records</div>
                                    <div class="summary-value">{{ $attendances->total() }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="summary-item">
                                    <div class="summary-title">Total Hours</div>
                                    <div class="summary-value">
                                        @php
                                            $totalHours = 0;
                                            foreach($attendances as $record) {
                                                if($record->clock_in && $record->clock_out) {
                                                    $totalHours += \Carbon\Carbon::parse($record->clock_out)
                                                        ->diffInHours(\Carbon\Carbon::parse($record->clock_in));
                                                }
                                            }
                                            echo $totalHours;
                                        @endphp
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mt-3 mt-md-0">
                                <div class="summary-item">
                                    <div class="summary-title">Department</div>
                                    <div class="summary-value">{{ $employee->department ?? 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mt-3 mt-md-0">
                                <div class="summary-item">
                                    <div class="summary-title">Status</div>
                                    <div class="summary-value">
                                        @if($attendances->where('date', \Carbon\Carbon::today()->toDateString())->first())
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive attendance-table-wrapper">
                            <table class="table table-striped table-hover attendance-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendances as $record)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($record->date)->format('M d, Y') }}</td>
                                        <td>
                                            @if($record->clock_in)
                                                <span class="time-badge time-in">
                                                    <i class="bi bi-box-arrow-in-right me-1"></i>
                                                    {{ \Carbon\Carbon::parse($record->clock_in)->format('H:i') }}
                                                </span>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($record->clock_out)
                                                <span class="time-badge time-out">
                                                    <i class="bi bi-box-arrow-right me-1"></i>
                                                    {{ \Carbon\Carbon::parse($record->clock_out)->format('H:i') }}
                                                </span>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($record->clock_in && $record->clock_out)
                                                <span class="hours-badge">
                                                    <i class="bi bi-clock me-1"></i>
                                                    {{ \Carbon\Carbon::parse($record->clock_out)->diffInHours(\Carbon\Carbon::parse($record->clock_in)) }}
                                                </span>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$record->clock_in)
                                                <span class="badge bg-warning text-dark">Not Started</span>
                                            @elseif(!$record->clock_out)
                                                <span class="badge bg-success">Working</span>
                                            @else
                                                <span class="badge bg-primary">Complete</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-center mt-4">
                            {{ $attendances->links() }}
                        </div>
                        
                        <!-- Mobile view print button -->
                        <div class="d-flex d-md-none justify-content-center mt-3">
                            <button id="printAttendanceMobile" class="btn btn-outline-primary">
                                <i class="bi bi-printer me-1"></i> Print Attendance Record
                            </button>
                        </div>
                    @else
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="bi bi-info-circle me-2 fs-4"></i>
                            <div>No attendance records found for this employee.</div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button id="createRecord" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Create First Record
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show success message if exists
        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonColor: '#28a745'
            });
        @endif
        
        // Show error message if exists
        @if(session('error'))
            Swal.fire({
                title: 'Error!',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        @endif
        
        // Print functionality
        const printButtons = [
            document.getElementById('printAttendance'),
            document.getElementById('printAttendanceMobile')
        ];
        
        printButtons.forEach(button => {
            if (button) {
                button.addEventListener('click', function() {
                    // First show a loading indicator
                    Swal.fire({
                        title: 'Preparing Print View',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create print content
                    const employeeName = "{{ $employee->first_name }} {{ $employee->last_name }}";
                    const printWindow = window.open('', '_blank');
                    
                    // Get only the table HTML
                    const tableHtml = document.querySelector('.attendance-table-wrapper').innerHTML;
                    
                    printWindow.document.write(`
                        <html>
                        <head>
                            <title>Attendance Report - ${employeeName}</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                h1 { font-size: 18px; text-align: center; margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; }
                                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                                th { background-color: #f2f2f2; }
                                .badge { 
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-weight: bold;
                                    font-size: 12px;
                                }
                                .bg-warning { background-color: #ffc107; color: #212529; }
                                .bg-success { background-color: #28a745; color: white; }
                                .bg-primary { background-color: #007bff; color: white; }
                                .time-badge {
                                    padding: 3px 6px;
                                    border-radius: 4px;
                                    font-size: 12px;
                                }
                                .employee-info {
                                    margin-bottom: 20px;
                                    padding: 10px;
                                    background-color: #f8f9fa;
                                    border-radius: 4px;
                                }
                                .print-date {
                                    text-align: right;
                                    font-size: 12px;
                                    color: #6c757d;
                                    margin-top: 20px;
                                }
                            </style>
                        </head>
                        <body>
                            <h1>Attendance Report</h1>
                            <div class="employee-info">
                                <p><strong>Employee:</strong> ${employeeName}</p>
                                <p><strong>Department:</strong> {{ $employee->department ?? 'N/A' }}</p>
                                <p><strong>Report Date:</strong> ${new Date().toLocaleDateString()}</p>
                            </div>
                            ${tableHtml}
                            <div class="print-date">
                                Generated on ${new Date().toLocaleString()}
                            </div>
                        </body>
                        </html>
                    `);
                    
                    // Close the SweetAlert once ready
                    setTimeout(() => {
                        Swal.close();
                        printWindow.document.close();
                        printWindow.focus();
                        printWindow.print();
                    }, 1000);
                });
            }
        });
        
        // Create record button
        const createRecordBtn = document.getElementById('createRecord');
        if (createRecordBtn) {
            createRecordBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Create Attendance Record',
                    html: `
                        <form id="createAttendanceForm" class="text-start">
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" id="recordDate" value="${new Date().toISOString().split('T')[0]}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Clock In</label>
                                <input type="time" class="form-control" id="recordClockIn">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Clock Out</label>
                                <input type="time" class="form-control" id="recordClockOut">
                            </div>
                        </form>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Create Record',
                    confirmButtonColor: '#2563eb',
                    cancelButtonText: 'Cancel',
                    focusConfirm: false,
                    preConfirm: () => {
                        const date = document.getElementById('recordDate').value;
                        const clockIn = document.getElementById('recordClockIn').value;
                        const clockOut = document.getElementById('recordClockOut').value;
                        
                        if (!date) {
                            Swal.showValidationMessage('Please enter a date');
                            return false;
                        }
                        
                        // Submit the form (this is where you'd normally handle the AJAX request)
                        // For now, we'll just return the values and show a success message
                        return { date, clockIn, clockOut };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Here you would normally make an AJAX request to create the record
                        // For now, we'll just show a success message
                        Swal.fire({
                            title: 'Record Created!',
                            text: 'The attendance record has been created successfully.',
                            icon: 'success',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // Reload the page to show the new record
                            window.location.reload();
                        });
                    }
                });
            });
        }
    });
</script>
@endpush