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

require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Seo.php';
require_once ROOT_PATH . '/src/Core/Catalog.php';
require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Models/Favorite.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Services/Mailer.php';
