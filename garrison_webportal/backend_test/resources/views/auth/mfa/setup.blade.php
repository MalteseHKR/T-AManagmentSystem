@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Set Up Two-Factor Authentication</div>
                
                <div class="card-body">
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    
                    <div class="mb-4">
                        <h5>1. Scan this QR code with your authenticator app</h5>
                        <p>Use an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator to scan this QR code.</p>
                        
                        <div class="text-center my-4">
                            {!! $qrCodeUrl !!}
                        </div>
                        
                        <div class="alert alert-info">
                            <p class="mb-0"><strong>Can't scan the QR code?</strong> Enter this code manually in your app: <code>{{ $secretKey }}</code></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>2. Enter the verification code from your app</h5>
                        <p>After scanning the QR code, enter the 6-digit verification code displayed in your authenticator app to verify setup.</p>
                        
                        <form method="POST" action="{{ route('mfa.enable') }}">
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
                            
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('mfa.index') }}" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Verify and Enable</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection