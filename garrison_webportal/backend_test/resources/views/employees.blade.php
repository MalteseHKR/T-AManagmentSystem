@extends('layouts.app')

@section('title', 'Manage Employees - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Manage Employees</h1>

    <!-- Nav Link -->
    <div class="d-flex justify-content-between mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back</a>
        <a href="{{ route('create') }}" class="btn btn-primary">Create New Employee</a>
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