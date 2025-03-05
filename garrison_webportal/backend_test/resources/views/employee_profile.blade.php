@extends('layouts.app')

@section('title', 'Employee Profile - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Employee Profile</h1>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <div class="card mb-4">
        <div class="card-header">
            {{ $employee->first_name }} {{ $employee->surname }}
        </div>
        <div class="card-body">
            <!-- Employee Portrait -->
            @if($employee->portrait_url)
                <img src="{{ asset($employee->portrait_url) }}" alt="Portrait of {{ $employee->first_name }} {{ $employee->surname }}" class="img-fluid mb-3" style="max-width: 150px; border-radius: 50%;">
            @else
                <img src="{{ asset('images/default-portrait.png') }}" alt="Default Portrait" class="img-fluid mb-3" style="max-width: 150px; border-radius: 50%;">
            @endif

            <p><strong>Department:</strong> {{ $employee->department }}</p>
            <p><strong>Email:</strong> {{ $employee->email }}</p>
            <p><strong>Phone:</strong> {{ $employee->phone_number }}</p>
            <p><strong>Date of Birth:</strong> {{ $employee->date_of_birth }}</p>
            <p><strong>Start Date:</strong> {{ $employee->start_date }}</p>
            <p><strong>Active:</strong> {{ $employee->is_active ? 'Yes' : 'No' }}</p>
            <!-- Add more employee details as needed -->
        </div>
    </div>

    <!-- Link to Attendance Page -->
    <a href="{{ route('attendance.show', $employee->id) }}" class="btn btn-primary">View Attendance</a>
</div>
@endsection