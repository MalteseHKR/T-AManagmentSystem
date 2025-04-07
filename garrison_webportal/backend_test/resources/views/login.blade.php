@extends('layouts.auth')

@section('title', 'Login | Garrison Time and Attendance System')

@section('content')
<div class="login-page">
    <div class="login-card">
        <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" class="login-logo" width="100" height="100">
        <h1>Login</h1>
        
        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                @foreach ($errors->all() as $error)
                    <p class="text-sm text-red-600">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        @if(session('attempts_left'))
            <div class="mt-3 text-center text-sm text-danger">
                {{ session('attempts_left') }} attempts remaining before account lockout
            </div>
        @endif
    </div>
</div>
@endsection