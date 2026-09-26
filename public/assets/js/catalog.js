(() => {
    const sortSelect = document.getElementById('catalog-sort');

    if (!sortSelect) {
        return;
    }

    const applySort = async (value) => {
        const url = new URL(sortSelect.form.action, window.location.href);
        if (value !== 'popularity') {
            url.searchParams.set('sort', value);
        }

        let response;
        try {
            response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        } catch {
            sortSelect.form.submit();
            return;
        }

        if (!response.ok) {
            sortSelect.form.submit();
            return;
        }

        const fragment = document.createElement('div');
        fragment.innerHTML = await response.text();

        const newGrid = fragment.querySelector('#catalog-products');
        if (!newGrid) {
            sortSelect.form.submit();
            return;
        }

        const shownCount = document.getElementById('catalog-shown-count');
        const newShownCount = fragment.querySelector('#catalog-shown-count');
        if (shownCount && newShownCount) {
            shownCount.textContent = newShownCount.textContent;
        }

        document.getElementById('catalog-products')?.replaceWith(newGrid);

        const newPagination = fragment.querySelector('#catalog-pagination');
        const pagination = document.getElementById('catalog-pagination');
        if (pagination) {
            pagination.replaceWith(newPagination ?? document.createTextNode(''));
        } else if (newPagination) {
            newGrid.after(newPagination);
        }

        history.pushState(null, '', url.pathname + url.search);
    };

    // nice-select (jquery.nice-select.min.js) updates the hidden <select> via
    // jQuery's .trigger('change'), which never reaches a native
    // addEventListener('change', ...) — listen for the option click itself
    // instead and read its value straight from data-value.
    sortSelect.form.addEventListener('click', (event) => {
        const option = event.target.closest('.option');

        if (option && option.dataset.value !== sortSelect.value) {
            sortSelect.value = option.dataset.value;
            applySort(option.dataset.value);
        }
    });

    // AJAX-навигация не создаёт настоящих записей истории с собственным
    // содержимым — назад/вперёд после смены сортировки проще и надёжнее
    // просто перезагрузить страницу по адресу из URL, чем реплеить fetch.
    window.addEventListener('popstate', () => {
        window.location.reload();
    });
})();
