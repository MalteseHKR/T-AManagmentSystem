<!-- filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_live/resources/views/dashboard.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" href="{{ asset('garrison.svg') }}" type="image/svg+xml">
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
        .header img {
            max-width: 100px;
            margin-right: 20px;
        }
        .notifications {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .notifications h2 {
            margin-top: 0;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            display: block;
            margin-bottom: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center;">
                <img src="{{ asset('garrison.svg') }}" alt="Company Logo">
                <h1>Garrison Dashboard</h1>
            </div>
        </div>
    </header>

    <div class="container">
        <h1>Welcome to the Dashboard</h1>

        @php
            $user = Auth::user();
        @endphp

        <p>Department: {{ $user->user_department }}</p>
        <p>Title: {{ $user->user_title }}</p>

        <div class="notifications">
            <h2>Notifications</h2>
            <p>You have 3 new messages.</p>
            <p>Your next meeting is at 3 PM.</p>
        </div>

        <div class="links">
            <h2>Quick Links</h2>
            @if ($user->user_department == 'HR')
                <a href="{{ route('dashboard.hr') }}">HR Dashboard</a>
                <a href="{{ route('dashboard.hr.reports') }}">HR Reports</a>
            @elseif ($user->user_department == 'IT')
                <a href="{{ route('dashboard.it') }}">IT Dashboard</a>
                <a href="{{ route('dashboard.it.support') }}">IT Support</a>
            @else
                <a href="{{ route('dashboard.generic') }}">General Dashboard</a>
                <a href="{{ route('dashboard.generic.resources') }}">Resources</a>
            @endif
        </div>
    </div>
</body>
</html>