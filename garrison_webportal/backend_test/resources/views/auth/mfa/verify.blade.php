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
                        
                        <div class="form-group">
                            <label for="code">Authentication Code</label>
                            <input id="code" type="text" class="form-control" name="code" required autofocus>
                            <small class="form-text text-muted">Enter the 6-digit code from your authenticator app OR enter a recovery code.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Verify</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection