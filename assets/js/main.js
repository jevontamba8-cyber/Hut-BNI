/**
 * BNI Anniversary Celebration - Main Global JS
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Bootstrap Tooltips if any
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // 2. Unlock AudioContext on first user click anywhere
    const unlockAudio = () => {
        if (window.soundEngine) {
            window.soundEngine.ensureContext();
        }
        document.removeEventListener('click', unlockAudio);
        document.removeEventListener('keydown', unlockAudio);
    };
    document.addEventListener('click', unlockAudio);
    document.addEventListener('keydown', unlockAudio);

    // 3. Auto dismiss alerts after 6 seconds
    const autoDismissAlerts = document.querySelectorAll('.alert-dismissible');
    autoDismissAlerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });
});
