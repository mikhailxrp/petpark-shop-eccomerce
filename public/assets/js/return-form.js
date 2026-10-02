const reason = document.getElementById('return-reason');
const counter = document.getElementById('return-reason-counter');
const photos = document.getElementById('return-photos');
const photosList = document.getElementById('return-photos-list');

const BYTES_IN_MB = 1024 * 1024;

const formatSize = (bytes) => `${(bytes / BYTES_IN_MB).toFixed(1)} МБ`;

if (reason && counter) {
    const limit = Number(reason.maxLength);
    const updateCounter = () => {
        counter.textContent = `${reason.value.length} из ${limit} символов`;
    };

    reason.addEventListener('input', updateCounter);
    updateCounter();
}

if (photos && photosList) {
    photos.addEventListener('change', () => {
        photosList.replaceChildren(
            ...Array.from(photos.files).map((file) => {
                const item = document.createElement('li');
                item.textContent = `${file.name} — ${formatSize(file.size)}`;
                return item;
            })
        );
    });
}
