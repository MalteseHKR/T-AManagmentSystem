@extends('layouts.app')

@section('title', 'Edit Leave Request - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container leave-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">Edit Leave Request</h1>
        <a href="{{ route('leaves') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Leaves
        </a>
    </div>

    <div class="card leave-card">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0 text-white"><i class="fas fa-edit me-2"></i>Edit Leave Request Form</h5>
        </div>
        <div class="card-body px-4">
            <form action="{{ route('leaves.update', $leave->request_id) }}" method="POST" id="leaveRequestForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
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
                            <option value="{{ $employee->user_id }}" {{ (old('user_id', $leave->user_id) == $employee->user_id) ? 'selected' : '' }}>
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
                            @foreach($leaveTypes as $type)
                            <option value="{{ $type->leave_type_id }}" {{ (old('leave_type_id', $leave->leave_type_id) == $type->leave_type_id) ? 'selected' : '' }}>
                                {{ $type->leave_type_name }}
                            </option>
                            @endforeach
                        </select>
                        @error('leave_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if($leave->request_date)
                <div class="mb-3">
                    <label class="form-label fw-bold">Request Date</label>
                    <input type="text" class="form-control" value="{{ date('d F Y', strtotime($leave->request_date)) }}" disabled>
                    <div class="form-text">
                        <i class="fas fa-calendar-check me-1"></i> Original request submission date
                    </div>
                </div>
                @else
                <input type="hidden" name="request_date" value="{{ date('Y-m-d') }}">
                @endif
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label fw-bold">Start Date</label>
                        <input type="date" class="form-control leave-form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date', $leave->start_date) }}" required>
                        @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label for="end_date" class="form-label fw-bold">End Date</label>
                        <input type="date" class="form-control leave-form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" value="{{ old('end_date', $leave->end_date) }}" required>
                        @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Leave Duration Type</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="is_full_day" id="fullDayOption" value="1" 
                            {{ old('is_full_day', $leave->is_full_day) == 1 ? 'checked' : '' }}>
                        <label class="form-check-label" for="fullDayOption">
                            <i class="fas fa-calendar-day me-1"></i> Full Day
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="is_full_day" id="partialDayOption" value="0"
                            {{ old('is_full_day', $leave->is_full_day) == 0 ? 'checked' : '' }}>
                        <label class="form-check-label" for="partialDayOption">
                            <i class="fas fa-hourglass-half me-1"></i> Partial Day
                        </label>
                    </div>
                </div>

                <div class="row mb-3" id="timeSelectionRow" style="display: {{ old('is_full_day', $leave->is_full_day) == 0 ? 'flex' : 'none' }};">
                    <div class="col-md-6">
                        <label for="start_time" class="form-label fw-bold">Start Time</label>
                        <input type="time" class="form-control leave-form-control @error('start_time') is-invalid @enderror" 
                               id="start_time" name="start_time" value="{{ old('start_time', $leave->start_time) }}">
                        @error('start_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label for="end_time" class="form-label fw-bold">End Time</label>
                        <input type="time" class="form-control leave-form-control @error('end_time') is-invalid @enderror" 
                               id="end_time" name="end_time" value="{{ old('end_time', $leave->end_time) }}">
                        @error('end_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i> For partial day leave, please specify the start and end times.
                        </small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="reason" class="form-label fw-bold">Reason (Optional)</label>
                    <textarea class="form-control leave-form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="3">{{ old('reason', $leave->reason) }}</textarea>
                    @error('reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- File upload field for sick leave (optional) -->
                <div class="mb-3" id="certificateUploadField" style="{{ old('leave_type_id', $leave->leave_type_id) == 2 ? 'display: block;' : 'display: none;' }}">
                    <label for="medical_certificate" class="form-label fw-bold">
                        Medical Certificate <span class="text-muted">(Optional)</span>
                    </label>
                    
                    @if($leave->medical_certificate)
                    <div class="mb-2">
                        <p class="mb-1">Current certificate: <a href="{{ asset('certificates/' . $leave->medical_certificate) }}" target="_blank" class="text-primary">View Document</a></p>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remove_certificate" name="remove_certificate" value="1">
                            <label class="form-check-label" for="remove_certificate">
                                Remove current certificate
                            </label>
                        </div>
                    </div>
                    @endif
                    
                    <input type="file" class="form-control" id="medical_certificate" name="medical_certificate" accept="image/*,.pdf">
                    <div class="form-text">
                        You may upload a new medical certificate if available. This is recommended but not required for sick leave.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label fw-bold">Status</label>
                    
                    @if(Auth::user()->id == $leave->user_id)
                        <!-- Completely disable status field for own requests -->
                        <select class="form-select leave-form-control" disabled>
                            <option>Pending (Cannot change status of own request)</option>
                        </select>
                        <!-- Hidden field to submit the status -->
                        <input type="hidden" name="status" value="pending">
                        <div class="form-text text-danger">
                            <i class="fas fa-info-circle me-1"></i> You cannot change the status of your own leave requests. They must be reviewed by a supervisor.
                        </div>
                    @else
                        <!-- Normal status selection for other users' requests -->
                        <select class="form-select leave-form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                            <option value="pending" {{ old('status', strtolower($leave->status)) == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ old('status', strtolower($leave->status)) == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ old('status', strtolower($leave->status)) == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                        @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    @endif
                </div>

                <div class="mb-3" id="adminNotesSection" {{ Auth::user()->id == $leave->user_id ? 'style="display: none;"' : '' }}>
                    <label for="admin_notes" class="form-label fw-bold">
                        Admin Notes <span class="text-muted">(Visible to employee)</span>
                    </label>
                    <textarea class="form-control leave-form-control @error('admin_notes') is-invalid @enderror" 
                        id="admin_notes" name="admin_notes" rows="3" {{ Auth::user()->id == $leave->user_id ? 'disabled' : '' }}>{{ old('admin_notes', $leave->admin_notes) }}</textarea>
                    @error('admin_notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i> Use this field to provide feedback or reasons for approval/rejection.
                    </div>
                </div>
                
                <div class="d-flex flex-column flex-md-row justify-content-between mt-4">
                    <a href="{{ route('leaves') }}" class="btn btn-outline-secondary mb-3 mb-md-0">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for all DOM elements to be fully loaded
    setTimeout(function() {
        // Calculate dates for backdating (30 days ago)
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        const todayStr = today.toISOString().split('T')[0];
        const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];

        // Allow backdating up to 30 days for both start and end dates
        if (startDateInput) startDateInput.min = thirtyDaysAgoStr;
        if (endDateInput) endDateInput.min = thirtyDaysAgoStr;
        
        // Update end date validation when start date changes
        if (startDateInput) {
            startDateInput.addEventListener('change', function() {
                // If end date is before start date, update end date
                if (endDateInput && endDateInput.value && endDateInput.value < startDateInput.value) {
                    endDateInput.value = startDateInput.value;
                }
            });
        }
        
        // Validate end date is not before start date
        if (endDateInput) {
            endDateInput.addEventListener('change', function() {
                if (startDateInput && this.value < startDateInput.value) {
                    // Replace the alert with SweetAlert
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Date Range',
                        text: 'End date cannot be before start date',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        this.value = startDateInput.value;
                    });
                }
            });
        }
        
        // Show/hide certificate upload based on leave type
        const leaveTypeSelect = document.getElementById('leave_type_id');
        const certificateField = document.getElementById('certificateUploadField');
        
        if (leaveTypeSelect && certificateField) {
            leaveTypeSelect.addEventListener('change', function() {
                // Show certificate field only for sick leave (ID = 2)
                certificateField.style.display = this.value === '2' ? 'block' : 'none';
            });
        }

        // Show/hide time selection based on leave duration type
        const fullDayOption = document.getElementById('fullDayOption');
        const partialDayOption = document.getElementById('partialDayOption');
        const timeSelectionRow = document.getElementById('timeSelectionRow');
        const startTimeInput = document.getElementById('start_time');
        const endTimeInput = document.getElementById('end_time');

        function toggleTimeFields() {
            if (partialDayOption.checked) {
                timeSelectionRow.style.display = 'flex';
                startTimeInput.setAttribute('required', 'required');
                endTimeInput.setAttribute('required', 'required');
            } else {
                timeSelectionRow.style.display = 'none';
                startTimeInput.removeAttribute('required');
                endTimeInput.removeAttribute('required');
            }
        }

        // Set initial state and add event listeners
        fullDayOption.addEventListener('change', toggleTimeFields);
        partialDayOption.addEventListener('change', toggleTimeFields);

        // Validate that end time is after start time
        endTimeInput.addEventListener('change', function() {
            if (startTimeInput.value && this.value <= startTimeInput.value) {
                Swal.fire({
                    title: 'Invalid Time Range',
                    text: 'End time must be after start time',
                    icon: 'warning',
                    confirmButtonColor: '#3085d6'
                });
                this.value = '';
            }
        });

        // Show admin notes field only for supervisors
        const statusSelect = document.getElementById('status');
        const adminNotesSection = document.getElementById('adminNotesSection');

        if (statusSelect && adminNotesSection) {
            statusSelect.addEventListener('change', function() {
                if (this.value === 'approved' || this.value === 'rejected') {
                    adminNotesSection.style.display = 'block';
                }
            });
        }

        // Add SweetAlert for better user experience
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // Show confirmation when form is submitted
        document.getElementById('leaveRequestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Save Changes?',
                text: 'Are you sure you want to update this leave request?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, save changes'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }, 100); // Small delay to ensure DOM is fully ready
});
</script>
@endpush