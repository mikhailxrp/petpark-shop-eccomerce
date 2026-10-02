// Форма Товара: предпросмотр выбранных файлов, выбор главного среди новых фото
// и проверка числа/размера до отправки. Сервер всё проверяет заново (тип — по
// содержимому), здесь только подсказка. Без JS форма работает: новые файлы
// просто загружаются, главным остаётся отмеченное фото.

const MAX_FILES_FALLBACK = 8;
const MAX_MB_FALLBACK = 5;
const BYTES_IN_MB = 1024 * 1024;

const buildPreviewItem = (file, index, objectUrl) => {
    const col = document.createElement('div');
    col.className = 'col-6 col-md-3';

    const card = document.createElement('div');
    card.className = 'card h-100';

    const ratio = document.createElement('div');
    ratio.className = 'ratio ratio-1x1';
    const img = document.createElement('img');
    img.className = 'card-img-top object-fit-cover';
    img.src = objectUrl;
    img.alt = `Новое фото: ${file.name}`;
    ratio.append(img);

    const body = document.createElement('div');
    body.className = 'card-body p-2';
    const check = document.createElement('div');
    check.className = 'form-check';
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.className = 'form-check-input';
    radio.name = 'main_image';
    radio.value = `new:${index}`;
    radio.id = `product-main-new-${index}`;
    const label = document.createElement('label');
    label.className = 'form-check-label';
    label.htmlFor = radio.id;
    label.textContent = 'Главное';
    check.append(radio, label);
    body.append(check);

    card.append(ratio, body);
    col.append(card);

    return col;
};

const init = () => {
    const input = document.getElementById('product-photos-input');
    const preview = document.getElementById('product-photos-preview');
    if (!input || !preview) {
        return;
    }

    const maxFiles = Number(input.dataset.maxFiles) || MAX_FILES_FALLBACK;
    const maxBytes = (Number(input.dataset.maxMb) || MAX_MB_FALLBACK) * BYTES_IN_MB;
    let objectUrls = [];

    const clearPreview = () => {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls = [];
        preview.replaceChildren();
    };

    input.addEventListener('change', () => {
        clearPreview();

        const files = Array.from(input.files ?? []);
        if (files.length > maxFiles || files.some((file) => file.size > maxBytes)) {
            input.setCustomValidity('Слишком много файлов или файл больше допустимого размера.');
            input.reportValidity();
            input.setCustomValidity('');
            input.value = '';
            return;
        }

        files.forEach((file, index) => {
            const objectUrl = URL.createObjectURL(file);
            objectUrls.push(objectUrl);
            preview.append(buildPreviewItem(file, index, objectUrl));
        });
    });
};

init();
