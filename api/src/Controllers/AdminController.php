<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Migrator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Operações administrativas da própria API.
 */
final class AdminController
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    /**
     * POST /api/admin/migrate
     * Autorizado por: sessão de admin OU header X-Migrate-Token
     * (usado pelo workflow de deploy).
     */
    public function migrate(Request $request, Response $response): Response
    {
        $token = $request->getHeaderLine('X-Migrate-Token');
        $tokenOk = ($this->config['migrate_token'] ?? '') !== ''
            && hash_equals($this->config['migrate_token'], $token);
        $adminOk = ($_SESSION['usuario']['perfil'] ?? null) === 'admin';

        if (!$tokenOk && !$adminOk) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Token de migração ou sessão admin exigidos.', 403);
        }

        $dir = dirname(__DIR__, 2) . '/migrations';
        try {
            $aplicadas = (new Migrator($this->pdo, $dir))->run();
        } catch (\Throwable $e) {
            error_log('[adab-api] migrate: ' . $e->getMessage());
            return Json::erro($response, 'MIGRATION_FALHOU', 'Falha ao aplicar migrations.', 500);
        }

        return Json::resposta($response, ['data' => [
            'aplicadas' => $aplicadas,
            'total' => count($aplicadas),
        ]]);
    }
}
