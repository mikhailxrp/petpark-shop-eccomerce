// Алерты на всём сайте (публичная часть и админка): у каждого есть крестик
// закрытия, а уведомление само пропадает через AUTO_HIDE_MS. Постоянные плашки
// состояния («Демо…», «лимит ИИ») помечены data-alert-persist — крестик у них
// есть, автоскрытия нет. Алерты, которые JS-код показывает повторно
// (cart-message, booking-alert), подхватываются через MutationObserver.

const AUTO_HIDE_MS = 5000;
const HIDDEN_CLASS = 'd-none';
const CLOSE_LABEL = 'Закрыть';

const timers = new WeakMap();

const isVisible = (alert) => !alert.classList.contains(HIDDEN_CLASS);

const hide = (alert) => {
    window.clearTimeout(timers.get(alert));
    alert.classList.add(HIDDEN_CLASS);
};

// В админке (Valex) у .btn-close нет фона — крестик рисует иконка внутри.
const buildCloseButton = (alert) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn-close';
    button.setAttribute('aria-label', CLOSE_LABEL);

    if (alert.closest('.app-content')) {
        const icon = document.createElement('i');
        icon.className = 'fe fe-x';
        icon.setAttribute('aria-hidden', 'true');
        button.append(icon);
    }

    return button;
};

const ensureCloseButton = (alert) => {
    if (alert.querySelector('.btn-close')) {
        return;
    }

    alert.classList.add('alert-dismissible');
    const button = buildCloseButton(alert);
    button.addEventListener('click', () => hide(alert));
    alert.append(button);
};

const scheduleHide = (alert) => {
    window.clearTimeout(timers.get(alert));

    if (!isVisible(alert) || alert.dataset.alertPersist !== undefined) {
        return;
    }

    timers.set(alert, window.setTimeout(() => hide(alert), AUTO_HIDE_MS));
};

const enhance = (alert) => {
    ensureCloseButton(alert);
    scheduleHide(alert);
};

const init = () => {
    document.querySelectorAll('.alert').forEach(enhance);

    // Алерт показали/сменили текст из JS — заново запускаем отсчёт.
    new MutationObserver((mutations) => {
        const touched = new Set();
        mutations.forEach((mutation) => {
            const alert = mutation.target.closest?.('.alert') ?? mutation.target.parentElement?.closest('.alert');
            if (alert) {
                touched.add(alert);
            }
        });
        touched.forEach(enhance);
    }).observe(document.body, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['class'],
    });
};

init();
