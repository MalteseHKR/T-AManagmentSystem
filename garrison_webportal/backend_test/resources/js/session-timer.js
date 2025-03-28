class SessionTimer {
    constructor() {
        this.timerElement = document.getElementById('session-timer');
        this.timer = null;
        this.startTime = new Date(this.timerElement.dataset.loginTime).getTime();
        this.sessionDuration = 60 * 60; // 1 hour in seconds
        this.init();
    }

    init() {
        if (this.timerElement) {
            this.startTimer();
            console.log('Timer initialized with login time:', new Date(this.startTime));
        }
    }

    startTimer() {
        this.updateDisplay(); // Initial display
        this.timer = setInterval(() => {
            const elapsedSeconds = Math.floor((Date.now() - this.startTime) / 1000);
            this.timeLeft = this.sessionDuration - elapsedSeconds;
            
            if (this.timeLeft <= 0) {
                clearInterval(this.timer);
                this.timeLeft = 0;
                window.location.href = '/logout'; // Redirect to logout when session expires
            }
            
            this.updateDisplay();
        }, 1000);
    }

    updateDisplay() {
        if (!this.timerElement) return;

        const minutes = Math.floor(this.timeLeft / 60);
        const seconds = this.timeLeft % 60;
        const display = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        
        this.timerElement.textContent = display;
        console.log('Timer updated:', display); // Debug log
        
        // Update badge colors based on time remaining
        const badge = this.timerElement.closest('.badge');
        if (badge) {
            badge.classList.remove('bg-info', 'bg-warning', 'bg-danger');
            if (this.timeLeft <= 900) { // 15 minutes
                badge.classList.add('bg-danger');
            } else if (this.timeLeft <= 1800) { // 30 minutes
                badge.classList.add('bg-warning');
            } else {
                badge.classList.add('bg-info');
            }
        }
    }

    resetTimer() {
        this.startTime = Date.now();
        this.timeLeft = 60 * 60;
        this.updateDisplay();
    }
}

// Initialize timer when document is ready
document.addEventListener('DOMContentLoaded', () => {
    const sessionTimer = new SessionTimer();
    // Make timer accessible globally for debugging
    window.sessionTimer = sessionTimer;
});