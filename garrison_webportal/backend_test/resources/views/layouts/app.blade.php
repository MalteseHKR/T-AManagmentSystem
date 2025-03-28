<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Garrison - Time and Attendance System')</title>
    
    <link rel="icon" type="image/svg+xml" href="{{ asset('garrison.svg') }}">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="{{ asset('css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    
    <!-- Custom Styles -->
    @stack('styles')
</head>
<body>
    @auth
        <!-- Conditionally include the navbar -->
        @if(View::hasSection('show_navbar') && View::getSection('show_navbar', true))
            <x-nav />
        @endif
    @endauth

    <!-- Main Content -->
    <main class="@if(View::hasSection('show_navbar') && View::getSection('show_navbar', true)) py-4 @endif">
        @yield('content')
    </main>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Session Timeout -->
    @vite(['resources/js/session-timeout.js'])
    
    <!-- App JS -->
    <script src="{{ asset('js/app.js') }}"></script>
    
    <!-- Additional Scripts -->
    @stack('scripts')
</body>
</html>