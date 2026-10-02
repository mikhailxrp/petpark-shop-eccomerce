// Кнопка мессенджеров (FR-NOTIF-003): раскрывает список ссылок. Без скрипта
// список виден всегда (классы --js/--open ставит только этот файл).
const root = document.getElementById('messenger-button');
const toggle = document.getElementById('messenger-toggle');

if (root && toggle) {
    const setOpen = (open) => {
        root.classList.toggle('messenger-button--open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    root.classList.add('messenger-button--js');

    toggle.addEventListener('click', () => {
        setOpen(!root.classList.contains('messenger-button--open'));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('messenger-button--open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });
}
