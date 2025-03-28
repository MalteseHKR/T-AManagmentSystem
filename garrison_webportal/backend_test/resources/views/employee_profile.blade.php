@extends('layouts.app')

@section('title', 'Employee Profile - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container">
    <h1 class="mb-4">Employee Profile</h1>

    <!-- Back Link -->
    <a href="{{ route('employees') }}" class="btn btn-secondary mb-4">Back</a>

    <div class="card mb-4">
        <div class="card-header">
            {{ $userInfo->user_name }}
        </div>
        <div class="card-body">
            <!-- Employee Portrait -->
            @if(isset($userInfo->portrait_url) && $userInfo->portrait_url)
                <img src="{{ asset($userInfo->portrait_url) }}" alt="Portrait of {{ $userInfo->user_name }}" class="img-fluid mb-3" style="max-width: 150px; border-radius: 50%;">
            @else
                <img src="{{ asset('images/default-portrait.png') }}" alt="Default Portrait" class="img-fluid mb-3" style="max-width: 150px; border-radius: 50%;">
            @endif

            <p><strong>Employee ID:</strong> {{ $userInfo->user_id }}</p>
            <p><strong>Department:</strong> {{ $userInfo->user_department ?? 'Not specified' }}</p>
            <p><strong>Job Title:</strong> {{ $userInfo->user_title ?? 'Not specified' }}</p>
            <p><strong>Email:</strong> {{ $userInfo->user_email ?? 'Not specified' }}</p>
            
            @if(isset($userInfo->phone_number))
            <p><strong>Phone:</strong> {{ $userInfo->user_phone }}</p>
            @endif
            
            @if(isset($userInfo->date_of_birth))
            <p><strong>Date of Birth:</strong> {{ $userInfo->user_dob }}</p>
            @endif
            
            @if(isset($userInfo->hire_date))
            <p><strong>Hire Date:</strong> {{ $userInfo->user_job_start }}</p>
            @endif
            
            <p><strong>Status:</strong> 
                @if(isset($userInfo->user_active) && $userInfo->user_active == 1)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-danger">Inactive</span>
                @endif
            </p>
        </div>
    </div>

    <!-- Attendance Records -->
    <div class="card mb-4">
        <div class="card-header">Recent Attendance</div>
        <div class="card-body">
            @if($attendanceRecords->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendanceRecords->take(5) as $record)
                                <tr>
                                    <td>{{ $record->punch_date }}</td>
                                    <td>{{ $record->punch_time }}</td>
                                    <td>
                                        @if($record->punch_type == 'IN')
                                            <span class="badge bg-success">Clock In</span>
                                        @elseif($record->punch_type == 'OUT')
                                            <span class="badge bg-danger">Clock Out</span>
                                        @else
                                            <span class="badge bg-info">{{ $record->punch_type }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($record->latitude && $record->longitude)
                                            <a href="https://www.google.com/maps?q={{ $record->latitude }},{{ $record->longitude }}" 
                                               target="_blank" class="text-primary">
                                                <i class="fas fa-map-marker-alt"></i> View on Map
                                            </a>
                                        @else
                                            <span class="text-muted">No location</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center">No attendance records found.</p>
            @endif
        </div>
        <div class="card-footer">
            <!-- Link to Attendance Page -->
            <a href="{{ route('attendance.employee', ['employeeId' => $userInfo->user_id]) }}" class="btn btn-primary">View Full Attendance History</a>
        </div>
    </div>
</div>
@endsection