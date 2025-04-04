@extends('layouts.app')

@section('title', 'Edit Announcement - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="announcement-header">Edit Announcement</h1>
        <a href="{{ route('announcements') }}" class="btn btn-secondary announcement-btn">Back to Announcements</a>
    </div>

    <div class="card announcement-card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Edit Announcement</h5>
        </div>
        <div class="card-body">
            <form id="announcementForm" action="{{ route('announcements.update', $announcement->id) }}" method="POST">
                @csrf
                @method('PUT')
                
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
                    <input type="text" class="form-control announcement-form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $announcement->title) }}" required>
                    @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-3">
                    <label for="category" class="form-label announcement-label">Category</label>
                    <select class="form-select announcement-form-control @error('category') is-invalid @enderror" id="category" name="category">
                        <option value="">Select Category</option>
                        <option value="General" {{ old('category', $announcement->category) == 'General' ? 'selected' : '' }}>General</option>
                        <option value="Important" {{ old('category', $announcement->category) == 'Important' ? 'selected' : '' }}>Important</option>
                        <option value="HR" {{ old('category', $announcement->category) == 'HR' ? 'selected' : '' }}>HR</option>
                        <option value="IT" {{ old('category', $announcement->category) == 'IT' ? 'selected' : '' }}>IT</option>
                        <option value="Finance" {{ old('category', $announcement->category) == 'Finance' ? 'selected' : '' }}>Finance</option>
                        <option value="Operations" {{ old('category', $announcement->category) == 'Operations' ? 'selected' : '' }}>Operations</option>
                    </select>
                    @error('category')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <label for="content" class="form-label announcement-label">Content</label>
                    <textarea class="form-control announcement-form-control @error('content') is-invalid @enderror" id="content" name="content" rows="6" required>{{ old('content', $announcement->content) }}</textarea>
                    @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- Author Information (Display Only - Not Editable) -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Author Information (Not Editable)</h6>
                    </div>
                    <div class="card-body bg-light">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3 bg-primary text-white">
                                {{ strtoupper(substr($announcement->author_name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="mb-1"><strong>{{ $announcement->author_name }}</strong></p>
                                <p class="mb-0 small text-muted">
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
                                </p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <p class="mb-0"><i class="fas fa-info-circle me-1"></i> Author information cannot be changed when editing an announcement.</p>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <button type="button" class="btn btn-outline-danger" onclick="confirmCancel()">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    
                    <div>
                        <button type="reset" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                        <button type="button" id="submitEdit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .form-label {
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }
    
    .card {
        border-radius: 10px;
        border: none;
    }
    
    .avatar-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 500;
    }
    
    /* Category badge previews */
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
        // Category badge preview
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
        
        // Auto-resize textarea as user types
        const textarea = document.getElementById('content');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Initial resize
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
        
        // Submit button with confirmation
        document.getElementById('submitEdit').addEventListener('click', function() {
            const form = document.getElementById('announcementForm');
            
            // Check form validity
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            Swal.fire({
                title: 'Save Changes?',
                text: 'Are you sure you want to update this announcement?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3490dc',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, save changes'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Saving changes...',
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
    
    function confirmCancel() {
        Swal.fire({
            title: 'Discard changes?',
            text: 'Any unsaved changes will be lost.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6c757d',
            cancelButtonColor: '#0d6efd',
            confirmButtonText: 'Yes, discard changes',
            cancelButtonText: 'No, keep editing'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ route('announcements') }}";
            }
        });
    }
</script>
@endpush
