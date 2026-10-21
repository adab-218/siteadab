<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\ProgramacaoRepository;
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
    private ProgramacaoRepository $programacao;

    public function __construct(PDO $pdo)
    {
        $this->programacao = new ProgramacaoRepository($pdo);
    }

    public function programacao(Request $request, Response $response): Response
    {
        try {
            $rows = $this->programacao->programacaoPublica();
        } catch (PDOException $e) {
            error_log('[adab-api] programacao pública: ' . $e->getMessage());
            return Json::erro($response, 'INDISPONIVEL', 'Programação indisponível no momento.', 503);
        }

        return Json::resposta($response, ['data' => $rows]);
    }

    public function eventos(Request $request, Response $response): Response
    {
        try {
            $rows = $this->programacao->eventosPublicos();
        } catch (PDOException $e) {
            error_log('[adab-api] eventos públicos: ' . $e->getMessage());
            return Json::erro($response, 'INDISPONIVEL', 'Eventos indisponíveis no momento.', 503);
        }

        return Json::resposta($response, ['data' => $rows]);
    }
}
