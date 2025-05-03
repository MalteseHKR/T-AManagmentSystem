<!-- filepath: c:\xampp\htdocs\5CS024\sprint 2\T-AManagmentSystem\garrison_webportal\backend_test\resources\views\profile\leave.blade.php -->

@extends('profile.layout')

@section('profile-content')
    <h2 class="mb-4">My Leave</h2>
    
    <!-- Leave Balance Summary -->
@php
    $annual = $leaveBalances->firstWhere('leaveType.leave_type_name', 'Annual');
    $sick = $leaveBalances->firstWhere('leaveType.leave_type_name', 'Sick');
    $other = $leaveBalances->firstWhere('leaveType.leave_type_name', 'Personal'); // or 'Other Leave'

    $annualRemaining = $annual ? $annual->total_days - $annual->used_days : 0;
    $sickRemaining = $sick ? $sick->total_days - $sick->used_days : 0;
    $otherRemaining = $other ? $other->total_days - $other->used_days : 0;
@endphp

<!-- Leave Balance Summary -->
<div class="card mb-4">
    <div class="card-body">
        <h5>Leave Balances</h5>
        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <div class="p-3 bg-light rounded text-center">
                    <h6>Annual Leave</h6>
                    <h4>{{ $annual->total_days ?? '0' }} days</h4>
                    <small class="text-muted">Remaining: {{ $annualRemaining }} days</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="p-3 bg-light rounded text-center">
                    <h6>Sick Leave</h6>
                    <h4>{{ $sick->total_days ?? '0' }} days</h4>
                    <small class="text-muted">Remaining: {{ $sickRemaining }} days</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="p-3 bg-light rounded text-center">
                    <h6>Other Leave</h6>
                    <h4>{{ $other->total_days ?? '0' }} days</h4>
                    <small class="text-muted">Remaining: {{ $otherRemaining }} days</small>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <!-- Leave Applications -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white">Leave Applications</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                <i class="fas fa-plus me-1"></i> Apply for Leave
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Request Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaveRequests as $request)
                            <tr>
                                <td>{{ $request->leaveType->leave_type_name ?? 'Unknown' }}</td>
                                <td>
                                    {{ \Carbon\Carbon::parse($request->start_date)->format('d M, Y') }}
                                    @if($request->is_full_day == 0 && $request->start_time)
                                        <br><small class="text-muted">{{ date('h:i A', strtotime($request->start_time)) }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ \Carbon\Carbon::parse($request->end_date)->format('d M, Y') }}
                                    @if($request->is_full_day == 0 && $request->end_time)
                                        <br><small class="text-muted">{{ date('h:i A', strtotime($request->end_time)) }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $request->duration }}
                                    @if($request->is_full_day == 0)
                                        <span class="badge bg-info text-dark">Partial</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $request->reason }}
                                    @if($request->admin_notes)
                                        <div class="mt-1">
                                            <small class="text-muted d-block"><i class="fas fa-comment-dots me-1"></i> {{ $request->admin_notes }}</small>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('d M, Y') : 'N/A' }}</td>
                                <td>
                                    @php
                                        $statusClass = [
                                            'approved' => 'success',
                                            'pending' => 'warning',
                                            'rejected' => 'danger',
                                        ][$request->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $statusClass }}">{{ ucfirst($request->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No leave applications found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Apply Leave Modal -->
    <div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="applyLeaveModalLabel">Apply for Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('profile.leave.apply') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="leave_type" class="form-label">Leave Type</label>
                            <select class="form-select" id="leave_type" name="leave_type" required>
                                <option value="">Select leave type</option>
                                <option value="annual">Annual Leave</option>
                                <option value="sick">Sick Leave</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="from_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="from_date" name="from_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="to_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="to_date" name="to_date" required>
                            </div>
                        </div>
                        
                        <!-- Add duration type -->
                        <div class="mb-3">
                            <label class="form-label">Duration Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_full_day" id="fullDayOption" value="1" checked>
                                <label class="form-check-label" for="fullDayOption">
                                    <i class="fas fa-calendar-day me-1"></i> Full Day
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_full_day" id="partialDayOption" value="0">
                                <label class="form-check-label" for="partialDayOption">
                                    <i class="fas fa-hourglass-half me-1"></i> Partial Day
                                </label>
                            </div>
                        </div>
                        
                        <!-- Add time fields -->
                        <div class="row mb-3" id="timeSelectionRow" style="display: none;">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time">
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        
                        <!-- Add hidden field for request date -->
                        <input type="hidden" name="request_date" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle full day / partial day toggle
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
        
        // Set initial state
        toggleTimeFields();
        
        // Add event listeners
        fullDayOption.addEventListener('change', toggleTimeFields);
        partialDayOption.addEventListener('change', toggleTimeFields);
        
        // Validate date ranges
        const fromDateInput = document.getElementById('from_date');
        const toDateInput = document.getElementById('to_date');
        
        toDateInput.addEventListener('change', function() {
            if (fromDateInput.value && this.value < fromDateInput.value) {
                alert('End date cannot be before start date');
                this.value = fromDateInput.value;
            }
        });
        
        // Validate time ranges
        endTimeInput.addEventListener('change', function() {
            if (startTimeInput.value && this.value <= startTimeInput.value) {
                alert('End time must be after start time');
                this.value = '';
            }
        });
    });
</script>
@endpush