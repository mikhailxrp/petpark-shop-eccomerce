<?php

declare(strict_types=1);

/**
 * Константы и настройки приложения.
 * Секреты — только через .env, не здесь.
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/Core/functions.php';

loadEnv(ROOT_PATH . '/.env');

define('APP_ENV',  env('APP_ENV', 'production'));
define('APP_URL',  env('APP_URL', ''));

// SEO-фолбэк (Core/Seo.php) — название магазина и город, ADR-004
define('SHOP_NAME', env('SHOP_NAME', 'PetPark'));
define('SHOP_CITY', env('SHOP_CITY', 'Ростов-на-Дону'));

// Логгер
define('LOG_DIR',       env('LOG_DIR',       ROOT_PATH . '/storage/logs'));
define('LOG_FILE',      env('LOG_FILE',      'app.log'));
define('APP_LOG_LEVEL', env('APP_LOG_LEVEL', 'error'));

// Доставка и Заказ (Core/Order.php) — BR-006, BR-003, FR-SHIP-004.
// Деньги — строки DECIMAL, не float (ADR-022)
define('DELIVERY_FREE_THRESHOLD', '2000.00');
define('DELIVERY_COURIER_COST',   '300.00');
define('ORDER_RESERVE_MINUTES',   30);
define('ORDER_UNCLAIMED_DAYS',    3); // FR-ORD-007: срок ожидания получения Заказа
define('SHOP_PICKUP_ADDRESS', env('SHOP_PICKUP_ADDRESS', 'Ростов-на-Дону, адрес магазина уточняется'));
define('SHOP_PICKUP_HOURS',   '10:00–20:00, без выходных');

// Запись на Услугу (Core/Booking.php) — Q-037, FR-SV-008. Горизонт, шаг сетки
// и буфер груминга — константы самого Core/Booking.php.
define('BOOKING_SLOT_HOLD_MINUTES',    30); // FR-SV-009: срок оплаты Депозита
define('BOOKING_CANCEL_THRESHOLD_HOURS', 3); // FR-SV-008: отмена не позже чем за N часов

// ИИ-ядро (Core/Ai.php, Services/Ai) — BR-AI-001, NFR-AI §11.8. Провайдер на
// класс задачи — .env, не БД (Q-DEV-002). Класс «с ПДн» берёт
// AI_PROVIDER_PERSONAL, при его отсутствии — AI_PROVIDER_USER_INPUT.
define('AI_PROVIDER_ANONYMOUS', env('AI_PROVIDER_ANONYMOUS', 'yandexgpt'));
define('AI_PROVIDER_PERSONAL',  env('AI_PROVIDER_PERSONAL', env('AI_PROVIDER_USER_INPUT', 'yandexgpt')));
define('AI_MONTHLY_LIMIT_RUB',  '10000.00'); // §11.8: на все 4 помощника суммарно
define('AI_PRICE_PER_1K_TOKENS', env('AI_PRICE_PER_1K_TOKENS', '0.8000')); // ₽ за 1000 токенов, сверить с тарифом
define('AI_TIMEOUT_SECONDS',    20);

// Консультант в чате (Services/Ai/Consultant.php, FR-AI-003). Демо-режим: при
// CHAT_LIMIT_REQUESTS=true — не более CHAT_DEMO_MAX_REQUESTS вопросов на
// посетителя (по cookie). После публикации проекта включить в .env.
define('CHAT_LIMIT_REQUESTS',     filter_var(env('CHAT_LIMIT_REQUESTS', 'false'), FILTER_VALIDATE_BOOLEAN));
define('CHAT_DEMO_MAX_REQUESTS',  10);
define('CHAT_TIMEOUT_SECONDS',    5);
define('CHAT_MAX_QUESTION_LENGTH', 500);
define('CHAT_HISTORY_MESSAGES',   10);
define('CHAT_FALLBACK_LINKS', [
    'Telegram' => env('CHAT_LINK_TELEGRAM', ''),
    'MAX'      => env('CHAT_LINK_MAX', ''),
    'VK'       => env('CHAT_LINK_VK', ''),
]);

// Единый инбокс (FR-CHANNELS-002/005). Включённые Каналы — .env: Канал без
// подтверждённого доступа к API не показывается в панели (FR-CHANNELS-005).
define('CHANNELS_ENABLED',                  env('CHANNELS_ENABLED', 'max,telegram,vk,avito'));
define('CHANNEL_POLL_INTERVAL_SECONDS',     5);
define('CHANNEL_REPLY_MAX_LENGTH',          2000);

require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Seo.php';
require_once ROOT_PATH . '/src/Core/Catalog.php';
require_once ROOT_PATH . '/src/Core/Cache.php';
require_once ROOT_PATH . '/src/Core/Order.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/Booking.php';
require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/Pii.php';
require_once ROOT_PATH . '/src/Core/Notification.php';
require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Models/Favorite.php';
require_once ROOT_PATH . '/src/Models/Cart.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Models/Pet.php';
require_once ROOT_PATH . '/src/Models/Service.php';
require_once ROOT_PATH . '/src/Models/Booking.php';
require_once ROOT_PATH . '/src/Models/Specialist.php';
require_once ROOT_PATH . '/src/Models/AiCall.php';
require_once ROOT_PATH . '/src/Models/AttributeDraft.php';
require_once ROOT_PATH . '/src/Models/Conversation.php';
require_once ROOT_PATH . '/src/Models/Notification.php';
require_once ROOT_PATH . '/src/Services/Mailer.php';
require_once ROOT_PATH . '/src/Services/Notifier.php';
require_once ROOT_PATH . '/src/Services/ChannelGateway.php';
require_once ROOT_PATH . '/src/Services/Ai/AiProvider.php';
require_once ROOT_PATH . '/src/Services/Ai/OfflineProvider.php';
require_once ROOT_PATH . '/src/Services/Ai/YandexGptProvider.php';
require_once ROOT_PATH . '/src/Services/Ai/AiClient.php';
require_once ROOT_PATH . '/src/Services/Ai/AttributeExtractor.php';
require_once ROOT_PATH . '/src/Services/Ai/DescriptionGenerator.php';
require_once ROOT_PATH . '/src/Services/Ai/Consultant.php';
require_once ROOT_PATH . '/src/Services/Ai/OrderDraftParser.php';
