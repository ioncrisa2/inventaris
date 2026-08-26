const peekButtonSelector = '[data-password-peek]';

const getPasswordInput = (button) => {
    const inputId = button.getAttribute('aria-controls');

    return (inputId ? document.getElementById(inputId) : null)
        ?? button.closest('[data-password-field]')?.querySelector('[data-password-input]');
};

const setPasswordVisibility = (button, isVisible) => {
    const input = getPasswordInput(button);

    if (!input) return;

    input.type = isVisible ? 'text' : 'password';
    button.classList.toggle('is-revealing', isVisible);
    button.setAttribute('aria-pressed', String(isVisible));
    button.setAttribute(
        'aria-label',
        isVisible
            ? 'Password terlihat. Lepaskan untuk menyembunyikan'
            : 'Tekan dan tahan untuk melihat password',
    );

    const hiddenIcon = button.querySelector('[data-password-hidden-icon]');
    const visibleIcon = button.querySelector('[data-password-visible-icon]');

    if (hiddenIcon) hiddenIcon.hidden = isVisible;
    if (visibleIcon) visibleIcon.hidden = !isVisible;
};

const hideAllPasswords = () => {
    document.querySelectorAll(peekButtonSelector).forEach((button) => {
        setPasswordVisibility(button, false);
    });
};

document.addEventListener('pointerdown', (event) => {
    const button = event.target.closest(peekButtonSelector);

    if (!button || button.disabled || (event.pointerType === 'mouse' && event.button !== 0)) return;

    event.preventDefault();
    setPasswordVisibility(button, true);

    if (button.setPointerCapture) {
        button.setPointerCapture(event.pointerId);
    }
});

document.addEventListener('pointerup', hideAllPasswords);
document.addEventListener('pointercancel', hideAllPasswords);
document.addEventListener('lostpointercapture', (event) => {
    const button = event.target.closest(peekButtonSelector);

    if (button) setPasswordVisibility(button, false);
});

document.addEventListener('keydown', (event) => {
    const button = event.target.closest(peekButtonSelector);

    if (!button || !['Enter', ' '].includes(event.key)) return;

    event.preventDefault();
    setPasswordVisibility(button, true);
});

document.addEventListener('keyup', (event) => {
    const button = event.target.closest(peekButtonSelector);

    if (button && ['Enter', ' '].includes(event.key)) {
        setPasswordVisibility(button, false);
    }
});

document.addEventListener('focusout', (event) => {
    const button = event.target.closest(peekButtonSelector);

    if (button) setPasswordVisibility(button, false);
});

document.addEventListener('contextmenu', (event) => {
    if (event.target.closest(peekButtonSelector)) event.preventDefault();
});

document.addEventListener('submit', hideAllPasswords);
document.addEventListener('visibilitychange', () => {
    if (document.hidden) hideAllPasswords();
});
window.addEventListener('blur', hideAllPasswords);
