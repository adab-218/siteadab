<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\DashboardRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** GET /dashboard/resumo — KPIs agregados (sem dados pessoais). */
final class DashboardController
{
    private DashboardRepository $repo;

    public function __construct(PDO $pdo)
    {
        $this->repo = new DashboardRepository($pdo);
    }

    public function resumo(Request $request, Response $response): Response
    {
        return Json::resposta($response, ['data' => $this->repo->resumo()]);
    }
}
