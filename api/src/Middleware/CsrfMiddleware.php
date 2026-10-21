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
 * Exige header X-CSRF-Token igual ao token da sessão em métodos
 * que alteram estado (POST/PUT/PATCH/DELETE).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(Request $request, Handler $handler): Response
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $token = $request->getHeaderLine('X-CSRF-Token');
        $sessToken = $_SESSION['csrf'] ?? '';

        if ($sessToken === '' || !hash_equals($sessToken, $token)) {
            return Json::erro(new SlimResponse(), 'CSRF_INVALIDO', 'Token CSRF ausente ou inválido.', 403);
        }
        return $handler->handle($request);
    }
}
