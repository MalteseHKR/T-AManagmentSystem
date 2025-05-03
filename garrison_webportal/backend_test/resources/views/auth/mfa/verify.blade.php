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
                    <input type="hidden" name="recovery_mode" id="recovery_mode" value="false">
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="code" class="form-label mb-0">Authentication Code</label>
                            <div class="input-mode-toggle">
                                <span id="mode-indicator" class="badge bg-primary">Authenticator Mode</span>
                            </div>
                        </div>
                        
                        <div class="code-input-container">
                            <input id="code" type="text" 
                                   class="form-control @error('code') is-invalid @enderror" 
                                   name="code" required autofocus 
                                   maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                   placeholder="Enter 6-digit code">
                            <i class="fa fa-lock input-icon"></i>
                        </div>
                        
                        <small class="form-text text-muted" id="code-instructions">
                            Enter the 6-digit code from your authenticator app.
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

@push('scripts')
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
        const modeIndicator = document.getElementById('mode-indicator');
        const codeInstructions = document.getElementById('code-instructions');
        let isRecoveryMode = false;
        
        if (codeInput) {
            codeInput.focus();
        }

        // Format code input based on mode
        codeInput.addEventListener('input', function(e) {
            if (!isRecoveryMode) {
                // Authenticator mode - 6 digits only
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

                    setTimeout(() => {
                        document.querySelector('.verify-form button[type="submit"]').click();
                    }, 300);
                }
            } else {
                // Recovery mode - allow alphanumeric and hyphens
                this.value = this.value.replace(/[^a-zA-Z0-9\-]/g, '');
            }
        });

        // Help link for recovery options with mode toggle
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
                    confirmButtonColor: '#2563eb',
                    showCancelButton: true,
                    confirmButtonText: 'Switch to Recovery Mode',
                    cancelButtonText: 'Stay in Authenticator Mode'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Switch to recovery code mode
                        document.getElementById('recovery_mode').value = 'true';
                        
                        isRecoveryMode = true;
                        
                        // Update UI to reflect recovery mode
                        modeIndicator.textContent = 'Recovery Mode';
                        modeIndicator.classList.remove('bg-primary');
                        modeIndicator.classList.add('bg-warning');
                        
                        // Update input attributes
                        codeInput.removeAttribute('maxlength');
                        codeInput.removeAttribute('pattern');
                        codeInput.removeAttribute('inputmode');
                        codeInput.placeholder = 'Enter recovery code';
                        codeInstructions.textContent = 'Enter your recovery code (e.g. ABCD-EFGH-IJKL)';
                        
                        // Clear any existing input and focus
                        codeInput.value = '';
                        codeInput.focus();
                    }
                });
            });
        }
        
        // Add a button to switch back to authenticator mode
        const resetModeBtn = document.createElement('button');
        resetModeBtn.type = 'button';
        resetModeBtn.className = 'btn btn-sm btn-outline-secondary mt-2 d-none';
        resetModeBtn.innerHTML = '<i class="fa fa-undo me-1"></i> Switch back to Authenticator Mode';
        resetModeBtn.id = 'reset-mode-btn';
        
        document.querySelector('.verify-form').appendChild(resetModeBtn);
        
        resetModeBtn.addEventListener('click', function() {
            // Switch back to authenticator mode
            document.getElementById('recovery_mode').value = 'false';
            isRecoveryMode = false;
            
            // Update UI to reflect authenticator mode
            modeIndicator.textContent = 'Authenticator Mode';
            modeIndicator.classList.remove('bg-warning');
            modeIndicator.classList.add('bg-primary');
            
            // Update input attributes
            codeInput.setAttribute('maxlength', '6');
            codeInput.setAttribute('pattern', '[0-9]{6}');
            codeInput.setAttribute('inputmode', 'numeric');
            codeInput.placeholder = 'Enter 6-digit code';
            codeInstructions.textContent = 'Enter the 6-digit code from your authenticator app.';
            
            // Clear any existing input and focus
            codeInput.value = '';
            codeInput.focus();
            
            // Hide the reset button
            this.classList.add('d-none');
        });
        
        // Show/hide reset button based on mode
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (modeIndicator.classList.contains('bg-warning')) {
                        resetModeBtn.classList.remove('d-none');
                    } else {
                        resetModeBtn.classList.add('d-none');
                    }
                }
            });
        });
        
        observer.observe(modeIndicator, { attributes: true });
    });
</script>
@endpush