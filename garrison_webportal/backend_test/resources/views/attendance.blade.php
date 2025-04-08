@extends('layouts.app')

@section('title', 'Attendance - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')
<div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h1 class="attendance-header mb-0">Attendance Records</h1>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary attendance-btn">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <a href="{{ route('attendance.analytics') }}" class="btn btn-primary attendance-btn analytics-btn">
                <i class="fas fa-chart-bar me-2"></i> Analytics Dashboard
            </a>
        </div>
    </div>

    <x-filter 
        route="{{ route('attendance') }}"
        :has-name-filter="true"
        :has-date-filter="true"
        name-label="Employee"
        name-placeholder="Search by employee name"
        :columns="3"
    />

    <div class="card attendance-card shadow border-0">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="card-title mb-0 fw-bold">
                <i class="fas fa-clock me-2"></i> Attendance List
            </h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive attendance-table-wrapper">
                <table class="table table-hover table-striped align-middle mb-0 w-100 attendance-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-3 py-3">Employee</th>
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Time</th>
                            <th class="px-3 py-3">Punch Type</th>
                            <th class="px-3 py-3">Device</th>
                            <th class="px-3 py-3">Location</th>
                            <th class="px-3 py-3">Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td class="px-3 py-3 fw-semibold align-middle">
                                    {{ optional($attendance->userInformation)->user_name }} 
                                    {{ optional($attendance->userInformation)->user_surname }}
                                </td>
                                <td class="px-3 py-3">{{ $attendance->punch_date }}</td>
                                <td class="px-3 py-3">{{ $attendance->punch_time }}</td>
                                <td class="px-3 py-3">
                                    @if($attendance->punch_type == 'IN')
                                        <span class="badge bg-success">Clock In</span>
                                    @elseif($attendance->punch_type == 'OUT')
                                        <span class="badge bg-danger">Clock Out</span>
                                    @else
                                        <span class="badge bg-info">{{ $attendance->punch_type }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    {{ optional($attendance->device)->device_name ?? 'Device #'.$attendance->device_id }}
                                </td>
                                <td class="px-3 py-3">
                                    @if($attendance->latitude && $attendance->longitude)
                                        <div>
                                            <a href="javascript:void(0)" class="text-primary map-link"
                                               data-lat="{{ $attendance->latitude }}" 
                                               data-lng="{{ $attendance->longitude }}"
                                               onclick="viewLocation('{{ $attendance->latitude }}', '{{ $attendance->longitude }}')">
                                                <i class="fas fa-map-marker-alt me-1"></i> 
                                                <span class="location-text">Loading location...</span>
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
                                <td class="px-3 py-3">
                                    @if($attendance->photo_url)
                                        @php
                                            // Get the filename from the database
                                            $photoFilename = basename($attendance->photo_url ?? '');
                                        
                                            // Ensure correct formatting (remove unwanted characters)
                                            $photoFilename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $photoFilename);
                                        
                                            // Secure image URL (only logged-in users can access)
                                            $imageUrl = route('secure-image', ['filename' => $photoFilename]);
                                        @endphp
                                        
                                        <a href="javascript:void(0)" class="text-primary" onclick="viewAttendancePhoto('{{ $imageUrl }}')">
                                            <img src="{{ $imageUrl }}"
                                                 alt="Attendance photo"
                                                 class="attendance-thumbnail"
                                                 onerror="this.onerror=null; this.src='{{ asset('/uploads/placeholder.jpg') }}';">
                                        </a>
                                    @else
                                        <span class="text-muted">No photo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 empty-state">
                                    <div class="empty-state-icon mb-3">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <h5 class="text-muted mb-2">No Records Found</h5>
                                    <p class="text-muted mb-3">There are no attendance records matching your search criteria.</p>
                                    <a href="{{ route('attendance') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-sync-alt me-1"></i> Reset Filters
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        @if($attendances->isEmpty())
            <div class="card-footer bg-white border-0 py-3">
                <p class="text-center mb-0">
                    <button type="button" class="btn btn-outline-primary" id="refresh-data">
                        <i class="fas fa-sync-alt me-1"></i> Refresh Data
                    </button>
                </p>
            </div>
        @endif
    </div>

    @if($attendances->hasPages())
        <div class="pagination-container">
            <div class="pagination-info">
                Showing {{ $attendances->firstItem() ?? 0 }} to {{ $attendances->lastItem() ?? 0 }} of {{ $attendances->total() }} entries
            </div>
            
            <nav aria-label="Attendance pages">
                <ul class="pagination">
                    <!-- First Page Link -->
                    <li class="page-item {{ $attendances->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->url(1) }}" aria-label="First">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Previous Page Link -->
                    <li class="page-item {{ $attendances->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->previousPageUrl() }}" aria-label="Previous">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <!-- Current Page Info -->
                    <li class="page-item active">
                        <span class="page-link">
                            {{ $attendances->currentPage() }}
                        </span>
                    </li>
                    
                    <!-- Next Page Link -->
                    <li class="page-item {{ !$attendances->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->nextPageUrl() }}" aria-label="Next">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Last Page Link -->
                    <li class="page-item {{ !$attendances->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $attendances->url($attendances->lastPage()) }}" aria-label="Last">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show success toast if exists
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: "{{ session('success') }}",
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        @endif
        
        // Show error message if exists
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ session('error') }}",
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        @endif
        
        // Show loading toast when first loaded
        Swal.fire({
            title: 'Loading attendance data...',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            icon: 'info',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        // Refresh data button
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Refreshing Data',
                    text: 'Please wait while we refresh attendance records...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            });
        }
    });
    
    // Function to view location
    function viewLocation(lat, lng) {
        // Get cached location name
        const cacheKey = `location_${lat}_${lng}`;
        let locationName = 'Unknown Location';
        
        try {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                const data = JSON.parse(cached);
                if (data.location) {
                    locationName = data.location;
                }
            }
        } catch (e) {
            // If error, continue with unknown location
        }
        
        Swal.fire({
            title: 'Location Details',
            html: `
                <div class="location-details">
                    <p class="mb-2"><strong>${locationName}</strong></p>
                    <p class="mb-3 text-muted small">Coordinates: ${lat}, ${lng}</p>
                    <div class="location-map-container">
                        <iframe 
                            width="100%" 
                            height="250" 
                            frameborder="0" 
                            scrolling="no" 
                            marginheight="0" 
                            marginwidth="0" 
                            src="https://maps.google.com/maps?q=${lat},${lng}&hl=en&z=15&output=embed">
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
            showConfirmButton: false,
            focusConfirm: false
        });
    }
    
    // Function to view attendance photo
    function viewAttendancePhoto(imageUrl) {
        Swal.fire({
            title: 'Attendance Photo',
            html: `
                <div class="attendance-photo-container">
                    <img src="${imageUrl}" alt="Attendance Photo" class="img-fluid">
                </div>
            `,
            width: '500px',
            showCloseButton: true,
            showConfirmButton: false,
            focusConfirm: false
        });
    }
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all map links
    const mapLinks = document.querySelectorAll('.map-link');
    
    // Cache location data in local storage
    function getLocationFromCache(lat, lng) {
        const cacheKey = `location_${lat}_${lng}`;
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            try {
                const data = JSON.parse(cached);
                // Cache expires after 30 days
                if (Date.now() - data.timestamp < 30 * 24 * 60 * 60 * 1000) {
                    return data.location;
                } else {
                    // Remove expired cache
                    localStorage.removeItem(cacheKey);
                }
            } catch (e) {
                // Handle old format or invalid data
                localStorage.removeItem(cacheKey);
            }
        }
        return null;
    }
    
    function saveLocationToCache(lat, lng, location) {
        if (location && location !== 'Location unavailable') {
            const cacheKey = `location_${lat}_${lng}`;
            const cacheData = {
                location: location,
                timestamp: Date.now()
            };
            localStorage.setItem(cacheKey, JSON.stringify(cacheData));
        }
    }
    
    // Process all locations automatically rather than on hover
    // To avoid rate limiting, add a slight delay between requests
    let delay = 0;
    const DELAY_INCREMENT = 500; // Half second to avoid rate limiting
    
    // Process each link
    mapLinks.forEach(function(link) {
        const lat = link.getAttribute('data-lat');
        const lng = link.getAttribute('data-lng');
        const locationText = link.querySelector('.location-text');
        const loadingIndicator = link.querySelector('.loading-indicator');
        
        // First check if we have this in cache
        const cachedLocation = getLocationFromCache(lat, lng);
        if (cachedLocation) {
            locationText.textContent = cachedLocation;
            loadingIndicator.style.display = 'none';
            return;
        }
        
        // Add a delay to avoid rate limiting
        setTimeout(function() {
            // Try both Nominatim and a fallback service
            tryNominatim(lat, lng, locationText, loadingIndicator);
        }, delay);
        
        // Increase delay for next request
        delay += DELAY_INCREMENT;
    });
    
    function tryNominatim(lat, lng, locationText, loadingIndicator) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10&addressdetails=1`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('API response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data && data.address) {
                    // Display city or town, or county if those aren't available
                    const location = data.address.city || data.address.town || 
                                    data.address.village || data.address.hamlet ||
                                    data.address.county || data.address.state;
                    if (location) {
                        locationText.textContent = location;
                        // Hide loading indicator after showing location
                        hideLoadingIndicator(loadingIndicator);
                        saveLocationToCache(lat, lng, location);
                    } else {
                        tryFallbackService(lat, lng, locationText, loadingIndicator);
                    }
                } else {
                    tryFallbackService(lat, lng, locationText, loadingIndicator);
                }
            })
            .catch(error => {
                tryFallbackService(lat, lng, locationText, loadingIndicator);
            });
    }
    
    function tryFallbackService(lat, lng, locationText, loadingIndicator) {
        // Try BigDataCloud as fallback (also free, different rate limits)
        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
            .then(response => response.json())
            .then(data => {
                if (data && (data.locality || data.city || data.principalSubdivision)) {
                    const location = data.locality || data.city || data.principalSubdivision;
                    locationText.textContent = location;
                    saveLocationToCache(lat, lng, location);
                } else {
                    locationText.textContent = 'View Location';
                }
                // Hide loading indicator
                hideLoadingIndicator(loadingIndicator);
            })
            .catch(error => {
                locationText.textContent = 'View Location';
                hideLoadingIndicator(loadingIndicator);
            });
    }
    
    // Function to properly hide the loading indicator
    function hideLoadingIndicator(indicator) {
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
});
</script>
@endsection

@push('styles')
<style>
/* Add this to your CSS */
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

/* Attendance photo thumbnail */
.attendance-thumbnail {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
    transition: transform 0.2s;
    cursor: pointer;
}

.attendance-thumbnail:hover {
    transform: scale(1.1);
}

/* Location Map in SweetAlert */
.location-map-container {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

/* Attendance Photo in SweetAlert */
.attendance-photo-container {
    text-align: center;
}

.attendance-photo-container img {
    max-height: 400px;
    max-width: 100%;
    border-radius: 8px;
}

/* Empty state styling */
.empty-state-icon {
    font-size: 2.5rem;
    color: #adb5bd;
}

/* Improve table on small screens */
@media (max-width: 768px) {
    .attendance-table th,
    .attendance-table td {
        padding: 0.5rem !important;
        font-size: 0.85rem;
    }
    
    .attendance-table .badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.5rem;
    }
    
    .attendance-thumbnail {
        width: 30px;
        height: 30px;
    }
}
</style>
@endpush

