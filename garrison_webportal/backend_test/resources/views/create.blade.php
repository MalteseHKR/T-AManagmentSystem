@extends('layouts.app')

@section('title', 'Create Employee - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container">
    <h1 class="mb-4">Create New Employee</h1>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('create') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if ($errors->has('_token'))
            <div class="alert alert-danger">
                Session expired. Please try again.
            </div>
        @endif
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle"></i> 
            Fields with <i class="fas fa-magic text-primary"></i> support autocomplete suggestions as you type.
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">
                    Name <i class="fas fa-magic text-primary" title="Autocomplete enabled"></i>
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
                    Surname <i class="fas fa-magic text-primary" title="Autocomplete enabled"></i>
                </label>
                <input type="text" class="form-control" id="surname" name="surname" required
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
            </div>

            <div class="col-md-6 mb-3">
                <label for="job_role" class="form-label">
                    Job Role <i class="fas fa-magic text-primary" title="Autocomplete enabled"></i>
                </label>
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

            <div class="col-md-6 mb-3">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                       pattern="[0-9]{10,15}" title="Phone number should be 10-15 digits" required
                       autocomplete="tel">
            </div>

            <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required
                       autocomplete="email">
            </div>

            <div class="col-md-6 mb-3">
                <label for="date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                       min="1900-01-01" max="{{ date('Y-m-d', strtotime('-16 years')) }}" required
                       autocomplete="bday">
            </div>

            <div class="col-md-6 mb-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" required
                       value="{{ date('Y-m-d') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label for="department" class="form-label">Department</label>
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

            <div class="col-md-6 mb-3">
                <label for="active" class="form-label">Active Status</label>
                <select class="form-control" id="active" name="active" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="col-12 mb-3">
                <label class="form-label">Employee Images (Max 3)</label>
                <div class="row">
                    @for ($i = 1; $i <= 3; $i++)
                        <div class="col-md-4 mb-2">
                            <input type="file" class="form-control" name="images[]" 
                                   accept="image/*" max-size="5242880" 
                                   onchange="validateFileSize(this, 5);">
                        </div>
                    @endfor
                </div>
                <small class="form-text text-muted">
                    Images will be saved for AI facial recognition training purposes.
                </small>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('employees') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Employee</button>
        </div>
    </form>
</div>

{{-- <div class="container mt-5">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Image Upload Debug Console</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="enableDebug" checked>
                <label class="form-check-label" for="enableDebug">Enable Debug Info</label>
            </div>
        </div>
        <div class="card-body debug-console" id="debugConsole" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; font-family: monospace; font-size: 0.85rem;">
            <div class="text-muted">Select images to see debug information...</div>
        </div>
    </div>
</div> --}}

<script>
function validateFileSize(input, maxSizeMB) {
    if (!document.getElementById('enableDebug').checked) return;
    
    const debugConsole = document.getElementById('debugConsole');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSizeMB = file.size / 1024 / 1024;
        const fileIndex = Array.from(input.parentNode.parentNode.children).indexOf(input.parentNode);
        
        // Add file info to debug console
        const timestamp = new Date().toLocaleTimeString();
        const fileInfo = document.createElement('div');
        fileInfo.innerHTML = `
            <div class="debug-entry border-bottom pb-2 mb-2">
                <div><strong>[${timestamp}] File ${fileIndex + 1} selected:</strong></div>
                <div>• Name: <span class="text-primary">${file.name}</span></div>
                <div>• Size: ${fileSizeMB.toFixed(2)} MB</div>
                <div>• Type: ${file.type}</div>
                <div>• Will be renamed to: <span class="text-success" id="rename-preview-${fileIndex}">
                    Calculating...</span>
                </div>
            </div>
        `;
        debugConsole.appendChild(fileInfo);
        debugConsole.scrollTop = debugConsole.scrollHeight;
        
        // Preview the renamed file - UPDATED TO MATCH THE EXACT FORMAT
        const firstName = document.getElementById('name').value || '[FirstName]';
        const lastName = document.getElementById('surname').value || '[LastName]';
        const firstNameInitial = firstName.charAt(0).toUpperCase();
        const lastNameInitial = lastName.charAt(0).toUpperCase();
        const monthDay = new Date().toLocaleDateString('en-US', {
            month: 'numeric',
            day: 'numeric'
        });
        
        // Updated to match exact format: FirstName LastName(Number) (LastNameInitial FirstNameInitial)(m/dd)
        const imageNumber = fileIndex + 1;
        const newFilename = `${firstName} ${lastName}(${imageNumber}) (${lastNameInitial}${firstNameInitial})(${monthDay}).${file.name.split('.').pop()}`;
        document.getElementById(`rename-preview-${fileIndex}`).innerText = newFilename;
        
        // Validate file size
        if (fileSizeMB > maxSizeMB) {
            const errorMsg = document.createElement('div');
            errorMsg.innerHTML = `
                <div class="text-danger">⚠️ File size exceeds ${maxSizeMB}MB limit. Please choose a smaller file.</div>
            `;
            debugConsole.appendChild(errorMsg);
            input.value = '';
        }
    }
}

