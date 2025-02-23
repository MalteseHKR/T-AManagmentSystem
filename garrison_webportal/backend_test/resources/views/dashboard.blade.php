@extends('app')

@section('title', 'Dashboard - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">HR Dashboard</h1>

    <!-- Customized Welcome Message -->
    <div class="alert alert-info">
        <h4>Welcome, {{ Auth::user()->name }}!</h4>
        <p>We're glad to have you back. Here's an overview of your HR management tools.</p>
    </div>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <div class="row mb-4">
        <!-- Employee Management -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    Employee Management
                </div>
                <div class="card-body">
                    <p>Manage employee records, add new employees, and update existing employee information.</p>
                    <a href="{{ route('employees') }}" class="btn btn-primary">Manage Employees</a>
                </div>
            </div>
        </div>

        <!-- Attendance Tracking -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    Attendance Tracking
                </div>
                <div class="card-body">
                    <p>Track employee attendance, view attendance reports, and manage attendance records.</p>
                    <a href="{{ route('attendance.index') }}" class="btn btn-primary">Track Attendance</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Announcements -->
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    Announcements
                </div>
                <div class="card-body">
                    <p>Post company-wide announcements and keep employees informed about important updates.</p>
                    <a href="{{ route('announcements') }}" class="btn btn-primary">Post Announcement</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Payroll -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    Payroll
                </div>
                <div class="card-body">
                    <p>Calculate and manage payroll, generate payslips, and handle payroll-related functions.</p>
                    <a href="{{ route('payroll') }}" class="btn btn-primary">Manage Payroll</a>
                </div>
            </div>
        </div>

        <!-- Leave Management -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    Leave Management
                </div>
                <div class="card-body">
                    <p>Manage employee leave requests, approve or reject leave applications, and view leave balances.</p>
                    <a href="{{ route('leaves') }}" class="btn btn-primary">Manage Leaves</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection