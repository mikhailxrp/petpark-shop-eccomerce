(() => {
    const sortSelect = document.getElementById('catalog-sort');
    const filtersForm = document.getElementById('catalog-filters-form');

    if (!sortSelect && !filtersForm) {
        return;
    }

    const swapFragment = (html) => {
        const fragment = document.createElement('div');
        fragment.innerHTML = html;

        const newGrid = fragment.querySelector('#catalog-products');
        if (!newGrid) {
            return false;
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

        return true;
    };

    const applyUrl = async (url, fallbackSubmit) => {
        let response;
        try {
            response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        } catch {
            fallbackSubmit();
            return;
        }

        if (!response.ok || !swapFragment(await response.text())) {
            fallbackSubmit();
            return;
        }

        history.pushState(null, '', url.pathname + url.search);
    };

    if (sortSelect) {
        const applySort = (value) => {
            const url = new URL(sortSelect.form.action, window.location.href);
            const currentParams = new URLSearchParams(window.location.search);
            for (const [key, value_] of currentParams) {
                if (key !== 'sort' && key !== 'page') {
                    url.searchParams.append(key, value_);
                }
            }
            if (value !== 'popularity') {
                url.searchParams.set('sort', value);
            }

            applyUrl(url, () => {
                sortSelect.value = value;
                sortSelect.form.submit();
            });
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
    }

    if (filtersForm) {
        // Параметры, значения которых полностью задаёт форма фильтров —
        // при сабмите не копируются из текущего адреса, а берутся заново
        // из FormData (иначе снятая галочка не исчезла бы из URL).
        const isManagedByFiltersForm = (key) => key === 'sort'
            || key === 'q'
            || key === 'page'
            || key === 'brand[]'
            || key === 'price_min'
            || key === 'price_max'
            || key.startsWith('attr[');

        filtersForm.addEventListener('submit', (event) => {
            event.preventDefault();

            const url = new URL(filtersForm.action, window.location.href);
            const currentParams = new URLSearchParams(window.location.search);
            for (const [key, value] of currentParams) {
                if (!isManagedByFiltersForm(key)) {
                    url.searchParams.append(key, value);
                }
            }

            for (const [key, value] of new FormData(filtersForm)) {
                if (typeof value === 'string' && value !== '') {
                    url.searchParams.append(key, value);
                }
            }

            applyUrl(url, () => filtersForm.submit());
        });

        // Ползунок цены макета (Patte, our-products.html) — то же поведение,
        // что у оригинального assets/js/wrapper.js (минимальный зазор между
        // ползунками, синхронизация с текстовыми полями), переписанное без
        // глобальных переменных (general.md — новый/переносимый JS проекта
        // без var/глобального состояния, в отличие от скопированных
        // jQuery-плагинов макета).
        const lower = document.getElementById('lower');
        const upper = document.getElementById('upper');
        const minField = document.getElementById('one');
        const maxField = document.getElementById('two');
        const MIN_GAP = 1;

        if (lower && upper && minField && maxField) {
            lower.addEventListener('input', () => {
                if (Number(lower.value) > Number(upper.value) - MIN_GAP) {
                    lower.value = String(Number(upper.value) - MIN_GAP);
                }
                minField.value = lower.value;
            });
            upper.addEventListener('input', () => {
                if (Number(upper.value) < Number(lower.value) + MIN_GAP) {
                    upper.value = String(Number(lower.value) + MIN_GAP);
                }
                maxField.value = upper.value;
            });
            minField.addEventListener('change', () => {
                const value = Math.min(Number(minField.value) || 0, Number(upper.value) - MIN_GAP);
                lower.value = String(value);
                minField.value = String(value);
            });
            maxField.addEventListener('change', () => {
                const value = Math.max(Number(maxField.value) || 0, Number(lower.value) + MIN_GAP);
                upper.value = String(value);
                maxField.value = String(value);
            });

            // Chrome рисует стилизованный thumb <input type="range"> в
            // отдельном композитном слое, который проступает поверх
            // выпадающего списка nice-select независимо от z-index (не
            // лечится через CSS — проверено: ни повышение z-index списка,
            // ни понижение z-index thumb'а не помогает). Единственный
            // надёжный вариант — прятать сами ползунки, пока открыт
            // любой select фильтра.
            const toggleRangeVisibility = () => {
                const anyOpen = filtersForm.querySelector('.nice-select.open') !== null;
                lower.style.visibility = anyOpen ? 'hidden' : '';
                upper.style.visibility = anyOpen ? 'hidden' : '';
            };
            new MutationObserver(toggleRangeVisibility).observe(filtersForm, {
                attributes: true,
                attributeFilter: ['class'],
                subtree: true,
            });
        }
    }

    // AJAX-навигация не создаёт настоящих записей истории с собственным
    // содержимым — назад/вперёд после смены сортировки/фильтра проще и
    // надёжнее просто перезагрузить страницу по адресу из URL, чем
    // реплеить fetch.
    window.addEventListener('popstate', () => {
        window.location.reload();
    });
})();
