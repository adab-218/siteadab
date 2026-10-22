<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use Adab\Api\Repositories\EventoRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M4 — CRUD, soft delete, atividades e visibilidade
 * pública (publico + status + período). Só com ADAB_TEST_DSN.
 */
final class EventosIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private EventoRepository $repo;

    protected function setUp(): void
    {
        $dsn = getenv('ADAB_TEST_DSN');
        if (!$dsn) {
            $this->markTestSkipped('ADAB_TEST_DSN não configurado.');
        }
        $this->pdo = new PDO($dsn, getenv('ADAB_TEST_USER') ?: null, getenv('ADAB_TEST_PASS') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        (new Migrator($this->pdo, dirname(__DIR__) . '/migrations'))->run();
        $this->repo = new EventoRepository($this->pdo);
    }

    public function testCicloDeVidaESoftDelete(): void
    {
        $id = $this->repo->criar([
            'nome' => 'Congressão 2026',
            'tipo' => 'congresso',
            'data_inicio' => '2026-11-20',
            'data_fim' => '2026-11-22',
            'local' => 'Templo Arthur Bernardes',
            'publico' => 1,
            'status' => 'confirmado',
        ], null);
        $this->assertGreaterThan(0, $id);

        $this->repo->atualizar($id, ['status' => 'realizado']);
        $this->assertSame('realizado', $this->repo->buscar($id)['status']);

        $this->repo->remover($id);
        $this->assertNull($this->repo->buscar($id));

        $lista = $this->repo->listarPaginado([], 1, 50);
        $this->assertNotContains($id, array_column($lista['itens'], 'id'));
    }

    public function testFiltrosTipoStatusPeriodo(): void
    {
        $this->repo->criar(['nome' => 'Festividade', 'tipo' => 'festividade', 'data_inicio' => '2026-11-05', 'status' => 'planejado'], null);
        $this->repo->criar(['nome' => 'Culto Especial', 'tipo' => 'culto_especial', 'data_inicio' => '2026-12-25', 'status' => 'cancelado'], null);

        $fest = $this->repo->listarPaginado(['tipo' => 'festividade'], 1, 50);
        $this->assertSame(['Festividade'], array_column($fest['itens'], 'nome'));

        $cancel = $this->repo->listarPaginado(['status' => 'cancelado'], 1, 50);
        $this->assertSame(['Culto Especial'], array_column($cancel['itens'], 'nome'));

        $dez = $this->repo->listarPaginado(['de' => '2026-12-01'], 1, 50);
        $this->assertSame(['Culto Especial'], array_column($dez['itens'], 'nome'));
    }

    public function testAtividadesCrud(): void
    {
        $id = $this->repo->criar(['nome' => 'E', 'tipo' => 'outro', 'data_inicio' => '2026-11-10'], null);
        $aid = $this->repo->criarAtividade($id, [
            'titulo' => 'Abertura',
            'data' => '2026-11-10',
            'horario_inicio' => '19:00',
        ]);
        $this->assertSame(1, count($this->repo->listarAtividades($id)));

        $this->repo->atualizarAtividade($aid, ['titulo' => 'Abertura oficial']);
        $this->assertSame('Abertura oficial', $this->repo->buscarAtividade($aid)['titulo']);

        $this->repo->removerAtividade($aid);
        $this->assertSame(0, count($this->repo->listarAtividades($id)));
    }

    public function testPublicoAlimentaSiteSemCancelado(): void
    {
        $this->repo->criar(['nome' => 'Público futuro', 'tipo' => 'festividade', 'data_inicio' => '2099-01-01', 'publico' => 1], null);
        $this->repo->criar(['nome' => 'Público cancelado', 'tipo' => 'festividade', 'data_inicio' => '2099-01-01', 'publico' => 1, 'status' => 'cancelado'], null);
        $this->repo->criar(['nome' => 'Interno', 'tipo' => 'outro', 'data_inicio' => '2099-01-01', 'publico' => 0], null);

        $nomes = $this->pdo->query(
            "SELECT nome FROM eventos
              WHERE publico = 1 AND status <> 'cancelado'
                AND COALESCE(data_fim, data_inicio) >= CURDATE()
                AND deleted_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('Público futuro', $nomes);
        $this->assertNotContains('Público cancelado', $nomes);
        $this->assertNotContains('Interno', $nomes);
    }
}
