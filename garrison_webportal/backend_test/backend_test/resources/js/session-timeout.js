class SessionTimeout {
    constructor() {
        this.warningTimeout = 45 * 60 * 1000; // 45 minutes
        this.finalTimeout = 60 * 60 * 1000; // 1 hour
        this.graceTimeout = 15 * 60 * 1000; // 15 minutes grace period
        this.warningDialog = $('#session-timeout-dialog');
        this.warningTimer = null;
        this.graceTimer = null;
        this.init();
    }

    init() {
        this.startWarningTimer();
        this.setupEventListeners();
    }

    startWarningTimer() {
        this.warningTimer = setTimeout(() => {
            this.showWarning();
        }, this.warningTimeout);
    }

    showWarning() {
        this.warningDialog.modal('show');
        this.startGraceTimer();
    }

    startGraceTimer() {
        let timeLeft = 15;
        const timeLeftElement = $('#session-time-left');
        
        this.graceTimer = setInterval(() => {
            timeLeft--;
            timeLeftElement.text(timeLeft);
            
            if (timeLeft <= 0) {
                this.endSession();
            }
        }, 60000); // Update every minute
    }

    extendSession() {
        $.ajax({
            url: '/extend-session',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: () => {
                this.resetTimers();
                this.warningDialog.modal('hide');
            }
        });
    }

    endSession() {
        window.location.href = '/logout';
    }

    resetTimers() {
        clearTimeout(this.warningTimer);
        clearInterval(this.graceTimer);
        this.startWarningTimer();
    }

    setupEventListeners() {
        $('#extend-session').on('click', () => this.extendSession());
        this.warningDialog.on('hidden.bs.modal', () => this.endSession());
    }
}

$(document).ready(() => {
    new SessionTimeout();
});