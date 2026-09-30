// Форма ручного Заказа: поиск Варианта, строки состава, предпросмотр итога
// (те же правила, что orderRecalculateTotals() на сервере — BR-006).
// Предпросмотр только подсказка: цены, остаток и итог проверяет сервер.

const KOPECKS_PER_RUBLE = 100;
const SEARCH_DELAY_MS = 400;
const SEARCH_MIN_LENGTH = 2;
const PRICE_PATTERN = /^\d{1,8}(?:\.\d{1,2})?$/;

const toKopecks = (value) => {
    const clean = String(value).replace(/\s/g, '').replace(',', '.');
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

const createElement = (tag, { className = '', text = '', attrs = {} } = {}) => {
    const element = document.createElement(tag);
    element.className = className;
    element.textContent = text;
    for (const [name, value] of Object.entries(attrs)) {
        element.setAttribute(name, value);
    }
    return element;
};

const init = () => {
    const form = document.getElementById('order-create-form');
    if (!form) {
        return;
    }

    const tbody = form.querySelector('#order-lines-table tbody');
    const emptyHint = form.querySelector('#order-lines-empty');
    const totalPreview = form.querySelector('#order-total-preview');
    const deliveryPreview = form.querySelector('#order-delivery-preview');
    const searchInput = form.querySelector('#variant-search');
    const results = form.querySelector('#variant-search-results');
    const address = form.querySelector('#order-create-address');
    const maxQuantity = Number(form.dataset.maxQuantity);
    const freeThreshold = toKopecks(form.dataset.freeThreshold);
    const courierCost = toKopecks(form.dataset.courierCost);

    let rowIndex = 0;
    let searchTimer = null;
    let searchController = null;

    const isCourier = () => form.elements.delivery_method.value === 'courier';

    const rows = () => [...tbody.querySelectorAll('tr')];

    const updateTotals = () => {
        let subtotal = 0;
        let valid = rows().length > 0;

        for (const row of rows()) {
            const price = Number(row.dataset.priceKopecks);
            const quantity = Number(row.querySelector('[data-quantity]').value);
            const lineTotal = row.querySelector('[data-line-total]');

            if (!Number.isInteger(quantity) || quantity < 1 || quantity > maxQuantity) {
                lineTotal.textContent = '—';
                valid = false;
                continue;
            }
            lineTotal.textContent = formatKopecks(price * quantity);
            subtotal += price * quantity;
        }

        emptyHint.classList.toggle('d-none', rows().length > 0);

        if (!valid) {
            deliveryPreview.textContent = '—';
            totalPreview.textContent = '—';
            return;
        }

        const delivery = isCourier() && subtotal < freeThreshold ? courierCost : 0;
        deliveryPreview.textContent = formatKopecks(delivery);
        totalPreview.textContent = formatKopecks(subtotal + delivery);
    };

    const addRow = ({ variant_id: variantId, name, sku, price, quantity = 1 }) => {
        const existing = tbody.querySelector(`tr[data-variant-id="${variantId}"] [data-quantity]`);
        if (existing) {
            existing.value = Math.min(Number(existing.value) + quantity, maxQuantity);
            updateTotals();
            return;
        }

        const index = rowIndex++;
        const row = createElement('tr', { attrs: { 'data-variant-id': variantId } });
        row.dataset.priceKopecks = String(toKopecks(price) ?? 0);

        const title = createElement('td', { text: name });
        title.append(createElement('div', { className: 'text-muted fs-12', text: sku }));
        title.append(createElement('input', { attrs: { type: 'hidden', name: `items[${index}][variant_id]`, value: variantId } }));

        const quantityCell = createElement('td');
        quantityCell.append(createElement('input', {
            className: 'form-control form-control-sm',
            attrs: {
                type: 'number',
                name: `items[${index}][quantity]`,
                min: '1',
                max: String(maxQuantity),
                value: String(quantity),
                'aria-label': `Количество: ${name}`,
                'data-quantity': '',
                required: '',
            },
        }));

        const removeButton = createElement('button', {
            className: 'btn btn-sm btn-outline-danger',
            text: 'Удалить',
            attrs: { type: 'button', 'data-remove': '' },
        });
        const actionCell = createElement('td');
        actionCell.append(removeButton);

        row.append(
            title,
            createElement('td', { text: formatKopecks(toKopecks(price) ?? 0) }),
            quantityCell,
            createElement('td', { attrs: { 'data-line-total': '' } }),
            actionCell,
        );
        tbody.append(row);
        updateTotals();
    };

    const hideResults = () => {
        results.classList.add('d-none');
        results.replaceChildren();
    };

    const showResults = (variants) => {
        results.replaceChildren();

        if (variants.length === 0) {
            results.append(createElement('li', { className: 'list-group-item text-muted', text: 'Ничего не найдено' }));
        }

        for (const variant of variants) {
            const label = variant.label !== variant.sku ? `${variant.name}, ${variant.label}` : variant.name;
            const item = createElement('li', { attrs: { role: 'option' } });
            const button = createElement('button', {
                className: 'list-group-item list-group-item-action',
                text: `${label} · ${variant.sku} · ${formatKopecks(toKopecks(variant.price) ?? 0)} · в наличии: ${variant.available}`,
                attrs: { type: 'button' },
            });
            button.disabled = variant.available < 1;
            button.addEventListener('click', () => {
                addRow({ ...variant, name: label });
                searchInput.value = '';
                hideResults();
                searchInput.focus();
            });
            item.append(button);
            results.append(item);
        }

        results.classList.remove('d-none');
    };

    const search = async (query) => {
        // Новый запрос отменяет предыдущий: БД может отвечать медленно, и
        // очередь устаревших запросов подвесила бы страницу.
        searchController?.abort();
        const controller = new AbortController();
        searchController = controller;

        try {
            const response = await fetch(`/admin/orders/variants?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            showResults(data.variants);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            results.replaceChildren(createElement('li', { className: 'list-group-item text-danger', text: 'Не удалось выполнить поиск' }));
            results.classList.remove('d-none');
        }
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < SEARCH_MIN_LENGTH) {
            searchController?.abort();
            hideResults();
            return;
        }
        searchTimer = setTimeout(() => search(query), SEARCH_DELAY_MS);
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    document.addEventListener('click', (event) => {
        if (!results.contains(event.target) && event.target !== searchInput) {
            hideResults();
        }
    });

    tbody.addEventListener('input', updateTotals);
    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove]');
        if (button) {
            button.closest('tr').remove();
            updateTotals();
        }
    });

    const toggleAddress = () => {
        const courier = isCourier();
        address.classList.toggle('d-none', !courier);
        for (const input of address.querySelectorAll('input')) {
            input.disabled = !courier;
        }
        for (const name of ['delivery_street', 'delivery_house']) {
            form.elements[name].required = courier;
        }
        updateTotals();
    };

    for (const radio of form.elements.delivery_method) {
        radio.addEventListener('change', toggleAddress);
    }

    form.addEventListener('submit', (event) => {
        if (rows().length === 0 || !form.checkValidity()) {
            event.preventDefault();
            emptyHint.classList.toggle('d-none', rows().length > 0);
            form.classList.add('was-validated');
        }
    });

    try {
        for (const line of JSON.parse(form.dataset.initialLines || '[]')) {
            addRow(line);
        }
    } catch {
        // Повреждённые данные предыдущего ввода — начинаем с пустого состава.
    }
    updateTotals();
};

init();
