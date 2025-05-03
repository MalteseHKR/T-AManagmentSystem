@extends('layouts.app')

@section('title', 'Manage Employees - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h1 class="employees-header mb-0">Manage Employees</h1>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary employees-btn">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="{{ route('employees.create') }}" class="btn btn-primary employees-btn">
                <i class="fas fa-user-plus me-2"></i>Add New Employee
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card employee-filter-card shadow-sm mb-4 p-3">
        <div class="card-body">
            <x-filter 
                route="{{ route('employees') }}"
                :has-name-filter="true"
                :has-department-filter="true"
                :departments="$departments"
                name-label="Employee Name"
                name-placeholder="Search by employee name"
                :columns="4"
            />
        </div>
    </div>

    <!-- Employee Stats Summary -->
    <div class="employee-stats-bar mb-4">
        <div class="stats-item">
            <div class="stats-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-info">
                <span class="stats-value">{{ $userInformation->total() }}</span>
                <span class="stats-label">Total Employees</span>
            </div>
        </div>
        <div class="stats-item">
            <div class="stats-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-info">
                <span class="stats-value">{{ $activeEmployees ?? 0 }}</span>
                <span class="stats-label">Active</span>
            </div>
        </div>
        <div class="stats-item">
            <div class="stats-icon">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stats-info">
                <span class="stats-value">{{ $inactiveEmployees ?? 0 }}</span>
                <span class="stats-label">Inactive</span>
            </div>
        </div>
        <div class="stats-item">
            <div class="stats-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="stats-info">
                <span class="stats-value">{{ count($departments) }}</span>
                <span class="stats-label">Departments</span>
            </div>
        </div>
    </div>

    <!-- Employee List -->
    <div class="employee-cards">
        @forelse ($userInformation as $user)
            <div class="employee-card" data-id="{{ $user->user_id }}">
                <div class="employee-header">
                    <div class="employee-avatar">
                        @if(isset($user->user_id))
                            <img src="{{ url('/profile-image/' . $user->user_id) }}"
                                 alt="Portrait of {{ $user->user_name }}"
                                 class="portrait-image"
                                 onerror="this.onerror=null; this.src='{{ asset('images/default-portrait.png') }}';">
                        @else
                            <div class="avatar-placeholder {{ $user->user_active ? 'active' : 'inactive' }}">
                                {{ strtoupper(substr($user->user_name, 0, 1)) }}{{ strtoupper(substr($user->user_surname, 0, 1)) }}
                            </div>
                        @endif
                        <span class="status-indicator {{ $user->user_active ? 'status-active' : 'status-inactive' }}"></span>
                    </div>
                    <div class="employee-info">
                        <h5 class="employee-name">{{ $user->user_name }} {{ $user->user_surname }}</h5>
                        <p class="employee-title">{{ $user->role ? $user->role->role : 'No Role' }}</p>
                        <p class="employee-department">
                            <i class="fas fa-building me-1 text-muted"></i>
                            {{ $user->department ? $user->department->department : 'No Department' }}
                        </p>
                    </div>
                </div>
                
                <div class="employee-contact">
                    @if($user->user_email)
                    <p class="mb-1">
                        <i class="fas fa-envelope me-1 text-muted"></i>
                        <a href="mailto:{{ $user->user_email }}">{{ $user->user_email }}</a>
                    </p>
                    @endif
                    
                    @if($user->user_phone)
                    <p class="mb-0">
                        <i class="fas fa-phone me-1 text-muted"></i>
                        <a href="tel:{{ $user->user_phone }}">{{ $user->user_phone }}</a>
                    </p>
                    @endif
                </div>
                
                <div class="employee-actions">
                    <!-- View button - direct link -->
                    <a href="{{ url('employee/profile', $user->user_id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i> View
                    </a>
                    
                    <!-- Edit button - correct route -->
                    <a href="{{ route('employees.edit', ['id' => $user->user_id]) }}" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-pen me-1"></i> Edit
                    </a>
                    
                    <!-- Remove attendance button since data isn't working properly -->
                    <!-- <a href="{{ url('attendance/employee', $user->user_id) }}" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-calendar-check me-1"></i> Attendance
                    </a> -->
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-users-slash"></i>
                </div>
                <h3>No Employees Found</h3>
                <p>No employee records match your search criteria.</p>
                <div class="mt-3">
                    <a href="{{ route('employees') }}" class="btn btn-outline-primary">
                        <i class="fas fa-sync-alt me-1"></i> Clear Filters
                    </a>
                    <a href="{{ route('employees.create') }}" class="btn btn-primary ms-2">
                        <i class="fas fa-user-plus me-1"></i> Add Employee
                    </a>
                </div>
            </div>
        @endforelse
    </div>
    
    <!-- Pagination -->
    @if(isset($userInformation) && method_exists($userInformation, 'links') && $userInformation->lastPage() > 1)
    <div class="pagination-container mt-4">
        <div class="pagination-info">
            Showing {{ $userInformation->firstItem() ?? 0 }} to {{ $userInformation->lastItem() ?? 0 }} of {{ $userInformation->total() }} employees
        </div>
        
        <nav aria-label="Employee pages">
            <ul class="pagination">
                <!-- First Page Link -->
                <li class="page-item {{ $userInformation->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $userInformation->url(1) }}" aria-label="First">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                
                <!-- Previous Page Link -->
                <li class="page-item {{ $userInformation->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $userInformation->previousPageUrl() }}" aria-label="Previous">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
                
                <!-- Current Page Info -->
                <li class="page-item active">
                    <span class="page-link">
                        {{ $userInformation->currentPage() }}
                    </span>
                </li>
                
                <!-- Next Page Link -->
                <li class="page-item {{ !$userInformation->hasMorePages() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $userInformation->nextPageUrl() }}" aria-label="Next">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                
                <!-- Last Page Link -->
                <li class="page-item {{ !$userInformation->hasMorePages() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $userInformation->url($userInformation->lastPage()) }}" aria-label="Last">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    @endif

    @if(session('show_image_debug') && session('image_debug_info'))
    <div class="card mt-4 image-debug-card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-camera me-2"></i> Image Upload Results</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Original File</th>
                            <th>New Filename</th>
                            <th>Size</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(session('image_debug_info') as $info)
                            <tr>
                                <td>{{ $info['original_name'] }}</td>
                                <td>{{ $info['new_name'] }}</td>
                                <td>{{ $info['size'] }}</td>
                                <td>
                                    @if(strpos($info['status'], 'Saved') !== false)
                                        <span class="text-success">✓ {{ $info['status'] }}</span>
                                    @elseif(strpos($info['status'], 'Failed') !== false)
                                        <span class="text-danger">✗ {{ $info['status'] }}</span>
                                    @else
                                        {{ $info['status'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Employee Form -->
    <form id="employeeForm" action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data">
    </form>
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
                icon: 'success',
                title: 'Success',
                text: "{{ session('success') }}",
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        @endif
        
        // Show error message if exists
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ session('error') }}",
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        @endif
        
        // Make entire employee card clickable (keep this if you want the card to be clickable)
        document.querySelectorAll('.employee-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Ignore if clicked on a button
                if (e.target.closest('.btn')) {
                    return;
                }
                
                const userId = this.getAttribute('data-id');
                window.location.href = "{{ url('employee/profile') }}/" + userId;
            });
        });
        
        // Implement search functionality with instant filter feedback
        const searchInput = document.querySelector('input[name="name"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (this.value.length > 0) {
                    Swal.fire({
                        title: 'Searching...',
                        text: 'Type at least 3 characters to search',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1000,
                        timerProgressBar: true,
                        icon: 'info'
                    });
                }
            });
        }
    });
</script>
@endpush