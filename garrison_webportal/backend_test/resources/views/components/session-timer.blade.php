<div class="dropdown session-timer-dropdown">
    <button class="btn btn-outline-light session-timer-btn" type="button" id="sessionTimerDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-clock me-1"></i>
        <span id="session-timer-display">--:--</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sessionTimerDropdown">
        <li>
            <div class="px-3 py-2">
                <h6 class="mb-2">Session Status</h6>
                <p class="mb-2 small">Your session will expire in: <br>
                   <strong id="session-timer-minutes-display">--:--</strong>
                </p>
                <button type="button" id="extend-session-btn" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-plus-circle me-1"></i> Extend Session
                </button>
            </div>
        </li>
    </ul>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store CSRF token in a variable to ensure it's available for AJAX requests
    const csrfToken = '{{ csrf_token() }}';

    // DEBUG: Console logs to troubleshoot
    console.log('Session timer initializing...');

    // Use localStorage to persist session expiry time across pages
    let sessionExpiry = localStorage.getItem('sessionExpiry');
    
    console.log('Stored session expiry:', sessionExpiry ? new Date(parseInt(sessionExpiry)).toLocaleTimeString() : 'None');
    
    // If no session expiry is stored or it's in the past, set a new one
    if (!sessionExpiry || new Date(parseInt(sessionExpiry)) < new Date()) {
        // Default session timeout: {{ config('session.lifetime', 120) * 60 * 1000 }} milliseconds
        sessionExpiry = Date.now() + {{ config('session.lifetime', 120) * 60 * 1000 }};
        localStorage.setItem('sessionExpiry', sessionExpiry);
        console.log('Set new session expiry:', new Date(parseInt(sessionExpiry)).toLocaleTimeString());
    }
    
    function updateSessionTimer() {
        const now = Date.now();
        const expiry = parseInt(localStorage.getItem('sessionExpiry'));
        let timeLeft = expiry - now;
        
        console.log('Time left:', Math.floor(timeLeft / 1000), 'seconds');
        
        if (timeLeft <= 0) {
            // Session has expired
            document.getElementById('session-timer-display').textContent = "00:00";
            document.getElementById('session-timer-minutes-display').textContent = "Expired";
            document.getElementById('session-timer-display').classList.add('text-danger');
            
            // Create and submit a logout form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('logout') }}";
            form.style.display = 'none';
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            
            form.appendChild(csrfToken);
            document.body.appendChild(form);
            form.submit();
            
            return;
        }
        
        // Format remaining time
        const minutes = Math.floor(timeLeft / 60000);
        const seconds = Math.floor((timeLeft % 60000) / 1000);
        
        const formattedTime = 
            (minutes < 10 ? '0' : '') + minutes + ':' + 
            (seconds < 10 ? '0' : '') + seconds;
            
        const timerDisplay = document.getElementById('session-timer-display');
        const minutesDisplay = document.getElementById('session-timer-minutes-display');
        const timerBtn = document.getElementById('sessionTimerDropdown');
        
        if (timerDisplay) timerDisplay.textContent = formattedTime;
        if (minutesDisplay) minutesDisplay.textContent = formattedTime;
        
        // Warning when less than 5 minutes left
        if (minutes < 5) {
            if (timerDisplay) timerDisplay.classList.add('text-warning');
            if (timerBtn) {
                timerBtn.classList.add('btn-warning');
                timerBtn.classList.remove('btn-outline-light');
            }
        } else {
            if (timerDisplay) timerDisplay.classList.remove('text-warning');
            if (timerBtn) {
                timerBtn.classList.remove('btn-warning');
                timerBtn.classList.add('btn-outline-light');
            }
        }
    }
    
    // Make sure updateSessionTimer is called regularly
    updateSessionTimer(); // Call once immediately
    
    // Use a more robust setInterval approach
    let timerInterval;
    
    function startTimer() {
        // Clear any existing interval first
        if (timerInterval) clearInterval(timerInterval);
        
        // Set a new interval
        timerInterval = setInterval(function() {
            updateSessionTimer();
        }, 1000);
        
        console.log('Timer started');
    }
    
    // Start the timer
    startTimer();
    
    // Clean up interval when page is unloaded
    window.addEventListener('beforeunload', function() {
        if (timerInterval) clearInterval(timerInterval);
        console.log('Timer cleared');
    });
    
    // Add event listener for the extend session button
    const extendBtn = document.getElementById('extend-session-btn');
    if (extendBtn) {
        extendBtn.addEventListener('click', function() {
            console.log('Extending session...');
            
            // Make AJAX call to extend session
            fetch('{{ route("session.extend") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Session extension response:', data);
                
                if (data.success) {
                    // Update local storage with new expiry time
                    const newExpiry = Date.now() + 60 * 60 * 1000; // 1 hour in milliseconds
                    localStorage.setItem('sessionExpiry', newExpiry);
                    console.log('New session expiry set to:', new Date(newExpiry).toLocaleTimeString());
                    
                    // Update UI
                    updateSessionTimer();
                    
                    // Show success message
                    const dropdown = document.getElementById('sessionTimerDropdown');
                    if (dropdown) {
                        const bootstrapDropdown = bootstrap.Dropdown.getInstance(dropdown);
                        if (bootstrapDropdown) bootstrapDropdown.hide();
                    }
                    
                    // Show toaster notification
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed top-0 end-0 p-3 session-toast';
                    toast.style.zIndex = '1050';
                    toast.innerHTML = `
                        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="toast-header bg-success text-white">
                                <strong class="me-auto">Success</strong>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                Your session has been extended by 1 hour.
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    // Add event listener for close button
                    const closeBtn = toast.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function() {
                            toast.remove();
                        });
                    }
                    
                    setTimeout(() => {
                        if (document.body.contains(toast)) {
                            toast.remove();
                        }
                    }, 3000);
                } else {
                    console.error('Failed to extend session:', data.message);
                    alert('Failed to extend session. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error extending session:', error);
                alert('An error occurred while extending your session.');
            });
        });
    } else {
        console.error('Extend session button not found');
    }

    // Add this near the start of your script
    let idleTime = 0;
    const idleInterval = setInterval(incrementIdleTime, 60000); // Check every minute

    function incrementIdleTime() {
        idleTime += 1;
        console.log('User idle for', idleTime, 'minutes');
        
        // If user has been idle for more than 30 minutes, don't auto-extend
        if (idleTime > 30) {
            console.log('User inactive - not extending session');
        }
    }

    // Reset the idle timer on user activity
    function resetIdleTime() {
        idleTime = 0;
    }

    // Add event listeners for user activity
    const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    activityEvents.forEach(event => {
        document.addEventListener(event, resetIdleTime, true);
    });
});
</script>

<style>
.session-timer-dropdown .btn {
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
}

.session-timer-dropdown .dropdown-menu {
    min-width: 240px;
}

.text-warning {
    color: #ffc107 !important;
}

.btn-warning {
    background-color:rgb(191, 4, 4);
    border-color: rgb(191, 4, 4);
    color: #212529;
}

.session-toast {
    z-index: 1050;
    top: 70px;
    right: 20px;
}

/* Add animation for visual feedback */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.btn-warning.session-timer-btn {
    animation: pulse 2s infinite;
}
</style>
@endpush