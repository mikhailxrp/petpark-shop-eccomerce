// Форма сотрудника: блок «Услуги и график» виден только при роли «Специалист».
// Без JS блок виден всегда; сервер всё равно читает его поля только для этой роли.

const init = () => {
    const roleSelect = document.getElementById('staff-role');
    const block = document.getElementById('staff-specialist-fields');
    if (!roleSelect || !block) {
        return;
    }

    const sync = () => {
        block.hidden = roleSelect.value !== block.dataset.specialistRole;
    };

    roleSelect.addEventListener('change', sync);
    sync();
};

init();
