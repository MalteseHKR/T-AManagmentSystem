<div class="session-timer" id="sessionTimer">
    <i class="fas fa-clock"></i>
    <span id="sessionTimeRemaining">--:--</span>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Session timeout in milliseconds (e.g., 30 minutes)
    const sessionTimeout = {{ config('session.lifetime') * 60 * 1000 }}; // Convert minutes to milliseconds
    
    // Warning threshold in milliseconds (e.g., 5 minutes)
    const warningThreshold = 5 * 60 * 1000; 
    
    // Update interval in milliseconds
    const updateInterval = 1000; 
    
    // Get the timer element
    const timerElement = document.getElementById('sessionTimeRemaining');
    const timerContainer = document.getElementById('sessionTimer');
    
    // Track session expiration
    let sessionExpiration = new Date().getTime() + sessionTimeout;
    
    // Initialize the timer
    updateTimer();
    
    // Set up the interval to update the timer
    const timerInterval = setInterval(updateTimer, updateInterval);
    
    // Function to update the timer
    function updateTimer() {
        const now = new Date().getTime();
        const timeLeft = sessionExpiration - now;
        
        if (timeLeft <= 0) {
            // Session has expired
            clearInterval(timerInterval);
            timerElement.textContent = "Expired";
            timerContainer.classList.add('session-warning');
            // You could redirect to login page or show a modal here
        } else {
            // Format and display the time remaining
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            // Add warning class if time is low
            if (timeLeft < warningThreshold) {
                timerContainer.classList.add('session-warning');
            } else {
                timerContainer.classList.remove('session-warning');
            }
        }
    }
    
    // Add event listeners to extend session when user is active
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    
    events.forEach(function(name) {
        document.addEventListener(name, extendSession, true);
    });
    
    function extendSession() {
        // Debounce to prevent excessive requests
        clearTimeout(window.sessionExtendTimer);
        window.sessionExtendTimer = setTimeout(function() {
            // Make an AJAX request to extend the session
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
                    // Reset the timer
                    sessionExpiration = new Date().getTime() + sessionTimeout;
                    updateTimer();
                }
            })
            .catch(error => {
                console.error('Failed to extend session:', error);
            });
        }, 1000); // Wait 1 second before making the request
    }
});
</script>
@endpush