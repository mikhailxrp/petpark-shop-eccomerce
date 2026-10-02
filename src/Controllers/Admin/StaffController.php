<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Учётные записи персонала — /admin/staff (phase-7.md, Таск 5, FR-ADM-003).
 * Создавать может Владелец и Администратор смены (набор ролей — по
 * `staffRolesCreatableBy()`); менять роль и отключать — только Владелец
 * (`canManageStaff()`). Права проверяются на backend, скрытая в форме
 * кнопка их не заменяет.
 */
final class StaffController
{
    private const FORM_FLASH = 'staff_form';

    private const NAME_MAX = 100;
    private const EMAIL_MAX = 150;
    private const PHONE_MAX = 20;

    private const ROLE_SPECIALIST = 'specialist';
    private const DEFAULT_WORK_START = '10:00';
    private const DEFAULT_WORK_END = '20:00';

    private const CREATE_RATE_LIMIT_ATTEMPTS = 10;
    private const CREATE_RATE_LIMIT_SECONDS = 60;

    // Одно сообщение на занятый email и сбой письма: детали аккаунта не
    // раскрываем (php.md), а у сбоя SMTP причина всё равно в логе.
    private const CREATE_FAILED_ERROR = 'Не удалось создать сотрудника. Проверьте данные и попробуйте ещё раз.';
    private const MAIL_FAILED_ERROR = 'Письмо с паролем не отправилось, учётная запись не создана. Попробуйте ещё раз.';
    private const RATE_LIMITED_ERROR = 'Слишком много попыток. Подождите минуту и повторите.';

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $actorRole = (string) $_SESSION['user_role'];
        $actorId = (int) $_SESSION['user_id'];

        $staff = array_map(
            static fn (array $member): array => $member + [
                'can_manage' => canManageStaff($actorRole, $actorId, (int) $member['id'], (string) $member['role']),
            ],
            userListStaff()
        );

