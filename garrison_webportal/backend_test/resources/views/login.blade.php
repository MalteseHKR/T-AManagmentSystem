@extends('layouts.auth')

@section('title', 'Login | Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
    /* Custom animations for alerts */
    .animated {
        animation-duration: 0.5s;
    }
    
    .shake {
        animation-name: shakeAnimation;
    }
    
    .fadeInDown {
        animation-name: fadeInDownAnimation;
    }
    
    .faster {
        animation-duration: 0.3s;
    }
    
    .colored-toast.swal2-icon-success {
        background-color: #a5dc86 !important;
    }
    
    .colored-toast.swal2-icon-error {
        background-color: #f27474 !important;
    }
    
    .colored-toast.swal2-icon-warning {
        background-color: #f8bb86 !important;
    }
    
    .colored-toast.swal2-icon-info {
        background-color: #3fc3ee !important;
    }
    
    .colored-toast .swal2-title,
    .colored-toast .swal2-content {
        color: white !important;
    }
    
    @keyframes shakeAnimation {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    @keyframes fadeInDownAnimation {
        from { opacity: 0; transform: translate3d(0, -20px, 0); }
        to { opacity: 1; transform: translate3d(0, 0, 0); }
    }
</style>
@endsection

@section('content')
<div class="login-card ">
    <div class="login-header">
        <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" class="login-logo">
        <h1 class="login-title">Welcome Back</h1>
        <p class="login-subtitle">Sign in to access your account</p>
    </div>
    
    <form action="{{ route('login') }}" method="POST" id="loginForm" class="login-form">
        @csrf
        <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-icon">
                    <i class="fas fa-envelope"></i>
                </span>
                <input type="email" id="email" name="email" class="form-control" 
                       value="{{ old('email') }}" required autofocus
                       placeholder="Enter your email">
            </div>
        </div>
        
        <div class="form-group">
            <div class="password-label-wrapper">
                <label for="password" class="form-label">Password</label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="forgot-password-link">
                        Forgot password?
                    </a>
                @endif
            </div>
            <div class="input-group">
                <span class="input-icon">
                    <i class="fas fa-lock"></i>
                </span>
                <input type="password" id="password" name="password" class="form-control" 
                       required placeholder="Enter your password">
                <button type="button" id="togglePassword" class="toggle-password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-login">
                <span class="btn-text">Sign In</span>
                <i class="fas fa-arrow-right btn-icon"></i>
            </button>
        </div>
    </form>
    
    <div id="debug-container" class="d-none">
        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Errors found:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">Session error: {{ session('error') }}</div>
        @endif
        
        @if(session('message'))
            <div class="alert alert-info">Session message: {{ session('message') }}</div>
        @endif
    </div>
    
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug log to confirm script execution
        console.log('Login page script loaded');

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
@endsection