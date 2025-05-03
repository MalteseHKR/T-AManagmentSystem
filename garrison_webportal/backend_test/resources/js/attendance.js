document.addEventListener('DOMContentLoaded', function () {
    const mapLinks = document.querySelectorAll('.map-link');

    const DELAY_INCREMENT = 1000;
    let delay = 0;

    function getLocationFromCache(lat, lng) {
        const cacheKey = `location_${lat}_${lng}`;
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            try {
                const data = JSON.parse(cached);
                if (Date.now() - data.timestamp < 30 * 24 * 60 * 60 * 1000) {
                    return data.location;
                } else {
                    localStorage.removeItem(cacheKey);
                }
            } catch {
                localStorage.removeItem(cacheKey);
            }
        }
        return null;
    }

    function saveLocationToCache(lat, lng, location) {
        const cacheKey = `location_${lat}_${lng}`;
        const data = { location, timestamp: Date.now() };
        localStorage.setItem(cacheKey, JSON.stringify(data));
    }

    mapLinks.forEach(link => {
        const lat = link.dataset.lat;
        const lng = link.dataset.lng;
        const locationText = link.querySelector('.location-text');
        const loadingIndicator = link.querySelector('.loading-indicator');

        const cached = getLocationFromCache(lat, lng);
        if (cached) {
            locationText.textContent = cached;
            loadingIndicator.style.display = 'none';
            return;
        }

        setTimeout(() => {
            tryNominatim(lat, lng, locationText, loadingIndicator);
        }, delay);

        delay += DELAY_INCREMENT;
    });

    function tryNominatim(lat, lng, locationText, loadingIndicator) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10&addressdetails=1`, {
            headers: {
                'User-Agent': 'GarrisonTimeAttendanceBot/1.0 (admin@garrisonta.org)',
                'Accept-Language': 'en'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error(`Nominatim response error ${response.status}`);
                return response.json();
            })
            .then(data => {
                const location = data.address?.city || data.address?.town || data.address?.village ||
                    data.address?.county || data.address?.state || 'Unknown';
                locationText.textContent = location;
                hideLoadingIndicator(loadingIndicator);
                saveLocationToCache(lat, lng, location);
            })
            .catch(err => {
                console.error('Nominatim failed:', err);
                tryFallbackService(lat, lng, locationText, loadingIndicator);
            });
    }

    function tryFallbackService(lat, lng, locationText, loadingIndicator) {
        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
            .then(response => response.json())
            .then(data => {
                const location = data.locality || data.city || data.principalSubdivision || 'View Location';
                locationText.textContent = location;
                saveLocationToCache(lat, lng, location);
                hideLoadingIndicator(loadingIndicator);
            })
            .catch(err => {
                console.error('Fallback failed:', err);
                locationText.textContent = 'View Location';
                hideLoadingIndicator(loadingIndicator);
            });
    }

    function hideLoadingIndicator(indicator) {
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
});

// Optional: viewLocation and viewAttendancePhoto if used elsewhere
function viewLocation(lat, lng) {
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
    } catch (e) { }

    Swal.fire({
        title: 'Location Details',
        html: `
            <div class="location-details">
                <p class="mb-2"><strong>${locationName}</strong></p>
                <p class="mb-3 text-muted small">Coordinates: ${lat}, ${lng}</p>
                <div class="location-map-container">
                    <iframe width="100%" height="250" frameborder="0" scrolling="no" src="https://maps.google.com/maps?q=${lat},${lng}&hl=en&z=15&output=embed"></iframe>
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