        render('admin/staff', [
            'pageTitle'     => 'Сотрудники — PetPark',
            'roleLabel'     => adminRoleLabel($actorRole),
            'homeUrl'       => homePathForRole($actorRole),
            'userRole'      => $actorRole,
            'staff'         => $staff,
            'assignable'    => staffRolesCreatableBy('owner'),
            'canCreate'     => staffRolesCreatableBy($actorRole) !== [],
            'success'       => getFlash('success'),
            'error'         => getFlash('error'),
        ]);
    }

    public function createForm(): void
    {
        requireRole('shift_admin', 'owner');

        $actorRole = (string) $_SESSION['user_role'];
        $form = $this->takeForm();

        render('admin/staff-form', [
            'pageTitle' => 'Новый сотрудник — PetPark',
            'roleLabel' => adminRoleLabel($actorRole),
            'homeUrl'   => homePathForRole($actorRole),
            'userRole'  => $actorRole,
            'roles'     => staffRolesCreatableBy($actorRole),
            'values'    => $form['values'] ?? $this->emptyValues(),
            'errors'    => $form['errors'] ?? [],
            'services'  => servicesActive(),
            'error'     => getFlash('error'),
        ]);
    }

    public function store(): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $actorRole = (string) $_SESSION['user_role'];

        if (tooManyAttempts('staff-create', self::CREATE_RATE_LIMIT_ATTEMPTS, self::CREATE_RATE_LIMIT_SECONDS)) {
            logWarning('Сотрудники: превышен лимит созданий', ['user_id' => (int) $_SESSION['user_id']]);
            setFlash('error', self::RATE_LIMITED_ERROR);
            redirect('/admin/staff/new');
        }
        hitRateLimit('staff-create');

        [$values, $errors] = $this->validate($actorRole);

        if ($errors !== []) {
            $this->rememberForm($values, $errors);
            redirect('/admin/staff/new');
        }

        $password = generatePassword();
        $phone = $values['phone'] === '' ? null : normalizePhone($values['phone']);

        $newId = userCreateStaff(
            $values['name'],
            $values['email'],
            $phone,
            $values['role'],
            password_hash($password, PASSWORD_DEFAULT),
            $values['role'] === self::ROLE_SPECIALIST
                ? [
                    'work_start'  => $values['work_start'],
                    'work_end'    => $values['work_end'],
                    'day_off'     => $values['day_off'] === '' ? null : (int) $values['day_off'],
                    'service_ids' => $values['service_ids'],
                ]
                : null
        );

        if ($newId === null) {
            logWarning('Сотрудники: email занят', ['user_id' => (int) $_SESSION['user_id']]);
            setFlash('error', self::CREATE_FAILED_ERROR);
            $this->rememberForm($values, []);
            redirect('/admin/staff/new');
        }

        // Пароль уходит напрямую, не через очередь `notifications`: иначе он
        // лёг бы в БД открытым текстом (ADR-033).
        try {
            sendEmail(
                $values['email'],
                $values['name'],
                'Доступ в админку — ' . SHOP_NAME,
                sprintf(
                    "Здравствуйте, %s!\n\nДля вас создана учётная запись в админке %s (%s).\nEmail для входа: %s\nПароль: %s\n\nВход: %s/admin/login",
                    $values['name'],
                    SHOP_NAME,
                    adminRoleLabel($values['role']),
                    $values['email'],
                    $password,
                    APP_URL
                )
            );
        } catch (\Throwable $e) {
            // Без письма пароль не знает никто — откатываем запись целиком.
            userDeleteStaffById($newId);
            logException($e, ['staff_email' => $values['email']]);
            setFlash('error', self::MAIL_FAILED_ERROR);
            $this->rememberForm($values, []);
            redirect('/admin/staff/new');
        }

        logWarning('Сотрудники: создана учётная запись', [
            'created_by' => (int) $_SESSION['user_id'],
            'staff_id'   => $newId,
            'role'       => $values['role'],
        ]);

        setFlash('success', 'Сотрудник создан, пароль отправлен на ' . $values['email'] . '.');
        redirect('/admin/staff');
    }

    public function changeRole(string $id): void
    {
        $member = $this->manageableMember($id);

        $role = (string) input('role');
        if (!in_array($role, staffRolesCreatableBy('owner'), true)) {
            setFlash('error', 'Выберите роль из списка.');
            redirect('/admin/staff');
        }

        userUpdateRole((int) $member['id'], $role);

        setFlash('success', 'Роль изменена: ' . $member['name'] . ' — ' . adminRoleLabel($role) . '.');
        redirect('/admin/staff');
    }

    public function toggleActive(string $id): void
    {
        $member = $this->manageableMember($id);

        $activate = (int) $member['is_active'] === 0;
        userSetActive((int) $member['id'], $activate);

        setFlash('success', $activate
            ? 'Доступ восстановлен: ' . $member['name'] . '.'
            : 'Сотрудник отключён: ' . $member['name'] . '.');
        redirect('/admin/staff');
    }

    /**
     * Общая защита действий над существующим сотрудником: роль, CSRF, наличие
     * записи и право `canManageStaff()`. Любой отказ — 404 без пояснений, чтобы
     * Администратор смены, подставивший id в POST, не узнал о записи.
     *
     * @return array<string, mixed>
     */
    private function manageableMember(string $id): array
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $member = ctype_digit($id) ? userFindStaffById((int) $id) : null;

        if ($member === null || !canManageStaff(
            (string) $_SESSION['user_role'],
            (int) $_SESSION['user_id'],
            (int) $member['id'],
            (string) $member['role']
        )) {
            logWarning('Сотрудники: действие запрещено', [
                'user_id'   => (int) $_SESSION['user_id'],
                'target_id' => $id,
            ]);
            http_response_code(404);
            render('errors/404');
            exit;
        }

        return $member;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyValues(): array
    {
        return [
            'name'        => '',
            'email'       => '',
            'phone'       => '',
            'role'        => '',
            'work_start'  => self::DEFAULT_WORK_START,
            'work_end'    => self::DEFAULT_WORK_END,
            'day_off'     => '',
            'service_ids' => [],
        ];
    }

    /**
     * Поля Специалиста (график, Услуги) читаются и проверяются только при
     * `role = specialist`; для остальных ролей остаются значения по умолчанию,
     * даже если пришли в POST.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [значения, ошибки по полям]
     */
    private function validate(string $actorRole): array
    {
        $values = [
            'name'  => trim(mb_scrub((string) input('name'))),
            'email' => mb_strtolower(trim(mb_scrub((string) input('email')))),
            'phone' => trim(mb_scrub((string) input('phone'))),
            'role'  => (string) input('role'),
        ] + $this->emptyValues();
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'] = 'Укажите имя.';
        } elseif (mb_strlen($values['name']) > self::NAME_MAX) {
            $errors['name'] = 'Имя — не длиннее ' . self::NAME_MAX . ' символов.';
        }

        if ($values['email'] === '' || mb_strlen($values['email']) > self::EMAIL_MAX
            || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Укажите корректный email.';
        }

        if ($values['phone'] !== '' && (mb_strlen($values['phone']) > self::PHONE_MAX || normalizePhone($values['phone']) === null)) {
            $errors['phone'] = 'Телефон — в формате +7 900 000-00-00.';
        }

        if (!in_array($values['role'], staffRolesCreatableBy($actorRole), true)) {
            $errors['role'] = 'Выберите роль из списка.';
        }

        if ($values['role'] === self::ROLE_SPECIALIST && !isset($errors['role'])) {
            $values['work_start'] = trim((string) input('work_start'));
            $values['work_end'] = trim((string) input('work_end'));
            $values['day_off'] = trim((string) input('day_off'));
            $errors += specialistScheduleErrors($values['work_start'], $values['work_end'], $values['day_off']);

            [$values['service_ids'], $servicesError] = $this->validateServices(input('service_ids', []));
            if ($servicesError !== null) {
                $errors['service_ids'] = $servicesError;
            }
        }

        return [$values, $errors];
    }

    /**
     * Услуги Специалиста: только существующие активные; пустой выбор допустим
     * (Специалист без Услуг в форме Записи не появится).
     *
     * @return array{0: list<int>, 1: string|null} [id Услуг, ошибка]
     */
    private function validateServices(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [[], 'Выберите Услуги из списка.'];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (!is_string($item) || !ctype_digit($item) || (int) $item < 1) {
                return [[], 'Выберите Услуги из списка.'];
            }
            $ids[(int) $item] = (int) $item;
        }
        $ids = array_values($ids);

        if (count(servicesFindActiveByIds($ids)) !== count($ids)) {
            return [[], 'Выберите Услуги из списка.'];
        }

        return [$ids, null];
    }

    /**
     * Ошибки и введённое переживают redirect через сессию (POST → redirect).
     *
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function rememberForm(array $values, array $errors): void
    {
        setFlash(self::FORM_FLASH, json_encode(['values' => $values, 'errors' => $errors], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{values: array<string, mixed>, errors: array<string, string>}|null
     */
    private function takeForm(): ?array
    {
        $raw = getFlash(self::FORM_FLASH);
        if ($raw === null) {
            return null;
        }

        $form = json_decode($raw, true);

        return is_array($form) && is_array($form['values'] ?? null) && is_array($form['errors'] ?? null)
            ? $form
            : null;
    }
}
