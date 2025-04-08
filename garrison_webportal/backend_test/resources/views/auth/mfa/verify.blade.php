@extends('layouts.app')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10 col-sm-12">
        <div class="card mfa-verify-card">
            <div class="card-header d-flex align-items-center">
                <i class="fa-solid fa-user-shield text-white me-2"></i>
                <span>Two-Factor Authentication</span>
            </div>
            
            <div class="card-body px-5">
                <!-- Hidden divs for SweetAlert messages -->
                @if (session('error'))
                    <div id="error-message" data-message="{{ session('error') }}" class="d-none"></div>
                @endif
                
                @if (session('success'))
                    <div id="success-message" data-message="{{ session('success') }}" class="d-none"></div>
                @endif
                
                @if (session('warning'))
                    <div id="warning-message" data-message="{{ session('warning') }}" class="d-none"></div>
                @endif
                
                @if (session('info'))
                    <div id="info-message" data-message="{{ session('info') }}" class="d-none"></div>
                @endif
                
                <div class="verify-icon-container mb-4 text-center">
                    <div class="verify-icon-wrapper mb-3">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <h5 class="verify-title">Authentication Required</h5>
                    <p class="verify-description">Please enter the authentication code from your authenticator app to continue.</p>
                </div>
                
                <form method="POST" action="{{ route('mfa.verify') }}" class="verify-form">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Authentication Code</label>
                        <div class="code-input-container">
                            <input id="code" type="text" 
                                   class="form-control @error('code') is-invalid @enderror" 
                                   name="code" required autofocus 
                                   maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                   placeholder="Enter 6-digit code">
                            <i class="fa fa-lock input-icon"></i>
                        </div>
                        
                        <small class="form-text text-muted">
                            Enter the 6-digit code from your authenticator app OR enter a recovery code.
                        </small>
                        
                        @error('code')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                    
                    <div class="mt-4 d-grid">
                        <button type="submit" class="btn btn-primary btn-verify">
                            <i class="fa fa-shield-halved me-1"></i> Verify
                        </button>
                    </div>
                </form>
                
                <div class="mt-4 text-center">
                    <a href="javascript:void(0);" id="recovery-help" class="text-decoration-none">
                        <i class="fa fa-question-circle me-1"></i> Need help? Lost your device?
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Display SweetAlert notifications for flash messages
        const messageTypes = [
            { id: 'error-message', title: 'Error', icon: 'error', buttonColor: '#dc3545' },
            { id: 'success-message', title: 'Success', icon: 'success', buttonColor: '#28a745' },
            { id: 'warning-message', title: 'Warning', icon: 'warning', buttonColor: '#ffc107' },
            { id: 'info-message', title: 'Information', icon: 'info', buttonColor: '#0dcaf0' }
        ];

        // Check for messages and display the first one found
        for (const type of messageTypes) {
            const messageEl = document.getElementById(type.id);
            if (messageEl && messageEl.dataset.message) {
                Swal.fire({
                    title: type.title,
                    text: messageEl.dataset.message,
                    icon: type.icon,
                    confirmButtonColor: type.buttonColor,
                    toast: true,
                    position: 'top-end',
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
                break; // Only show one message at a time
            }
        }

        // Focus input field automatically
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.focus();
        }

        // Format code input to numbers only
        codeInput.addEventListener('input', function(e) {
            // Allow for recovery codes (which can contain letters) or 6-digit codes
            if (this.value.length <= 6) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);

                // Auto-submit if 6 digits entered
                if (this.value.length === 6) {
                    Swal.fire({
                        title: 'Verifying...',
                        text: 'Please wait while we verify your code.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Optional: Add a small delay before submitting
                    setTimeout(() => {
                        document.querySelector('.verify-form button[type="submit"]').click();
                    }, 300);
                }
            }
        });

        // Help link for recovery options
        const recoveryHelpLink = document.getElementById('recovery-help');
        if (recoveryHelpLink) {
            recoveryHelpLink.addEventListener('click', function() {
                Swal.fire({
                    title: 'Recovery Options',
                    html: `
                        <div class="text-start">
                            <p>If you've lost access to your authenticator app, you can use one of your recovery codes to log in.</p>
                            <p>Recovery codes look like: <code>ABCD-EFGH-IJKL</code></p>
                            <p>If you don't have your recovery codes, please contact support for assistance.</p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonColor: '#2563eb'
                });
            });
        }
    });
</script>
@endsection