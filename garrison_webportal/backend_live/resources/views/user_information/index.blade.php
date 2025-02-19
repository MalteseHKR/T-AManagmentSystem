<!DOCTYPE html>
<html>
<head>
    <title>Garrison - Time and Attendance System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            text-align: center;
            padding: 50px;
        }
        .header {
            background-color: #333;
            color: #fff;
            padding: 20px 0;
        }
        .header h1 {
            margin: 0;
        }
        .content {
            margin-top: 50px;
        }
        .content h2 {
            color: #333;
        }
        .content p {
            color: #666;
            font-size: 18px;
        }
        .login-form {
            margin-top: 30px;
        }
        .login-form input[type="email"],
        .login-form input[type="password"] {
            padding: 10px;
            margin: 10px 0;
            width: 100%;
            max-width: 300px;
        }
        .login-form button {
            padding: 10px 20px;
            background-color: #333;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .register-link {
            margin-top: 20px;
            display: block;
            color: #333;
            text-decoration: none;
        }
        .footer {
            margin-top: 50px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Garrison</h1>
    </div>
    <div class="container">
        <div class="content">
            <h2>Welcome to Garrison</h2>
            <p>The Time and Attendance System created by PeakyBlinders.</p>
            <p>Manage your time and attendance efficiently and effectively.</p>
        </div>
        <div class="login-form">
            <form action="{{ route('login') }}" method="POST">
                @csrf
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            <a href="{{ route('register') }}" class="register-link">If you don't have an account, register here</a>
        </div>
        <div class="footer">
            <p>&copy; 2025 PeakyBlinders. All rights reserved.</p>
        </div>
    </div>
</body>
</html>