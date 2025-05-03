@extends('layouts.app')

@section('title', 'Attendance - Garrison Time and Attendance System')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('show_navbar', true)

@section('content')


<script>
    function viewLocation(lat, lng) {
        alert(`Location: ${lat}, ${lng}`);
    }
</script>


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

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapLinks = document.querySelectorAll('.map-link');
    const DELAY_INCREMENT = 1000;
    let delay = 0;

    // Cache Helpers
    function getLocationFromCache(lat, lng) {
        const cacheKey = `location_${lat}_${lng}`;
        try {
            const cached = JSON.parse(localStorage.getItem(cacheKey));
            if (cached && Date.now() - cached.timestamp < 30 * 24 * 60 * 60 * 1000) {
                return cached.location;
            }
        } catch (e) {
            console.warn('Cache parse error:', e);
        }
        return null;
    }

    function saveLocationToCache(lat, lng, location) {
        const cacheKey = `location_${lat}_${lng}`;
        localStorage.setItem(cacheKey, JSON.stringify({ location, timestamp: Date.now() }));
    }

    function hideLoading(indicator) {
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    function updateLocationText(textEl, indicatorEl, location) {
        textEl.textContent = location;
        hideLoading(indicatorEl);
    }

    function tryNominatim(lat, lng, locationText, loadingIndicator) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10&addressdetails=1`;
        fetch(url, {
            headers: {
                'User-Agent': 'GarrisonTimeAttendanceBot/1.0 (admin@garrisonta.org)',
                'Accept-Language': 'en'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error(`Nominatim error: ${res.status}`);
            return res.json();
        })
        .then(data => {
            const loc = data.address?.city || data.address?.town || data.address?.village ||
                        data.address?.county || data.address?.state || 'Unknown';
            updateLocationText(locationText, loadingIndicator, loc);
            saveLocationToCache(lat, lng, loc);
        })
        .catch(err => {
            console.error('Nominatim failed:', err);
            tryFallback(lat, lng, locationText, loadingIndicator);
        });
    }

    function tryFallback(lat, lng, locationText, loadingIndicator) {
        const url = `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`;
        fetch(url)
        .then(res => res.json())
        .then(data => {
            const loc = data.locality || data.city || data.principalSubdivision || 'View Location';
            updateLocationText(locationText, loadingIndicator, loc);
            saveLocationToCache(lat, lng, loc);
        })
        .catch(err => {
            console.error('Fallback failed:', err);
            updateLocationText(locationText, loadingIndicator, 'View Location');
        });
    }

    mapLinks.forEach(link => {
        const lat = link.dataset.lat;
        const lng = link.dataset.lng;
        const locationText = link.querySelector('.location-text');
        const loadingIndicator = link.querySelector('.loading-indicator');

        if (!lat || !lng || !locationText) return;

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

    // SweetAlert for location
    window.viewLocation = function(lat, lng) {
        const cacheKey = `location_${lat}_${lng}`;
        let locationName = 'Unknown Location';

        try {
            const cached = JSON.parse(localStorage.getItem(cacheKey));
            if (cached && cached.location) {
                locationName = cached.location;
            }
        } catch {}

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
    };

    // SweetAlert for attendance photo
    window.viewAttendancePhoto = function(imageUrl) {
        Swal.fire({
            title: 'Attendance Photo',
            html: `<div class="attendance-photo-container"><img src="${imageUrl}" alt="Attendance Photo" class="img-fluid"></div>`,
            width: '500px',
            showCloseButton: true,
            showConfirmButton: false,
            focusConfirm: false
        });
    };

    // Session-based alerts
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

    // Refresh data
    const refreshBtn = document.getElementById('refresh-data');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Refreshing Data',
                text: 'Please wait while we refresh attendance records...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            setTimeout(() => window.location.reload(), 1000);
        });
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

