@extends('layouts.app')

@section('title', 'Employee Profile - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
    /* Location styling */
    .location-text {
        transition: opacity 0.3s;
    }

    .loading-indicator {
        height: 16px;
        display: flex;
        align-items: center;
    }

    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 2px;
    }

    /* Location map container for modal */
    .location-map-container {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 16px;
    }

    /* Make sure modal is large enough */
    .swal2-popup {
        padding: 0 0 24px 0;
    }
    
    .swal2-html-container {
        margin: 0;
        padding: 0 24px 0 24px;
    }
</style>
@endsection

@section('head')
<script>
    const DEBUG = true;

    function log(...args) {
        if (DEBUG) console.log(...args);
    }

    // Define the viewLocation function globally
    function viewLocation(lat, lng, locationName = "Location Details") {
        console.log(`viewLocation called with lat: ${lat}, lng: ${lng}, locationName: ${locationName}`);
        Swal.fire({
            title: locationName,
            html: `
                <div class="location-details">
                    <p class="mb-3 text-muted small">Coordinates: ${lat}, ${lng}</p>
                    <div class="location-map-container">
                        <iframe 
                            width="100%" 
                            height="300" 
                            frameborder="0" 
                            scrolling="no" 
                            marginheight="0" 
                            marginwidth="0" 
                            src="https://maps.google.com/maps?q=${lat},${lng}&hl=en&z=14&output=embed">
                        </iframe>
                    </div>
                    <div class="mt-3">
                        <a href="https://www.google.com/maps?q=${lat},${lng}" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
            `,
            width: '500px',
            showCloseButton: true,
            showConfirmButton: false
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        log('Initializing location processing...');
        processLocations();
    });

    function processLocations() {
        const mapLinks = document.querySelectorAll('.map-link');
        log(`Found ${mapLinks.length} map links`);

        mapLinks.forEach(link => {
            const lat = link.getAttribute('data-lat');
            const lng = link.getAttribute('data-lng');
            const locationText = link.querySelector('.location-text');

            if (!lat || !lng || !locationText) {
                log('Missing required attributes for map link');
                return;
            }

            // Attach event listener instead of inline onclick
            link.addEventListener('click', () => {
                viewLocation(lat, lng, locationText.textContent || "Location Details");
            });
        });
    }
</script>
@endsection

@section('show_navbar', true)

@section('content')
<div class="container profile-container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h1 class="profile-header mb-0">Employee Profile</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('employees') }}" class="btn btn-secondary profile-btn">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
            <button id="printProfile" class="btn btn-primary profile-btn">
                <i class="fas fa-print me-2"></i> Print Profile
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Employee Basic Info Card -->
            <div class="card profile-card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 text-white"><i class="fas fa-user me-2"></i> Basic Information</h5>
                </div>
                <div class="card-body px-3">
                    <div class="text-center m-4">
                        <!-- Employee Portrait with improved display -->
                        <div class="portrait-container mx-auto position-relative" style="width: 200px; height: 200px;">
                            @if(isset($userInfo->portrait_url) && file_exists($userInfo->portrait_url))
                                <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($userInfo->portrait_url)) }}"
                                    alt="Portrait of {{ $userInfo->user_name }}"
                                    class="img-fluid w-100 h-100 object-fit-cover rounded-circle">
                            @else
                                <img src="{{ asset('images/default-portrait.png') }}" 
                                alt="Default Portrait"
                                class="img-fluid w-100 h-100 object-fit-cover rounded-circle">
                            @endif
                            
                            <div class="status-indicator {{ isset($userInfo->user_active) && $userInfo->user_active == 1 ? 'status-active' : 'status-inactive' }}">
                                <i class="fas {{ isset($userInfo->user_active) && $userInfo->user_active == 1 ? 'fa-check' : 'fa-times' }}"></i>
                            </div>
                        </div>
                        
                        <h4 class="employee-name mt-3 mb-1">{{ $userInfo->user_name ?? '' }} {{ $userInfo->user_surname ?? '' }}</h4>
                        <p class="employee-title text-muted mb-0">{{ $userInfo->role ? $userInfo->role->role : 'No Role' }}</p>
                    </div>
                    
                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-id-badge me-2"></i> Employee ID</div>
                            <div class="info-value">{{ $userInfo->user_id ?? 'N/A' }}</div>
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-building me-2"></i> Department</div>
                            <div class="info-value">{{ $userInfo->department ? $userInfo->department->department : 'No Department' }}</div>
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-envelope me-2"></i> Email</div>
                            <div class="info-value">
                                @if(isset($userInfo->user_email))
                                    <a href="mailto:{{ $userInfo->user_email }}" class="text-primary">
                                        {{ $userInfo->user_email }}
                                    </a>
                                @else
                                    Not specified
                                @endif
                            </div>
                        </div>
                        
                        @if(isset($userInfo->user_phone))
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-phone me-2"></i> Phone</div>
                            <div class="info-value">
                                <a href="tel:{{ $userInfo->user_phone }}" class="text-primary">
                                    {{ $userInfo->user_phone }}
                                </a>
                            </div>
                        </div>
                        @endif
                        
                        @if(isset($userInfo->user_dob))
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-birthday-cake me-2"></i> Date of Birth</div>
                            <div class="info-value">{{ $userInfo->user_dob }}</div>
                        </div>
                        @endif
                        
                        @if(isset($userInfo->user_job_start))
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-calendar-alt me-2"></i> Hire Date</div>
                            <div class="info-value">{{ $userInfo->user_job_start }}</div>
                        </div>
                        @endif
                        
                        <div class="profile-info-item">
                            <div class="info-label">
                                @if(isset($userInfo->user_active) && $userInfo->user_active == 1)
                                    <i class="fas fa-toggle-on text-success me-2"></i>
                                @else
                                    <i class="fas fa-toggle-off text-danger me-2"></i>
                                @endif
                                Status
                            </div>
                            <div class="info-value">
                                @if(isset($userInfo->user_active) && $userInfo->user_active == 1)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-grid gap-2">
                        <a href="{{ route('employees.edit', ['id' => $userInfo->user_id]) }}" class="btn btn-sm btn-outline-primary" id="editProfileBtn">
                            <i class="fas fa-user-edit me-1"></i> Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Recent Attendance Card -->
            <div class="card attendance-card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white"><i class="fas fa-calendar-check me-2"></i> Attendance Overview</h5>
                    <div>
                        <button id="locationButton" class="btn btn-sm btn-light me-2">
                            <i class="fas fa-map-marker-alt me-1"></i> My Location
                        </button>
                        <button id="refreshAttendance" class="btn btn-sm btn-light">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @if(isset($attendanceRecords) && count($attendanceRecords) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Device</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendanceRecords as $record)
                                    <tr>
                                        <td>{{ isset($record->punch_date) ? date('M d, Y', strtotime($record->punch_date)) : 'N/A' }}</td>
                                        <td>{{ isset($record->punch_time) ? date('h:i A', strtotime($record->punch_time)) : 'N/A' }}</td>
                                        <td>
                                            @if(isset($record->punch_type))
                                                @if(strtoupper($record->punch_type) == 'IN')
                                                    <span class="badge bg-success">Clock In</span>
                                                @elseif(strtoupper($record->punch_type) == 'OUT')
                                                    <span class="badge bg-danger">Clock Out</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $record->punch_type }}</span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Unknown</span>
                                            @endif
                                        </td>
                                        <td>{{ $record->device_id ?? 'N/A' }}</td>
                                        <td>
                                            @if(isset($record->latitude) && isset($record->longitude))
                                                <div>
                                                    <a href="javascript:void(0)" 
                                                       class="text-primary map-link"
                                                       data-lat="{{ $record->latitude }}" 
                                                       data-lng="{{ $record->longitude }}">
                                                        <i class="fas fa-map-marker-alt me-1"></i> 
                                                        <span class="location-text">View Location</span>
                                                    </a>
                                                </div>
                                            @else
                                                <span class="text-muted">No location</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No attendance records found for this employee.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
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
                title: 'Error!',
                text: "{{ session('error') }}",
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        @endif

        // Print Profile Button
        document.getElementById('printProfile').addEventListener('click', function () {
            Swal.fire({
                title: 'Generating Profile Report',
                text: 'Please wait while we prepare the profile for printing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    setTimeout(() => {
                        Swal.close();
                        printProfile();
                    }, 1500);
                }
            });
        });

        // Print Profile Function
        function printProfile() {
            const printWindow = window.open('', '_blank');
            const profileContent = document.querySelector('.profile-container').innerHTML;

            printWindow.document.write(`
                <html>
                <head>
                    <title>Employee Profile</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #2563eb; }
                        .profile-container { margin: 20px; }
                        .portrait-image { width: 150px; height: 150px; border-radius: 50%; }
                        .badge { padding: 5px 10px; font-size: 12px; }
                        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .table th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <h1>Employee Profile</h1>
                    ${profileContent}
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.print();
            };
        }

        // Refresh Attendance Button
        document.getElementById('refreshAttendance').addEventListener('click', function () {
            Swal.fire({
                title: 'Refreshing Attendance',
                text: 'Please wait while we refresh the attendance records...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    setTimeout(() => {
                        Swal.fire({
                            title: 'Attendance Refreshed',
                            text: 'The attendance records have been updated.',
                            icon: 'success',
                            confirmButtonColor: '#198754',
                            timer: 2000,
                            timerProgressBar: true
                        });
                    }, 1500);
                }
            });
        });

        // Location Button
        document.getElementById('locationButton').addEventListener('click', function () {
            Swal.fire({
                title: 'Fetching Location',
                text: 'Please wait while we retrieve your current location...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const { latitude, longitude } = position.coords;
                            Swal.close();
                            viewLocation(latitude, longitude, 'Your Current Location');
                        },
                        (error) => {
                            Swal.fire({
                                title: 'Error',
                                text: 'Unable to retrieve your location. Please ensure location services are enabled.',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    );
                }
            });
        });

        // View Location Links
        processLocations();
    });
</script>
@endsection