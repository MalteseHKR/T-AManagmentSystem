@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Two-Factor Authentication</div>
                
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <div class="mb-3">
                            <i class="bi bi-shield-lock" style="font-size: 3rem; color: #4e73df;"></i>
                        </div>
                        <h5>Authentication Required</h5>
                        <p>Please enter the authentication code from your authenticator app to continue.</p>
                    </div>
                    
                    <form method="POST" action="{{ route('mfa.verify') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="code" class="form-label">Authentication Code</label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                   id="code" name="code" required autocomplete="off" autofocus>
                            
                            @error('code')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary">Verify</button>
                        </div>
                        
                        <p class="text-center">
                            <a href="#" data-bs-toggle="collapse" data-bs-target="#recoveryCodeForm">
                                Lost access to your authenticator app?
                            </a>
                        </p>
                        
                        <div class="collapse mt-3" id="recoveryCodeForm">
                            <div class="card card-body">
                                <h6>Use a Recovery Code</h6>
                                <p class="small">If you can't access your authenticator app, you can use one of your recovery codes to sign in.</p>
                                
                                <div class="mb-3">
                                    <label for="recovery_code" class="form-label">Recovery Code</label>
                                    <input type="text" class="form-control" id="recovery_code" name="code">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-warning">Use Recovery Code</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection