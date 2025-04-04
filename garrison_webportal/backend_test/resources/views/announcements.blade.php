@extends('layouts.app')

@section('title', 'Announcements - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="announcement-header">Announcements</h1>
        <div>
            <a href="{{ route('dashboard') }}" class="btn btn-secondary announcement-btn me-2">Back</a>
            <a href="{{ route('announcements.create') }}" class="btn btn-primary announcement-btn announcement-btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Create an Announcement
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($announcements->isEmpty())
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> No announcements available at this time.
        </div>
    @else
        @foreach($announcements as $announcement)
            <div class="card mb-4 announcement-card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $announcement->title }}</h5>
                    <span class="badge category-badge {{ strtolower($announcement->category ?? 'general') }}">{{ $announcement->category ?? 'General' }}</span>
                </div>
                <div class="card-body">
                    <p class="card-text">{{ $announcement->content }}</p>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle me-2 bg-secondary text-white">
                            {{ strtoupper(substr($announcement->author_name ?? 'A', 0, 1)) }}
                        </div>
                        <div>
                            <p class="mb-0 fw-bold">{{ $announcement->author_name ?? 'Anonymous' }}</p>
                            <small class="text-muted">{{ $announcement->author_job_title ?? '' }}{{ ($announcement->author_job_title && $announcement->author_department) ? ', ' : '' }}{{ $announcement->author_department ?? '' }}</small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <small class="text-muted me-3">{{ $announcement->created_at->format('d M Y, H:i') }}</small>
                        
                        {{-- Get user_id using the login ID --}}
                        @php
                            $loginId = Auth::id();
                            $userIdQuery = \DB::table('login')->where('user_login_id', $loginId)->first();
                            $userId = $userIdQuery ? $userIdQuery->user_id : null;
                        @endphp
                        
                        {{-- Display edit/delete buttons if user created this announcement --}}
                        @if($userId == $announcement->user_id)
                            <div class="btn-group">
                                <a href="{{ route('announcements.edit', $announcement->id) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-announcement" 
                                        data-id="{{ $announcement->id }}" 
                                        data-title="{{ $announcement->title }}" 
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            {{-- Hidden delete form --}}
                            <form id="delete-form-{{ $announcement->id }}" action="{{ route('announcements.destroy', $announcement->id) }}" method="POST" style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        
        @if($announcements->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $announcements->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

@push('styles')
<style>
    .avatar-circle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 16px;
        font-weight: bold;
    }
    
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    /* Category badge styles */
    .category-badge {
        padding: 0.35em 0.65em;
        font-size: 0.85em;
        border-radius: 0.25rem;
    }
    
    .category-badge.general {
        background-color: #6c757d;
    }
    
    .category-badge.important {
        background-color: #fd7e14;
    }
    
    .category-badge.hr {
        background-color: #d63384;
    }
    
    .category-badge.it {
        background-color: #0dcaf0;
    }
    
    .category-badge.finance {
        background-color: #20c997;
    }
    
    .category-badge.operations {
        background-color: #0d6efd;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert:not(.alert-info)');
        alerts.forEach(alert => {
            setTimeout(() => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.click();
                }
            }, 5000);
        });

        // Use event delegation for better performance
        document.querySelector('.announcement-container').addEventListener('click', function(e) {
            if (e.target.closest('.delete-announcement')) {
                const button = e.target.closest('.delete-announcement');
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete the announcement "${title}". This action cannot be undone!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById(`delete-form-${id}`).submit();
                    }
                });
            }
        });
    });
</script>
@endpush