@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Change Your Password') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('password.change.submit') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="new_password" class="col-md-4 col-form-label text-md-right">{{ __('New Password') }}</label>

                            <div class="col-md-6">
                                <input id="new_password" type="password" class="form-control @error('new_password') is-invalid @enderror" name="new_password" required autocomplete="new-password">

                                @error('new_password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="new_password_confirmation" class="col-md-4 col-form-label text-md-right">{{ __('Confirm New Password') }}</label>

                            <div class="col-md-6">
                                <input id="new_password_confirmation" type="password" class="form-control" name="new_password_confirmation" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Change Password') }}
                                </button>
                            </div>
                        </div>
                        
                        <!-- Password requirements -->
                        <div class="form-group row">
                            <div class="col-md-8 offset-md-2 mt-3">
                                <div class="card">
                                    <div class="card-header bg-light">Password Requirements</div>
                                    <div class="card-body">
                                        <ul class="mb-0">
                                            <li>Password must be at least 8 characters in length.</li>
                                            <li>Password must be at maximum 50 characters in length.</li>
                                            <li>Password must include at least one upper case letter.</li>
                                            <li>Password must include at least one number.</li>
                                            <li>Password must include at least one special character.</li>
                                        </ul>
                                    </div>
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

@section('scripts')
<!-- Include SweetAlert from CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show password change required message with SweetAlert
        Swal.fire({
            title: 'Password Change Required',
            text: 'Your account requires a password change before continuing.',
            icon: 'warning',
            confirmButtonText: 'Understood',
            allowOutsideClick: false
        });
        
        // Show error message if exists
        @if(session('error'))
            Swal.fire({
                title: 'Error',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonText: 'OK'
            });
        @endif
        
        // Show success message if exists
        @if(session('success'))
            Swal.fire({
                title: 'Success',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonText: 'OK'
            });
        @endif

        // Client-side validation for password matching
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirmation = document.getElementById('new_password_confirmation').value;
            
            if (password !== confirmation) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'Password confirmation does not match!',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
</script>
@endsection