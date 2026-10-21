<?php

declare(strict_types=1);

namespace Adab\Api;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Helpers de resposta JSON padronizada.
 */
final class Json
{
    public static function resposta(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')
                   ->withStatus($status);
    }

    /** Emite JSON diretamente (bootstrap, antes do app existir) e encerra. */
    public static function emit(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function erro(Response $res, string $code, string $message, int $status): Response
    {
        return self::resposta($res, ['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
