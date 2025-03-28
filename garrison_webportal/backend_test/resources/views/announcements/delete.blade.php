@extends('layouts.app')

@section('title', 'Delete Announcement - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="announcement-header">Delete Announcement</h1>
        <a href="{{ route('announcements') }}" class="btn btn-secondary announcement-btn">Back to Announcements</a>
    </div>

    <div class="card announcement-card shadow-sm">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle me-2"></i>
                Are you sure you want to delete this announcement? This action cannot be undone.
            </div>
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <h5>{{ $announcement->title }}</h5>
                    @if(!empty($announcement->category))
                    <span class="badge category-badge {{ strtolower($announcement->category) }}">{{ $announcement->category }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-3 announcement-content">
                        {!! nl2br(e($announcement->content)) !!}
                    </div>
                    
                    <div class="announcement-author-info">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-2 bg-primary text-white">
                                {{ strtoupper(substr($announcement->author_name, 0, 1)) }}
                            </div>
                            <div>
                                <h6 class="mb-0">{{ $announcement->author_name }}</h6>
                                <div class="small text-muted">
                                    @if($announcement->author_job_title)
                                        <span class="me-2">{{ $announcement->author_job_title }}</span>
                                    @endif
                                    
                                    @if($announcement->author_department)
                                        <span>{{ $announcement->author_department }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <span class="text-muted small">
                        <i class="far fa-calendar-alt me-1"></i> {{ $announcement->created_at->format('M d, Y') }}
                        <span class="ms-2">
                            <i class="far fa-clock me-1"></i> {{ $announcement->created_at->format('h:i A') }}
                        </span>
                    </span>
                </div>
            </div>
            
            <form action="{{ route('announcements.destroy', $announcement) }}" method="POST" id="deleteForm">
                @csrf
                @method('DELETE')
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('announcements') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <button type="button" id="confirmDelete" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .announcement-container {
        max-width: 800px;
    }
    
    .announcement-header {
        color: #2d3748;
        font-weight: 600;
    }
    
    .announcement-content {
        white-space: pre-line;
    }
    
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .category-badge {
        padding: 0.35em 0.65em;
    }
    
    .category-badge.general {
        background-color: #4299e1;
    }
    
    .category-badge.important {
        background-color: #f56565;
    }
    
    .category-badge.hr {
        background-color: #9f7aea;
    }
    
    .category-badge.it {
        background-color: #38b2ac;
    }
    
    .category-badge.finance {
        background-color: #48bb78;
    }
    
    .category-badge.operations {
        background-color: #ed8936;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('confirmDelete').addEventListener('click', function() {
            Swal.fire({
                title: 'Are you absolutely sure?',
                text: "This announcement will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting announcement...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the delete form
                    document.getElementById('deleteForm').submit();
                }
            });
        });
    });
</script>
@endpush