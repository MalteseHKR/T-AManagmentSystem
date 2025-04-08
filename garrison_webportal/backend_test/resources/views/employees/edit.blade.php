@extends('layouts.app')

@section('title', 'Edit Employee')

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-user-edit me-2"></i> Edit Employee: {{ $userInfo->user_name }} {{ $userInfo->user_surname }}
                </h5>
            </div>
            <div class="card-body p-3">
                <form action="{{ route('employees.update', $userInfo->user_id) }}" method="POST">
                    @csrf
                    
                    @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="user_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="user_name" name="user_name" value="{{ old('user_name', $userInfo->user_name) }}" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="user_surname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="user_surname" name="user_surname" value="{{ old('user_surname', $userInfo->user_surname) }}" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="user_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="user_email" name="user_email" value="{{ old('user_email', $userInfo->user_email) }}" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="user_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="user_phone" name="user_phone" value="{{ old('user_phone', $userInfo->user_phone) }}">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                @foreach($departments as $department)
                                <option value="{{ $department->department_id }}" {{ old('department_id', $userInfo->department_id) == $department->department_id ? 'selected' : '' }}>
                                    {{ $department->department }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">Role</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                @foreach($roles as $role)
                                <option value="{{ $role->role_id }}" {{ old('role_id', $userInfo->role_id) == $role->role_id ? 'selected' : '' }}>
                                    {{ $role->role }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="user_active" name="user_active" value="1" {{ old('user_active', $userInfo->user_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="user_active">
                                Active Employee
                            </label>
                        </div>
                    </div>

                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-camera me-2"></i> AI Training Images
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Upload up to 3 portrait images of this employee to help train the facial recognition system.
                        </div>

                        <!-- Change the form enctype to support file uploads -->
                        <script>
                            document.querySelector('form').setAttribute('enctype', 'multipart/form-data');
                        </script>

                        <div class="row mb-3">
                            <!-- Image 1 -->
                            <div class="col-md-4">
                                <label for="ai_image_1" class="form-label">Image 1</label>
                                <div class="input-group mb-2">
                                    <input type="file" class="form-control" id="ai_image_1" name="ai_image_1" 
                                           accept="image/jpeg,image/png,image/jpg">
                                    <button class="btn btn-outline-secondary image-preview-btn" type="button" 
                                            data-input="ai_image_1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="ai_image_1_preview" class="image-preview mt-2 d-none">
                                    <img src="#" alt="Preview" class="img-fluid rounded">
                                    <button type="button" class="btn btn-sm btn-danger mt-1 clear-preview" 
                                            data-preview="ai_image_1_preview" data-input="ai_image_1">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Image 2 -->
                            <div class="col-md-4">
                                <label for="ai_image_2" class="form-label">Image 2</label>
                                <div class="input-group mb-2">
                                    <input type="file" class="form-control" id="ai_image_2" name="ai_image_2" 
                                           accept="image/jpeg,image/png,image/jpg">
                                    <button class="btn btn-outline-secondary image-preview-btn" type="button" 
                                            data-input="ai_image_2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="ai_image_2_preview" class="image-preview mt-2 d-none">
                                    <img src="#" alt="Preview" class="img-fluid rounded">
                                    <button type="button" class="btn btn-sm btn-danger mt-1 clear-preview" 
                                            data-preview="ai_image_2_preview" data-input="ai_image_2">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Image 3 -->
                            <div class="col-md-4">
                                <label for="ai_image_3" class="form-label">Image 3</label>
                                <div class="input-group mb-2">
                                    <input type="file" class="form-control" id="ai_image_3" name="ai_image_3" 
                                           accept="image/jpeg,image/png,image/jpg">
                                    <button class="btn btn-outline-secondary image-preview-btn" type="button" 
                                            data-input="ai_image_3">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="ai_image_3_preview" class="image-preview mt-2 d-none">
                                    <img src="#" alt="Preview" class="img-fluid rounded">
                                    <button type="button" class="btn btn-sm btn-danger mt-1 clear-preview" 
                                            data-preview="ai_image_3_preview" data-input="ai_image_3">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-text">
                            <ul class="mb-0">
                                <li>Images should be clear photos of the employee's face</li>
                                <li>Supported formats: JPEG, JPG, PNG</li>
                                <li>Recommended size: at least 500x500 pixels</li>
                                <li>Maximum file size: 5MB per image</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('employee.profile', $userInfo->user_id) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Display SweetAlert notifications for session messages
        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                confirmButtonColor: '#198754',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                title: 'Error',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        @endif

        // Display validation errors via SweetAlert
        @if($errors->any())
            Swal.fire({
                title: 'Validation Error',
                html: `
                    <div class="text-start">
                        <p>Please correct the following errors:</p>
                        <ul class="list-unstyled text-danger">
                            @foreach ($errors->all() as $error)
                                <li><i class="fas fa-exclamation-circle me-2"></i> {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                `,
                icon: 'warning',
                confirmButtonColor: '#ffc107'
            });
        @endif

        // Submit button confirmation
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            Swal.fire({
                title: 'Save Changes?',
                text: 'Are you sure you want to save the changes to this employee?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, save changes',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Saving...',
                        text: 'Please wait while we save the changes.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    this.submit(); // Submit the form after confirmation
                }
            });
        });

        // Cancel button confirmation
        document.querySelector('.btn-outline-secondary').addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default navigation

            Swal.fire({
                title: 'Discard Changes?',
                text: 'Any unsaved changes will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#0d6efd',
                confirmButtonText: 'Yes, discard changes',
                cancelButtonText: 'No, keep editing',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = this.href; // Navigate to the cancel URL
                }
            });
        });
    });
</script>
@endsection