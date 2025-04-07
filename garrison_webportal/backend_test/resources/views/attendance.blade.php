@extends('layouts.app')

@section('title', 'Attendance - Garrison Time and Attendance System')

@section('show_navbar', true)

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Attendance Records</h1>
        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary me-3">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <a href="{{ route('attendance.analytics') }}" class="btn-analytics">
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

    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white py-4">
            <h4 class="card-title mb-0 fw-bold">
                <i class="fas fa-clock me-2 fa-lg"></i> - Attendance List
            </h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 w-100">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3" style="width: 20%">Employee</th>
                            <th class="px-4 py-3" style="width: 15%">Date</th>
                            <th class="px-4 py-3" style="width: 10%">Time</th>
                            <th class="px-4 py-3" style="width: 15%">Punch Type</th>
                            <th class="px-4 py-3" style="width: 15%">Device</th>
                            <th class="px-4 py-3" style="width: 15%">Location</th>
                            <th class="px-4 py-3" style="width: 10%">Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td class="px-4 py-3 fw-semibold align-middle">
                                    {{ optional($attendance->userInformation)->user_name }} 
                                    {{ optional($attendance->userInformation)->user_surname }}
                                </td>
                                <td class="px-4 py-3">{{ $attendance->punch_date }}</td>
                                <td class="px-4 py-3">{{ $attendance->punch_time }}</td>
                                <td class="px-4 py-3">
                                    @if($attendance->punch_type == 'IN')
                                        <span class="badge bg-success">Clock In</span>
                                    @elseif($attendance->punch_type == 'OUT')
                                        <span class="badge bg-danger">Clock Out</span>
                                    @else
                                        <span class="badge bg-info">{{ $attendance->punch_type }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ optional($attendance->device)->device_name ?? 'Device #'.$attendance->device_id }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($attendance->latitude && $attendance->longitude)
                                        <div>
                                            <a href="https://www.google.com/maps?q={{ $attendance->latitude }},{{ $attendance->longitude }}" 
                                               target="_blank" class="text-primary map-link"
                                               data-lat="{{ $attendance->latitude }}" 
                                               data-lng="{{ $attendance->longitude }}">
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
                                <td class="px-4 py-3">
                                    @if($attendance->photo_url)
@php
    // Get the filename from the database
    $photoFilename = basename($attendance->photo_url ?? '');

    // Ensure correct formatting (remove unwanted characters)
    $photoFilename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $photoFilename);

    // Secure image URL (only logged-in users can access)
    $imageUrl = route('secure-image', ['filename' => $photoFilename]);
@endphp

<a href="{{ $imageUrl }}" target="_blank" class="text-primary">
    <img src="{{ $imageUrl }}"
         alt="Attendance photo"
         class="attendance-thumbnail"
         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
         onerror="this.onerror=null; this.src='{{ asset('/uploads/placeholder.jpg') }}';">
</a>
                                    @else
                                        <span class="text-muted">No photo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                    No attendance records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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

@push('scripts')
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

<script>
// Use a function that executes after the session timer is running
window.addEventListener('load', function() {
    // We'll use requestIdleCallback or setTimeout to defer non-critical operations
    (window.requestIdleCallback || setTimeout)(function() {
        initializeLocationLoading();
    }, 100); // short delay to ensure session timer runs first
    
    function initializeLocationLoading() {
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
        let delay = 100; // Start with a small initial delay
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
                hideLoadingIndicator(loadingIndicator);
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
    }
});
</script>
@endpush

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

/* Certificate modal styling */
.certificate-container {
    max-height: 70vh;
    overflow-y: auto;
    text-align: center;
}

.certificate-container img {
    max-width: 100%;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

/* View certificate button styling */
.view-certificate {
    white-space: nowrap;
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

/* Improve modal responsiveness */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
}
</style>
@endpush

@section('head')
    <!-- High-priority session timer initialization -->
    <script>
        // Session timer pre-initialization - runs before page fully loads
        document.addEventListener('DOMContentLoaded', function() {
            const sessionTimerElement = document.getElementById('session-timer-display');
            if (sessionTimerElement) {
                // Get session expiry from localStorage
                let sessionExpiry = localStorage.getItem('sessionExpiry');
                
                // If no session expiry is stored or it's in the past, set a new one
                if (!sessionExpiry || new Date(parseInt(sessionExpiry)) < new Date()) {
                    // Default session timeout in milliseconds (typical Laravel session is 2 hours)
                    sessionExpiry = Date.now() + {{ config('session.lifetime', 120) * 60 * 1000 }};
                    localStorage.setItem('sessionExpiry', sessionExpiry);
                }
                
                // Update timer immediately
                updateSessionTimer();
                
                // Start timer interval
                setInterval(updateSessionTimer, 1000);
                
                function updateSessionTimer() {
                    const now = Date.now();
                    const expiry = parseInt(localStorage.getItem('sessionExpiry'));
                    let timeLeft = expiry - now;
                    
                    if (timeLeft <= 0) {
                        // Session has expired
                        sessionTimerElement.textContent = "00:00";
                        return;
                    }
                    
                    // Format remaining time
                    const minutes = Math.floor(timeLeft / 60000);
                    const seconds = Math.floor((timeLeft % 60000) / 1000);
                    
                    const formattedTime = 
                        (minutes < 10 ? '0' : '') + minutes + ':' + 
                        (seconds < 10 ? '0' : '') + seconds;
                        
                    sessionTimerElement.textContent = formattedTime;
                }
            }
        });
    </script>
@endsection

