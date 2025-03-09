@extends('layouts.app')

@section('title', 'Leave Management - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container leave-container">
    <!-- Back to dashboard link -->
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary leave-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 leave-flex">
        <h1 class="mb-0 leave-header">Leave Management</h1>
        <div>
            <a href="{{ route('leaves.create') }}" class="btn btn-primary leave-btn">
                <i class="fas fa-plus me-2"></i> New Leave Request
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4 leave-card">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i> - Filter Leave Requests</h5>
        </div>
        <div class="card-body leave-filter-section">
            <form action="{{ route('leaves') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="employee_name" class="form-label fw-bold">Employee Name</label>
                    <input type="text" class="form-control leave-form-control" id="employee_name" name="employee_name" value="{{ request('employee_name') }}" placeholder="Search by name">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label fw-bold">Status</label>
                    <select class="form-select leave-form-control" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>Pending</option>
                        <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>Approved</option>
                        <option value="REJECTED" {{ request('status') == 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="leave_type" class="form-label fw-bold">Leave Type</label>
                    <select class="form-select leave-form-control" id="leave_type" name="leave_type">
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
                    <div class="d-flex">
                        <button type="submit" class="btn btn-primary leave-btn flex-grow-1 me-2">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('leaves') }}" class="btn btn-outline-secondary leave-btn">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Leave Requests Table -->
    <div class="card leave-card">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0 text-white"><i class="fas fa-calendar-alt me-2"></i> - Leave Requests</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover leave-table leave-hover">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaveRequests as $leave)
                        <tr class="{{ Auth::user()->id == $leave->user_id ? 'leave-own-request' : '' }}">
                            <td class="ps-4">
                                {{ $leave->request_id }}
                                @if(Auth::user()->id == $leave->user_id)
                                    <span class="badge bg-secondary ms-2" title="This is your request">
                                        <i class="fas fa-user me-1"></i> Your Request
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold">{{ $leave->user_name }}</span>
                            </td>
                            <td>{{ $leave->leave_type_name }}</td>
                            <td>{{ date('d M Y', strtotime($leave->start_date)) }}</td>
                            <td>{{ date('d M Y', strtotime($leave->end_date)) }}</td>
                            <td>
                                <span class="badge bg-light text-dark leave-badge">
                                    @php
                                        $start = new \Carbon\Carbon($leave->start_date);
                                        $end = new \Carbon\Carbon($leave->end_date);
                                        $days = $start->diffInDays($end) + 1;
                                    @endphp
                                    {{ $days }} {{ Str::plural('day', $days) }}
                                </span>
                            </td>
                            <td>
                                @if($leave->reason)
                                    <span class="leave-tooltip" data-bs-toggle="tooltip" title="{{ $leave->reason }}">
                                        {{ Str::limit($leave->reason, 20) }}
                                    </span>
                                @else
                                    <span class="text-muted">None provided</span>
                                @endif
                            </td>
                            <td>
                                @if(strtoupper($leave->status) == 'PENDING')
                                    <span class="badge status-pending leave-badge">Pending</span>
                                @elseif(strtoupper($leave->status) == 'APPROVED')
                                    <span class="badge status-approved leave-badge">Approved</span>
                                @elseif(strtoupper($leave->status) == 'REJECTED')
                                    <span class="badge status-rejected leave-badge">Rejected</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if(strtoupper($leave->status) == 'PENDING')
                                    @php
                                        $isOwnRequest = (string)Auth::id() === (string)$leave->user_id;
                                    @endphp
                                    
                                    @if($isOwnRequest)
                                        <span class="badge bg-info leave-badge" title="You cannot approve or reject your own request">
                                            <i class="fas fa-info-circle me-1"></i> Awaiting Other's Approval
                                        </span>
                                    @else
                                        <div class="btn-group leave-btn-group">
                                            <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST" class="me-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="btn btn-sm btn-success leave-btn leave-btn-sm">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="REJECTED">
                                                <button type="submit" class="btn btn-sm btn-danger leave-btn leave-btn-sm">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @else
                                    <button type="button" class="btn btn-sm btn-secondary leave-btn leave-btn-sm" disabled>No actions</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="leave-empty-state">
                                <div class="text-muted mb-4"><i class="fas fa-inbox fa-3x"></i></div>
                                <h5>No leave requests found</h5>
                                <p class="text-muted mb-4">Try adjusting your filters or create a new request</p>
                                <a href="{{ route('leaves.create') }}" class="btn btn-primary leave-btn">
                                    <i class="fas fa-plus me-2"></i> Create New Leave Request
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if(isset($leaveRequests) && $leaveRequests->hasPages())
            <div class="p-3 border-top leave-pagination">
                {{ $leaveRequests->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips - optimized version
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(el => {
            new bootstrap.Tooltip(el, {
                placement: 'top',
                trigger: 'hover focus',
                html: false,
                animation: true,
                delay: {show: 100, hide: 100}
            });
        });
        
        // Add confirmation for approval/rejection using SweetAlert
        document.querySelectorAll('form').forEach(function(form) {
            const statusInput = form.querySelector('input[name="status"]');
            if (!statusInput) return;
            
            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) return;
            
            submitButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                const isApproval = statusInput.value === 'APPROVED';
                const title = isApproval ? 'Approve Leave Request?' : 'Reject Leave Request?';
                const text = isApproval ? 
                    'Are you sure you want to approve this leave request?' : 
                    'Are you sure you want to reject this leave request?';
                const icon = isApproval ? 'question' : 'warning';
                const confirmButtonColor = isApproval ? '#28a745' : '#d33';
                const confirmButtonText = isApproval ? 'Yes, approve it' : 'Yes, reject it';
                
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonColor: confirmButtonColor,
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: confirmButtonText
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing...',
                            html: isApproval ? 'Approving leave request' : 'Rejecting leave request',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                                // Submit the form
                                form.submit();
                            }
                        });
                    }
                });
            });
        });
        
        // Show notifications based on session data - ONLY ONCE
        // FIXED: Removed duplicate notification code and placed it here
        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonColor: '#3085d6',
                timer: 3000,
                timerProgressBar: true
            });
        @endif
        
        @if(session('error'))
            Swal.fire({
                title: 'Error!',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        @endif

        // Add this for the filter form
        const filterInput = document.getElementById('employee_name');
        let debounceTimer;

        filterInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                // Only submit form after typing stops for 500ms
                document.querySelector('form').submit();
            }, 500);
        });

        // Add this to your script if you have many leave rows
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Load more leave requests via AJAX
                    loadMoreLeaves();
                    observer.unobserve(entry.target);
                }
            });
        }, {threshold: 0.5});

        // Observe the last table row
        const lastRow = document.querySelector('table.leave-table tbody tr:last-child');
        if (lastRow) observer.observe(lastRow);
    });
</script>
@endpush