<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garrison Management System - Register</title>
    <link rel="icon" href="{{ asset('garrison.svg') }}" type="image/svg+xml">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .register-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header img {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .register-button {
            width: 100%;
            padding: 0.75rem;
            background-color: #4a5568;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        .register-button:hover {
            background-color: #2d3748;
        }
        .error-message {
            color: #dc2626;
            margin-bottom: 1rem;
            text-align: center;
        }
        .success-message {
            color: #16a34a;
            margin-bottom: 1rem;
            text-align: center;
        }
        .login-link {
            text-align: center;
            margin-top: 1rem;
        }
        .login-link a {
            color: #4a5568;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <img src="{{ asset('garrison.svg') }}" alt="Company Logo">
            <h1>Create an Account</h1>
            <p>Please fill in the form to create an account</p>
        </div>

        @if ($errors->any())
            <div class="error-message">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="form-group">
                <label for="user_name">Name</label>
                <input type="text" id="user_name" name="user_name" value="{{ old('user_name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label for="user_surname">Surname</label>
                <input type="text" id="user_surname" name="user_surname" value="{{ old('user_surname') }}" required>
            </div>

            <div class="form-group">
                <label for="user_email">Email Address</label>
                <input type="email" id="user_email" name="user_email" value="{{ old('user_email') }}" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <button type="submit" class="register-button">Register</button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="{{ route('login') }}">Login here</a></p>
        </div>
    </div>
</body>
</html>