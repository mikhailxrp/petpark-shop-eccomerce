(() => {
    const form = document.getElementById('booking-form');

    if (!form) {
        return;
    }

    const alertBox = document.getElementById('booking-alert');
    const stepSpecialist = document.getElementById('booking-step-specialist');
    const stepTime = document.getElementById('booking-step-time');
    const stepPet = document.getElementById('booking-step-pet');
    const stepContacts = document.getElementById('booking-step-contacts');
    const specialistsBox = document.getElementById('booking-specialists');
    const slotsBox = document.getElementById('booking-slots');
    const dateInput = document.getElementById('booking-date');
    const newPetBox = document.getElementById('booking-new-pet');
    const submitButton = document.getElementById('booking-submit');

    const summary = {
        services: document.getElementById('booking-summary-services'),
        specialist: document.getElementById('booking-summary-specialist'),
        time: document.getElementById('booking-summary-time'),
        pet: document.getElementById('booking-summary-pet'),
    };

    const EMPTY_SUMMARY = '—';
    const NO_SPECIALISTS_MESSAGE = 'Нет Специалиста, который оказывает все выбранные Услуги. Выберите их отдельными записями.';
    const NO_SLOTS_MESSAGE = 'На эту дату свободного времени нет — выберите другую дату.';
    const LOAD_ERROR_MESSAGE = 'Не удалось загрузить данные. Попробуйте ещё раз.';

    // Ответ запроса, ушедшего раньше последнего выбора, игнорируем:
    // иначе медленный старый ответ перезапишет актуальный список.
    let specialistsRequest = 0;
    let slotsRequest = 0;

    const setVisible = (element, visible) => {
        element?.classList.toggle('d-none', !visible);
    };

    const showError = (message) => {
        alertBox.textContent = message ?? '';
        setVisible(alertBox, Boolean(message));
    };

    const checkedServices = () => [...form.querySelectorAll('input[name="services[]"]:checked')];

    const selectedSpecialist = () => form.querySelector('input[name="specialist_id"]:checked');

    const selectedSlot = () => form.querySelector('input[name="slot"]:checked');

    const serviceQuery = () => {
        const params = new URLSearchParams();
        checkedServices().forEach((input) => params.append('services[]', input.value));
        return params;
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    };

    const makeMessage = (text) => {
        const paragraph = document.createElement('p');
        paragraph.className = 'booking-form__hint';
        paragraph.textContent = text;
        return paragraph;
    };

    const makeChoice = ({ type, name, id, value, label, className }) => {
        const wrapper = document.createElement('div');
        wrapper.className = className;

        const input = document.createElement('input');
        input.type = type;
        input.name = name;
        input.id = id;
        input.value = value;

        const labelElement = document.createElement('label');
        labelElement.htmlFor = id;
        labelElement.textContent = label;

        wrapper.append(input, labelElement);
        return wrapper;
    };

    const labelOf = (input) => input.labels?.[0]?.textContent.trim() ?? '';

    const updateSummary = () => {
        const services = checkedServices();
        summary.services.textContent = services.length
            ? services.map((input) => input.closest('.booking-service').querySelector('.booking-service__name').textContent.trim()).join(', ')
            : EMPTY_SUMMARY;

        const specialist = selectedSpecialist();
        summary.specialist.textContent = specialist ? labelOf(specialist) : EMPTY_SUMMARY;

        const slot = selectedSlot();
        summary.time.textContent = slot && dateInput.value ? `${dateInput.value}, ${slot.value}` : EMPTY_SUMMARY;

        const pet = form.querySelector('input[name="pet_id"]:checked');
        const petName = form.querySelector('input[name="pet_name"]').value.trim();
        if (pet && pet.value !== 'new') {
            summary.pet.textContent = pet.dataset.label;
        } else {
            summary.pet.textContent = petName || EMPTY_SUMMARY;
        }

        updateSubmitState();
    };

    const filled = (name) => form.querySelector(`[name="${name}"]`)?.value.trim() !== '';

    const isFormReady = () => {
        if (checkedServices().length === 0 || !selectedSpecialist() || !selectedSlot()) {
            return false;
        }

        const pet = form.querySelector('input[name="pet_id"]:checked');
        const isNewPet = !pet || pet.value === 'new';
        if (isNewPet && (!filled('pet_name') || !filled('pet_species'))) {
            return false;
        }

        // Блок контактов есть только у Гостя
        return !stepContacts || ['contact_name', 'contact_phone', 'contact_email'].every(filled);
    };

    const updateSubmitState = () => {
        submitButton.disabled = !isFormReady();
    };

    const revealPetSteps = () => {
        const hasSlot = selectedSlot() !== null;
        setVisible(stepPet, hasSlot);
        setVisible(stepContacts, hasSlot);
    };

    const resetSlots = () => {
        slotsRequest += 1;
        slotsBox.replaceChildren();
        revealPetSteps();
    };

    const loadSlots = async () => {
        const specialist = selectedSpecialist();
        resetSlots();
        updateSummary();

        if (!specialist || !dateInput.value) {
            return;
        }

        const request = slotsRequest;
        const params = serviceQuery();
        params.set('specialist_id', specialist.value);
        params.set('date', dateInput.value);

        try {
            const data = await fetchJson(`/booking/slots?${params}`);
            if (request !== slotsRequest) {
                return;
            }
            showError(null);

            if (data.slots.length === 0) {
                slotsBox.append(makeMessage(NO_SLOTS_MESSAGE));
                return;
            }

            data.slots.forEach((time, index) => {
                slotsBox.append(makeChoice({
                    type: 'radio',
                    name: 'slot',
                    id: `slot-${index}`,
                    value: time,
                    label: time,
                    className: 'booking-slot',
                }));
            });
        } catch (error) {
            if (request === slotsRequest) {
                showError(LOAD_ERROR_MESSAGE);
            }
        }
    };

    const loadSpecialists = async () => {
        specialistsRequest += 1;
        const request = specialistsRequest;
        specialistsBox.replaceChildren();
        resetSlots();
        setVisible(stepTime, false);
        updateSummary();

        if (checkedServices().length === 0) {
            setVisible(stepSpecialist, false);
            return;
        }

        setVisible(stepSpecialist, true);

        try {
            const data = await fetchJson(`/booking/specialists?${serviceQuery()}`);
            if (request !== specialistsRequest) {
                return;
            }
            showError(null);

            if (data.specialists.length === 0) {
                specialistsBox.append(makeMessage(NO_SPECIALISTS_MESSAGE));
                return;
            }

            data.specialists.forEach((specialist) => {
                specialistsBox.append(makeChoice({
                    type: 'radio',
                    name: 'specialist_id',
                    id: `specialist-${specialist.id}`,
                    value: String(specialist.id),
                    label: specialist.name,
                    className: 'booking-specialist',
                }));
            });
        } catch (error) {
            if (request === specialistsRequest) {
                showError(LOAD_ERROR_MESSAGE);
            }
        }
    };

    form.addEventListener('change', (event) => {
        const target = event.target;
        updateSubmitState();

        if (target.matches('input[name="services[]"]')) {
            loadSpecialists();
        } else if (target.matches('input[name="specialist_id"]')) {
            setVisible(stepTime, true);
            loadSlots();
        } else if (target === dateInput) {
            loadSlots();
        } else if (target.matches('input[name="slot"]')) {
            revealPetSteps();
            updateSummary();
        } else if (target.matches('input[name="pet_id"]')) {
            setVisible(newPetBox, target.value === 'new');
            updateSummary();
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('input[name="pet_name"]')) {
            updateSummary();
        }
    });

    // Серверная проверка в BookingController::store() главная; здесь только
    // не даём отправить заведомо неполную форму и повторный клик.
    form.addEventListener('submit', (event) => {
        if (!isFormReady() || submitButton.dataset.sending === '1') {
            event.preventDefault();
            return;
        }
        submitButton.dataset.sending = '1';
        submitButton.disabled = true;
    });

    form.addEventListener('input', updateSubmitState);

    updateSummary();
})();
