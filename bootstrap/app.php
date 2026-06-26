<?php

declare(strict_types=1);

use App\Support\LoginSessionSetting;

require_once __DIR__ . '/autoload.php';
require_once dirname(__DIR__) . '/core/helpers.php';

$sessionLifetimeSeconds = LoginSessionSetting::getSeconds();
ini_set('session.gc_maxlifetime', (string) $sessionLifetimeSeconds);
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $sessionLifetimeSeconds,
    'path' => $cookieParams['path'] ?? '/',
    'domain' => $cookieParams['domain'] ?? '',
    'secure' => $cookieParams['secure'] ?? false,
    'httponly' => $cookieParams['httponly'] ?? true,
    'samesite' => $cookieParams['samesite'] ?? 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app = new Core\Application(dirname(__DIR__));

$app->bootstrap();
$app->loadRoutesFrom($app->path('routes/web.php'));

return $app;
