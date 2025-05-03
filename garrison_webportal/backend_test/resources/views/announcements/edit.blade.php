@extends('layouts.app')

@section('title', 'Edit Announcement - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container announcement-container">
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center gap-3 mb-4">
        <h1 class="announcement-header mb-0">Edit Announcement</h1>
        <a href="{{ route('announcements') }}" class="btn btn-secondary announcement-btn">
            <i class="fas fa-arrow-left me-1"></i> Back to Announcements
        </a>
    </div>

    <div class="card announcement-card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0 text-white">Edit Announcement</h5>
        </div>
        <div class="card-body px-4 py-3">
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
                    
                    <div id="category-preview" class="mt-2" style="display: none;"></div>
                </div>
                
                <div class="mb-4">
                    <label for="content" class="form-label announcement-label">Content</label>
                    <textarea class="form-control announcement-form-control @error('content') is-invalid @enderror" id="content" name="content" rows="6" required>{{ old('content', $announcement->content) }}</textarea>
                    @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-end">
                        <span id="char-count">0</span> characters
                    </div>
                </div>
                
                <!-- Author Information (Display Only - Not Editable) -->
                <div class="card author-info-card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-white">Author Information (Not Editable)</h6>
                    </div>
                    <div class="card-body bg-light px-4 py-3">
                        <div class="d-flex flex-column flex-sm-row align-items-center">
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
                
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                    <!-- Cancel Button - Link -->
                    <a href="{{ route('announcements') }}" class="btn btn-outline-danger w-100 w-md-auto order-md-1">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    
                    <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto order-md-2">
                        <!-- Reset Button - Reverts to initial values -->
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                        
                        <!-- Clear All Button - Empties all fields -->
                        <button type="button" class="btn btn-outline-warning" onclick="document.getElementById('announcementForm').reset(); document.querySelectorAll('#announcementForm input, #announcementForm textarea, #announcementForm select').forEach(el => el.value = '');">
                            <i class="fas fa-eraser me-1"></i> Clear All
                        </button>
                        
                        <!-- Save Button - Submit -->
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toast notification setup - consistent with other pages
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

    // Show success/error messages
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

    // Category badge preview
    const categorySelect = document.getElementById('category');
    const categoryPreview = document.getElementById('category-preview');

    if (categorySelect && categoryPreview) {
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
    }

    // Auto-resize textarea as user types and count characters
    const textarea = document.getElementById('content');
    const charCount = document.getElementById('char-count');

    if (textarea && charCount) {
        function updateTextarea() {
            // Update character count
            charCount.textContent = textarea.value.length;

            // Auto-resize
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }

        textarea.addEventListener('input', updateTextarea);

        // Initial resize and count
        updateTextarea();
    }
});
</script>
@endpush
