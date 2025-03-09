<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-labelledby="sessionTimeoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="sessionTimeoutModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Session Expiring Soon
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Your session is about to expire due to inactivity.</p>
                <p class="mb-0">You will be logged out in <span id="modalCountdown" class="fw-bold">30</span> seconds.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
                <button type="button" id="extendSessionBtn" class="btn btn-primary">
                    <i class="fas fa-clock me-1"></i>
                    Stay Logged In
                </button>
                <a href="{{ route('logout') }}" class="btn btn-danger" 
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    Logout Now
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Session timeout in milliseconds (30 minutes by default)
    const sessionTimeout = {{ config('session.lifetime') * 60 * 1000 }}; // Convert minutes to milliseconds
    
    // Warning threshold in milliseconds (5 minutes before expiry)
    const warningThreshold = 5 * 60 * 1000;
    
    // Countdown interval in milliseconds
    const countdownInterval = 1000;
    
    // Modal countdown time in seconds
    const modalCountdownTime = 30;
    
    // Get modal elements
    const timeoutModal = new bootstrap.Modal(document.getElementById('sessionTimeoutModal'), {
        backdrop: 'static',
        keyboard: false
    });
    const countdownElement = document.getElementById('modalCountdown');
    const extendSessionBtn = document.getElementById('extendSessionBtn');
    
    // Track session expiration
    let sessionExpiration = new Date().getTime() + sessionTimeout;
    let modalShown = false;
    let countdownTimer;
    let modalCountdown;
    
    // Check for session timeout
    function checkSessionTimeout() {
        const now = new Date().getTime();
        const timeLeft = sessionExpiration - now;
        
        if (timeLeft <= warningThreshold && !modalShown) {
            showTimeoutWarning();
        }
    }
    
    // Set interval to check session timeout
    setInterval(checkSessionTimeout, 30000); // Check every 30 seconds
    
    // Show timeout warning modal
    function showTimeoutWarning() {
        modalShown = true;
        timeoutModal.show();
        
        // Start modal countdown
        let secondsLeft = modalCountdownTime;
        countdownElement.textContent = secondsLeft;
        
        modalCountdown = setInterval(function() {
            secondsLeft--;
            countdownElement.textContent = secondsLeft;
            
            if (secondsLeft <= 0) {
                clearInterval(modalCountdown);
                // Log the user out
                document.getElementById('logout-form').submit();
            }
        }, countdownInterval);
    }
    
    // Extend session button click
    extendSessionBtn.addEventListener('click', function() {
        // Clear the modal countdown
        clearInterval(modalCountdown);
        
        // Make AJAX call to extend session
        fetch('{{ route("extend-session") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.ok) {
                // Reset session expiration
                sessionExpiration = new Date().getTime() + sessionTimeout;
                modalShown = false;
                timeoutModal.hide();
            }
        })
        .catch(error => {
            console.error('Failed to extend session:', error);
        });
    });
    
    // Hide the modal and clear countdown when dismissed
    document.getElementById('sessionTimeoutModal').addEventListener('hidden.bs.modal', function() {
        clearInterval(modalCountdown);
        modalShown = false;
    });
});
</script>
@endpush