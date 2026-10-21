<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoints públicos do site — sem autenticação.
 * Retornam apenas dados marcados como públicos (ver doc 05).
 */
final class PublicController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function programacao(Request $request, Response $response): Response
    {
        try {
            $rows = $this->pdo->query(
                'SELECT tipo, titulo, data, horario_inicio, data_fim, categoria
                   FROM v_programacao_publica
                  ORDER BY data, horario_inicio'
            )->fetchAll();
        } catch (PDOException $e) {
            error_log('[adab-api] programacao pública: ' . $e->getMessage());
            return Json::erro($response, 'INDISPONIVEL', 'Programação indisponível no momento.', 503);
        }

        return Json::resposta($response, ['data' => $rows]);
    }

    public function eventos(Request $request, Response $response): Response
    {
        try {
            $rows = $this->pdo->query(
                "SELECT id, nome, tipo, data_inicio, data_fim, local, descricao, banner_url
                   FROM eventos
                  WHERE publico = 1 AND COALESCE(data_fim, data_inicio) >= CURDATE()
                  ORDER BY data_inicio"
            )->fetchAll();
        } catch (PDOException $e) {
            error_log('[adab-api] eventos públicos: ' . $e->getMessage());
            return Json::erro($response, 'INDISPONIVEL', 'Eventos indisponíveis no momento.', 503);
        }

        return Json::resposta($response, ['data' => $rows]);
    }
}
