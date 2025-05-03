@extends('layouts.app')

@section('show_navbar', true)

@section('content')
<div class="row m-auto justify-content-center">
    <!-- Profile Sidebar -->
    <div class="col-md-3">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="portrait-container mx-auto my-3 position-relative" style="width: 100px; height: 100px;">
    @if(isset($userInfo->user_id))
        <img src="{{ url('/profile-image/' . $userInfo->user_id) }}"
             alt="Portrait of {{ $userInfo->user_name }}"
             class="img-fluid w-100 h-100 object-fit-cover rounded-circle"
             onerror="this.onerror=null; this.src='{{ asset('images/default-portrait.png') }}';">
    @else
        <div class="avatar-circle d-flex justify-content-center align-items-center w-100 h-100 bg-light rounded-circle">
            <i class="fas fa-user-circle fa-4x text-secondary"></i>
        </div>
    @endif
</div>
                
                <hr>
                
                <div class="list-group list-group-flush px-2">
                    <a href="{{ route('profile.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('profile.index') ? 'active' : '' }}">
                        <i class="fas fa-user me-2"></i> Profile Overview
                    </a>
                    <a href="{{ route('profile.edit') }}" class="list-group-item list-group-item-action {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                    <a href="{{ route('profile.password') }}" class="list-group-item list-group-item-action {{ request()->routeIs('profile.password') ? 'active' : '' }}">
                        <i class="fas fa-key me-2"></i> Change Password
                    </a>
                    <a href="{{ route('profile.attendance') }}" class="list-group-item list-group-item-action {{ request()->routeIs('profile.attendance') ? 'active' : '' }}">
                        <i class="fas fa-clock me-2"></i> My Attendance
                    </a>
                    <a href="{{ route('profile.leave') }}" class="list-group-item list-group-item-action {{ request()->routeIs('profile.leave') ? 'active' : '' }}">
                        <i class="fas fa-calendar-alt me-2"></i> My Leave
                    </a>
                </div>

                <hr>
                
            </div>
        </div>
    </div>
    
    <!-- Profile Content -->
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-body px-4 py-3">
                @yield('profile-content')
            </div>
        </div>
    </div>
</div>
@endsection