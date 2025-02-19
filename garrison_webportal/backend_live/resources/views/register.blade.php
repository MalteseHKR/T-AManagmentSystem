<!DOCTYPE html>
<html>
<head>
    <title>Register - Garrison</title>
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
        .register-form {
            margin-top: 30px;
        }
        .register-form input[type="text"],
        .register-form input[type="email"],
        .register-form input[type="password"],
        .register-form input[type="date"] {
            padding: 10px;
            margin: 10px 0;
            width: 100%;
            max-width: 300px;
        }
        .register-form button {
            padding: 10px 20px;
            background-color: #333;
            color: #fff;
            border: none;
            cursor: pointer;
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
            <h2>Register</h2>
            <p>Create your account to manage your time and attendance efficiently and effectively.</p>
        </div>
        <div class="register-form">
            <form action="{{ route('register') }}" method="POST">
                @csrf
                <input type="text" name="user_name" placeholder="Name" required>
                <input type="text" name="user_surname" placeholder="Surname" required>
                <input type="text" name="user_title" placeholder="Title" required>
                <input type="text" name="user_phone" placeholder="Phone" required>
                <input type="email" name="user_email" placeholder="Email" required>
                <input type="date" name="user_dob" placeholder="Date of Birth" required>
                <input type="date" name="user_job_start" placeholder="Job Start Date" required>
                <input type="date" name="user_job_end" placeholder="Job End Date">
                <label for="user_active">Active</label>
                <input type="checkbox" name="user_active" value="1">
                <input type="text" name="user_department" placeholder="Department" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="password" name="password_confirmation" placeholder="Confirm Password" required>
                <button type="submit">Register</button>
            </form>
        </div>
        <div class="footer">
            <p>&copy; 2025 PeakyBlinders. All rights reserved.</p>
        </div>
    </div>
</body>
</html>