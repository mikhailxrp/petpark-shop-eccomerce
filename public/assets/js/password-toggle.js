(() => {
    const toggles = document.querySelectorAll('.password-toggle');
    if (toggles.length === 0) {
        return;
    }

    toggles.forEach((toggle) => {
        const input = document.getElementById(toggle.dataset.target);
        const icon = toggle.querySelector('i');
        if (!input || !icon) {
            return;
        }

        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
            toggle.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
            toggle.setAttribute('aria-pressed', String(show));
        });
    });
})();
