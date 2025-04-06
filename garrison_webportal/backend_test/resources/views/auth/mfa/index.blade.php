
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Two-Factor Authentication</div>
                
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    
                    @if (session('info'))
                        <div class="alert alert-info">
                            {{ session('info') }}
                        </div>
                    @endif
                    
                    <div class="mb-4">
                        <h5>Two-Factor Authentication (2FA)</h5>
                        <p>Two-factor authentication adds an extra layer of security to your account by requiring more than just a password to log in.</p>
                    </div>
                    
                    @if ($isMfaEnabled)
                        <div class="alert alert-success">
                            <i class="bi bi-shield-check"></i> Two-factor authentication is currently <strong>enabled</strong>.
                        </div>
                        
                        <div class="mt-4">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('mfa.recovery-codes') }}" class="btn btn-info">
                                    View Recovery Codes
                                </a>
                                
                                <a href="{{ route('mfa.regenerate-codes') }}" class="btn btn-warning">
                                    Regenerate Recovery Codes
                                </a>
                                
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#disableMfaModal">
                                    Disable 2FA
                                </button>
                            </div>
                        </div>
                        
                        <!-- Disable MFA Modal -->
                        <div class="modal fade" id="disableMfaModal" tabindex="-1" aria-labelledby="disableMfaModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="disableMfaModalLabel">Disable Two-Factor Authentication</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="{{ route('mfa.disable') }}">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="alert alert-warning">
                                                <strong>Warning:</strong> Disabling 2FA will make your account less secure.
                                            </div>
                                            
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="confirm" value="confirm" id="confirmDisable" required>
                                                <label class="form-check-label" for="confirmDisable">
                                                    I understand and want to disable two-factor authentication
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Disable 2FA</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="bi bi-shield-exclamation"></i> Two-factor authentication is currently <strong>disabled</strong>.
                        </div>
                        
                        <div class="mt-4">
                            <a href="{{ route('mfa.setup') }}" class="btn btn-primary">
                                Enable Two-Factor Authentication
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection