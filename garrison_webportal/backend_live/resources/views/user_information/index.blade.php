<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Garrison - Time and Attendance System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #333;
            color: #fff;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        .nav-buttons {
            display: flex;
            gap: 20px;
        }
        .button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .login-btn {
            background-color: #4CAF50;
            color: white;
        }
        .register-btn {
            background-color: #2196F3;
            color: white;
        }
        .button:hover {
            opacity: 0.9;
        }
        .hero {
            text-align: center;
            padding: 80px 20px;
            background-color: #fff;
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .hero h2 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        .hero p {
            color: #666;
            font-size: 1.2em;
            max-width: 800px;
            margin: 0 auto 30px;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            padding: 40px 20px;
        }
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .feature-card h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .footer {
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 20px 0;
            position: relative;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Garrison</h1>
            <div class="nav-buttons">
                <a href="{{ route('login') }}" class="button login-btn">Login</a>
                <a href="{{ route('register') }}" class="button register-btn">Register</a>
            </div>
        </div>
    </header>

    <div class="container">
        <section class="hero">
            <h2>Welcome to Garrison</h2>
            <p>Your Complete Time and Attendance Management Solution</p>
            <p>Created by PeakyBlinders to streamline workforce management and enhance productivity.</p>
        </section>

        <section class="features">
            <div class="feature-card">
                <h3>Time Tracking</h3>
                <p>Accurately track employee hours with our advanced time recording system.</p>
            </div>
            <div class="feature-card">
                <h3>Attendance Management</h3>
                <p>Monitor and manage attendance patterns with comprehensive reporting tools.</p>
            </div>
            <div class="feature-card">
                <h3>User-Friendly Interface</h3>
                <p>Simple and intuitive interface designed for ease of use.</p>
            </div>
        </section>
    </div>

    <footer class="footer">
        <p>&copy; 2025 PeakyBlinders. All rights reserved.</p>
    </footer>
</body>
</html>