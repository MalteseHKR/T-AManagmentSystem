@extends('layouts.app')

@section('title', 'Create Employee - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
.img-preview {
    width: 150px;
    height: 150px;
    object-fit: cover;
    object-position: center;
    border: 2px solid #ccc; /* optional */
    box-shadow: 0 2px 6px rgba(0,0,0,0.1); /* optional */
}

/* Form input icon styles */
.form-input-icon {
    position: relative;
}

.form-input-icon input,
.form-input-icon select {
    padding-left: 50px; /* Make room for the icon */
    height: 50px; /* Consistent height */
}

.form-input-icon::before {
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border: 1px solid #ced4da;
    border-right: none;
    border-radius: 0.25rem 0 0 0.25rem;
    color: #6c757d;
    z-index: 5;
}

/* Icons for each input type */
.phone-input::before {
    content: "\f095"; /* Font Awesome phone icon */
}

.email-input::before {
    content: "\f0e0"; /* Font Awesome envelope icon */
}

.date-input::before {
    content: "\f073"; /* Font Awesome calendar icon */
}

.birthday-input::before {
    content: "\f1fd"; /* Font Awesome birthday cake icon */
}

.department-input::before {
    content: "\f0e8"; /* Font Awesome sitemap icon */
}

.job-role-input::before {
    content: "\f0b1"; /* Font Awesome briefcase icon */
}

.status-input::before {
    content: "\f111"; /* Font Awesome circle icon */
}
</style>
@endsection

@section('show_navbar', true)

@section('content')
<div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h1 class="employee-header mb-0">Create New Employee</h1>
        <div class="d-flex">
            <a href="{{ route('employees') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
            <button type="button" id="fillTestData" class="btn btn-outline-info">
                <i class="fas fa-vial me-1"></i> Fill Test Data
            </button>
        </div>
    </div>

    <div class="card employee-card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0 text-white"><i class="fas fa-user-plus me-2"></i> Employee Information</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <div class="d-flex">
                    <div class="me-3 fs-4">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <p class="mb-0">Fields with <i class="fas fa-magic text-primary"></i> support autocomplete suggestions as you type.</p>
                        <p class="mb-0 small">All fields marked with <span class="text-danger">*</span> are required.</p>
                    </div>
                </div>
            </div>

            <form id="employeeForm" action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($errors->has('_token'))
                    <div class="alert alert-danger">
                        Session expired. Please try again.
                    </div>
                @endif
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">
                            Name <span class="text-danger">*</span> <i class="fas fa-magic text-primary" title="Autocomplete enabled"></i>
                        </label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name') }}" required
                               autocomplete="given-name" list="common-names">
                        <datalist id="common-names">
                            <option value="John">
                            <option value="David">
                            <option value="Michael">
                            <option value="James">
                            <option value="Robert">
                            <option value="William">
                            <option value="Sarah">
                            <option value="Jennifer">
                            <option value="Elizabeth">
                            <option value="Linda">
                            <option value="Emily">
                        </datalist>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="surname" class="form-label">
                            Surname <span class="text-danger">*</span> <i class="fas fa-magic text-primary" title="Autocomplete enabled"></i>
                        </label>
                        <input type="text" class="form-control @error('surname') is-invalid @enderror" 
                               id="surname" name="surname" value="{{ old('surname') }}" required
                               autocomplete="family-name" list="common-surnames">
                        <datalist id="common-surnames">
                            <option value="Smith">
                            <option value="Johnson">
                            <option value="Williams">
                            <option value="Jones">
                            <option value="Brown">
                            <option value="Miller">
                            <option value="Davis">
                            <option value="Wilson">
                            <option value="Taylor">
                            <option value="Anderson">
                        </datalist>
                        @error('surname')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="job_role" class="form-label">
                            Job Role <span class="text-danger">*</span> <i class="fas fa-magic text-primary" title="Autocomplete enabled"></i>
                        </label>
                        <div class="form-input-icon job-role-input">
                            @if(count($roles) > 0)
                                <select class="form-control @error('job_role') is-invalid @enderror" 
                                       id="job_role" name="job_role" required>
                                    <option value="">Select Job Role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->role }}" {{ old('job_role') == $role->role ? 'selected' : '' }}>
                                            {{ $role->role }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <div class="form-control bg-light text-danger">
                                    Sorry, no job roles found in the database.
                                </div>
                                <input type="hidden" id="job_role" name="job_role" value="">
                            @endif
                            @error('job_role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Phone Number Field -->
                    <div class="col-md-6 mb-3">
                        <label for="phone_number" class="form-label">
                            Phone Number <span class="text-danger">*</span>
                        </label>
                        <div class="form-input-icon phone-input">
                            <input type="tel" 
                                class="form-control @error('phone_number') is-invalid @enderror" 
                                id="phone_number" 
                                name="phone_number" 
                                value="{{ old('phone_number') }}"
                                pattern="[0-9]{10,15}" 
                                title="Phone number should be 10-15 digits" 
                                required
                                autocomplete="tel" 
                                placeholder="e.g. 07123456789">
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Email Field -->
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <div class="form-input-icon email-input">
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email') }}" required
                                   autocomplete="email" placeholder="firstname.lastname@garrison.com">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div id="emailSuggestionContainer"></div>
                    </div>

                    <!-- Date of Birth Field -->
                    <div class="col-md-6 mb-3">
                        <label for="date_of_birth" class="form-label">
                            Date of Birth <span class="text-danger">*</span>
                        </label>
                        <div class="form-input-icon birthday-input">
                            <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" 
                                   id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}"
                                   min="1900-01-01" max="{{ date('Y-m-d', strtotime('-16 years')) }}" required
                                   autocomplete="bday">
                            @error('date_of_birth')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Start Date Field -->
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">
                            Start Date <span class="text-danger">*</span>
                        </label>
                        <div class="form-input-icon date-input">
                            <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                   id="start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="department" class="form-label">
                            Department <span class="text-danger">*</span>
                        </label>
                        <div class="form-input-icon department-input">
                            @if(count($departments) > 0)
                                <select class="form-control @error('department') is-invalid @enderror" 
                                       id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->department }}" {{ old('department') == $dept->department ? 'selected' : '' }}>
                                            {{ $dept->department }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <div class="form-control bg-light text-danger">
                                    Sorry, no departments found in the database.
                                </div>
                                <input type="hidden" id="department" name="department" value="">
                            @endif
                            @error('department')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="active" class="form-label">
                            Active Status <span class="text-danger">*</span>
                        </label>
                        <div class="form-input-icon status-input">
                            <select class="form-control @error('active') is-invalid @enderror" 
                                   id="active" name="active" required>
                                <option value="1" {{ old('active', '1') == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('active') == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('active')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-4 border-0 bg-light">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-camera me-2"></i> Employee Images
                            <span class="text-muted fs-6">(Optional)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row image-upload-container">
                            @for ($i = 1; $i <= 3; $i++)
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="image-upload-card">
                                        <div class="image-preview" id="preview-container-{{ $i }}">
                                            <i class="fas fa-user image-placeholder"></i>
                                        </div>
                                        <div class="upload-controls">
                                            <label for="image-{{ $i }}" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-upload me-1"></i> Choose Image {{ $i }}
                                            </label>
                                            <input type="file" class="form-control d-none" id="image-{{ $i }}" 
                                                   name="images[]" accept="image/*" max-size="5242880"
                                                   onchange="previewImage(this, {{ $i }})">
                                        </div>
                                        <small class="upload-info" id="info-{{ $i }}">No file selected</small>
                                    </div>
                                </div>
                            @endfor
                        </div>
                        <div class="alert alert-info mt-2 mb-0">
                            <div class="d-flex">
                                <div class="me-2">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div>
                                    <p class="mb-0">Images will be used for AI facial recognition training.</p>
                                    <p class="mb-0 small">Maximum file size: 5MB per image. Supported formats: JPG, PNG, GIF.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-3">
                    <button type="button" id="cancelBtn" class="btn btn-outline-secondary order-2 order-md-1">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" id="submitBtn" class="btn btn-primary order-1 order-md-2">
                        <i class="fas fa-user-plus me-1"></i> Create Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card employee-card shadow-sm d-none" id="debugConsoleCard">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-terminal me-2"></i> Image Upload Debug Console</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="enableDebug" checked>
                <label class="form-check-label text-white" for="enableDebug">Enable Debug</label>
            </div>
        </div>
        <div class="card-body debug-console" id="debugConsole" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; font-family: monospace; font-size: 0.85rem;">
            <div class="text-muted">Select images to see debug information...</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
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
                confirmButtonColor: '#3490dc',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                title: 'Error',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonColor: '#e3342f'
            });
        @endif

        // Display validation errors via SweetAlert
        @if($errors->any())
            Swal.fire({
                title: 'Form Validation Error',
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
                confirmButtonColor: '#3490dc'
            });
        @endif

        // Image preview functionality
        window.previewImage = function(input, index) {
            const previewContainer = document.getElementById(`preview-container-${index}`);
            const infoElement = document.getElementById(`info-${index}`);

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();

                reader.onload = function(e) {
                    previewContainer.innerHTML = `
                        <img src="${e.target.result}" class="img-preview img-fluid rounded" alt="Preview Image">
                    `;

                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    infoElement.innerHTML = `${file.name} (${fileSize} MB)`;

                    // Validate file size
                    if (fileSize > 5) {
                        Swal.fire({
                            title: 'File Too Large',
                            text: 'The selected image exceeds the 5MB limit. Please choose a smaller file.',
                            icon: 'error',
                            confirmButtonColor: '#e3342f'
                        });
                        input.value = ''; // Clear the file input
                        previewContainer.innerHTML = `<i class="fas fa-user image-placeholder"></i>`;
                        infoElement.innerHTML = 'No file selected';
                    }
                };

                reader.readAsDataURL(file);
            } else {
                previewContainer.innerHTML = `<i class="fas fa-user image-placeholder"></i>`;
                infoElement.innerHTML = 'No file selected';
            }
        };

        // Submit button confirmation
        document.getElementById('submitBtn').addEventListener('click', function() {
            const form = document.getElementById('employeeForm');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Create New Employee?',
                text: 'Are you sure you want to create this employee record?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3490dc',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, create employee',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Creating Employee...',
                        html: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit();
                }
            });
        });

        // Cancel button confirmation
        document.getElementById('cancelBtn').addEventListener('click', function() {
            Swal.fire({
                title: 'Discard Changes?',
                text: 'Any unsaved changes will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#3490dc',
                confirmButtonText: 'Yes, discard changes',
                cancelButtonText: 'No, keep editing',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('employees') }}";
                }
            });
        });

        // Fill test data functionality
        document.getElementById('fillTestData').addEventListener('click', function() {
            const testData = {
                name: ['John', 'Jane', 'Michael', 'Sarah', 'David'][Math.floor(Math.random() * 5)],
                surname: ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones'][Math.floor(Math.random() * 5)],
                phone_number: '07' + Math.floor(Math.random() * 900000000 + 100000000),
                date_of_birth: new Date(
                    1970 + Math.floor(Math.random() * 30),
                    Math.floor(Math.random() * 12),
                    Math.floor(Math.random() * 28) + 1
                ).toISOString().split('T')[0]
            };

            for (const [field, value] of Object.entries(testData)) {
                document.getElementById(field).value = value;
            }

            Swal.fire({
                position: 'top-end',
                icon: 'info',
                title: 'Test data filled',
                showConfirmButton: false,
                timer: 1500
            });
        });
    });
</script>
@endpush