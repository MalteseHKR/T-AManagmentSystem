<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Garrison Time and Attendance System')</title>
    <!-- Include the CSS file using Laravel Mix -->
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body class="font-sans antialiased">
    <div class="container">
        @yield('content')
    </div>
    <!-- Include the JS file using Laravel Mix -->
    <script src="{{ mix('js/app.js') }}"></script>
</body>
</html>