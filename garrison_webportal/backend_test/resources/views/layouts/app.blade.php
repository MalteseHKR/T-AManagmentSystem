<!DOCTYPE html>
<html lang="en">
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

    <style>
        .navbar {
            position: relative;
        }
        .logout-form {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div>
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" width="30" height="30" class="d-inline-block align-text-top me-2">
                Garrison
            </a>
            
            @auth
                <div class="d-flex align-items-center">
                    @include('components.session-timer')
                    <form action="{{ route('logout') }}" method="POST" class="logout-form ms-3">
                        @csrf
                        <button type="submit" class="btn btn-outline-light">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    @auth
        @include('components.session-timeout')
    @endauth

    <!-- Add jQuery if not already included -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    @vite(['resources/js/session-timeout.js'])
</body>
</html>