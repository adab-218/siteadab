<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use Adab\Api\Repositories\CultoRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M3 — seed 0008 (D12), unicidade tipo+data (D11) e
 * listagem por período. Só roda com MySQL de teste (ADAB_TEST_DSN).
 */
final class CultosIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;

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
    }

    public function testSeed0008PopulaTiposECargos(): void
    {
        $tipos = $this->pdo->query('SELECT nome FROM tipos_culto')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['Escola Bíblica Dominical', 'Culto de Adoração e Louvor', 'Culto da Família'] as $nome) {
            $this->assertContains($nome, $tipos);
        }
        $cargos = $this->pdo->query('SELECT nome FROM cargos')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['Membro', 'Diácono', 'Pastor'] as $nome) {
            $this->assertContains($nome, $cargos);
        }
    }

    public function testUnicidadeTipoDataD11(): void
    {
        $repo = new CultoRepository($this->pdo);
        $tipoId = (int) $this->pdo->query("SELECT id FROM tipos_culto WHERE nome = 'Culto da Família'")
            ->fetchColumn();
        $this->assertTrue($repo->tipoExisteEAtivo($tipoId));

        $id = $repo->criar([
            'tipo_culto_id' => $tipoId,
            'data' => '2026-10-28',
            'horario_inicio' => '19:30:00',
            'tema' => 'Família restaurada',
        ], null);
        $this->assertGreaterThan(0, $id);

        // Mesmo tipo + mesma data → viola uq_cultos_tipo_data
        $this->expectException(\PDOException::class);
        $repo->criar(['tipo_culto_id' => $tipoId, 'data' => '2026-10-28'], null);
    }

    public function testListarPorPeriodoETipo(): void
    {
        $repo = new CultoRepository($this->pdo);
        $adocao = (int) $this->pdo->query("SELECT id FROM tipos_culto WHERE nome = 'Culto de Adoração e Louvor'")
            ->fetchColumn();
        $ebd = (int) $this->pdo->query("SELECT id FROM tipos_culto WHERE nome = 'Escola Bíblica Dominical'")
            ->fetchColumn();

        $repo->criar(['tipo_culto_id' => $adocao, 'data' => '2026-11-01', 'horario_inicio' => '18:30:00'], null);
        $repo->criar(['tipo_culto_id' => $ebd, 'data' => '2026-11-01', 'horario_inicio' => '08:00:00'], null);
        $repo->criar(['tipo_culto_id' => $adocao, 'data' => '2026-12-25', 'horario_inicio' => '18:30:00'], null);

        $nov = $repo->listarPaginado(['de' => '2026-11-01', 'ate' => '2026-11-30'], 1, 20);
        $this->assertSame(2, $nov['total']);

        $soAdocao = $repo->listarPaginado(['tipo_culto_id' => $adocao, 'de' => '2026-11-01'], 1, 20);
        $nomes = array_column($soAdocao['itens'], 'tipo_nome');
        $this->assertSame(['Culto de Adoração e Louvor', 'Culto de Adoração e Louvor'], $nomes);
    }

    public function testPresencaNominal(): void
    {
        $repo = new CultoRepository($this->pdo);
        $tipoId = (int) $this->pdo->query("SELECT id FROM tipos_culto WHERE nome = 'Culto da Família'")
            ->fetchColumn();
        $cultoId = $repo->criar(['tipo_culto_id' => $tipoId, 'data' => '2026-11-25'], null);

        $this->pdo->prepare('INSERT INTO pessoas (nome_completo) VALUES ("Presente Um"), ("Presente Dois")')
            ->execute();
        $p1 = (int) $this->pdo->lastInsertId(); // multi-row: retorna o PRIMEIRO id
        $p2 = $p1 + 1;

        $repo->adicionarPresenca($cultoId, $p1);
        $repo->adicionarPresenca($cultoId, $p2);
        $repo->adicionarPresenca($cultoId, $p1); // uq → idempotente (IGNORE)
        $this->assertCount(2, $repo->listarPresencas($cultoId));

        // Contador na listagem
        $pag = $repo->listarPaginado([], 1, 50);
        $row = array_values(array_filter($pag['itens'], fn($c) => (int) $c['id'] === $cultoId))[0];
        $this->assertSame(2, (int) $row['total_nomeados']);

        $repo->removerPresenca($cultoId, $p1);
        $this->assertCount(1, $repo->listarPresencas($cultoId));

        // remover o culto apaga presenças (FK CASCADE)
        $repo->remover($cultoId);
        $this->assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM culto_presencas WHERE culto_id = {$cultoId}"
        )->fetchColumn());
    }
}
