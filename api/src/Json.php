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
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"error":{"code":"ERRO_INTERNO","message":"Erro ao serializar resposta."}}';
            $status = 500;
        }
        $res->getBody()->write($json);
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')
                   ->withHeader('Cache-Control', 'no-store')
                   ->withStatus($status);
    }

    /** Emite JSON diretamente (bootstrap, antes do app existir) e encerra. */
    public static function emit(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function erro(Response $res, string $code, string $message, int $status): Response
    {
        return self::resposta($res, ['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /**
     * Resposta paginada padrão do contrato (ver dev/docs/06):
     *   {data: {itens: [...], pagina, por_pagina, total}}
     *
     * @param array<int,mixed> $itens
     */
    public static function paginada(Response $res, array $itens, int $total, int $pagina, int $porPagina): Response
    {
        return self::resposta($res, ['data' => [
            'itens' => $itens,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
        ]]);
    }

    /**
     * Normaliza ?pagina= e ?por_pagina= da query string.
     * por_pagina limitado a $max (padrão 50, mínimo 1).
     *
     * @return array{0:int,1:int} [pagina, porPagina]
     */
    public static function parametrosPaginacao(array $query, int $max = 50): array
    {
        $pagina = max(1, (int) ($query['pagina'] ?? 1));
        $porPagina = (int) ($query['por_pagina'] ?? 20);
        $porPagina = max(1, min($max, $porPagina));
        return [$pagina, $porPagina];
    }
}
