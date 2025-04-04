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
            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Leave Request Form</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('leaves.store') }}" method="POST" id="leaveRequestForm">
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
                                {{ $employee->user_name }}
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

                <!-- In your leave request form, add a file upload field that only shows for sick leave -->
                <div class="mb-3" id="certificateUploadField" style="display: none;">
                    <label for="medical_certificate" class="form-label fw-bold">
                        Medical Certificate <span class="text-danger">*</span>
                    </label>
                    <input type="file" class="form-control" id="medical_certificate" name="medical_certificate" accept="image/*,.pdf">
                    <div class="form-text">
                        Please upload a clear image or PDF of your medical certificate. Required for sick leave.
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum date for end_date based on start_date
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        // Set default start date to today if not previously set
        if (!startDateInput.value) {
            const today = new Date().toISOString().split('T')[0];
            startDateInput.value = today;
            startDateInput.min = today; // Cannot select dates before today
            endDateInput.min = today;
        }
        
        startDateInput.addEventListener('change', function() {
            endDateInput.min = startDateInput.value;
            
            // If end date is before start date, reset it
            if (endDateInput.value && endDateInput.value < startDateInput.value) {
                endDateInput.value = startDateInput.value;
            }
        });
        
        // Initialize on load too
        if (startDateInput.value) {
            endDateInput.min = startDateInput.value;
            
            // If end date is already set but before start date, adjust it
            if (endDateInput.value && endDateInput.value < startDateInput.value) {
                endDateInput.value = startDateInput.value;
            }
        }
        
        // SweetAlert for form submission
        const form = document.getElementById('leaveRequestForm');
        
        form.addEventListener('submit', function(e) {
            // Only prevent default if validation passes
            if (form.checkValidity()) {
                e.preventDefault();
                
                // Show confirmation dialog
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, submit it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        });

        // Show/hide certificate upload based on leave type
        document.getElementById('leave_type_id').addEventListener('change', function() {
            const sickLeaveId = '1'; // Replace with your actual sick leave type ID
            const certificateField = document.getElementById('certificateUploadField');
            
            if (this.value === sickLeaveId) {
                certificateField.style.display = 'block';
                document.getElementById('medical_certificate').setAttribute('required', 'required');
            } else {
                certificateField.style.display = 'none';
                document.getElementById('medical_certificate').removeAttribute('required');
            }
        });
    });
</script>
@endpush