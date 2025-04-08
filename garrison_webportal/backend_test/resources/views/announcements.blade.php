@extends('layouts.app')

@section('title', 'Announcements - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h1 class="announcement-header mb-0">Announcements</h1>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary announcement-btn">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
            <a href="{{ route('announcements.create') }}" class="btn btn-primary announcement-btn">
                <i class="fas fa-plus-circle me-1"></i> Create Announcement
            </a>
        </div>
    </div>

    @if($announcements->isEmpty())
        <div class="announcement-empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <h3>No Announcements Yet</h3>
            <p>There are no announcements available at this time.</p>
            <a href="{{ route('announcements.create') }}" class="btn btn-primary mt-3">
                <i class="fas fa-plus-circle me-1"></i> Create the First Announcement
            </a>
        </div>
    @else
        <div class="announcement-list">
            @foreach($announcements as $announcement)
                <div class="card mb-4 announcement-card shadow-sm">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center gap-2">
                        <h5 class="mb-0 announcement-title text-white">{{ $announcement->title }}</h5>
                        <span class="badge category-badge {{ strtolower($announcement->category ?? 'general') }}">
                            {{ $announcement->category ?? 'General' }}
                        </span>
                    </div>
                    <div class="card-body px-4 py-2">
                        <div class="announcement-content">
                            {!! nl2br(e($announcement->content)) !!}
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                            <div class="d-flex align-items-center">
                                <div class="portrait-container-announcement">
                                    <img src="{{ url('/profile-image/' . $announcement->user_id) }}"
                                         alt="Portrait of {{ $announcement->author_name ?? 'Anonymous' }}"
                                         class="portrait-image"
                                         onerror="this.onerror=null; this.src='{{ asset('images/default-portrait.png') }}';">
                                </div>
                                <div class="ms-2">
                                    <p class="mb-0 fw-bold">{{ $announcement->author_name ?? 'Anonymous' }}</p>
                                    <small class="text-muted announcement-author-details">
                                        @if($announcement->author_job_title || $announcement->author_department)
                                            <span class="announcement-job-title">
                                                <i class="fas fa-briefcase fa-fw me-1"></i> {{ $announcement->author_job_title ?? 'Staff' }}
                                            </span>
                                            
                                            @if($announcement->author_department)
                                                <span class="announcement-department">
                                                    <i class="fas fa-building fa-fw me-1"></i> {{ $announcement->author_department }}
                                                </span>
                                            @endif
                                        @endif
                                    </small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div class="announcement-date text-muted">
                                    <i class="far fa-calendar-alt me-1"></i> {{ $announcement->created_at->format('d M Y') }}
                                    <i class="far fa-clock ms-2 me-1"></i> {{ $announcement->created_at->format('H:i') }}
                                </div>
                                
                                {{-- Get user_id using the login ID --}}
                                @php
                                    $loginId = Auth::id();
                                    $userIdQuery = \DB::table('login')->where('user_login_id', $loginId)->first();
                                    $userId = $userIdQuery ? $userIdQuery->user_id : null;
                                @endphp
                                
                                {{-- Display edit/delete buttons if user created this announcement --}}
                                @if($userId == $announcement->user_id)
                                    <div class="announcement-actions btn-group">
                                        <a href="{{ route('announcements.edit', $announcement->id) }}" class="btn btn-sm btn-outline-primary" title="Edit Announcement">
                                            <i class="fas fa-edit"></i>
                                            <span class="d-none d-sm-inline ms-1">Edit</span>
                                        </a>
                                        <form action="{{ route('announcements.destroy', $announcement->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this announcement? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i>
                                                <span class="d-none d-sm-inline ms-1">Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                    
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            
            @if($announcements->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $announcements->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // SweetAlert Toast configuration
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        // Show success message if exists
        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: "{{ session('success') }}"
            });
        @endif
        
        // Show error message if exists
        @if(session('error'))
            Toast.fire({
                icon: 'error',
                title: "{{ session('error') }}"
            });
        @endif
        
        // Use event delegation for delete buttons
        document.querySelector('.announcement-container').addEventListener('click', function(e) {
            if (e.target.closest('.delete-announcement')) {
                e.preventDefault();
                const button = e.target.closest('.delete-announcement');
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                
                Swal.fire({
                    title: 'Delete Announcement?',
                    html: `
                        <div class="text-start">
                            <p>You are about to delete the announcement:</p>
                            <div class="fw-bold p-2 my-2 bg-light rounded">${title}</div>
                            <p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> This action cannot be undone!</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'No, keep it',
                    reverseButtons: true,
                    focusCancel: true // Focus on cancel button for safety
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Deleting...',
                            text: 'Please wait while we delete the announcement.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        document.getElementById(`delete-form-${id}`).submit();
                    }
                });
            }
        });
        
        // Add animation to cards on load
        const cards = document.querySelectorAll('.announcement-card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('announcement-card-visible');
            }, index * 100); // Staggered animation
        });
    });
</script>
@endsection