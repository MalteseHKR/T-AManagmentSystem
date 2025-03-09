@extends('layouts.app')

@section('title', 'Create Announcement - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="announcement-header">Create Announcement</h1>
        <a href="{{ route('announcements') }}" class="btn btn-secondary announcement-btn">Back to Announcements</a>
    </div>

    <div class="card announcement-card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">New Announcement</h5>
        </div>
        <div class="card-body">
            <form id="announcementForm" action="{{ route('announcements.store') }}" method="POST">
                @csrf
                
                @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                
                <div class="mb-3">
                    <label for="title" class="form-label announcement-label">Title</label>
                    <input type="text" class="form-control announcement-form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
                    @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-3">
                    <label for="category" class="form-label announcement-label">Category</label>
                    <select class="form-select announcement-form-control @error('category') is-invalid @enderror" id="category" name="category">
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
                </div>
                
                <div class="mb-4">
                    <label for="content" class="form-label announcement-label">Content</label>
                    <textarea class="form-control announcement-form-control @error('content') is-invalid @enderror" id="content" name="content" rows="6" required>{{ old('content') }}</textarea>
                    @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- Author Information (Display Only) -->
                <div class="card announcement-author-card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Author Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="avatar-circle me-2 bg-primary text-white">
                                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                                </div>
                            </div>
                            <div class="col">
                                @php
                                    $userInfo = \App\Models\UserInformation::where('user_id', Auth::id())->first();
                                @endphp
                                <p class="mb-1 fw-bold">{{ $userInfo->user_name ?? Auth::user()->name }}</p>
                                <p class="mb-0 small text-muted">
                                    {{ $userInfo->job_title ?? 'No job title' }}{{ ($userInfo && $userInfo->job_title && $userInfo->department) ? ', ' : '' }}{{ $userInfo->department ?? 'No department' }}
                                </p>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i> 
                                    Your actual account details will appear as the author. This helps maintain accountability.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" id="submitAnnouncement" class="btn btn-primary announcement-btn announcement-btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Create Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation to field focus
        const formControls = document.querySelectorAll('.announcement-form-control');
        formControls.forEach(control => {
            control.addEventListener('focus', function() {
                this.closest('.mb-3, .mb-4').classList.add('animate__animated', 'animate__pulse');
            });
            
            control.addEventListener('blur', function() {
                this.closest('.mb-3, .mb-4').classList.remove('animate__animated', 'animate__pulse');
            });
        });
        
        // Show category badge preview when selecting a category
        const categorySelect = document.getElementById('category');
        const categoryPreview = document.createElement('div');
        categoryPreview.className = 'mt-2';
        categoryPreview.style.display = 'none';
        categorySelect.parentNode.appendChild(categoryPreview);
        
        categorySelect.addEventListener('change', function() {
            const selectedCategory = this.value.toLowerCase();
            if (selectedCategory) {
                categoryPreview.style.display = 'block';
                categoryPreview.innerHTML = `
                    <div class="small text-muted mb-1">Preview:</div>
                    <span class="badge category-badge ${selectedCategory}">${this.value}</span>
                `;
            } else {
                categoryPreview.style.display = 'none';
            }
        });
        
        // Trigger change if category is pre-selected
        if (categorySelect.value) {
            const event = new Event('change');
            categorySelect.dispatchEvent(event);
        }
        
        // SweetAlert for form submission
        document.getElementById('submitAnnouncement').addEventListener('click', function() {
            const form = document.getElementById('announcementForm');
            
            // Check form validity
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            Swal.fire({
                title: 'Create Announcement?',
                text: 'Are you sure you want to publish this announcement? It will display your actual account details.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3490dc',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, publish it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Creating announcement...',
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
    });
</script>
@endpush