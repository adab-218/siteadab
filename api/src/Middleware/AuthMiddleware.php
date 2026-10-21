<?php

declare(strict_types=1);

namespace Adab\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;
use Adab\Api\Json;

/**
 * Exige sessão autenticada (401 caso contrário).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if (empty($_SESSION['usuario'])) {
            return Json::erro(new SlimResponse(), 'NAO_AUTENTICADO', 'Login necessário.', 401);
        }
        return $handler->handle($request);
    }
}
