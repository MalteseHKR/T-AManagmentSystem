<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm py-0">
    <div class="container">
        <!-- Logo and Brand -->
        <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
            <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" width="36" height="36" class="d-inline-block align-text-top me-2">
            <span class="fw-bold">Garrison</span>
        </a>
        
        <!-- Navbar Toggler for mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Main Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('employees') ? 'active' : '' }}" href="{{ route('employees') }}">
                        <i class="fas fa-users me-1"></i> Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('attendance*') ? 'active' : '' }}" href="{{ route('attendance') }}">
                        <i class="fas fa-clipboard-check me-1"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('announcements*') ? 'active' : '' }}" href="{{ route('announcements') }}">
                        <i class="fas fa-bullhorn me-1"></i> Announcements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('leaves*') ? 'active' : '' }}" href="{{ route('leaves') }}">
                        <i class="fas fa-calendar me-1"></i> Leave Management
                    </a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('payroll') ? 'active' : '' }}" href="{{ route('payroll') }}">
                        <i class="fas fa-money-bill-wave me-1"></i> Payroll
                    </a>
                </li> -->
            </ul>
            
            <!-- Right Side: Session Timer and Actions -->
            <div class="d-flex align-items-center navbar-right">
                <!-- Session Timer -->
                <div class="session-timer-container me-3">
                    @include('components.session-timer')
                </div>
                
                <!-- User Dropdown -->
                <div class="dropdown me-3">
                    <button class="btn btn-outline-light dropdown-toggle user-dropdown" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i> 
                        @if(Auth::check())
                            <span class="user-name-display">
                                @if(Auth::user()->userInformation && Auth::user()->userInformation->user_name)
                                    {{ Auth::user()->userInformation->user_name }}
                                @else
                                    {{ Auth::user()->name }}
                                @endif
                            </span>
                        @else
                            User
                        @endif
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="{{ route('dashboard') }}">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="{{ route('home') }}">
                            <i class="fas fa-user"></i> Profile
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST" class="dropdown-item-form">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Make sure this is included before the closing </body> tag -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>