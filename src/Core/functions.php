<?php

declare(strict_types=1);

/**
 * Общие хелперы приложения.
 */

function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        throw new RuntimeException("Файл окружения не найден: {$path}");
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim(trim($value), '"\'');
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

function env(string $key, ?string $default = null): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Отсутствует переменная окружения: {$key}");
    }
    return $value;
}

function ensureSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => defined('APP_ENV') && APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function regenerateSession(): void
{
    ensureSessionStarted();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function isAuthenticated(): bool
{
    ensureSessionStarted();
    return normalizeUserId($_SESSION['user_id'] ?? null) !== null;
}

function redirectIfAuthenticated(): void
{
    if (isAuthenticated()) {
        ensureSessionStarted();
        $role = $_SESSION['user_role'] ?? null;
        redirect(homePathForRole(is_string($role) ? $role : 'customer'));
    }
}

function homePathForRole(string $role): string
{
    return match ($role) {
        'customer' => '/account',
        'specialist' => '/specialist',
        'shift_admin', 'owner' => '/admin',
        'content_editor' => '/admin/products',
        default => '/',
    };
}

function adminRoleLabel(string $role): string
{
    return match ($role) {
        'specialist' => 'Специалист',
        'shift_admin' => 'Администратор смены',
        'content_editor' => 'Контент-редактор',
        'owner' => 'Владелец',
        default => 'Персонал',
    };
}

function requireRole(string ...$roles): void
{
    ensureSessionStarted();

    // Незалогиненный видит форму входа своей части сайта — персонал
    // (specialist/shift_admin/content_editor/owner) шлём на
    // /admin/login, не на /login Покупателя (phase-1.md, Таск 7).
    if (!isAuthenticated()) {
        redirect(in_array('customer', $roles, true) ? '/login' : '/admin/login');
    }

    $role = $_SESSION['user_role'] ?? null;

    // Отключённый сотрудник (users.is_active = 0, ADR-033) теряет открытую
    // сессию на ближайшем запросе, а не только при следующем входе.
    if (!userIsActive((int) $_SESSION['user_id'])) {
        $_SESSION = [];
        session_destroy();
        redirect(in_array('customer', $roles, true) ? '/login' : '/admin/login');
    }

    if (!is_string($role) || !in_array($role, $roles, true)) {
        redirect(homePathForRole(is_string($role) ? $role : 'customer'));
    }
}

/**
 * Роли, которые `$actorRole` вправе назначить при создании сотрудника
 * (FR-ADM-003 п. 1, 3): Владелец — любые роли персонала кроме `owner`,
 * Администратор смены — только Специалиста и Администратора смены.
 *
 * @return list<string>
 */
function staffRolesCreatableBy(string $actorRole): array
{
    return match ($actorRole) {
        'owner'       => ['specialist', 'shift_admin', 'content_editor'],
        'shift_admin' => ['specialist', 'shift_admin'],
        default       => [],
    };
}

/**
 * Можно ли менять роль или отключать уже существующего сотрудника
 * (FR-ADM-003 п. 2): только Владельцу, не себе (иначе можно остаться без
 * Владельца) и не другому Владельцу.
 */
function canManageStaff(string $actorRole, int $actorId, int $targetId, string $targetRole): bool
{
    return $actorRole === 'owner'
        && $actorId !== $targetId
        && in_array($targetRole, ['specialist', 'shift_admin', 'content_editor'], true);
}

function setFlash(string $key, string $message): void
{
    ensureSessionStarted();
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string
{
    ensureSessionStarted();
    $flash = $_SESSION['flash'][$key] ?? null;
    if (!is_string($flash) || $flash === '') {
        return null;
    }
    unset($_SESSION['flash'][$key]);
    return $flash;
}

function normalizeUserId(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }
    return null;
}

function render(string $view, array $data = []): void
{
    if ($view === '' || str_contains($view, '..') || str_ends_with($view, '.php')) {
        throw new RuntimeException("Некорректное имя шаблона: {$view}");
    }

    $viewPath = ROOT_PATH . '/src/Views/' . trim($view, '/') . '.php';

    if (!is_file($viewPath)) {
        throw new RuntimeException("Шаблон не найден: {$view}. Ожидался путь: {$viewPath}");
    }

    extract($data, EXTR_SKIP);
    require $viewPath;
}

/** Как render(), но возвращает результат строкой — для шаблонов писем. */
function renderToString(string $view, array $data = []): string
{
    ob_start();
    try {
        render($view, $data);
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }

    return (string) ob_get_clean();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * X-Requested-With ставит сам fetch()-вызов (public/assets/js/catalog.js) —
 * браузер его не добавляет автоматически, поэтому по заголовку надёжно
 * отличаем AJAX-запрос сортировки от обычного захода на страницу.
 */
function isAjaxRequest(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

// ─── Пароли ─────────────────────────────────────────────────────────────

/**
 * Новый пароль для восстановления (FR-AUTH-002/003) — без визуально
 * похожих символов (0/O, 1/l/I), random_int — криптостойкий генератор,
 * не mt_rand().
 */
function generatePassword(int $length = 12): string
{
    $alphabet = str_replace(
        ['0', 'O', '1', 'l', 'I'],
        '',
        '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
    );
    $max = strlen($alphabet) - 1;

    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

// ─── CSRF ───────────────────────────────────────────────────────────────

function csrfToken(): string
{
    ensureSessionStarted();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(mixed $token): bool
{
    ensureSessionStarted();
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sessionToken) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function requireCsrf(): void
{
    if (!verifyCsrfToken(input('_csrf'))) {
        // 419 — нестандартный код (Laravel): Apache отдаёт его как 500.
        http_response_code(403);
        exit('403 Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
}

// ─── Долгоживущие cookie-токены ─────────────────────────────────────────
// Для состояния, которое должно пережить закрытие браузера — PHP-сессия
// его не переживает. Первый и пока единственный потребитель — токен
// корзины Гостя (Core/Cart.php, ADR-015), хелпер написан без привязки к
// корзине, чтобы не дублировать код при следующем похожем случае.

function getOrSetPersistentToken(string $cookieName, string $pattern, int $bytes, int $days): string
{
    $token = $_COOKIE[$cookieName] ?? null;
    if (is_string($token) && preg_match($pattern, $token) === 1) {
        return $token;
    }

    $token = bin2hex(random_bytes($bytes));

    if (!headers_sent()) {
        setcookie($cookieName, $token, [
            'expires'  => time() + $days * 86400,
            'path'     => '/',
            'secure'   => defined('APP_ENV') && APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    // Без этого следующий вызов в том же запросе не увидел бы токен —
    // setcookie() только ставит заголовок ответа, $_COOKIE обновится лишь
    // при следующем запросе браузера.
    $_COOKIE[$cookieName] = $token;

    return $token;
}

// ─── Антибот для публичных форм от незалогиненного посетителя ──────────
// Форма отзыва (add-review, phase-1.md Таск 8) — первая публичная форма
// от Гостя в проекте, поэтому хелпер заводится здесь впервые
// (dod-global.md, раздел «Безопасность»: honeypot — проверяется инлайн в
// контроллере по имени поля, минимальное время заполнения + одноразовый
// токен — здесь, в одной функции).

function generateFormToken(string $formName): string
{
    ensureSessionStarted();
    $token = bin2hex(random_bytes(16));
    $_SESSION['form_tokens'][$formName] = ['token' => $token, 'shown_at' => time()];
    return $token;
}

/**
 * Проверяет одноразовый токен показа формы и минимальное время
 * заполнения. Токен удаляется из сессии при любом исходе — повторная
 * отправка тем же токеном (в т.ч. после успеха) всегда отклоняется.
 */
function verifyFormToken(string $formName, ?string $token, int $minFillSeconds = 3): bool
{
    ensureSessionStarted();

    $stored = $_SESSION['form_tokens'][$formName] ?? null;
    unset($_SESSION['form_tokens'][$formName]);

    if (!is_array($stored) || !isset($stored['token'], $stored['shown_at'])) {
        return false;
    }
    if (!is_string($token) || $token === '' || !hash_equals((string) $stored['token'], $token)) {
        return false;
    }

    return time() - (int) $stored['shown_at'] >= $minFillSeconds;
}

// ─── Rate limiting ──────────────────────────────────────────────────────
// Файловый счётчик в storage/cache/rate-limit/ — без Redis/Memcached,
// подходит для shared-хостинга. Ключ = действие + IP клиента.

function rateLimitStoragePath(string $action): string
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = ROOT_PATH . '/storage/cache/rate-limit';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать директорию: {$dir}");
    }
    return $dir . '/' . sha1($action . '|' . $ip) . '.json';
}

function tooManyAttempts(string $action, int $maxAttempts, int $decaySeconds = 60): bool
{
    $path = rateLimitStoragePath($action);
    if (!is_file($path)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['count'], $data['first_at'])) {
        return false;
    }
    if (time() - (int) $data['first_at'] > $decaySeconds) {
        return false;
    }
    return (int) $data['count'] >= $maxAttempts;
}

function hitRateLimit(string $action): void
{
    $path = rateLimitStoragePath($action);
    $data = ['count' => 1, 'first_at' => time()];

    if (is_file($path)) {
        $existing = json_decode((string) file_get_contents($path), true);
        if (is_array($existing) && isset($existing['count'], $existing['first_at'])) {
            $data = $existing;
            $data['count'] = (int) $data['count'] + 1;
        }
    }

    file_put_contents($path, json_encode($data), LOCK_EX);
}

function clearRateLimit(string $action): void
{
    $path = rateLimitStoragePath($action);
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * Телефон РФ → `+7XXXXXXXXXX` (для сопоставления Обращения с Покупателем,
 * phase-5.md, Таск 7). Принимает +7 / 8 / 10 цифр без кода, пробелы, скобки,
 * дефисы; всё остальное — null (ник в Telegram, чужой формат).
 */
function normalizePhone(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';

    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        return '+7' . substr($digits, 1);
    }

    if (strlen($digits) === 10 && $digits[0] === '9') {
        return '+7' . $digits;
    }

    return null;
}

/**
 * Включённые Каналы инбокса из строки конфига `max,telegram,…`
 * (FR-CHANNELS-005): пробелы и регистр не важны, неизвестные коды и
 * повторы отбрасываются, порядок сохраняется.
 *
 * @param array<int, string> $known
 * @return array<int, string>
 */
function enabledChannels(string $configured, array $known = ['max', 'telegram', 'vk', 'avito']): array
{
    $codes = array_map(
        static fn (string $code): string => strtolower(trim($code)),
        explode(',', $configured)
    );

    return array_values(array_unique(array_filter(
        $codes,
        static fn (string $code): bool => in_array($code, $known, true)
    )));
}

/**
 * Мессенджеры, в которых можно открыть диалог с магазином (код → подпись).
 * Avito сюда не входит: это Канал входящих, ссылки на диалог у него нет.
 *
 * @return array<string, string>
 */
function messengerLabels(): array
{
    return ['telegram' => 'Telegram', 'max' => 'MAX', 'vk' => 'VK'];
}

/**
 * Мессенджеры для кнопки, запасного блока чата и соц-иконок (FR-NOTIF-003):
 * Каналы из `CHANNELS_ENABLED`, у которых задана ссылка. Порядок — как в
 * конфиге; каналы без подписи (Avito) и с пустой ссылкой отбрасываются.
 *
 * @param array<string, string> $urls   код → ссылка
 * @param array<string, string> $labels код → подпись
 * @return list<array{code: string, label: string, url: string}>
 */
function messengerLinks(string $enabled, array $urls, array $labels): array
{
    $links = [];

    foreach (enabledChannels($enabled, array_keys($labels)) as $code) {
        $url = trim($urls[$code] ?? '');

        // Проверка и при выводе: строка в БД могла появиться в обход формы.
        if (isSafeMessengerUrl($url)) {
            $links[] = ['code' => $code, 'label' => $labels[$code], 'url' => $url];
        }
    }

    return $links;
}

/**
 * Ссылка на мессенджер, пригодная для `href`: абсолютный `https://` с доменом
 * (`t.me`, `vk.com` — хост без точки вроде `https://vk/…` не принимается), без
 * пробелов и управляющих символов, не длиннее лимита. `javascript:`, `http://`
 * и прочие схемы отклоняются.
 */
function isSafeMessengerUrl(string $url, int $maxLength = 255): bool
{
    if ($url === '' || mb_strlen($url) > $maxLength || preg_match('/[\s\x00-\x1F\x7F]/u', $url) === 1) {
        return false;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);

    return is_array($parts)
        && ($parts['scheme'] ?? '') === 'https'
        && preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', (string) ($parts['host'] ?? '')) === 1;
}
