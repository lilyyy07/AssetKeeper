'use strict';
const passwordToggle = document.querySelector('.password-toggle');
if (passwordToggle) {
    passwordToggle.hidden = false;
    passwordToggle.addEventListener('click', () => {
        const input = document.getElementById('password');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', String(show));
        passwordToggle.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
}
