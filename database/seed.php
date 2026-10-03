<?php

declare(strict_types=1);

/**
 * Сид тестовых пользователей — по одному на каждую роль users.role.
 * Запускать из CLI: php database/seed.php
 * Идемпотентно — INSERT ... ON DUPLICATE KEY UPDATE по email, повторный
 * запуск не плодит дубли и пересинхронизирует пароль при смене в .env.
 *
 * Пароль общий для всех тестовых пользователей — SEED_USER_PASSWORD
 * в .env (только local, не деплоится на прод — см. .env.example).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Доступ только из командной строки: php database/seed.php');
}

require_once dirname(__DIR__) . '/config/config.php';

if (APP_ENV === 'production') {
    fwrite(STDERR, "Сид тестовых пользователей не запускается при APP_ENV=production.\n");
    exit(1);
}

$password = env('SEED_USER_PASSWORD', '');
if (strlen($password) < 8) {
    fwrite(STDERR, "SEED_USER_PASSWORD не задан или короче 8 символов — заполните .env.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Роли — полный ENUM users.role (database.md).
$seedUsers = [
    ['name' => 'Виктория Смирнова', 'email' => 'owner@petpark.test',          'phone' => null,             'role' => 'owner'],
    ['name' => 'Администратор смены', 'email' => 'shift-admin@petpark.test',  'phone' => null,             'role' => 'shift_admin'],
    ['name' => 'Контент-редактор',  'email' => 'content-editor@petpark.test', 'phone' => null,             'role' => 'content_editor'],
    ['name' => 'Специалист Груминг', 'email' => 'specialist@petpark.test',    'phone' => null,             'role' => 'specialist'],
    ['name' => 'Тестовый покупатель', 'email' => 'customer@petpark.test',     'phone' => '+79000000000',   'role' => 'customer'],
    ['name' => 'Специалист Груминг 2', 'email' => 'groomer2@petpark.test',    'phone' => null,             'role' => 'specialist'],
    ['name' => 'Ветеринарный врач',   'email' => 'vet@petpark.test',          'phone' => null,             'role' => 'specialist'],
];

// Услуги (ADR-013): цены и длительности — демо-значения, в ТЗ их нет.
// Депозит — только груминг (tz.md §6.1).
// description — черновики исполнителя, Владелец проверяет (Q-046); цену и длительность в текст не пишем.
$seedServices = [
    [
        'name' => 'Гигиеническая стрижка', 'slug' => 'gigienicheskaya-strizhka', 'kind' => 'grooming',
        'duration' => 60, 'price' => '1500.00', 'deposit' => '500.00',
        'description' => 'Аккуратно подстрижём шерсть в гигиенических зонах: вокруг глаз, ушей, лап и под хвостом. '
            . 'Процедура делает уход за питомцем проще, помогает избежать колтунов и поддерживает чистоту шерсти между полными стрижками.',
    ],
    [
        'name' => 'Полная стрижка и мытьё', 'slug' => 'polnaya-strizhka-i-myitye', 'kind' => 'grooming',
        'duration' => 120, 'price' => '2500.00', 'deposit' => '500.00',
        'description' => 'Комплексный уход: купание с подходящей косметикой, сушка, вычёсывание и стрижка по породе или вашему пожеланию. '
            . 'Мастер учтёт тип шерсти и характер питомца, чтобы визит прошёл спокойно.',
    ],
    [
        'name' => 'Тримминг', 'slug' => 'trimming', 'kind' => 'grooming',
        'duration' => 90, 'price' => '2000.00', 'deposit' => '500.00',
        'description' => 'Удаление отмершей шерсти вручную или специальным инструментом для жёсткошёрстных пород. '
            . 'Тримминг сохраняет структуру и цвет шерсти, помогает коже дышать и уменьшает линьку.',
    ],
    [
        'name' => 'Экспресс-мытьё', 'slug' => 'ekspress-myitye', 'kind' => 'grooming',
        'duration' => 45, 'price' => '1000.00', 'deposit' => '500.00',
        'description' => 'Быстрое купание с мягким шампунем, сушка и расчёсывание. '
            . 'Подходит, когда питомец испачкался на прогулке или нужно освежить шерсть перед важным событием.',
    ],
    [
        'name' => 'Ветконсультация', 'slug' => 'vetkonsultatsiya', 'kind' => 'vet',
        'duration' => 30, 'price' => '800.00', 'deposit' => null,
        'description' => 'Приём ветеринарного врача: осмотр питомца, ответы на вопросы о здоровье, питании и поведении, рекомендации по дальнейшему уходу. '
            . 'Если потребуется лечение или обследование, врач подскажет, что делать дальше.',
    ],
    [
        'name' => 'Вакцинация', 'slug' => 'vaktsinatsiya', 'kind' => 'vet',
        'duration' => 30, 'price' => '1200.00', 'deposit' => null,
        'description' => 'Плановая вакцинация питомца после осмотра врача. '
            . 'Врач подберёт схему прививок по возрасту и состоянию здоровья и внесёт отметку в ветеринарный паспорт.',
    ],
];

// Специалисты: day_off 0 = воскресенье (у ветврача, tz.md §6.1), null = без выходного.
$seedSpecialists = [
    ['email' => 'specialist@petpark.test', 'day_off' => null, 'kind' => 'grooming'],
    ['email' => 'groomer2@petpark.test',   'day_off' => null, 'kind' => 'grooming'],
    ['email' => 'vet@petpark.test',        'day_off' => 0,    'kind' => 'vet'],
];

$pdo = getPdo();

$statement = $pdo->prepare('
    INSERT INTO users (name, email, phone, password_hash, role)
    VALUES (:name, :email, :phone, :password_hash, :role)
    ON DUPLICATE KEY UPDATE
        name          = VALUES(name),
        phone         = VALUES(phone),
        password_hash = VALUES(password_hash),
        role          = VALUES(role)
');

foreach ($seedUsers as $user) {
    $statement->execute([
        'name'          => $user['name'],
        'email'         => $user['email'],
        'phone'         => $user['phone'],
        'password_hash' => $passwordHash,
        'role'          => $user['role'],
    ]);
}

echo "✅ Тестовые пользователи созданы/обновлены (" . count($seedUsers) . ").\n";

// У services нет UNIQUE по названию — идемпотентность через поиск по name.
$findService = $pdo->prepare('SELECT id FROM services WHERE name = :name');
$insertService = $pdo->prepare('
    INSERT INTO services (name, slug, kind, duration_minutes, price, deposit_amount, description)
    VALUES (:name, :slug, :kind, :duration, :price, :deposit, :description)
');
$updateService = $pdo->prepare('
    UPDATE services
    SET slug = :slug, kind = :kind, duration_minutes = :duration, price = :price,
        deposit_amount = :deposit, description = :description
    WHERE id = :id
');

$serviceIdsByKind = ['grooming' => [], 'vet' => []];
foreach ($seedServices as $service) {
    $fields = [
        'slug'        => $service['slug'],
        'kind'        => $service['kind'],
        'duration'    => $service['duration'],
        'price'       => $service['price'],
        'deposit'     => $service['deposit'],
        'description' => $service['description'],
    ];

    $findService->execute(['name' => $service['name']]);
    $serviceId = $findService->fetchColumn();

    if ($serviceId === false) {
        $insertService->execute($fields + ['name' => $service['name']]);
        $serviceId = $pdo->lastInsertId();
    } else {
        $updateService->execute($fields + ['id' => $serviceId]);
    }

    $serviceIdsByKind[$service['kind']][] = (int) $serviceId;
}

$findUser = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$upsertSpecialist = $pdo->prepare('
    INSERT INTO specialists (user_id, day_off)
    VALUES (:user_id, :day_off)
    ON DUPLICATE KEY UPDATE day_off = VALUES(day_off)
');
$findSpecialist = $pdo->prepare('SELECT id FROM specialists WHERE user_id = :user_id');
$linkService = $pdo->prepare('
    INSERT IGNORE INTO specialist_services (specialist_id, service_id)
    VALUES (:specialist_id, :service_id)
');

foreach ($seedSpecialists as $specialist) {
    $findUser->execute(['email' => $specialist['email']]);
    $userId = (int) $findUser->fetchColumn();

    $upsertSpecialist->execute(['user_id' => $userId, 'day_off' => $specialist['day_off']]);
    $findSpecialist->execute(['user_id' => $userId]);
    $specialistId = (int) $findSpecialist->fetchColumn();

    foreach ($serviceIdsByKind[$specialist['kind']] as $serviceId) {
        $linkService->execute(['specialist_id' => $specialistId, 'service_id' => $serviceId]);
    }
}

echo "✅ Услуги и специалисты созданы/обновлены (" . count($seedServices) . " услуг, "
    . count($seedSpecialists) . " специалиста).\n";

// Обращения единого инбокса (phase-5.md, Таск 7; FR-CHANNELS-001). Каналы —
// заглушка (ADR-001): тестовая переписка. Идемпотентно по (channel,
// external_conversation_id) — сообщения тредов пересоздаются, время считается
// от «сейчас», чтобы список выглядел свежим. Телефон Покупателя записан в
// разных форматах — сопоставление идёт через normalizePhone().
// minutes_ago — возраст сообщения; direction: in / out.
$seedConversations = [
    [
        'channel' => 'telegram', 'external' => 'tg-demo-1', 'contact' => '8 (900) 000-00-00',
        'sender' => 'Тестовый', 'is_read' => 0,
        'messages' => [
            ['in', 'Здравствуйте! Есть корм для стерилизованной кошки, 2 кг?', 35],
            ['out', 'Добрый день! Да, посмотрите в разделе «Корма для кошек».', 30],
            ['in', 'Хочу заказать два пакета, можно через вас?', 4],
        ],
    ],
    [
        'channel' => 'vk', 'external' => 'vk-demo-1', 'contact' => '+7 900 000-00-00',
        'sender' => 'Тест Т.', 'is_read' => 1,
        'messages' => [
            ['in', 'Подскажите, до скольки работает груминг в субботу?', 180],
            ['out', 'В субботу принимаем с 10:00 до 19:00.', 170],
        ],
    ],
    [
        'channel' => 'telegram', 'external' => 'tg-demo-2', 'contact' => '@marina_cat',
        'sender' => 'Марина', 'is_read' => 0,
        'messages' => [
            ['in', 'Добрый вечер! Нужен наполнитель комкующийся, 10 литров.', 12],
        ],
    ],
    [
        'channel' => 'max', 'external' => 'max-demo-1', 'contact' => '+7 918 555-12-34',
        'sender' => 'Алексей', 'is_read' => 0,
        'messages' => [
            ['in', 'Привет! Привезёте корм для собаки до Западного микрорайона?', 95],
            ['out', 'Здравствуйте! Да, доставка по Ростову курьером.', 90],
            ['in', 'Отлично, тогда оформляю. Крупные гранулы, 12 кг.', 60],
        ],
    ],
    [
        'channel' => 'avito', 'external' => 'avito-demo-1', 'contact' => 'Объявление №48213',
        'sender' => 'Ольга (Avito)', 'is_read' => 1,
        'messages' => [
            ['in', 'Клетка для попугая ещё в наличии?', 1500],
            ['out', 'Здравствуйте! Да, есть, самовывоз или доставка.', 1440],
        ],
    ],
    [
        'channel' => 'avito', 'external' => 'avito-demo-2', 'contact' => 'Объявление №48977',
        'sender' => 'Дмитрий (Avito)', 'is_read' => 0,
        'messages' => [
            ['in', 'Сколько стоит когтеточка и можно ли забрать сегодня?', 22],
        ],
    ],
    [
        'channel' => 'vk', 'external' => 'vk-demo-2', 'contact' => null,
        'sender' => null, 'is_read' => 1,
        'messages' => [
            ['in', 'Принимаете ли вы щенков на первую стрижку?', 2880],
            ['out', 'Да, записывайтесь через сайт, подберём мастера.', 2800],
        ],
    ],
];

$upsertConversation = $pdo->prepare('
    INSERT INTO conversations (channel, external_conversation_id, user_id, contact_identifier, sender_name, is_read)
    VALUES (:channel, :external, :user_id, :contact, :sender, :is_read)
    ON DUPLICATE KEY UPDATE
        user_id            = VALUES(user_id),
        contact_identifier = VALUES(contact_identifier),
        sender_name        = VALUES(sender_name),
        is_read            = VALUES(is_read)
');
$findConversation = $pdo->prepare('
    SELECT id FROM conversations WHERE channel = :channel AND external_conversation_id = :external
');
$clearMessages = $pdo->prepare('DELETE FROM conversation_messages WHERE conversation_id = :id');
$insertMessage = $pdo->prepare('
    INSERT INTO conversation_messages (conversation_id, direction, body, sent_at)
    VALUES (:id, :direction, :body, NOW() - INTERVAL :minutes MINUTE)
');

foreach ($seedConversations as $conversation) {
    $userId = $conversation['contact'] !== null
        ? conversationFindUserIdByPhone($conversation['contact'])
        : null;

    $upsertConversation->execute([
        'channel'  => $conversation['channel'],
        'external' => $conversation['external'],
        'user_id'  => $userId,
        'contact'  => $conversation['contact'],
        'sender'   => $conversation['sender'],
        'is_read'  => $conversation['is_read'],
    ]);

    $findConversation->execute(['channel' => $conversation['channel'], 'external' => $conversation['external']]);
    $conversationId = (int) $findConversation->fetchColumn();

    $clearMessages->execute(['id' => $conversationId]);
    foreach ($conversation['messages'] as [$direction, $body, $minutesAgo]) {
        $insertMessage->bindValue('id', $conversationId, PDO::PARAM_INT);
        $insertMessage->bindValue('direction', $direction);
        $insertMessage->bindValue('body', $body);
        $insertMessage->bindValue('minutes', $minutesAgo, PDO::PARAM_INT);
        $insertMessage->execute();
    }
}

echo "✅ Обращения инбокса созданы/обновлены (" . count($seedConversations) . ").\n";

// ─── Статические страницы (phase-8.md, Таск 1) ──────────────────────────
// slug UNIQUE: повторный запуск не дублирует строку и не перезаписывает
// правки текста; фото страницы добавляются, только пока у неё нет ни одного.

const CONTENT_PAGE_IMAGE_SOURCE_DIR = '/public/assets/img/home-page/';
const CONTENT_PAGE_UPLOAD_DIR = 'content-pages';

$seedContentPages = require __DIR__ . '/seed-data/content-pages.php';

$insertContentPage = $pdo->prepare('
    INSERT INTO content_pages (slug, title, body)
    VALUES (:slug, :title, :body)
    ON DUPLICATE KEY UPDATE slug = slug
');
$findContentPage = $pdo->prepare('SELECT id FROM content_pages WHERE slug = :slug');
$countContentPageImages = $pdo->prepare('SELECT COUNT(*) FROM content_page_images WHERE content_page_id = :id');
$insertContentPageImage = $pdo->prepare('
    INSERT INTO content_page_images (content_page_id, path, sort_order)
    VALUES (:id, :path, :sort_order)
');

foreach ($seedContentPages as $contentPage) {
    $insertContentPage->execute([
        'slug'  => $contentPage['slug'],
        'title' => $contentPage['title'],
        'body'  => $contentPage['body'],
    ]);
    $findContentPage->execute(['slug' => $contentPage['slug']]);
    $contentPageId = (int) $findContentPage->fetchColumn();

    $countContentPageImages->execute(['id' => $contentPageId]);
    if ($contentPage['images'] === [] || (int) $countContentPageImages->fetchColumn() > 0) {
        continue;
    }

    $relativeDir = CONTENT_PAGE_UPLOAD_DIR . '/' . $contentPage['slug'];
    $targetDir = ROOT_PATH . '/public/uploads/' . $relativeDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        fwrite(STDERR, "Не удалось создать {$targetDir}\n");
        exit(1);
    }

    foreach ($contentPage['images'] as $sortOrder => $fileName) {
        if (!copy(ROOT_PATH . CONTENT_PAGE_IMAGE_SOURCE_DIR . $fileName, $targetDir . '/' . $fileName)) {
            fwrite(STDERR, "Не удалось скопировать {$fileName}\n");
            exit(1);
        }
        $insertContentPageImage->execute([
            'id'         => $contentPageId,
            'path'       => $relativeDir . '/' . $fileName,
            'sort_order' => $sortOrder,
        ]);
    }
}

echo "✅ Статические страницы созданы (" . count($seedContentPages) . ").\n";
