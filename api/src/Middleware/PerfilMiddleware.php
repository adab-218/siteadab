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
 * Restringe a rota a uma lista de perfis (403 fora deles).
 * Deve ser adicionado DEPOIS do AuthMiddleware na cadeia:
 * no Slim o último ->add() executa primeiro, então faça
 * ->add(PerfilMiddleware::para([...]))->add(new AuthMiddleware()).
 */
final class PerfilMiddleware implements MiddlewareInterface
{
    /** @param string[] $perfis */
    public function __construct(private array $perfis)
    {
    }

    /** @param string[] $perfis */
    public static function para(array $perfis): self
    {
        return new self($perfis);
    }

    public function process(Request $request, Handler $handler): Response
    {
        $perfil = $_SESSION['usuario']['perfil'] ?? null;
        if ($perfil === 'admin' || ($perfil !== null && in_array($perfil, $this->perfis, true))) {
            return $handler->handle($request);
        }
        return Json::erro(new SlimResponse(), 'SEM_PERMISSAO', 'Perfil sem acesso a este recurso.', 403);
    }
}