// Add event listener to form submission to show debug info
document.querySelector('form').addEventListener('submit', function(e) {
    if (!document.getElementById('enableDebug').checked) return;
    
    const debugConsole = document.getElementById('debugConsole');
    const timestamp = new Date().toLocaleTimeString();
    
    // Count how many files are selected
    const fileInputs = document.querySelectorAll('input[type="file"]');
    let selectedFileCount = 0;
    fileInputs.forEach(input => {
        if (input.files && input.files.length > 0) selectedFileCount++;
    });
    
    const submissionInfo = document.createElement('div');
    submissionInfo.innerHTML = `
        <div class="alert alert-info">
            <strong>[${timestamp}] Form submitted with ${selectedFileCount} image(s)</strong><br>
            Sending to: /Home/employeephotos on 192.168.10.11 via SFTP<br>
            <div class="spinner-border spinner-border-sm text-primary mt-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span class="ms-2">Uploading images, please wait...</span>
        </div>
    `;
    debugConsole.appendChild(submissionInfo);
    debugConsole.scrollTop = debugConsole.scrollHeight;
});

// Add name/surname change listeners to update filename previews
['name', 'surname'].forEach(fieldId => {
    document.getElementById(fieldId).addEventListener('input', function() {
        if (!document.getElementById('enableDebug').checked) return;
        
        // Update all filename previews
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach((input, index) => {
            if (input.files && input.files.length > 0) {
                const firstName = document.getElementById('name').value || '[FirstName]';
                const lastName = document.getElementById('surname').value || '[LastName]';
                const firstNameInitial = firstName.charAt(0).toUpperCase();
                const lastNameInitial = lastName.charAt(0).toUpperCase();
                const monthDay = new Date().toLocaleDateString('en-US', {
                    month: 'numeric',
                    day: 'numeric'
                });
                
                // Updated to match exact format
                const imageNumber = index + 1;
                const newFilename = `${firstName} ${lastName}(${imageNumber}) (${lastNameInitial}${firstNameInitial})(${monthDay}).${input.files[0].name.split('.').pop()}`;
                const previewElement = document.getElementById(`rename-preview-${index}`);
                if (previewElement) previewElement.innerText = newFilename;
            }
        });
    });
});

// Auto-generate email when name and surname are completed
function updateEmailSuggestion() {
    const firstName = document.getElementById('name').value.trim();
    const lastName = document.getElementById('surname').value.trim();
    const emailField = document.getElementById('email');
    
    // Only update if email field is empty and we have both name parts
    if (firstName && lastName && !emailField.value) {
        // Create suggested email from first name and surname
        const suggestedEmail = `${firstName.toLowerCase()}.${lastName.toLowerCase()}@garrison.com`;
        
        // Ask user if they want to use the suggested email
        const useEmailCheck = document.createElement('div');
        useEmailCheck.className = 'form-check mt-2';
        useEmailCheck.id = 'emailSuggestion';
        useEmailCheck.innerHTML = `
            <input class="form-check-input" type="checkbox" id="useEmailSuggestion" checked>
            <label class="form-check-label" for="useEmailSuggestion">
                Use suggested email: <strong>${suggestedEmail}</strong>
            </label>
        `;
        
        // Remove existing suggestion if present
        const existingSuggestion = document.getElementById('emailSuggestion');
        if (existingSuggestion) {
            existingSuggestion.remove();
        }
        
        // Add the suggestion checkbox after the email field
        emailField.parentNode.appendChild(useEmailCheck);
        
        // Add event listener to checkbox
        document.getElementById('useEmailSuggestion').addEventListener('change', function() {
            if (this.checked) {
                emailField.value = suggestedEmail;
            } else {
                emailField.value = '';
            }
        });
        
        // Set the email field value
        emailField.value = suggestedEmail;
    }
}

