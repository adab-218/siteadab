<?php

declare(strict_types=1);

use Adab\Api\Controllers\AdminController;
use Adab\Api\Controllers\AuthController;
use Adab\Api\Controllers\PublicController;
use Adab\Api\Database;
use Adab\Api\Json;
use Adab\Api\Middleware\AuthMiddleware;
use Adab\Api\Middleware\CsrfMiddleware;
use Adab\Api\Middleware\PerfilMiddleware;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require dirname(__DIR__) . '/vendor/autoload.php';

// ---------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    Json::emit(['error' => [
        'code' => 'CONFIG_MISSING',
        'message' => 'config.php ausente — copie config.example.php (dev) ou rode o deploy (prod).',
    ]], 500);
}

/** @var array $config */
$config = require $configFile;

// ---------------------------------------------------------------------
// Sessão (cookie httpOnly + SameSite; Secure apenas sob HTTPS)
// ---------------------------------------------------------------------
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('adab_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https,
]);
session_start();

// ---------------------------------------------------------------------
// App
// ---------------------------------------------------------------------
$pdo = Database::pdo($config['db']);
$app = AppFactory::create();
$app->setBasePath('/api');
$app->addBodyParsingMiddleware();

$app->get('/health', fn($req, $res) => Json::resposta($res, ['data' => ['ok' => true]]));

// Auth
$app->group('/auth', function (RouteCollectorProxy $g) use ($pdo, $config) {
    $auth = new AuthController($pdo, $config);
    $g->post('/login', [$auth, 'login']);
    $g->post('/logout', [$auth, 'logout']);
    $g->get('/me', [$auth, 'me'])->add(new AuthMiddleware());
    $g->post('/recuperar', [$auth, 'recuperar']);
    $g->post('/redefinir', [$auth, 'redefinir']);
});

// Site público — sem autenticação, dados públicos apenas
$app->group('/public', function (RouteCollectorProxy $g) use ($pdo) {
    $pub = new PublicController($pdo);
    $g->get('/programacao', [$pub, 'programacao']);
    $g->get('/eventos', [$pub, 'eventos']);
});

// Admin
$app->group('/admin', function (RouteCollectorProxy $g) use ($pdo, $config) {
    $admin = new AdminController($pdo, $config);
    // No Slim, o último middleware adicionado executa primeiro:
    // token migração OU sessão admin são verificados dentro da rota.
    $g->post('/migrate', [$admin, 'migrate']);
});

// CSRF em mutações autenticadas (rotas futuras entram neste grupo)
$app->group('', function (RouteCollectorProxy $g) use ($pdo) {
    // Módulos serão registrados aqui, ex.:
    // $g->group('/membros', ...)  ->add(PerfilMiddleware::para([...]))
})->add(new CsrfMiddleware())->add(new AuthMiddleware());

// ---------------------------------------------------------------------
// Erros — sempre JSON
// ---------------------------------------------------------------------
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(function ($request, Throwable $e) use ($app) {
    $status = 500;
    if ($e instanceof Slim\Exception\HttpException) {
        $status = $e->getCode() ?: 500;
    }
    error_log('[adab-api] ' . $e->getMessage());
    $response = $app->getResponseFactory()->createResponse();
    return Json::resposta($response, ['error' => [
        'code' => $status === 404 ? 'NOT_FOUND' : 'ERRO_INTERNO',
        'message' => $status === 404 ? 'Rota não encontrada.' : 'Erro interno do servidor.',
    ]], $status);
});

$app->run();
