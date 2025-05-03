@extends('layouts.app')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10 col-sm-12">
        <div class="card password-change-card">
            <div class="card-header d-flex align-items-center">
                <i class="fa fa-shield-lock me-2"></i>
                <span>{{ __('Change Your Password') }}</span>
            </div>

            <div class="card-body px-4 py-3">
                <form method="POST" action="{{ route('password.change.submit') }}" id="password-change-form">
                    @csrf

                    <div class="mb-4">
                        <label for="new_password" class="form-label fw-semibold">{{ __('New Password') }}</label>
                        <div class="password-input-container">
                            <input id="new_password" type="password" 
                                   class="form-control @error('new_password') is-invalid @enderror" 
                                   name="new_password" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-password" tabindex="-1">
                                <i class="fa fa-eye-slash"></i>
                            </button>
                        </div>
                        @error('new_password')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="new_password_confirmation" class="form-label fw-semibold">{{ __('Confirm New Password') }}</label>
                        <div class="password-input-container">
                            <input id="new_password_confirmation" type="password" 
                                   class="form-control" 
                                   name="new_password_confirmation" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-password" tabindex="-1">
                                <i class="fa fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Password Strength Meter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Password Strength</label>
                        <div class="password-strength-container">
                            <div class="password-strength-meter">
                                <div class="password-strength-bar" id="password-strength-bar"></div>
                            </div>
                            <div class="password-strength-text" id="password-strength-text">No password entered</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary btn-change-password">
                            <i class="fa fa-check-circle me-1"></i> {{ __('Change Password') }}
                        </button>
                    </div>
                    
                    <!-- Password requirements -->
                    <div class="password-requirements mt-4">
                        <h5 class="requirements-title">
                            <i class="fa fa-info-circle me-1"></i> Password Requirements
                        </h5>
                        <div class="requirements-list">
                            <div class="requirement-item" id="req-length">
                                <i class="fa fa-x-circle requirement-icon"></i>
                                <span>8-50 characters in length</span>
                            </div>
                            <div class="requirement-item" id="req-uppercase">
                                <i class="fa fa-x-circle requirement-icon"></i>
                                <span>At least one uppercase letter</span>
                            </div>
                            <div class="requirement-item" id="req-number">
                                <i class="fa fa-x-circle requirement-icon"></i>
                                <span>At least one number</span>
                            </div>
                            <div class="requirement-item" id="req-special">
                                <i class="fa fa-x-circle requirement-icon"></i>
                                <span>At least one special character</span>
                            </div>
                            <div class="requirement-item" id="req-match">
                                <i class="fa fa-x-circle requirement-icon"></i>
                                <span>Passwords match</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Display SweetAlert notifications for flash messages
        @if(session('error'))
            Swal.fire({
                title: 'Error',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        @endif

        @if(session('success'))
            Swal.fire({
                title: 'Success',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#198754'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('dashboard') }}";
                }
            });
        @endif

        // Password toggle buttons
        document.querySelectorAll('.btn-toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        });

        // Password strength and requirements checker
        const passwordInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('new_password_confirmation');
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');

        // Requirement elements
        const reqLength = document.getElementById('req-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        const reqMatch = document.getElementById('req-match');

        function updatePasswordStrength() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            let strength = 0;

            // Reset requirements
            reqLength.querySelector('i').className = 'fa fa-x-circle requirement-icon';
            reqUppercase.querySelector('i').className = 'fa fa-x-circle requirement-icon';
            reqNumber.querySelector('i').className = 'fa fa-x-circle requirement-icon';
            reqSpecial.querySelector('i').className = 'fa fa-x-circle requirement-icon';
            reqMatch.querySelector('i').className = 'fa fa-x-circle requirement-icon';

            // Check length (8-50 characters)
            if (password.length >= 8 && password.length <= 50) {
                strength += 25;
                reqLength.querySelector('i').className = 'fa fa-check-circle requirement-icon text-success';
            }

            // Check for uppercase letter
            if (/[A-Z]/.test(password)) {
                strength += 25;
                reqUppercase.querySelector('i').className = 'fa fa-check-circle requirement-icon text-success';
            }

            // Check for number
            if (/[0-9]/.test(password)) {
                strength += 25;
                reqNumber.querySelector('i').className = 'fa fa-check-circle requirement-icon text-success';
            }

            // Check for special character
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 25;
                reqSpecial.querySelector('i').className = 'fa fa-check-circle requirement-icon text-success';
            }

            // Check if passwords match
            if (password && confirm && password === confirm) {
                reqMatch.querySelector('i').className = 'fa fa-check-circle requirement-icon text-success';
            }

            // Update strength bar
            strengthBar.style.width = strength + '%';

            // Style based on strength
            if (strength === 0) {
                strengthBar.className = 'password-strength-bar';
                strengthText.textContent = 'No password entered';
            } else if (strength <= 25) {
                strengthBar.className = 'password-strength-bar strength-weak';
                strengthText.textContent = 'Weak';
            } else if (strength <= 50) {
                strengthBar.className = 'password-strength-bar strength-fair';
                strengthText.textContent = 'Fair';
            } else if (strength <= 75) {
                strengthBar.className = 'password-strength-bar strength-good';
                strengthText.textContent = 'Good';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                strengthText.textContent = 'Strong';
            }
        }

        passwordInput.addEventListener('input', updatePasswordStrength);
        confirmInput.addEventListener('input', updatePasswordStrength);

        // Client-side validation for password matching
        document.getElementById('password-change-form').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmation = confirmInput.value;

            // Check if all requirements are met
            const allRequirementsMet =
                password.length >= 8 &&
                password.length <= 50 &&
                /[A-Z]/.test(password) &&
                /[0-9]/.test(password) &&
                /[^A-Za-z0-9]/.test(password) &&
                password === confirmation;

            if (!allRequirementsMet) {
                e.preventDefault();
                Swal.fire({
                    title: 'Password Requirements Not Met',
                    text: 'Please ensure your password meets all the requirements listed below.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
</script>
@endpush