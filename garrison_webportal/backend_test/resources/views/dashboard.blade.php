@extends('layouts.app')

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
    <a href="login" class="btn btn-secondary mb-4">Back</a>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Employee Management -->
        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                Employee Management
            </div>
            <div class="card-body">
                <p>Manage employee records, add new employees, and update existing employee information.</p>
                <a href="{{ route('employees') }}" class="btn btn-primary">Manage Employees</a>
            </div>
        </div>

        <!-- Attendance Tracking -->
        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Attendance Tracking
            </div>
            <div class="card-body">
                <p>Track employee attendance, view attendance reports, and manage attendance records.</p>
                <a href="{{ route('attendance') }}" class="btn btn-primary">View Attendance</a>
            </div>
        </div>

        <!-- Announcements -->
        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                </svg>
                Announcements
            </div>
            <div class="card-body">
                <p>Post company-wide announcements and keep employees informed about important updates.</p>
                <a href="{{ route('announcements') }}" class="btn btn-primary">View Announcements</a>
            </div>
        </div>

        <!-- Payroll -->
        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Payroll
            </div>
            <div class="card-body">
                <p>Calculate and manage payroll, generate payslips, and handle payroll-related functions.</p>
                <a href="{{ route('payroll') }}" class="btn btn-primary">Manage Payroll</a>
            </div>
        </div>

        <!-- Leave Management -->
        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Leave Management
            </div>
            <div class="card-body">
                <p>Manage employee leave requests, approve or reject leave applications, and view leave balances.</p>
                <a href="{{ route('leaves') }}" class="btn btn-primary">Manage Leaves</a>
            </div>
        </div>
    </div>
</div>
@endsection