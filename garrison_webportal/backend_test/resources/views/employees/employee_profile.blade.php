@extends('layouts.app')

@section('title', 'Employee Profile - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
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

    .location-map-container {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 16px;
    }

    .swal2-popup {
        padding: 0 0 24px 0;
    }

    .swal2-html-container {
        margin: 0;
        padding: 0 24px 0 24px;
    }
</style>
@endsection
@yield('styles')
<style>
@media print {
    body::before {
        content: "PRINT VERSION";
        display: block;
        font-size: 24px;
        color: red;
        margin-bottom: 20px;
    }

    /* Hide buttons, navbar, alerts, etc. */
    .profile-btn,
    .navbar,
    .alert,
    .card-footer,
    .btn,
    footer,
    header {
        display: none !important;
    }

    /* Remove shadows and adjust layout for print */
    .card,
    .profile-container {
        box-shadow: none !important;
        border: none !important;
    }

    .profile-container {
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Expand tables full-width */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    th, td {
        padding: 8px;
        border: 1px solid #000;
    }

    /* Set a print-friendly background */
    body {
        background: white !important;
        color: black !important;
    }

    /* Hide loading indicators and map links */
    .loading-indicator,
    .map-link {
        display: none !important;
    }
}
</style>


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
            <!-- Employee Info Card -->
            <div class="card profile-card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 text-white"><i class="fas fa-user me-2"></i> Basic Information</h5>
                </div>
                <div class="card-body px-3">
                    <div class="text-center m-4">
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
                        <p class="employee-title text-muted mb-0">{{ $userInfo->role->role ?? 'No Role' }}</p>
                    </div>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-id-badge me-2"></i> Employee ID</div>
                            <div class="info-value">{{ $userInfo->user_id ?? 'N/A' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-building me-2"></i> Department</div>
                            <div class="info-value">{{ $userInfo->department->department ?? 'No Department' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-envelope me-2"></i> Email</div>
                            <div class="info-value">
                                <a href="mailto:{{ $userInfo->user_email ?? '#' }}" class="text-primary">{{ $userInfo->user_email ?? 'Not specified' }}</a>
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-phone me-2"></i> Phone</div>
                            <div class="info-value">
                                <a href="tel:{{ $userInfo->user_phone }}" class="text-primary">{{ $userInfo->user_phone ?? 'N/A' }}</a>
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-birthday-cake me-2"></i> Date of Birth</div>
                            <div class="info-value">{{ $userInfo->user_dob ?? 'N/A' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-label"><i class="fas fa-calendar-alt me-2"></i> Hire Date</div>
                            <div class="info-value">{{ $userInfo->user_job_start ?? 'N/A' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-label">
                                <i class="fas fa-toggle-{{ $userInfo->user_active ? 'on text-success' : 'off text-danger' }} me-2"></i>
                                Status
                            </div>
                            <div class="info-value">
                                <span class="badge bg-{{ $userInfo->user_active ? 'success' : 'danger' }}">
                                    {{ $userInfo->user_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <a href="{{ route('employees.edit', ['id' => $userInfo->user_id]) }}" class="btn btn-sm btn-outline-primary w-100">
                        <i class="fas fa-user-edit me-1"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Attendance Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Attendance Overview</h5>
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
                                    <td>{{ date('M d, Y', strtotime($record->punch_date)) }}</td>
                                    <td>{{ date('h:i A', strtotime($record->punch_time)) }}</td>
                                    <td>
                                        <span class="badge bg-{{ strtoupper($record->punch_type) == 'IN' ? 'success' : (strtoupper($record->punch_type) == 'OUT' ? 'danger' : 'secondary') }}">
                                            {{ strtoupper($record->punch_type) == 'IN' ? 'Clock In' : (strtoupper($record->punch_type) == 'OUT' ? 'Clock Out' : $record->punch_type) }}
                                        </span>
                                    </td>
                                    <td>{{ $record->device_id ?? 'N/A' }}</td>
                                    <td>
                                        @if($record->latitude && $record->longitude)
                                        <div>
                                            <a href="javascript:void(0)" class="text-primary map-link"
                                               data-lat="{{ $record->latitude }}" 
                                               data-lng="{{ $record->longitude }}">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <span class="location-text text-primary text-decoration-underline" style="cursor: pointer;"
      onclick="viewLocation('{{ $record->latitude }}', '{{ $record->longitude }}', this.textContent)">
    Loading location...
</span>

                                                <div class="mt-1 loading-indicator">
                                                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                </div>
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const DELAY_INCREMENT = 1000;
    let delay = 0;

    function getLocationFromCache(lat, lng) {
        const cacheKey = `location_${lat}_${lng}`;
        try {
            const cached = JSON.parse(localStorage.getItem(cacheKey));
            if (cached && Date.now() - cached.timestamp < 30 * 24 * 60 * 60 * 1000) {
                return cached.location;
            }
        } catch (e) {}
        return null;
    }

    function saveLocationToCache(lat, lng, location) {
        const cacheKey = `location_${lat}_${lng}`;
        localStorage.setItem(cacheKey, JSON.stringify({ location, timestamp: Date.now() }));
    }

    function hideLoading(indicator) {
        if (indicator) indicator.style.display = 'none';
    }

    function updateLocationText(textEl, indicatorEl, location) {
        textEl.textContent = location;
        hideLoading(indicatorEl);
    }

    function tryNominatim(lat, lng, locationText, loadingIndicator) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10&addressdetails=1`;
        fetch(url)
        .then(res => res.json())
        .then(data => {
            const loc = data.address?.city || data.address?.town || data.address?.village ||
                        data.address?.county || data.address?.state || 'Unknown';
            updateLocationText(locationText, loadingIndicator, loc);
            saveLocationToCache(lat, lng, loc);
        })
        .catch(() => tryFallback(lat, lng, locationText, loadingIndicator));
    }

    function tryFallback(lat, lng, locationText, loadingIndicator) {
        const url = `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`;
        fetch(url)
        .then(res => res.json())
        .then(data => {
            const loc = data.locality || data.city || data.principalSubdivision || 'Unknown';
            updateLocationText(locationText, loadingIndicator, loc);
            saveLocationToCache(lat, lng, loc);
        })
        .catch(() => updateLocationText(locationText, loadingIndicator, 'Unknown'));
    }

    function processLocations() {
        const mapLinks = document.querySelectorAll('.map-link');
        mapLinks.forEach(link => {
            const lat = link.getAttribute('data-lat');
            const lng = link.getAttribute('data-lng');
            const locationText = link.querySelector('.location-text');
            const loadingIndicator = link.querySelector('.loading-indicator');

            const cached = getLocationFromCache(lat, lng);
            if (cached) {
                updateLocationText(locationText, loadingIndicator, cached);
            } else {
                setTimeout(() => {
                    tryNominatim(lat, lng, locationText, loadingIndicator);
                }, delay);
                delay += DELAY_INCREMENT;
            }
        });
    }

    function viewLocation(lat, lng, locationName = "Location Details") {
        Swal.fire({
            title: locationName,
            html: `
                <div class="location-details">
                    <p class="mb-3 text-muted small">Coordinates: ${lat}, ${lng}</p>
                    <div class="location-map-container">
                        <iframe 
                            width="100%" height="300" frameborder="0" scrolling="no" 
                            src="https://maps.google.com/maps?q=${lat},${lng}&hl=en&z=14&output=embed">
                        </iframe>
                    </div>
                    <div class="mt-3">
                        <a href="https://www.google.com/maps?q=${lat},${lng}" 
                           class="btn btn-sm btn-outline-primary" target="_blank">
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

    document.addEventListener('DOMContentLoaded', processLocations);
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const printButton = document.getElementById('printProfile');
        if (printButton) {
            printButton.addEventListener('click', function () {
                window.print();
            });
        }
    });
</script>


@endpush
