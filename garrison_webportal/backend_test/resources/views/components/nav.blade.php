<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
            <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" width="32" height="32" class="d-inline-block align-text-top me-2">
            <span class="fw-bold">Garrison</span>
        </a>
        
        <div class="d-flex align-items-center">
            @include('components.session-timer')
            <form action="{{ route('logout') }}" method="POST" class="logout-form ms-3">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </button>
            </form>
        </div>

        @auth
             @include('components.session-timeout')
        @endauth
    </div>
</nav>