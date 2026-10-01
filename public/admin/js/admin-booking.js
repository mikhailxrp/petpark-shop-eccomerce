// Формы ручной Записи и переноса в панели: Специалисты по Услугам, свободные
// слоты, Питомцы клиента по email. Слоты и список Специалистов — только с
// сервера (/admin/bookings/specialists|slots|pets); сервер перепроверяет всё
// при отправке, здесь — подсказки.

const EMAIL_DELAY_MS = 400;
const NO_SPECIALISTS_MESSAGE = 'Нет Специалиста, который оказывает все выбранные Услуги.';
const NO_SLOTS_MESSAGE = 'На эту дату свободного времени нет — выберите другую дату.';
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить данные. Попробуйте ещё раз.';

const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
};

const makeMessage = (text) => {
    const paragraph = document.createElement('p');
    paragraph.className = 'text-muted mb-0';
    paragraph.textContent = text;
    return paragraph;
};

const makeChoice = ({ name, id, value, label, checked = false, extra = {} }) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'form-check form-check-inline';

    const input = document.createElement('input');
    input.type = 'radio';
    input.className = 'form-check-input';
    input.name = name;
    input.id = id;
    input.value = value;
    input.checked = checked;
    Object.assign(input.dataset, extra);

    const labelElement = document.createElement('label');
    labelElement.className = 'form-check-label';
    labelElement.htmlFor = id;
    labelElement.textContent = label;

    wrapper.append(input, labelElement);
    return wrapper;
};

