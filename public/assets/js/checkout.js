(() => {
    const page = document.querySelector('.checkout-page');

    if (!page) {
        return;
    }

    const pickupInfo = document.getElementById('checkout-pickup-info');
    const addressBlock = document.getElementById('checkout-address');
    const deliveryCostEl = document.getElementById('checkout-delivery-cost');
    const totalEl = document.getElementById('checkout-total');

    // Обе стоимости (самовывоз/курьер) уже посчитаны Controller'ом
    // (Core/Order.php) и лежат в data-атрибутах каждого radio — здесь
    // только подстановка готовых строк, без расчётов на клиенте.
    const applyDeliveryMethod = (radio) => {
        const isCourier = radio.value === 'courier';
        addressBlock?.classList.toggle('d-none', !isCourier);
        pickupInfo?.classList.toggle('d-none', isCourier);

        if (deliveryCostEl && radio.dataset.cost) {
            deliveryCostEl.textContent = radio.dataset.cost;
        }
        if (totalEl && radio.dataset.total) {
            totalEl.textContent = radio.dataset.total;
        }
    };

    page.querySelectorAll('input[name="delivery_method"]').forEach((radio) => {
        radio.addEventListener('change', () => applyDeliveryMethod(radio));
    });
})();