// Attach event listeners to name and surname fields
document.getElementById('name').addEventListener('blur', updateEmailSuggestion);
document.getElementById('surname').addEventListener('blur', updateEmailSuggestion);

// Add autocomplete for department based on job role
document.getElementById('job_role').addEventListener('change', function() {
    // Only proceed if departments dropdown exists and has options
    const departmentSelect = document.getElementById('department');
    if (!departmentSelect || departmentSelect.tagName !== 'SELECT') {
        return; // Exit if department selector doesn't exist or isn't a dropdown
    }
    
    const jobRole = this.value.toLowerCase();
    
    // Map common job roles to departments
    const roleToDepartment = {
        'developer': 'Information Technology',
        'programmer': 'Information Technology',
        'engineer': 'Information Technology',
        'designer': 'Information Technology',
        'qa': 'Information Technology',
        'tester': 'Information Technology',
        'accountant': 'Finance',
        'finance': 'Finance',
        'sales': 'Sales',
        'representative': 'Sales',
        'marketing': 'Marketing',
        'social media': 'Marketing',
        'manager': '', // Don't auto-select for generic roles
        'hr': 'Human Resources',
        'human resources': 'Human Resources',
        'support': 'Customer Support',
        'customer': 'Customer Support',
        'research': 'Research & Development'
    };
    
    // Find matching department
    for (const [roleKeyword, department] of Object.entries(roleToDepartment)) {
        if (jobRole.includes(roleKeyword) && department) {
            // Try to find this department in the dropdown
            const allOptions = Array.from(departmentSelect.options);
            const matchingOption = allOptions.find(opt => 
                opt.text.toLowerCase().includes(department.toLowerCase())
            );
            
            if (matchingOption) {
                departmentSelect.value = matchingOption.value;
                break;
            }
        }
    }
});

// Add a button to populate the form with random test data
const formHeader = document.querySelector('h1');
const testDataButton = document.createElement('button');
testDataButton.type = 'button';
testDataButton.className = 'btn btn-sm btn-outline-secondary ms-3';
testDataButton.innerHTML = '<i class="fas fa-vial"></i> Fill Test Data';
testDataButton.title = 'Populate form with test data';
testDataButton.addEventListener('click', function() {
    const testData = {
        'name': ['John', 'Jane', 'Michael', 'Sarah', 'David'][Math.floor(Math.random() * 5)],
        'surname': ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones'][Math.floor(Math.random() * 5)],
        'phone_number': '07' + Math.floor(Math.random() * 900000000 + 100000000),
        'date_of_birth': new Date(
            1970 + Math.floor(Math.random() * 30), 
            Math.floor(Math.random() * 12), 
            Math.floor(Math.random() * 28) + 1
        ).toISOString().split('T')[0]
    };
    
    // Fill the form fields
    for (const [field, value] of Object.entries(testData)) {
        document.getElementById(field).value = value;
    }
    
    // Select a random job role
    const roleSelect = document.getElementById('job_role');
    const roleOptions = Array.from(roleSelect.options).filter(opt => opt.value !== '');
    if (roleOptions.length > 0) {
        const randomRole = roleOptions[Math.floor(Math.random() * roleOptions.length)];
        roleSelect.value = randomRole.value;
    }
    
    // Trigger department selection based on job role
    document.getElementById('job_role').dispatchEvent(new Event('change'));
    
    // Trigger email generation
    updateEmailSuggestion();
    
    // Update file rename previews if any files are selected
    ['name', 'surname'].forEach(field => {
        document.getElementById(field).dispatchEvent(new Event('input'));
    });
});
formHeader.appendChild(testDataButton);
</script>

<style>
.debug-console:empty::before {
    content: "No debug information available";
    color: #6c757d;
    font-style: italic;
}
.debug-entry {
    border-left: 3px solid #6c757d;
    padding-left: 10px;
    margin-bottom: 10px;
}
</style>
@endsection