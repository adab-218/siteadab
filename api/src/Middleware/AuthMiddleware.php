<?php

declare(strict_types=1);

namespace Adab\Api\Middleware;

use Adab\Api\Json;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Exige sessão autenticada (401 caso contrário).
 * Revalida o usuário no banco a cada request: desativação ou troca
 * de perfil passam a valer imediatamente (sem esperar expirar sessão).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $usuario = $_SESSION['usuario'] ?? null;
        if (empty($usuario['id'])) {
            return Json::erro(new SlimResponse(), 'NAO_AUTENTICADO', 'Login necessário.', 401);
        }

        $stmt = $this->pdo->prepare('SELECT ativo, perfil FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario['id']]);
        $linha = $stmt->fetch();

        if (!$linha || !$linha['ativo']) {
            $_SESSION = [];
            return Json::erro(new SlimResponse(), 'NAO_AUTENTICADO', 'Sessão encerrada.', 401);
        }

        // Mantém perfil da sessão sincronizado com o banco
        $_SESSION['usuario']['perfil'] = $linha['perfil'];

        return $handler->handle($request);
    }
}
