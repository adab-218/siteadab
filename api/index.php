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

$app = AppFactory::create();
$app->setBasePath('/api');
$app->addBodyParsingMiddleware();

$app->get('/health', fn($req, $res) => Json::resposta($res, ['data' => ['ok' => true]]));

// Auth
$app->group('/auth', function (RouteCollectorProxy $g) use ($pdo, $config) {
    $auth = new AuthController($pdo, $config);
    $g->post('/login', [$auth, 'login']);
    $g->post('/logout', [$auth, 'logout']);
    $g->get('/me', [$auth, 'me'])->add(new AuthMiddleware($pdo));
    $g->post('/recuperar', [$auth, 'recuperar']);
    $g->post('/redefinir', [$auth, 'redefinir']);
});

// Site público — sem autenticação, dados públicos apenas
$app->group('/public', function (RouteCollectorProxy $g) use ($pdo) {
    $pub = new PublicController($pdo);
    $g->get('/programacao', [$pub, 'programacao']);
    $g->get('/eventos', [$pub, 'eventos']);
});

// Admin — POST /migrate: sessão admin OU X-Migrate-Token (deploy)
$app->group('/admin', function (RouteCollectorProxy $g) use ($pdo, $config) {
    $admin = new AdminController($pdo, $config);
    $g->post('/migrate', [$admin, 'migrate']);
});

// Rotas autenticadas: TODA rota de módulo deve ser registrada neste
// grupo (CSRF + Auth aplicados na ordem correta pelo encadeamento).
$app->group('', function (RouteCollectorProxy $g) use ($pdo) {
    // Módulos serão registrados aqui, ex.:
    // $g->group('/membros', ...)  ->add(PerfilMiddleware::para([...]))
})->add(new CsrfMiddleware())->add(new AuthMiddleware($pdo));

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

    $codigos = [
        400 => ['REQUISICAO_INVALIDA', 'Requisição inválida.'],
        404 => ['NOT_FOUND', 'Rota não encontrada.'],
        405 => ['METODO_NAO_PERMITIDO', 'Método não permitido.'],
    ];
    [$code, $message] = $codigos[$status] ?? ['ERRO_INTERNO', 'Erro interno do servidor.'];

    $response = $app->getResponseFactory()->createResponse();
    return Json::resposta($response, ['error' => [
        'code' => $code,
        'message' => $message,
    ]], $status);
});

$app->run();
