@extends('app')

@section('title', 'Login | Garrison Time and Attendance System')

@section('content')
<div class="login-container">
    <div class="login-card">
        <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" class="login-logo">
        <h1>Login</h1>
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
    </div>
</div>
@endsection