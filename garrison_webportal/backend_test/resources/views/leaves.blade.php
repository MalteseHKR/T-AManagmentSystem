@extends('layouts.app')

@section('title', 'Leave Management - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container leave-container">
    <!-- Page header with action buttons -->
    <div class="leave-page-header">
        <div class="leave-header-left">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary leave-btn">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>
        <div class="leave-header-middle">
            <h1 class="leave-header">Leave Management</h1>
        </div>
        <div class="leave-header-right">
            <a href="{{ route('leaves.create') }}" class="btn btn-primary leave-btn">
                <i class="fas fa-plus me-2"></i> New Leave Request
            </a>
        </div>
    </div>

    <!-- Statistics summary -->
    <div class="leave-stats-bar mb-3">
        <div class="leave-stat-item">
            <div class="leave-stat-icon pending-bg">
                <i class="fas fa-clock"></i>
            </div>
            <div class="leave-stat-info">
                <span class="leave-stat-value">{{ $pendingCount ?? 0 }}</span>
                <span class="leave-stat-label">Pending</span>
            </div>
        </div>
        <div class="leave-stat-item">
            <div class="leave-stat-icon approved-bg">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="leave-stat-info">
                <span class="leave-stat-value">{{ $approvedCount ?? 0 }}</span>
                <span class="leave-stat-label">Approved</span>
            </div>
        </div>
        <div class="leave-stat-item">
            <div class="leave-stat-icon rejected-bg">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="leave-stat-info">
                <span class="leave-stat-value">{{ $rejectedCount ?? 0 }}</span>
                <span class="leave-stat-label">Rejected</span>
            </div>
        </div>
        <div class="leave-stat-item">
            <div class="leave-stat-icon total-bg">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="leave-stat-info">
                <span class="leave-stat-value">{{ $totalCount ?? 0 }}</span>
                <span class="leave-stat-label">Total Requests</span>
            </div>
        </div>
    </div>

    <!-- Filter controls -->
    <div class="card leave-filter-card mb-4">
        <div class="card-body px-2 pt-0 pb-3">
            <form id="leaveFilterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Leave Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="annual" {{ request('type') == 'annual' ? 'selected' : '' }}>Annual Leave</option>
                        <option value="sick" {{ request('type') == 'sick' ? 'selected' : '' }}>Sick Leave</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="employee_name" class="form-label">Employee Name</label>
                    <input type="text" id="employee_name" name="employee_name" class="form-control" value="{{ request('employee_name') }}" placeholder="Search by name">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Section -->
    <div class="card leave-card">
        <div class="card-header leave-card-header">
            <h5 class="mb-0 text-white"><i class="fas fa-calendar-alt me-2"></i> Leave Requests</h5>
            <div class="leave-header-actions">
            </div>
        </div>
        
        <!-- Table View -->
        <div class="leave-table-view">
            <div class="table-responsive">
                <table class="table table-hover leave-table">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Date Range</th>
                            <th>Duration</th>
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
                                    <span class="badge bg-secondary ms-2 leave-badge" title="This is your request">
                                        <i class="fas fa-user me-1"></i> Yours
                                    </span>
                                @endif
                            </td>
                            <td class="leave-employee-col">
                                <span>{{ $leave->user_name }} {{ $leave->user_surname }}</span>
                            </td>
                            <td>{{ $leave->leave_type_name }}</td>
                            <td class="leave-date-col">
                                <div class="leave-date-range">
                                    <div class="leave-date">
                                        <i class="fas fa-calendar-day me-1 text-primary"></i> 
                                        {{ date('d M Y', strtotime($leave->start_date)) }}
                                    </div>
                                    <div class="leave-date-arrow">
                                        <i class="fas fa-arrow-right text-muted"></i>
                                    </div>
                                    <div class="leave-date">
                                        <i class="fas fa-calendar-day me-1 text-danger"></i> 
                                        {{ date('d M Y', strtotime($leave->end_date)) }}
                                    </div>
                                </div>
                            </td>
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
                            <td class="leave-status-col">
                                @if(strtoupper($leave->status) == 'PENDING')
                                    <span class="badge status-pending leave-badge text-warning">Pending</span>
                                @elseif(strtoupper($leave->status) == 'APPROVED')
                                    <span class="badge status-approved leave-badge text-success">Approved</span>
                                @elseif(strtoupper($leave->status) == 'REJECTED')
                                    <span class="badge status-rejected leave-badge text-danger">Rejected</span>
                                @endif
                            </td>
                            <td class="text-end pe-4 leave-actions-col">
                                <div class="leave-actions">
					@if($leave->medical_certificate)
    						<button type="button" class="btn btn-sm btn-info leave-btn-sm view-details-btn me-1" 
        						data-id="{{ $leave->request_id }}"
        						data-employee="{{ $leave->user_name }}"
        						data-type="{{ $leave->leave_type_name }}"
        						data-start="{{ date('d M Y', strtotime($leave->start_date)) }}"
        						data-end="{{ date('d M Y', strtotime($leave->end_date)) }}"
        						data-status="{{ $leave->status }}"
        						data-reason="{{ $leave->reason }}"
        						data-has-certificate="true"
        						data-certificate="{{ asset('certificates/' . $leave->medical_certificate) }}">
        					<i class="fas fa-eye"></i>
    						</button>
					@endif
                                    
                                    <a href="{{ route('leaves.edit', $leave->request_id) }}" class="btn btn-sm btn-primary leave-btn-sm">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    
                                    @if(strtoupper($leave->status) == 'PENDING')
                                        @php
                                            $isOwnRequest = (string)Auth::id() === (string)$leave->user_id;
                                        @endphp
                                        
                                        @if(!$isOwnRequest)
                                            <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST" class="d-inline-block leave-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="APPROVED">
                                                <button type="submit" class="btn btn-sm btn-success leave-btn-sm approve-btn">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            
                                            <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST" class="d-inline-block leave-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="REJECTED">
                                                <button type="submit" class="btn btn-sm btn-danger leave-btn-sm reject-btn">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-secondary leave-btn-sm" disabled>
                                                <i class="fas fa-clock me-1"></i> Pending
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="leave-empty-state">
                                <div class="empty-state-container">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <h5>No leave requests found</h5>
                                    <p class="text-muted mb-4">Try adjusting your filters or create a new request</p>
                                    <a href="{{ route('leaves.create') }}" class="btn btn-primary leave-btn">
                                        <i class="fas fa-plus me-2"></i> Create New Leave Request
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Card View (for mobile) -->
        <div class="leave-card-view d-none">
            <div class="leave-grid">
                @forelse ($leaveRequests as $leave)
                <div class="leave-grid-item {{ Auth::user()->id == $leave->user_id ? 'leave-own-request' : '' }}">
                    <div class="leave-grid-header">
                        <div class="leave-grid-id">
                            <span class="fw-bold">#{{ $leave->request_id }}</span>
                            @if(Auth::user()->id == $leave->user_id)
                                <span class="badge bg-secondary ms-2 leave-badge">
                                    <i class="fas fa-user me-1"></i> Your Request
                                </span>
                            @endif
                        </div>
                        <div class="leave-grid-status">
                            @if(strtoupper($leave->status) == 'PENDING')
                                <span class="badge status-pending leave-badge">Pending</span>
                            @elseif(strtoupper($leave->status) == 'APPROVED')
                                <span class="badge status-approved leave-badge">Approved</span>
                            @elseif(strtoupper($leave->status) == 'REJECTED')
                                <span class="badge status-rejected leave-badge">Rejected</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="leave-grid-content">
                        <div class="leave-grid-employee">
                            <i class="fas fa-user me-2"></i>
                            <span>{{ $leave->user_name }} {{ $leave->user_surname }}</span>
                        </div>
                        
                        <div class="leave-grid-type">
                            <i class="fas fa-tag me-2"></i>
                            <span>{{ $leave->leave_type_name }}</span>
                        </div>
                        
                        <div class="leave-grid-dates">
                            <div>
                                <i class="fas fa-calendar-day me-2 text-primary"></i> 
                                <span>{{ date('d M Y', strtotime($leave->start_date)) }}</span>
                            </div>
                            <div class="leave-grid-date-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div>
                                <i class="fas fa-calendar-day me-2 text-danger"></i> 
                                <span>{{ date('d M Y', strtotime($leave->end_date)) }}</span>
                            </div>
                        </div>
                        
                        <div class="leave-grid-duration">
                            <i class="fas fa-clock me-2"></i>
                            @php
                                $start = new \Carbon\Carbon($leave->start_date);
                                $end = new \Carbon\Carbon($leave->end_date);
                                $days = $start->diffInDays($end) + 1;
                            @endphp
                            <span>{{ $days }} {{ Str::plural('day', $days) }}</span>
                        </div>
                        
                        @if($leave->reason)
                        <div class="leave-grid-reason">
                            <i class="fas fa-comment-alt me-2"></i>
                            <span>{{ Str::limit($leave->reason, 40) }}</span>
                        </div>
                        @endif
                    </div>
                    
                    <div class="leave-grid-actions">
                        <button type="button" class="btn btn-sm btn-info leave-btn-sm view-details-btn" 
                            data-id="{{ $leave->request_id }}"
                            data-employee="{{ $leave->user_name }}"
                            data-type="{{ $leave->leave_type_name }}"
                            data-start="{{ date('d M Y', strtotime($leave->start_date)) }}"
                            data-end="{{ date('d M Y', strtotime($leave->end_date)) }}"
                            data-status="{{ $leave->status }}"
                            data-reason="{{ $leave->reason }}"
                            data-has-certificate="{{ $leave->medical_certificate ? 'true' : 'false' }}"
                            data-certificate="{{ $leave->medical_certificate ? asset('certificates/' . $leave->medical_certificate) : '' }}">
                            <i class="fas fa-eye me-1"></i> View Details
                        </button>
                        
                        <a href="{{ route('leaves.edit', $leave->request_id) }}" class="btn btn-sm btn-primary leave-btn-sm mt-2">
                            <i class="fas fa-pen me-1"></i> Edit Request
                        </a>
                        
                        @if(strtoupper($leave->status) == 'PENDING')
                            @php
                                $isOwnRequest = (string)Auth::id() === (string)$leave->user_id;
                            @endphp
                            
                            @if(!$isOwnRequest)
                                <div class="leave-grid-approve-reject">
                                    <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST" class="d-inline-block leave-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="APPROVED">
                                        <button type="submit" class="btn btn-sm btn-success leave-btn-sm approve-btn">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <form action="{{ route('leaves.update-status', $leave->request_id) }}" method="POST" class="d-inline-block leave-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="REJECTED">
                                        <button type="submit" class="btn btn-sm btn-danger leave-btn-sm reject-btn">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
                @empty
                <div class="leave-empty-grid">
                    <div class="empty-state-container">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h5>No leave requests found</h5>
                        <p class="text-muted mb-4">Try adjusting your filters or create a new request</p>
                        <a href="{{ route('leaves.create') }}" class="btn btn-primary leave-btn">
                            <i class="fas fa-plus me-2"></i> Create New Leave Request
                        </a>
                    </div>
                </div>
                @endforelse
            </div>
        </div>
        
        <!-- Pagination -->
        @if(isset($leaveRequests) && $leaveRequests->hasPages())
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing {{ $leaveRequests->firstItem() ?? 0 }} to {{ $leaveRequests->lastItem() ?? 0 }} of {{ $leaveRequests->total() }} leave requests
                </div>
                
                <nav aria-label="Leave request pages">
                    <ul class="pagination">
                        <!-- First Page Link -->
                        <li class="page-item {{ $leaveRequests->onFirstPage() ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $leaveRequests->url(1) }}" aria-label="First">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        
                        <!-- Previous Page Link -->
                        <li class="page-item {{ $leaveRequests->onFirstPage() ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $leaveRequests->previousPageUrl() }}" aria-label="Previous">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        
                        <!-- Current Page Info -->
                        <li class="page-item active">
                            <span class="page-link">
                                {{ $leaveRequests->currentPage() }}
                            </span>
                        </li>
                        
                        <!-- Next Page Link -->
                        <li class="page-item {{ !$leaveRequests->hasMorePages() ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $leaveRequests->nextPageUrl() }}" aria-label="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        
                        <!-- Last Page Link -->
                        <li class="page-item {{ !$leaveRequests->hasMorePages() ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $leaveRequests->url($leaveRequests->lastPage()) }}" aria-label="Last">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        @endif
    </div>
</div>

<!-- Leave Details Modal -->
<div class="modal fade" id="leaveDetailsModal" tabindex="-1" aria-labelledby="leaveDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaveDetailsModalLabel">Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="leave-details-container">
                    <div class="leave-detail-header">
                        <div class="leave-detail-id">
                            Request #<span id="leaveDetailId"></span>
                        </div>
                        <div class="leave-detail-status">
                            <span id="leaveDetailStatus" class="badge"></span>
                        </div>
                    </div>
                    
                    <div class="leave-detail-grid">
                        <div class="leave-detail-item">
                            <div class="leave-detail-label">
                                <i class="fas fa-user me-2"></i> Employee
                            </div>
                            <div class="leave-detail-value" id="leaveDetailEmployee"></div>
                        </div>
                        
                        <div class="leave-detail-item">
                            <div class="leave-detail-label">
                                <i class="fas fa-tag me-2"></i> Leave Type
                            </div>
                            <div class="leave-detail-value" id="leaveDetailType"></div>
                        </div>
                        
                        <div class="leave-detail-item">
                            <div class="leave-detail-label">
                                <i class="fas fa-calendar-day me-2"></i> Start Date
                            </div>
                            <div class="leave-detail-value" id="leaveDetailStart"></div>
                        </div>
                        
                        <div class="leave-detail-item">
                            <div class="leave-detail-label">
                                <i class="fas fa-calendar-day me-2"></i> End Date
                            </div>
                            <div class="leave-detail-value" id="leaveDetailEnd"></div>
                        </div>
                        
                        <div class="leave-detail-item full-width">
                            <div class="leave-detail-label">
                                <i class="fas fa-comment-alt me-2"></i> Reason
                            </div>
                            <div class="leave-detail-value" id="leaveDetailReason">
                                <em class="text-muted">No reason provided</em>
                            </div>
                        </div>
                        
                        <div class="leave-detail-item full-width" id="certificateSection">
                            <div class="leave-detail-label">
                                <i class="fas fa-file-medical me-2"></i> Medical Certificate
                            </div>
                            <div class="leave-detail-value">
                                <button id="viewCertificateBtn" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> View Certificate
                                </button>
                                <a id="downloadCertificateBtn" href="#" class="btn btn-sm btn-outline-secondary" download>
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="leaveModalActions" class="d-flex gap-2 w-100">
                    <!-- Approval/Rejection buttons will be dynamically inserted here -->
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Medical Certificate Modal -->
<div class="modal fade" id="certificateModal" tabindex="-1" aria-labelledby="certificateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="certificateModalLabel">Medical Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="certificate-container">
                    <div id="certificateLoading" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <img id="certificateImage" class="img-fluid d-none" alt="Medical Certificate">
                    <div id="certificateError" class="text-danger d-none">Failed to load certificate.</div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="downloadCertificate" href="#" class="btn btn-primary" download>
                    <i class="fas fa-download me-1"></i> Download Certificate
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        // Initialize tooltips
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
        
        // Show success message if exists
        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        @endif
        
        // Show error message if exists
        @if(session('error'))
            Swal.fire({
                title: 'Error!',
                text: "{{ session('error') }}",
                icon: 'error',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000
            });
        @endif
        
        // Toggle between table and card view
        const tableViewBtn = document.getElementById('tableViewBtn');
        const cardViewBtn = document.getElementById('cardViewBtn');
        const tableView = document.querySelector('.leave-table-view');
        const cardView = document.querySelector('.leave-card-view');
        
        tableViewBtn.addEventListener('click', function() {
            tableView.classList.remove('d-none');
            cardView.classList.add('d-none');
            tableViewBtn.classList.add('active');
            cardViewBtn.classList.remove('active');
            localStorage.setItem('leaveViewPreference', 'table');
        });
        
        cardViewBtn.addEventListener('click', function() {
            tableView.classList.add('d-none');
            cardView.classList.remove('d-none');
            cardViewBtn.classList.add('active');
            tableViewBtn.classList.remove('active');
            localStorage.setItem('leaveViewPreference', 'card');
        });
        
        // Use stored preference or default to table on larger screens, cards on mobile
        const storedViewPreference = localStorage.getItem('leaveViewPreference');
        if (storedViewPreference === 'card' || (window.innerWidth < 768 && !storedViewPreference)) {
            cardViewBtn.click();
        }
        
        // Leave request approval handling
        document.querySelectorAll('.approve-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                
                Swal.fire({
                    title: 'Approve Leave Request?',
                    text: 'Are you sure you want to approve this leave request?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, approve it'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing...',
                            html: 'Approving leave request',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                                form.submit();
                            }
                        });
                    }
                });
            });
        });
        
        // Leave request rejection handling
        document.querySelectorAll('.reject-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                
                Swal.fire({
                    title: 'Reject Leave Request?',
                    text: 'Are you sure you want to reject this leave request?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, reject it'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing...',
                            html: 'Rejecting leave request',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                                form.submit();
                            }
                        });
                    }
                });
            });
        });
        
        // Leave details modal
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Get data attributes
                const leaveId = this.getAttribute('data-id');
                const employee = this.getAttribute('data-employee');
                const type = this.getAttribute('data-type');
                const start = this.getAttribute('data-start');
                const end = this.getAttribute('data-end');
                const status = this.getAttribute('data-status');
                const reason = this.getAttribute('data-reason');
                const hasCertificate = this.getAttribute('data-has-certificate') === 'true';
                const certificateUrl = this.getAttribute('data-certificate');
                
                // Populate modal fields
                document.getElementById('leaveDetailId').textContent = leaveId;
                document.getElementById('leaveDetailEmployee').textContent = employee;
                document.getElementById('leaveDetailType').textContent = type;
                document.getElementById('leaveDetailStart').textContent = start;
                document.getElementById('leaveDetailEnd').textContent = end;
                
                // Handle status badge
                const statusBadge = document.getElementById('leaveDetailStatus');
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
                statusBadge.className = 'badge';
                statusBadge.classList.add(
                    status.toLowerCase() === 'pending' ? 'bg-warning' : 
                    status.toLowerCase() === 'approved' ? 'bg-success' : 'bg-danger'
                );
                
                // Handle reason
                if (reason) {
                    document.getElementById('leaveDetailReason').textContent = reason;
                } else {
                    document.getElementById('leaveDetailReason').innerHTML = '<em class="text-muted">No reason provided</em>';
                }
                
                // Handle certificate section
                const certificateSection = document.getElementById('certificateSection');
                if (hasCertificate) {
                    certificateSection.classList.remove('d-none');
                    document.getElementById('viewCertificateBtn').setAttribute('data-certificate', certificateUrl);
                    document.getElementById('downloadCertificateBtn').setAttribute('href', certificateUrl);
                } else {
                    certificateSection.classList.add('d-none');
                }
                
                // Open the modal
                const leaveDetailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
                leaveDetailsModal.show();
            });
        });
        
        // Certificate handling
        document.getElementById('viewCertificateBtn').addEventListener('click', function() {
            const certificateUrl = this.getAttribute('data-certificate');
            
            // Set up certificate modal
            document.getElementById('certificateImage').classList.add('d-none');
            document.getElementById('certificateLoading').classList.remove('d-none');
            document.getElementById('certificateError').classList.add('d-none');
            
            // Set download link
            document.getElementById('downloadCertificate').setAttribute('href', certificateUrl);
            
            // Load the image
            const certificateImage = document.getElementById('certificateImage');
            certificateImage.setAttribute('src', certificateUrl);
            
            // Handle image load success
            certificateImage.onload = function() {
                document.getElementById('certificateLoading').classList.add('d-none');
                certificateImage.classList.remove('d-none');
            };
            
            // Handle image load error
            certificateImage.onerror = function() {
                document.getElementById('certificateLoading').classList.add('d-none');
                document.getElementById('certificateError').classList.remove('d-none');
            };
            
            // Show the certificate modal
            const certificateModal = new bootstrap.Modal(document.getElementById('certificateModal'));
            certificateModal.show();
        });
        
        // Clear filters button
        document.getElementById('clearFilters').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show clearing message
            Swal.fire({
                title: 'Clearing Filters',
                text: 'Resetting to show all leave requests...',
                icon: 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
            
            // Redirect to the base URL
            setTimeout(() => {
                window.location.href = "{{ route('leaves') }}";
            }, 500);
        });
        
        // Auto-submit filter form on change for dropdowns
        document.querySelectorAll('#status, #leave_type').forEach(select => {
            select.addEventListener('change', function() {
                // Show loading spinner
                Swal.fire({
                    title: 'Filtering...',
                    text: 'Applying your filters',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true
                });
                
                // Submit the form
                setTimeout(() => {
                    document.getElementById('leaveFilterForm').submit();
                }, 300);
            });
        });
        
        // Debounce function for search input
        const debounce = (func, delay) => {
            let debounceTimer;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => func.apply(context, args), delay);
            }
        };
        
        // Apply debounce to employee name filter
        const employeeNameInput = document.getElementById('employee_name');
        employeeNameInput.addEventListener('input', debounce(function() {
            if (this.value.length > 0) {
                Swal.fire({
                    title: 'Searching...',
                    text: 'Finding employees matching "' + this.value + '"',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true
                });
                
                document.getElementById('leaveFilterForm').submit();
            }
        }, 800));
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Preview Medical Certificate from table
        document.querySelectorAll('.view-details-btn[data-has-certificate="true"]').forEach(button => {
            button.addEventListener('click', () => {
                const certUrl = button.dataset.certificate;
                if (!certUrl) return;

                Swal.fire({
                    title: 'Medical Certificate',
                    html: `<img src="${certUrl}" alt="Medical Certificate" class="img-fluid rounded shadow">`,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: 600
                });
            });
        });
    });
</script>

@endpush