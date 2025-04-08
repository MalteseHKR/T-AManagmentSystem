@extends('layouts.app')

@section('title', 'Create Announcement - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h1 class="announcement-header mb-0">Create Announcement</h1>
        <a href="{{ route('announcements') }}" class="btn btn-secondary announcement-btn">
            <i class="fas fa-arrow-left me-1"></i> Back to Announcements
        </a>
    </div>

    <div class="card announcement-card shadow-sm">
        <div class="card-header">
            <i class="fa-solid fa-bullhorn text-white"></i>
            <h5 class="mb-0 text-white  ">New Announcement</h5>
        </div>
        <div class="card-body px-4 py-2">
            <form id="announcementForm" action="{{ route('announcements.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="title" class="form-label announcement-label">Title</label>
                    <input type="text" class="form-control announcement-form-control @error('title') is-invalid @enderror" 
                           id="title" name="title" value="{{ old('title') }}" required 
                           placeholder="Enter a descriptive title">
                    @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">A clear, concise title for your announcement (max 100 characters)</div>
                </div>
                
                <div class="mb-3">
                    <label for="category" class="form-label announcement-label">Category</label>
                    <select class="form-select announcement-form-control @error('category') is-invalid @enderror" 
                            id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="General" {{ old('category') == 'General' ? 'selected' : '' }}>General</option>
                        <option value="Important" {{ old('category') == 'Important' ? 'selected' : '' }}>Important</option>
                        <option value="HR" {{ old('category') == 'HR' ? 'selected' : '' }}>HR</option>
                        <option value="IT" {{ old('category') == 'IT' ? 'selected' : '' }}>IT</option>
                        <option value="Finance" {{ old('category') == 'Finance' ? 'selected' : '' }}>Finance</option>
                        <option value="Operations" {{ old('category') == 'Operations' ? 'selected' : '' }}>Operations</option>
                    </select>
                    @error('category')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div id="category-preview" class="mt-2" style="display: none;"></div>
                </div>
                
                <div class="mb-4">
                    <label for="content" class="form-label announcement-label">Content</label>
                    <textarea class="form-control announcement-form-control @error('content') is-invalid @enderror" 
                              id="content" name="content" rows="6" required 
                              placeholder="Enter your announcement content here...">{{ old('content') }}</textarea>
                    @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text d-flex justify-content-between align-items-center">
                        <span>Provide clear and concise information</span>
                        <span id="char-count">0 characters</span>
                    </div>
                </div>
                
                <!-- Author Information (Display Only) -->
                <div class="card author-card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-white">Author Information</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start">
                            <div class="portrait-container me-0 me-sm-3 mb-2 mb-sm-0">
                                @if(isset($userInfo->portrait_url) && file_exists($userInfo->portrait_url))
                                    <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($userInfo->portrait_url)) }}"
                                         alt="Portrait of {{ $userInfo->user_name }}"
                                         class="portrait-image-announcement">
                                @else
                                    <img src="{{ asset('images/default-portrait.png') }}" alt="Default Portrait"
                                         class="portrait-image-announcement">
                                @endif
                            </div>
                            
                            <div class="text-center text-sm-start">
                                <h6 class="mb-1">
                                    @if($userInfo && $userInfo->user_name)
                                        {{ $userInfo->user_name }} {{ $userInfo->user_surname }}
                                    @else
                                        {{ Auth::user()->name }}
                                    @endif
                                </h6>
                                
                                <div class="small text-muted">
                                    @if($userInfo && ($userInfo->role || $userInfo->department))
                                        <span class="me-2 d-inline-block">
                                            <i class="fas fa-briefcase fa-fw me-1"></i>
                                            {{ $userInfo->role ? $userInfo->role->role : 'No job title' }}
                                        </span>
                                        <span class="d-inline-block">
                                            <i class="fas fa-building fa-fw me-1"></i>
                                            {{ $userInfo->department ? $userInfo->department->department : 'No department' }}
                                        </span>
                                    @else
                                        <div class="alert alert-warning py-1 px-2 mb-0">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            User information not found
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        @if(!$userInfo)
                        <div class="alert alert-info mt-3 mb-0">
                            <p class="mb-0"><i class="fas fa-info-circle me-1"></i> Your author information is missing. The system will use your login name instead.</p>
                        </div>
                        @endif
                    </div>
                </div>
                
                <div class="announcement-actions">
                    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center gap-3">
                        <button type="button" class="btn btn-outline-secondary order-2 order-md-1">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        
                        <div class="d-flex flex-column flex-sm-row gap-2 order-1 order-md-2">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset Form
                            </button>
                            
                            <!-- Single submit button that works without JavaScript -->
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> Create Announcement
                            </button>
                        </div>
                    </div>
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
    // Toast notification setup - exact same as in analytics.blade.php
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    // Show success/error messages (same as in analytics)
    @if(session('success'))
        Toast.fire({
            icon: 'success',
            title: "{{ session('success') }}"
        });
    @endif

    @if(session('error'))
        Toast.fire({
            icon: 'error',
            title: "{{ session('error') }}"
        });
    @endif

    // Character counter for content field
    const contentField = document.getElementById('content');
    const charCount = document.getElementById('char-count');
    
    if (contentField && charCount) {
        // Set initial character count
        charCount.textContent = contentField.value.length + ' characters';
        
        // Update character count on input
        contentField.addEventListener('input', function() {
            charCount.textContent = this.value.length + ' characters';
        });
    }
    
    // Form reference
    const form = document.getElementById('announcementForm');
    
    // Simplified form submission with confirmation
    form.addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent default form submission
        
        // Get the form data
        const title = document.getElementById('title').value.trim();
        const content = document.getElementById('content').value.trim();
        const category = document.getElementById('category').value;
        
        // Check form validity
        if (!form.checkValidity()) {
            // Use browser's built-in validation
            form.reportValidity();
            return;
        }
        
        // Additional validation for content length
        if (content.length < 10) {
            Swal.fire({
                icon: 'error',
                title: 'Content Too Short',
                text: 'Please provide more detailed content for your announcement.',
                confirmButtonColor: '#dc3545'
            });
            return;
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Create Announcement?',
            html: `
                <p>Are you sure you want to publish this announcement?</p>
                <div class="text-start mt-3 mb-1 text-muted small">Title:</div>
                <div class="text-start p-2 mb-3 border rounded bg-light">${title}</div>
                <div class="text-start text-muted small">This will display with your account details.</div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, publish it!',
            cancelButtonText: 'No, keep editing',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Creating announcement...',
                    text: 'Please wait while we publish your announcement.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit the form
                form.submit();
            }
        });
    });
    
    // Fix the cancel button
    const cancelButton = document.querySelector('button[type="button"].btn-outline-secondary');
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            Swal.fire({
                title: 'Discard changes?',
                text: 'Any unsaved changes will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#2563eb',
                confirmButtonText: 'Yes, discard changes',
                cancelButtonText: 'No, keep editing',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('announcements') }}";
                }
            });
        });
    }
});
</script>
@endsection