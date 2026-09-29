(() => {
    const page = document.querySelector('.cart-page');

    if (!page) {
        return;
    }

    const message = document.getElementById('cart-message');
    const messageText = message?.querySelector('.cart-message__text');

    // Без JS количество отправляется кнопкой «Обновить»; с JS — сразу по
    // изменению поля, кнопка не нужна.
    page.querySelectorAll('.cart-item__update').forEach((button) => button.classList.add('d-none'));

    const showMessage = (text, isError) => {
        if (!message || !messageText) {
            return;
        }
        messageText.textContent = text ?? '';
        message.classList.toggle('d-none', !text);
        message.classList.toggle('alert-danger', Boolean(isError));
        message.classList.toggle('alert-info', !isError);
    };

    // Крестик — просто прячет алерт (d-none), не data-bs-dismiss: тот
    // удаляет сам узел из DOM, а showMessage() переиспользует его для
    // следующего уведомления (см. комментарий в cart.php).
    document.getElementById('cart-message-close')?.addEventListener('click', () => {
        message?.classList.add('d-none');
    });

    // Все суммы и количества приходят готовыми из JSON сервера
    // (CartController::respond(), пересчёт из product_variants) — здесь
    // только подстановка в DOM, никаких расчётов на клиенте.
    const applyCart = (data) => {
        const itemsById = new Map(data.items.map((item) => [String(item.id), item]));

        page.querySelectorAll('.cart-item').forEach((row) => {
            const item = itemsById.get(row.dataset.itemId);
            if (!item) {
                row.remove();
                return;
            }
            const quantityInput = row.querySelector('.cart-item__quantity');
            if (quantityInput) {
                quantityInput.value = String(item.quantity);
            }
            const lineTotal = row.querySelector('.cart-item__line-total');
            if (lineTotal) {
                lineTotal.textContent = item.lineTotal;
            }
            row.querySelector('.cart-item__warning')?.remove();
        });

        const subtotal = document.getElementById('cart-subtotal');
        if (subtotal) {
            subtotal.textContent = data.subtotal;
        }

        if (data.isEmpty) {
            document.getElementById('cart-content')?.remove();
            document.getElementById('cart-empty')?.classList.remove('d-none');
        }

        showMessage(data.error ?? data.warning, Boolean(data.error));
    };

    const sendForm = async (form) => {
        let data;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            data = await response.json();
        } catch {
            // Сеть/не-JSON (например, 419 после истёкшего CSRF) — обычная
            // отправка формы, сервер сам покажет результат.
            HTMLFormElement.prototype.submit.call(form);
            return;
        }

        applyCart(data);
    };

    page.addEventListener('submit', (event) => {
        const form = event.target.closest('.cart-item__remove-form, .cart-item__quantity-form');
        if (!form) {
            return;
        }
        event.preventDefault();
        sendForm(form);
    });

    page.addEventListener('change', (event) => {
        if (event.target.classList.contains('cart-item__quantity')) {
            sendForm(event.target.form);
        }
    });
})();

(() => {
    // Мини-корзина в шапке (components/header.php) — на каждой странице
    // сайта, не только на /cart (FR-CART-007). Перехватывает submit любой
    // формы «В корзину» (карточка листинга, страница товара — обе шлют
    // POST на /cart/add) и обновляет счётчик/попап без перезагрузки.
    const countEl = document.getElementById('cart-count');
    const emptyEl = document.getElementById('cart-popup-empty');
    const subtotalEl = document.getElementById('cart-popup-subtotal');
    const popupItems = document.getElementById('cart-popup-items');
    const widgetToggle = document.getElementById('cart-widget-toggle');
    const widget = widgetToggle?.closest('.cart-widget');

    // Открытие/закрытие попапа — свой toggle, не Bootstrap Dropdown: тот
    // требует Popper, а в проекте подключён bootstrap.min.js без него
    // (см. комментарий у .cart-widget в petpark.css).
    const closeWidget = () => {
        if (!widget) {
            return;
        }
        widget.classList.remove('cart-widget--open');
        widgetToggle?.setAttribute('aria-expanded', 'false');
    };

    if (widget && widgetToggle) {
        widgetToggle.addEventListener('click', (event) => {
            event.preventDefault();
            const isOpen = widget.classList.toggle('cart-widget--open');
            widgetToggle.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', (event) => {
            if (!widget.contains(event.target)) {
                closeWidget();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeWidget();
            }
        });
    }

    const applyMiniCart = (data) => {
        if (countEl) {
            countEl.textContent = String(data.count);
            countEl.classList.toggle('d-none', data.count === 0);
        }
        if (emptyEl) {
            emptyEl.classList.toggle('d-none', data.count > 0);
        }
        if (subtotalEl) {
            subtotalEl.textContent = data.subtotal;
        }
        if (popupItems) {
            // Строки, уже показанные в попапе, получают свежие
            // количество/сумму. Новый Вариант, которого в попапе ещё не
            // было (нет имени/фото в этом JSON — respond(), CartController),
            // появится в нём после следующей загрузки страницы: счётчик и
            // сумма при этом уже верны сейчас.
            const itemsById = new Map(data.items.map((item) => [String(item.id), item]));
            popupItems.querySelectorAll('[data-item-id]').forEach((row) => {
                const item = itemsById.get(row.dataset.itemId);
                if (!item) {
                    row.remove();
                    return;
                }
                const line = row.querySelector('.cart-popup-item__line');
                if (line) {
                    line.textContent = `${item.quantity} шт. — ${item.lineTotal}`;
                }
            });
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[action="/cart/add"]');
        if (!form) {
            return;
        }
        event.preventDefault();

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => response.json())
            .then((data) => {
                // Ошибка (нет в наличии и т.п.) — обычный submit, чтобы
                // сработал уже готовый flash+redirect на /cart, а не
                // придумывать для неё ещё один способ показа на любой
                // странице сайта.
                if (!data.ok) {
                    HTMLFormElement.prototype.submit.call(form);
                    return;
                }
                applyMiniCart(data);
            })
            .catch(() => {
                HTMLFormElement.prototype.submit.call(form);
            });
    });
})();
