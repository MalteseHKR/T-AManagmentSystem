@extends('layouts.app')

@section('title', 'Attendance - Garrison Time and Attendance System')

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
                                                <span class="location-text"> </span>
                                                <small class="d-block mt-1 loading-indicator">
                                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                                </small>
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-muted">No location</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($attendance->photo_url)
                                        @php
                                            // Remove redundant uploads prefix if present
                                            $photoPath = $attendance->photo_url;
                                            if (strpos($photoPath, 'uploads/') === 0) {
                                                $photoPath = substr($photoPath, strlen('uploads/'));
                                            }
                                        @endphp
                                        <a href="{{ route('images.show', ['filename' => $photoPath]) }}" 
                                           target="_blank" class="text-primary">
                                            <img src="{{ route('images.show', ['filename' => $photoPath]) }}" 
                                                 alt="Attendance photo" class="attendance-thumbnail"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                                 onerror="this.src='{{ route('images.placeholder') }}'; this.onerror=null;">
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
        if (cached && cached !== 'Location unavailable') {
            return cached;
        }
        return null;
    }
    
    function saveLocationToCache(lat, lng, location) {
        if (location && location !== 'Location unavailable') {
            const cacheKey = `location_${lat}_${lng}`;
            localStorage.setItem(cacheKey, location);
        }
    }
    
    // To avoid rate limiting, add a slight delay between requests
    let delay = 0;
    const DELAY_INCREMENT = 1000; // 1 second to avoid rate limiting
    
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
                        loadingIndicator.style.display = 'none';
                        saveLocationToCache(lat, lng, location);
                    } else {
                        tryFallbackService(lat, lng, locationText, loadingIndicator);
                    }
                } else {
                    tryFallbackService(lat, lng, locationText, loadingIndicator);
                }
            })
            .catch(error => {
                console.error('Error fetching location from Nominatim:', error);
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
                loadingIndicator.style.display = 'none';
            })
            .catch(error => {
                console.error('Error fetching location from fallback:', error);
                locationText.textContent = 'View Location';
                loadingIndicator.style.display = 'none';
            });
    }
});
</script>
@endpush