<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Garrison')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('garrison.svg') }}">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Additional Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Add Font Awesome if not already included -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>
<body>
    @auth
        <main>
            @yield('content')
        </main>
    @endauth

    <!-- Add jQuery if not already included -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    @vite(['resources/js/session-timeout.js'])
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>