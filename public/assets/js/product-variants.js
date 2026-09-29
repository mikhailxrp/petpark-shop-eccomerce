(() => {
    const form = document.getElementById('product-variants-form');
    const select = document.getElementById('product-variant-select');

    if (!form || !select) {
        return;
    }

    let variants;
    try {
        variants = JSON.parse(form.dataset.variants || '{}');
    } catch {
        return;
    }

    const priceEl = document.getElementById('product-price');
    const availabilityEl = document.getElementById('product-availability');
    const skuEl = document.getElementById('product-sku');
    const quantityInput = document.getElementById('product-quantity');
    const addToCartButton = document.getElementById('product-add-to-cart');
    const favoriteVariantInput = document.getElementById('favorite-variant-id');
    const cartVariantInput = document.getElementById('cart-variant-id');
    const defaultVariantId = form.dataset.defaultVariant;

    // Наличие/цена/подпись уже посчитаны на сервере теми же функциями,
    // что и первичный рендер (Core/Catalog.php) — здесь только подстановка
    // готовых строк в DOM, без пересчёта бизнес-логики на фронтенде.
    const applyVariant = (variantId) => {
        const variant = variants[variantId];
        if (!variant) {
            return;
        }

        if (priceEl) {
            priceEl.innerHTML = variant.priceHtml;
        }
        if (availabilityEl) {
            // Текст — во вложенном <span> (style.css: `.stock h6 span` —
            // отступ от цены), не прямо в <h6> — иначе перезапишет сам span.
            const labelEl = availabilityEl.querySelector('span') || availabilityEl;
            labelEl.textContent = variant.availabilityLabel;
            availabilityEl.className = variant.availabilityClass;
        }
        if (skuEl) {
            skuEl.textContent = variant.sku;
        }
        if (quantityInput) {
            const max = Math.max(1, variant.available);
            quantityInput.max = String(max);
            quantityInput.disabled = variant.available <= 0;
            if (Number(quantityInput.value) > max) {
                quantityInput.value = String(max);
            }
        }
        if (addToCartButton) {
            addToCartButton.disabled = variant.available <= 0;
        }
        // Форма корзины (#product-cart-form) кладёт тот Вариант, что
        // выбран сейчас, — сервер всё равно перепроверит остаток сам.
        if (cartVariantInput) {
            cartVariantInput.value = variantId;
        }
        // Кнопка «В избранное» всегда должна ставить отметку на реально
        // выбранный Вариант, а не на дефолтный из первичного рендера.
        if (favoriteVariantInput) {
            favoriteVariantInput.value = variantId;
        }

        const url = new URL(window.location.href);
        if (variantId === defaultVariantId) {
            url.searchParams.delete('variant');
        } else {
            url.searchParams.set('variant', variantId);
        }
        history.pushState(null, '', url.pathname + url.search);
    };

    // nice-select (jquery.nice-select.min.js) обновляет скрытый <select> через
    // jQuery .trigger('change'), которое не доходит до addEventListener('change', ...)
    // (тот же приём, что sortSelect в catalog.js) — слушаем клик по самому
    // пункту списка и берём значение из data-value.
    form.addEventListener('click', (event) => {
        const option = event.target.closest('.option');
        if (option && option.dataset.value !== select.value) {
            select.value = option.dataset.value;
            applyVariant(option.dataset.value);
        }
    });

    window.addEventListener('popstate', () => {
        window.location.reload();
    });
})();
