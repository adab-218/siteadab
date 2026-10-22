<?php

declare(strict_types=1);

use Adab\Api\Database;
use Adab\Api\Json;

require __DIR__ . '/vendor/autoload.php';

// ---------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    Json::emit(['error' => [
        'code' => 'CONFIG_MISSING',
        'message' => 'config.php ausente — copie config.example.php (dev) ou rode o deploy (prod).',
    ]], 500);
}

/** @var array $config */
$config = require $configFile;

// ---------------------------------------------------------------------
// Sessão — somente onde é necessária (rotas /auth, /admin e protegidas).
// /api/health e /api/public/* não criam sessão nem Set-Cookie.
// ---------------------------------------------------------------------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$precisaSessao = $requestPath !== '/api/health'
    && !str_starts_with($requestPath, '/api/public');

if ($precisaSessao) {
    $savePath = $config['session_save_path'] ?? null;
    if (is_string($savePath) && $savePath !== '') {
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0700, true);
        }
        if (is_dir($savePath)) {
            session_save_path($savePath);
        }
    }
    session_name('adab_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https,
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// App
// ---------------------------------------------------------------------
try {
    $pdo = Database::pdo($config['db'] ?? []);
} catch (Throwable $e) {
    error_log('[adab-api] bootstrap: ' . $e->getMessage());
    Json::emit(['error' => [
        'code' => 'INDISPONIVEL',
        'message' => 'Serviço indisponível no momento.',
    ]], 503);
}

$app = Adab\Api\criarApp($pdo, $config);
$app->run();
