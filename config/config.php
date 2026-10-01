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

require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Seo.php';
require_once ROOT_PATH . '/src/Core/Catalog.php';
require_once ROOT_PATH . '/src/Core/Cache.php';
require_once ROOT_PATH . '/src/Core/Order.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/Booking.php';
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
require_once ROOT_PATH . '/src/Services/Mailer.php';
