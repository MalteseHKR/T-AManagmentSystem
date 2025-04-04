@extends('layouts.app')

@section('title', 'Manage Employees - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container">
    <h1 class="mb-4">Manage Employees</h1>

    <!-- Nav Link - FIXED: Removed duplicate button -->
    <div class="d-flex justify-content-between mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <a href="{{ route('create') }}" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Add New Employee
        </a>
    </div>

    <!-- Filter Form -->
    <x-filter 
        route="{{ route('employees') }}"
        :has-name-filter="true"
        :has-department-filter="true"
        :departments="$departments"
        name-label="Employee Name"
        name-placeholder="Search by employee name"
        :columns="4"
    />

    <!-- Employee List -->
    <div class="list-group">
        @forelse ($userInformation as $user)
        <a href="{{ route('employee.profile', ['id' => $user->user_id]) }}" class="list-group-item list-group-item-action">
            <div class="d-flex w-100 justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">{{ $user->user_name }}</h5>
                    <div>
                        <span class="mb-1">Employee ID:</span>
                        <span class="badge bg-secondary">{{ $user->user_id }}</span>
                        
                        @if($user->department)
                        <span class="ms-3 mb-1">Department:</span>
                        <span class="badge bg-info">{{ $user->user_department }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="badge bg-primary">{{ $user->user_title }}</span>
                </div>
            </div>
        </a>
        @empty
        <div class="alert alert-info">
            No employees found. Please add employees to the system.
        </div>
        @endforelse
    </div>
    
    <!-- Pagination -->
    @if(isset($userInformation) && method_exists($userInformation, 'links'))
    <div class="mt-4">
        {{ $userInformation->links() }}
    </div>
    @endif

    @if(session('show_image_debug') && session('image_debug_info'))
    <div class="card mt-4">
        <div class="card-header">Image Upload Results</div>
        <div class="card-body">
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
            
            @if(count(session('saved_image_paths') ?? []) > 0)
                <div class="alert alert-success">
                    Images were saved to: <code>C:\Users\Keith\Pictures\EmployeePhotos</code>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .badge {
        font-size: 0.85em;
    }
    .list-group-item {
        transition: all 0.2s;
    }
    .list-group-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
</style>
@endpush