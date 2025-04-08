@extends('layouts.app')

@section('title', 'Delete Announcement - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center gap-3 mb-4">
        <h1 class="announcement-header mb-0">Delete Announcement</h1>
        <a href="{{ route('announcements') }}" class="btn btn-secondary announcement-btn">
            <i class="fas fa-arrow-left me-1"></i> Back to Announcements
        </a>
    </div>

    <div class="card announcement-card shadow-sm">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div>Are you sure you want to delete this announcement? This action cannot be undone.</div>
            </div>
            
            <div class="card announcement-preview-card mb-4">
                <div class="card-header d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                    <h5 class="mb-2 mb-sm-0 announcement-title">{{ $announcement->title }}</h5>
                    @if(!empty($announcement->category))
                    <span class="badge category-badge {{ strtolower($announcement->category) }}">{{ $announcement->category }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-3 announcement-content">
                        {!! nl2br(e($announcement->content)) !!}
                    </div>
                    
                    <div class="announcement-author-info">
                        <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start">
                            <div class="avatar-circle me-0 me-sm-3 mb-2 mb-sm-0 bg-primary text-white">
                                {{ strtoupper(substr($announcement->author_name, 0, 1)) }}
                            </div>
                            <div class="text-center text-sm-start">
                                <h6 class="mb-1">{{ $announcement->author_name }}</h6>
                                <div class="small text-muted">
                                    @if($announcement->author_job_title)
                                        <span class="me-2">
                                            <i class="fas fa-briefcase fa-fw me-1"></i>
                                            {{ $announcement->author_job_title }}
                                        </span>
                                    @endif
                                    
                                    @if($announcement->author_department)
                                        <span>
                                            <i class="fas fa-building fa-fw me-1"></i>
                                            {{ $announcement->author_department }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <span class="text-muted small d-flex align-items-center flex-wrap">
                            <span class="me-3">
                                <i class="far fa-calendar-alt me-1"></i> {{ $announcement->created_at->format('M d, Y') }}
                            </span>
                            <span>
                                <i class="far fa-clock me-1"></i> {{ $announcement->created_at->format('h:i A') }}
                            </span>
                        </span>
                        
                        @if($announcement->created_at != $announcement->updated_at)
                        <span class="text-muted small mt-2 mt-sm-0">
                            <i class="fas fa-edit me-1"></i> Edited {{ $announcement->updated_at->diffForHumans() }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            
            <form action="{{ route('announcements.destroy', $announcement) }}" method="POST" id="deleteForm">
                @csrf
                @method('DELETE')
                
                <div class="deletion-warning mb-4">
                    <div class="deletion-consequences">
                        <h6 class="consequences-title"><i class="fas fa-info-circle me-2"></i> What happens when you delete?</h6>
                        <ul class="consequences-list">
                            <li>The announcement will be permanently removed for all users</li>
                            <li>All information related to this announcement will be deleted</li>
                            <li>This action cannot be reversed or recovered</li>
                        </ul>
                    </div>
                </div>
                
                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                    <a href="{{ route('announcements') }}" class="btn btn-secondary mb-2 mb-sm-0">
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

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toast notification setup
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
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

        // Handle confirmation for delete
        document.getElementById('confirmDelete').addEventListener('click', function() {
            Swal.fire({
                title: 'Are you absolutely sure?',
                text: "This announcement will be permanently deleted and cannot be recovered.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, cancel',
                reverseButtons: true,
                focusCancel: true // Safer default is to focus on cancel
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting announcement...',
                        text: 'Please wait while we process your request.',
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
@endsection