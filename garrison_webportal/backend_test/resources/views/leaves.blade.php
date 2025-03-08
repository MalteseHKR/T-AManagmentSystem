@extends('layouts.app')

@section('title', 'Leave Management - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Leave Management</h1>
        <div>
            <a href="{{ route('leaves.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> New Leave Request
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Filter Leave Requests</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('leaves') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="employee_name" class="form-label">Employee Name</label>
                    <input type="text" class="form-control" id="employee_name" name="employee_name" value="{{ request('employee_name') }}">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>Pending</option>
                        <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>Approved</option>
                        <option value="REJECTED" {{ request('status') == 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="leave_type" class="form-label">Leave Type</label>
                    <select class="form-select" id="leave_type" name="leave_type">
                        <option value="">All Types</option>
                        @foreach($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->leave_type_id }}" {{ request('leave_type') == $leaveType->leave_type_id ? 'selected' : '' }}>
                            {{ $leaveType->leave_type_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Leave Requests Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Leave Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaveRequests as $leave)
                        <tr>
                            <td>{{ $leave->request_id }}</td>
                            <td>{{ $leave->user_name }}</td>
                            <td>{{ $leave->leave_type_name }}</td>
                            <td>{{ date('d M Y', strtotime($leave->start_date)) }}</td>
                            <td>{{ date('d M Y', strtotime($leave->end_date)) }}</td>
                            <td>
                                @php
                                    $start = new \Carbon\Carbon($leave->start_date);
                                    $end = new \Carbon\Carbon($leave->end_date);
                                    $days = $start->diffInDays($end) + 1; // Include both start and end days
                                @endphp
                                {{ $days }} {{ Str::plural('day', $days) }}
                            </td>
                            <td>
                                @if($leave->reason)
                                    <span data-bs-toggle="tooltip" title="{{ $leave->reason }}">
                                        {{ Str::limit($leave->reason, 20) }}
                                    </span>
                                @else
                                    <span class="text-muted">None provided</span>
                                @endif
                            </td>
                            <td>
                                @if(strtoupper($leave->status) == 'PENDING')
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @elseif(strtoupper($leave->status) == 'APPROVED')
                                    <span class="badge bg-success">Approved</span>
                                @elseif(strtoupper($leave->status) == 'REJECTED')
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </td>
                            <td>
                                @if(strtoupper($leave->status) == 'PENDING')
                                <div class="btn-group">
                                    <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST" class="me-1">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="APPROVED">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="REJECTED">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                                @else
                                <button type="button" class="btn btn-sm btn-secondary" disabled>No actions</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center">No leave requests found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-4">
                {{ $leaveRequests->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush