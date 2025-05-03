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
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
<tbody>
    @forelse ($leaveRequests as $request)
        <tr>
            <td>{{ $request->leaveType->leave_type_name ?? 'Unknown' }}</td>
            <td>{{ \Carbon\Carbon::parse($request->start_date)->format('d M, Y') }}</td>
            <td>{{ \Carbon\Carbon::parse($request->end_date)->format('d M, Y') }}</td>
            <td>{{ $request->duration }}</td>
            <td>{{ $request->reason }}</td>
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
            <td colspan="6" class="text-center">No leave applications found.</td>
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
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
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