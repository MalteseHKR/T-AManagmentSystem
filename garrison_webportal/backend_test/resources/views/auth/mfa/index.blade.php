@extends('layouts.app')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10 col-sm-12">
        <div class="card mfa-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fa-solid fa-user-shield text-white me-2"></i> Two-Factor Authentication</span>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary mfa-btn">
                    <i class="fa fa-house me-2"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="card-body px-5">
                <!-- Hidden divs with flash messages for SweetAlert -->
                @if (session('success'))
                    <div id="success-message" data-message="{{ session('success') }}" class="d-none"></div>
                @endif
                
                @if (session('error'))
                    <div id="error-message" data-message="{{ session('error') }}" class="d-none"></div>
                @endif
                
                @if (session('info'))
                    <div id="info-message" data-message="{{ session('info') }}" class="d-none"></div>
                @endif
                
                <div class="mb-4">
                    <h5 class="mfa-title">Two-Factor Authentication (2FA)</h5>
                    <p class="mfa-description">Two-factor authentication adds an extra layer of security to your account by requiring more than just a password to log in.</p>
                </div>
                
                @if ($isMfaEnabled)
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="fa fa-shield-halved fs-5 me-2"></i> 
                        <div>Two-factor authentication is currently <strong>enabled</strong>.</div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="mfa-button-group">
                            <a href="{{ route('mfa.recovery-codes') }}" class="btn btn-info mfa-btn">
                                <i class="fa fa-key me-1"></i> View Recovery Codes
                            </a>
                            
                            <a href="{{ route('mfa.regenerate-codes') }}" class="btn btn-warning mfa-btn">
                                <i class="fa fa-repeat me-1"></i> Regenerate Codes
                            </a>
                            
                            <button type="button" class="btn btn-danger mfa-btn" id="disable-mfa-btn">
                                <i class="fa fa-shield-virus me-1"></i> Disable 2FA
                            </button>
                            
                        </div>
                    </div>
                    
                    <!-- Form for disable MFA action (hidden) -->
		    <form id="disable-mfa-form" method="POST" action="{{ route('mfa.disable') }}" class="d-none">
    			@csrf
    			<input type="hidden" name="confirm" value="confirm">
		    </form>
                @else
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="fa fa-shield-virus fs-5 me-2"></i>
                        <div>Two-factor authentication is currently <strong>disabled</strong>.</div>
                    </div>
                    
                    <div class="mt-4 d-flex flex-column flex-md-row align-items-center justify-content-md-between">
                        <a href="{{ route('mfa.setup') }}" class="btn btn-primary btn-lg mfa-enable-btn mb-3 mb-md-0">
                            <i class="fa-solid fa-shield me-1"></i> Enable Two-Factor Authentication
                        </a>
                        
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                            <i class="fa fa-house me-1"></i> Back to Dashboard
                        </a>
                    </div>
                @endif
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
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            Swal.fire({
                title: 'Success!',
                text: successMessage.dataset.message,
                icon: 'success',
                confirmButtonColor: '#198754'
            });
        }

        const errorMessage = document.getElementById('error-message');
        if (errorMessage) {
            Swal.fire({
                title: 'Error!',
                text: errorMessage.dataset.message,
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        }

        const infoMessage = document.getElementById('info-message');
        if (infoMessage) {
            Swal.fire({
                title: 'Information',
                text: infoMessage.dataset.message,
                icon: 'info',
                confirmButtonColor: '#0dcaf0'
            });
        }

        // Handle "Disable 2FA" button click
        const disableMfaBtn = document.getElementById('disable-mfa-btn');
        if (disableMfaBtn) {
            disableMfaBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Disable Two-Factor Authentication?',
                    html: `
                        <div class="alert alert-warning text-start mb-3">
                            <strong>Warning:</strong> Disabling 2FA will make your account less secure.
                        </div>
                        <div class="form-check text-start">
                            <input class="form-check-input" type="checkbox" id="swal-confirm-disable">
                            <label class="form-check-label" for="swal-confirm-disable">
                                I understand and want to disable two-factor authentication
                            </label>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Disable 2FA',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    preConfirm: () => {
                        if (!document.getElementById('swal-confirm-disable').checked) {
                            Swal.showValidationMessage('Please confirm that you understand the security implications');
                            return false;
                        }
                        return true;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('disable-mfa-form').submit();
                    }
                });
            });
        }
    });
</script>
@endpush