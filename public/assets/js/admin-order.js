// Правка Позиций в карточке Заказа: подтверждение удаления и предпросмотр
// итога (те же правила, что orderRecalculateTotals() на сервере — BR-006).
// Предпросмотр только подсказка: итог всегда пересчитывает сервер.

const KOPECKS_PER_RUBLE = 100;
const PRICE_PATTERN = /^\d{1,8}(?:\.\d{1,2})?$/;

const toKopecks = (value) => {
    const clean = value.replace(/\s/g, '').replace(',', '.');
    if (!PRICE_PATTERN.test(clean)) {
        return null;
    }
    const [rubles, fraction = ''] = clean.split('.');
    return Number(rubles) * KOPECKS_PER_RUBLE + Number(fraction.padEnd(2, '0'));
};

const formatKopecks = (kopecks) => {
    const rubles = Math.floor(kopecks / KOPECKS_PER_RUBLE);
    const fraction = kopecks % KOPECKS_PER_RUBLE;
    return fraction === 0 ? `${rubles} ₽` : `${rubles},${String(fraction).padStart(2, '0')} ₽`;
};

const calcDelivery = (table, subtotal) => {
    if (table.dataset.deliveryMethod !== 'courier') {
        return 0;
    }
    const threshold = toKopecks(table.dataset.freeThreshold);
    return subtotal >= threshold ? 0 : toKopecks(table.dataset.courierCost);
};

const updatePreview = (table, preview) => {
    let subtotal = 0;

    for (const row of table.querySelectorAll('tbody tr')) {
        const price = toKopecks(row.querySelector('[data-item-price]')?.value ?? '');
        const quantity = Number(row.querySelector('[data-item-quantity]')?.value);
        if (price === null || !Number.isInteger(quantity) || quantity < 1) {
            preview.textContent = '—';
            return;
        }
        subtotal += price * quantity;
    }

    preview.textContent = formatKopecks(subtotal + calcDelivery(table, subtotal));
};

const init = () => {
    const table = document.getElementById('order-items-table');
    const preview = document.getElementById('order-total-preview');
    if (!table || !preview) {
        return;
    }

    table.addEventListener('input', () => updatePreview(table, preview));

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-confirm]');
        if (button && !window.confirm(button.dataset.confirm)) {
            event.preventDefault();
        }
    });
};

init();
