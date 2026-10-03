<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Каждый маршрут из config/routes.php указывает на существующий метод контроллера.
 * Без этого теста пропавший метод проявляется только 500-й ошибкой при переходе по ссылке.
 * Контроллеры не загружаются (у них нет Composer-автозагрузки) — проверяется исходный код.
 */
final class RoutesTest extends TestCase
{
    public function testEveryRouteHandlerExists(): void
    {
        /** @var array<string, array<string, array{0: string, 1: string}>> $routes */
        $routes = require ROOT_PATH . '/config/routes.php';

        $missing = [];
        foreach ($routes as $method => $paths) {
            foreach ($paths as $path => [$controller, $action]) {
                $file = ROOT_PATH . '/src/Controllers/' . str_replace('\\', '/', $controller) . '.php';

                $found = is_file($file)
                    && preg_match('/function\s+' . preg_quote($action, '/') . '\s*\(/', (string) file_get_contents($file)) === 1;

                if (!$found) {
                    $missing[] = "{$method} {$path} → {$controller}::{$action}()";
                }
            }
        }

        $this->assertSame([], $missing, "Маршруты без обработчика:\n" . implode("\n", $missing));
    }
}
