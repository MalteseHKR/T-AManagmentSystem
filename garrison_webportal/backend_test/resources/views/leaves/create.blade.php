@extends('layouts.app')

@section('title', 'Create Leave Request - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container leave-container">
    <div class="mb-4">
        <a href="{{ route('leaves') }}" class="btn btn-outline-secondary leave-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Leave Management
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 leave-header">Create Leave Request</h1>
    </div>

    <div class="card leave-card">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0 text-white"><i class="fas fa-plus me-2"></i>Leave Request Form</h5>
        </div>
        <div class="card-body px-4">
            <form action="{{ route('leaves.store') }}" method="POST" id="leaveRequestForm" enctype="multipart/form-data">
                @csrf
                
                @if ($errors->any())
                <div class="alert alert-danger leave-alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="user_id" class="form-label fw-bold">Employee</label>
                        <select class="form-select leave-form-control @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                            <option value="">Select Employee</option>
                            @foreach($employees as $employee)
                            <option value="{{ $employee->user_id }}" {{ old('user_id') == $employee->user_id || (empty(old('user_id')) && Auth::user()->id == $employee->user_id) ? 'selected' : '' }}>
                                {{ $employee->user_name }} {{ $employee->user_surname }}
                                @if(Auth::user()->id == $employee->user_id)
                                    (You)
                                @endif
                            </option>
                            @endforeach
                        </select>
                        @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Note: You cannot approve your own leave request.</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="leave_type_id" class="form-label fw-bold">Leave Type</label>
                        <select class="form-select leave-form-control @error('leave_type_id') is-invalid @enderror" id="leave_type_id" name="leave_type_id" required>
                            <option value="">Select Leave Type</option>
                            @foreach($leaveTypes as $leaveType)
                            <option value="{{ $leaveType->leave_type_id }}" {{ old('leave_type_id') == $leaveType->leave_type_id ? 'selected' : '' }}>
                                {{ $leaveType->leave_type_name }}
                            </option>
                            @endforeach
                        </select>
                        @error('leave_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label fw-bold">Start Date</label>
                        <input type="date" class="form-control leave-form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                        @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label for="end_date" class="form-label fw-bold">End Date</label>
                        <input type="date" class="form-control leave-form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                        @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="reason" class="form-label fw-bold">Reason (Optional)</label>
                    <textarea class="form-control leave-form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="3">{{ old('reason') }}</textarea>
                    @error('reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- File upload field for sick leave (now optional) -->
                <div class="mb-3" id="certificateUploadField" style="display: none;">
                    <label for="medical_certificate" class="form-label fw-bold">
                        Medical Certificate <span class="text-muted">(Optional)</span>
                    </label>
                    <input type="file" class="form-control" id="medical_certificate" name="medical_certificate" accept="image/*,.pdf">
                    <div class="form-text">
                        You may upload a medical certificate if available. This is recommended but not required for sick leave.
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary leave-btn" id="submitLeaveRequest">
                        <i class="fas fa-save me-2"></i> Create Leave Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calculate dates for backdating (30 days ago)
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        const todayStr = today.toISOString().split('T')[0];
        const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];

        // Set default dates if not previously set
        if (!startDateInput.value) {
            startDateInput.value = todayStr;
        }
        if (!endDateInput.value) {
            endDateInput.value = todayStr;
        }

        // Allow backdating up to 30 days for both start and end dates
        startDateInput.min = thirtyDaysAgoStr;
        endDateInput.min = thirtyDaysAgoStr; // Allow end date to also be backdated

        // Update end date min value when start date changes
        startDateInput.addEventListener('change', function() {
            // If start date is after end date, update end date to match start date
            if (endDateInput.value && endDateInput.value < startDateInput.value) {
                endDateInput.value = startDateInput.value;
            }
        });

        // Add listener to end date to ensure it's not before start date
        endDateInput.addEventListener('change', function() {
            if (this.value < startDateInput.value) {
                Swal.fire({
                    title: 'Invalid Date Range',
                    text: 'End date cannot be before start date',
                    icon: 'warning',
                    confirmButtonColor: '#3085d6'
                });
                this.value = startDateInput.value;
            }
        });

        // Show/hide certificate upload based on leave type 
        const leaveTypeSelect = document.getElementById('leave_type_id');
        const certificateField = document.getElementById('certificateUploadField');
        
        leaveTypeSelect.addEventListener('change', function() {
            const sickLeaveId = '2'; // Updated to use ID 2 for sick leave
            
            if (this.value === sickLeaveId) {
                certificateField.style.display = 'block';
                // Remove required attribute - file upload is optional
                document.getElementById('medical_certificate').removeAttribute('required');
            } else {
                certificateField.style.display = 'none';
                document.getElementById('medical_certificate').removeAttribute('required');
            }
        });
        
        // Check if sick leave is already selected on page load
        if (leaveTypeSelect.value === '2') {
            certificateField.style.display = 'block';
        }

        // SweetAlert for form submission
        const form = document.getElementById('leaveRequestForm');

        form.addEventListener('submit', function(e) {
            // Only prevent default if validation passes
            if (form.checkValidity()) {
                e.preventDefault();

                // Show confirmation dialog
                Swal.fire({
                    title: 'Submit Leave Request?',
                    text: "Are you sure you want to submit this leave request?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, submit it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Submitting...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            }
        });

        // Display SweetAlert notifications for session messages
        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonColor: '#198754',
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

        // Display validation errors via SweetAlert
        @if($errors->any())
            Swal.fire({
                title: 'Validation Error',
                html: `
                    <div class="text-start">
                        <p>Please correct the following errors:</p>
                        <ul class="list-unstyled text-danger">
                            @foreach ($errors->all() as $error)
                                <li><i class="fas fa-exclamation-circle me-2"></i> {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                `,
                icon: 'warning',
                confirmButtonColor: '#ffc107'
            });
        @endif
    });
</script>
@endpush