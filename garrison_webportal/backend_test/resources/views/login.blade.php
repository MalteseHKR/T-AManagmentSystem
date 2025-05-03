@extends('layouts.auth')

@section('title', 'Login | Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
    .form-input-icon {
        position: relative;
    }
    
    .form-input-icon input {
        padding-left: 50px; /* Make room for the icon */
        height: 50px; /* Consistent height */
    }
    
    .form-input-icon::before {
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-right: none;
        border-radius: 0.25rem 0 0 0.25rem;
        color: #6c757d;
        z-index: 5;
    }
    
    .email-input::before {
        content: "\f0e0"; /* Font Awesome envelope icon */
    }
    
    .password-input::before {
        content: "\f023"; /* Font Awesome lock icon */
    }
    
    /* For password toggle button */
    .password-input input {
        padding-right: 50px;
    }
    
    .toggle-password {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: 45px;
        background: none;
        border: 1px solid #ced4da;
        border-left: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 5;
        border-radius: 0 0.25rem 0.25rem 0;
        background-color: #f8f9fa;
    }
    
    .toggle-password:focus {
        outline: none;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>
@endsection

@section('content')
<div class="d-flex justify-content-center align-items-center min-vh-100 px-3">
    <div class="card border-0 shadow-sm" style="max-width: 450px; width: 100%;">
        <div class="card-body p-4">
            <!-- Logo & Welcome -->
            <div class="text-center mb-4">
                <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" class="img-fluid mb-3" style="max-height: 80px;">
                <h1 class="h3 fw-bold mb-2">Welcome Back</h1>
                <p class="text-muted">Sign in to access your account</p>
            </div>
            
            <!-- Login Form -->
            <form action="{{ route('login') }}" method="POST" id="loginForm">
                @csrf
                <!-- Email Input -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="form-input-icon email-input">
                        <input type="email" id="email" name="email" class="form-control" 
                               value="{{ old('email') }}" required autofocus
                               placeholder="Enter your email">
                    </div>
                </div>
                
                <!-- Password Input -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label mb-0">Password</label>
                        @if(Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-decoration-none small">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <div class="form-input-icon password-input">
                        <input type="password" id="password" name="password" class="form-control" 
                               required placeholder="Enter your password">
                        <span class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" id="loginButton" class="btn btn-primary btn-lg">
                        <span class="d-flex align-items-center justify-content-center">
                            <span>Sign In</span>
                            <i class="fas fa-arrow-right ms-2"></i>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug log to confirm script execution
        console.log('Login page script loaded');
        
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        
        // Form submission - add loading state
        if (loginForm) {
            loginForm.addEventListener('submit', function() {
                if (loginButton) {
                    loginButton.disabled = true;
                    loginButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Signing In...';
                }
            });
        }

        // Display SweetAlert for Laravel validation errors
        @if ($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                html: `
                    <ul class="text-start">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                `,
                confirmButtonColor: '#dc3545'
            });
        @endif

        // Display SweetAlert for session error messages
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ session('error') }}",
                confirmButtonColor: '#dc3545'
            });
        @endif

        // Display SweetAlert for session info messages
        @if(session('message'))
            Swal.fire({
                icon: 'info',
                title: 'Information',
                text: "{{ session('message') }}",
                confirmButtonColor: '#17a2b8'
            });
        @endif

        // Display SweetAlert for remaining login attempts
        @if(session()->has('attempts_left'))
            Swal.fire({
                icon: 'warning',
                title: 'Login Failed',
                html: `
                    <div class="text-center">
                        <p class="mb-3 text-danger">These credentials do not match our records.</p>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            @for ($i = 1; $i <= 4; $i++)
                                <div class="p-2 rounded-circle 
                                    {{ $i <= session('attempts_left') ? 'bg-success bg-opacity-25 border border-success' : 'bg-danger bg-opacity-25 border border-danger' }}" 
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas {{ $i <= session('attempts_left') ? 'fa-unlock text-success' : 'fa-lock text-danger' }}"></i>
                                </div>
                            @endfor
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You have <strong>{{ session('attempts_left') }}</strong> 
                            {{ session('attempts_left') == 1 ? 'attempt' : 'attempts' }} remaining 
                            before your account is temporarily locked.
                        </div>
                    </div>
                `,
                confirmButtonColor: '#ffc107',
                customClass: {
                    popup: 'animated shake faster'
                }
            });
        @endif

        // Display SweetAlert for account lockout with live timer
        @if(session('account_locked'))
            let lockoutTime = {{ session('lockout_time_seconds') }}; // Lockout time in seconds

            const updateTimer = () => {
                const minutes = Math.floor(lockoutTime / 60);
                const seconds = lockoutTime % 60;
                return `${minutes} minute${minutes !== 1 ? 's' : ''} and ${seconds} second${seconds !== 1 ? 's' : ''}`;
            };

            const timerInterval = setInterval(() => {
                if (lockoutTime > 0) {
                    lockoutTime--;
                    Swal.update({
                        html: `
                            <div class="text-center">
                                <div class="lockout-icon mb-3">
                                    <i class="fas fa-user-lock fa-3x text-danger"></i>
                                </div>
                                <p>Your account has been temporarily locked due to multiple failed login attempts.</p>
                                <div class="alert alert-danger mt-3">
                                    <i class="fas fa-clock me-2"></i>
                                    Please try again in: <strong>${updateTimer()}</strong>
                                </div>
                                <p class="mt-3 small text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    This is a security measure to protect your account from unauthorized access.
                                </p>
                            </div>
                        `
                    });
                } else {
                    clearInterval(timerInterval);
                    Swal.close();
                }
            }, 1000);

            Swal.fire({
                icon: 'error',
                title: 'Account Temporarily Locked',
                html: `
                    <div class="text-center">
                        <div class="lockout-icon mb-3">
                            <i class="fas fa-user-lock fa-3x text-danger"></i>
                        </div>
                        <p>Your account has been temporarily locked due to multiple failed login attempts.</p>
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-clock me-2"></i>
                            Please try again in: <strong>${updateTimer()}</strong>
                        </div>
                        <p class="mt-3 small text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            This is a security measure to protect your account from unauthorized access.
                        </p>
                    </div>
                `,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'I Understand',
                allowOutsideClick: false,
                customClass: {
                    popup: 'animated fadeInDown faster'
                },
                didClose: () => {
                    clearInterval(timerInterval); // Ensure the interval is cleared when the modal is closed
                }
            });
        @endif

        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Toggle eye icon
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
    });
</script>
@endpush