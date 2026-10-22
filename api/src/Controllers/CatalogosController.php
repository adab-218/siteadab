<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\CargoRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Catálogos/lookups compartilhados pelos formulários do painel.
 */
final class CatalogosController
{
    private CargoRepository $cargos;

    public function __construct(PDO $pdo)
    {
        $this->cargos = new CargoRepository($pdo);
    }

    public function cargos(Request $request, Response $response): Response
    {
        return Json::resposta($response, ['data' => $this->cargos->listar()]);
    }
}
