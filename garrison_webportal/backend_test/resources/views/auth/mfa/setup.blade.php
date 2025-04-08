@extends('layouts.app')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10 col-sm-12">
            <div class="card mfa-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-lock me-2"></i>
                        <span>Set Up Two-Factor Authentication</span>
                    </div>
                    
                    <!-- Add back button in header -->
                    <a href="{{ route('mfa.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to 2FA Settings
                    </a>
                </div>
                
                <div class="card-body">
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
                    
                    <div class="setup-progress mb-4">
                        <div class="progress-step active">
                            <span class="step-number">1</span>
                            <span class="step-label d-none d-md-inline">Scan QR Code</span>
                        </div>
                        <div class="progress-connector"></div>
                        <div class="progress-step">
                            <span class="step-number">2</span>
                            <span class="step-label d-none d-md-inline">Verify Code</span>
                        </div>
                        <div class="progress-connector"></div>
                        <div class="progress-step">
                            <span class="step-number">3</span>
                            <span class="step-label d-none d-md-inline">Complete</span>
                        </div>
                    </div>
                    
                    <!-- STEP 1: QR CODE SECTION -->
                    <div class="step-section mb-4">
                        <h5 class="step-title">Scan this QR code with your authenticator app</h5>
                        <p class="step-description">Use an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator to scan this QR code.</p>
                        
                        <div class="qr-container my-4">
                            <div class="qr-code-wrapper">
                                <img src="{!! $qrCodeDataUri !!}" alt="QR Code" class="qr-code-image">
                            </div>
                            
                            <!-- Manual entry option -->
                            <div class="manual-entry-container">
                                <div class="alert alert-info">
                                    <strong>Secret Key for Manual Entry:</strong>
                                    <div class="secret-key mt-2">
                                        <code>{{ $secretKey }}</code>
                                    </div>
                                    <small class="d-block mt-2">Enter this code manually in your authenticator app</small>
                                </div>
                                
                                <!-- Direct link for mobile devices -->
                                <a href="{{ $otpauthUrl }}" class="btn btn-primary mt-2 mobile-app-btn">
                                    <i class="bi bi-phone me-1"></i> Open in Authenticator App
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 2: VERIFICATION FORM SECTION -->
                    <div class="step-section mb-4">
                        <h5 class="step-title">Enter the verification code from your app</h5>
                        <p class="step-description">After scanning the QR code, enter the 6-digit verification code displayed in your authenticator app to verify setup.</p>
                        
                        <form method="POST" action="{{ route('mfa.enable') }}" class="verification-form">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="code" class="form-label">Authentication Code</label>
                                <div class="code-input-container">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                           id="code" name="code" required autocomplete="off" autofocus
                                           maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                           placeholder="Enter 6-digit code">
                                    <i class="bi bi-lock-fill input-icon"></i>
                                </div>
                                
                                @error('code')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            
                            <div class="d-flex flex-column flex-md-row justify-content-between mt-4">
                                <a href="{{ route('mfa.index') }}" class="btn btn-secondary mb-3 mb-md-0">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-check me-1"></i> Verify and Enable
                                </button>
                            </div>
                        </form>
                    </div>
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
        // Display notifications if present
        const messageTypes = [
            { id: 'success-message', title: 'Success', icon: 'success', buttonColor: '#28a745' },
            { id: 'error-message', title: 'Error', icon: 'error', buttonColor: '#dc3545' },
            { id: 'warning-message', title: 'Warning', icon: 'warning', buttonColor: '#ffc107' },
            { id: 'info-message', title: 'Information', icon: 'info', buttonColor: '#17a2b8' }
        ];
        
        // Check for messages and display the first one found
        for (const type of messageTypes) {
            const messageEl = document.getElementById(type.id);
            if (messageEl) {
                Swal.fire({
                    title: type.title,
                    text: messageEl.dataset.message,
                    icon: type.icon,
                    confirmButtonColor: type.buttonColor,
                    toast: true,
                    position: 'top-end',
                    timer: 5000,
                    timerProgressBar: true
                });
                break; // Only show one message at a time
            }
        }

        // QR Code Copy Functionality
        const secretKeyElement = document.querySelector('.secret-key code');
        if (secretKeyElement) {
            secretKeyElement.addEventListener('click', function() {
                const secretKey = this.textContent.trim();
                navigator.clipboard.writeText(secretKey).then(() => {
                    Swal.fire({
                        title: 'Copied!',
                        text: 'The secret key has been copied to your clipboard.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }).catch(err => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to copy the secret key: ' + err,
                        icon: 'error'
                    });
                });
            });
        }

        // Focus input field automatically
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.focus();

            // Format code input to numbers only
            codeInput.addEventListener('input', function(e) {
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
                        document.querySelector('.verification-form button[type="submit"]').click();
                    }, 500);
                }
            });
        }
    });
</script>
@endsection