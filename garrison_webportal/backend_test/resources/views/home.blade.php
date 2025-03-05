@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-gray-50 to-gray-100">
    <div class="flex flex-col items-center justify-center">
        <!-- Hero Section -->
        <div class="hero-section">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="{{ asset('garrison.svg') }}" 
                     alt="Garrison Logo" 
                     class="logo">
            </div>

            <!-- Welcome Text Section -->
            <div class="welcome-text">
                <h1 class="text-4xl font-bold text-gray-800 mb-4">Welcome to Garrison</h1>
                <p class="text-xl text-gray-600 mb-8">Time and Attendance Management System</p>
                <div class="w-24 h-1 bg-blue-600 mb-8"></div>
                <p class="text-gray-600 mb-8">
                    Streamline your workforce management with our comprehensive time and attendance solution. 
                    Track attendance, manage schedules, and optimize productivity all in one place.
                </p>
                <!-- Login Button -->
                <div class="login-section">
                    <div class="button-container">
                        <a href="{{ route('login') }}" class="login-button">
                            <span class="button-content">
                                <span class="button-text">Login to Dashboard</span>
                                <svg class="button-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="feature-grid">
            <!-- Real-time Tracking Card -->
            <div class="feature-card">
                <div class="text-blue-600">
                    <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Real-time Tracking</h3>
                <p class="text-gray-600">Monitor attendance and time tracking in real-time</p>
            </div>

            <!-- Easy Reporting Card -->
            <div class="feature-card">
                <div class="text-blue-600">
                    <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Easy Reporting</h3>
                <p class="text-gray-600">Generate comprehensive reports with just a few clicks</p>
            </div>

            <!-- Smart Management Card -->
            <div class="feature-card">
                <div class="text-blue-600">
                    <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Smart Management</h3>
                <p class="text-gray-600">Efficiently manage your workforce and schedules</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 py-8 mt-16 flex justify-center items-center">
        <p class="text-center">&copy; {{ date('Y') }} Garrison. All rights reserved.</p>
    </footer>
</div>
@endsection
