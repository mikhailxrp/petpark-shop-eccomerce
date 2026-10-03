<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/Core/functions.php';
require_once ROOT_PATH . '/src/Core/Router.php';
require_once ROOT_PATH . '/src/Core/Seo.php';
require_once ROOT_PATH . '/src/Core/ContentHtml.php';
require_once ROOT_PATH . '/src/Core/Catalog.php';
require_once ROOT_PATH . '/src/Core/Cache.php';
require_once ROOT_PATH . '/src/Core/Order.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/Booking.php';
require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/Notification.php';
require_once ROOT_PATH . '/src/Core/OrderReturn.php';
require_once ROOT_PATH . '/src/Core/Product.php';
require_once ROOT_PATH . '/src/Core/ProductVariant.php';
require_once ROOT_PATH . '/src/Core/Report.php';
require_once ROOT_PATH . '/src/Core/Client.php';
require_once ROOT_PATH . '/src/Core/ContactForm.php';
require_once ROOT_PATH . '/src/Core/ContentPageForm.php';
require_once ROOT_PATH . '/src/Services/FileUpload.php';

// requireRole() сверяется с БД через Models/User.php, которую unit-тесты не
// подключают (нет БД) — заглушка: любая учётная запись активна.
function userIsActive(int $userId): bool
{
    return true;
}