const init = () => {
    const form = document.getElementById('booking-manual-form') ?? document.getElementById('booking-reschedule-form');
    if (!form) {
        return;
    }

    const isCreate = form.id === 'booking-manual-form';
    const specialistsBox = form.querySelector('#booking-specialists');
    const slotsBox = form.querySelector('#booking-slots');
    const dateInput = form.querySelector('#booking-date');
    const submitButton = form.querySelector('#booking-submit');

    // Ответ запроса, ушедшего раньше последнего выбора, игнорируем.
    let specialistsRequest = 0;
    let slotsRequest = 0;

    const selectedSpecialist = () => form.querySelector('input[name="specialist_id"]:checked');
    const selectedSlot = () => form.querySelector('input[name="slot"]:checked');
    const checkedServices = () => [...form.querySelectorAll('input[name="services[]"]:checked')];

    const updateSubmitState = () => {
        submitButton.disabled = !(selectedSpecialist() && dateInput.value && selectedSlot());
    };

    const loadSlots = async (initialSlot = '') => {
        const request = ++slotsRequest;
        slotsBox.replaceChildren();
        updateSubmitState();

        const specialist = selectedSpecialist();
        if (!specialist || !dateInput.value) {
            return;
        }

        const params = new URLSearchParams({ specialist_id: specialist.value, date: dateInput.value });
        if (isCreate) {
            checkedServices().forEach((input) => params.append('services[]', input.value));
        } else {
            params.set('booking_id', form.dataset.bookingId);
        }

        try {
            const { slots } = await fetchJson(`${form.dataset.slotsUrl}?${params}`);
            if (request !== slotsRequest) {
                return;
            }
            if (slots.length === 0) {
                slotsBox.append(makeMessage(NO_SLOTS_MESSAGE));
                return;
            }
            slots.forEach((slot, index) => slotsBox.append(makeChoice({
                name: 'slot',
                id: `booking-slot-${index}`,
                value: slot,
                label: slot,
                checked: slot === initialSlot,
            })));
        } catch {
            if (request === slotsRequest) {
                slotsBox.append(makeMessage(LOAD_ERROR_MESSAGE));
            }
        }
        updateSubmitState();
    };

    const loadSpecialists = async (initialSpecialist = '', initialSlot = '') => {
        const request = ++specialistsRequest;
        specialistsBox.replaceChildren();
        slotsRequest++;
        slotsBox.replaceChildren();
        updateSubmitState();

        const services = checkedServices();
        if (services.length === 0) {
            specialistsBox.append(makeMessage('Сначала выберите Услуги.'));
            return;
        }

        const params = new URLSearchParams();
        services.forEach((input) => params.append('services[]', input.value));

        try {
            const { specialists } = await fetchJson(`${form.dataset.specialistsUrl}?${params}`);
            if (request !== specialistsRequest) {
                return;
            }
            if (specialists.length === 0) {
                specialistsBox.append(makeMessage(NO_SPECIALISTS_MESSAGE));
                return;
            }
            specialists.forEach((specialist) => specialistsBox.append(makeChoice({
                name: 'specialist_id',
                id: `booking-specialist-${specialist.id}`,
                value: String(specialist.id),
                label: specialist.name,
                checked: String(specialist.id) === initialSpecialist || specialists.length === 1,
            })));
            loadSlots(initialSlot);
        } catch {
            if (request === specialistsRequest) {
                specialistsBox.append(makeMessage(LOAD_ERROR_MESSAGE));
            }
        }
    };

    form.addEventListener('change', (event) => {
        const { target } = event;
        if (target.matches('input[name="services[]"]')) {
            loadSpecialists();
        } else if (target.matches('input[name="specialist_id"]') || target === dateInput) {
            loadSlots();
        } else if (target.matches('input[name="slot"]')) {
            updateSubmitState();
        }
    });

    if (isCreate) {
        const petBox = form.querySelector('#booking-pets');
        const newPetBox = form.querySelector('#booking-new-pet');
        const emailInput = form.querySelector('#booking-contact-email');
        const initialPetId = form.dataset.initialPet ?? 'new';
        let emailTimer = null;
        let petsRequest = 0;

        const togglePetFields = () => {
            const isNew = (form.querySelector('input[name="pet_id"]:checked')?.value ?? 'new') === 'new';
            newPetBox.classList.toggle('d-none', !isNew);
            newPetBox.querySelectorAll('input, select').forEach((field) => {
                field.disabled = !isNew;
            });
            form.querySelector('#booking-pet-name').required = isNew;
        };

        const renderPets = (pets, selectedId) => {
            petBox.replaceChildren(
                ...pets.map((pet) => makeChoice({
                    name: 'pet_id',
                    id: `booking-pet-${pet.id}`,
                    value: String(pet.id),
                    label: `${pet.name} (${pet.species})`,
                    checked: String(pet.id) === selectedId,
                })),
                makeChoice({
                    name: 'pet_id',
                    id: 'booking-pet-new',
                    value: 'new',
                    label: 'Новый Питомец',
                    checked: !pets.some((pet) => String(pet.id) === selectedId),
                })
            );
            togglePetFields();
        };

        const loadPets = async (selectedId = 'new') => {
            const request = ++petsRequest;
            const email = emailInput.value.trim();
            if (!emailInput.validity.valid || email === '') {
                renderPets([], 'new');
                return;
            }
            try {
                const { pets } = await fetchJson(`${form.dataset.petsUrl}?${new URLSearchParams({ email })}`);
                if (request === petsRequest) {
                    renderPets(pets, selectedId);
                }
            } catch {
                if (request === petsRequest) {
                    renderPets([], 'new');
                }
            }
        };

        emailInput.addEventListener('input', () => {
            clearTimeout(emailTimer);
            emailTimer = setTimeout(() => loadPets(), EMAIL_DELAY_MS);
        });
        petBox.addEventListener('change', togglePetFields);

        loadPets(initialPetId);
        if (checkedServices().length > 0) {
            loadSpecialists(form.dataset.initialSpecialist ?? '', form.dataset.initialSlot ?? '');
        } else {
            specialistsBox.append(makeMessage('Сначала выберите Услуги.'));
        }
    } else {
        loadSlots(form.dataset.initialSlot ?? '');
    }

    form.addEventListener('submit', (event) => {
        if (!form.checkValidity() || !selectedSlot()) {
            event.preventDefault();
            form.classList.add('was-validated');
        }
    });

    updateSubmitState();
};

init();
