<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use Adab\Api\Repositories\AcaoRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M5 — CRUD, soft delete, voluntários (upsert papel),
 * beneficiários e filtros. Só com ADAB_TEST_DSN.
 */
final class AcoesIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private AcaoRepository $repo;

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
        $this->repo = new AcaoRepository($this->pdo);
    }

    private function novaPessoa(): int
    {
        $this->pdo->prepare('INSERT INTO pessoas (nome_completo) VALUES (?)')
            ->execute(['Voluntária Teste']);
        return (int) $this->pdo->lastInsertId();
    }

    public function testCrudESoftDelete(): void
    {
        $id = $this->repo->criar([
            'nome' => 'Mutirão Natal Solidário',
            'tipo' => 'social',
            'data_inicio' => '2026-12-12',
            'status' => 'em_andamento',
        ], null);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('em_andamento', $this->repo->buscar($id)['status']);

        $this->repo->atualizar($id, ['resultado' => 'Distribuídas 120 cestas']);
        $this->assertSame('Distribuídas 120 cestas', $this->repo->buscar($id)['resultado']);

        $this->repo->remover($id);
        $this->assertNull($this->repo->buscar($id));
        $this->assertNotContains($id, array_column(
            $this->repo->listarPaginado([], 1, 50)['itens'], 'id'
        ));
    }

    public function testVoluntarioUpsertPapel(): void
    {
        $pid = $this->novaPessoa();
        $id = $this->repo->criar(['nome' => 'A', 'tipo' => 'outra'], null);

        $this->repo->vincularVoluntario($id, $pid, 'voluntario');
        $this->assertSame('voluntario', $this->repo->listarVoluntarios($id)[0]['papel']);

        // Re-vínculo promove (uq_acao_pessoa) — sem duplicar
        $this->repo->vincularVoluntario($id, $pid, 'coordenador');
        $vols = $this->repo->listarVoluntarios($id);
        $this->assertCount(1, $vols);
        $this->assertSame('coordenador', $vols[0]['papel']);

        $this->repo->desvincularVoluntario($id, $pid);
        $this->assertCount(0, $this->repo->listarVoluntarios($id));
    }

    public function testBeneficiariosEFiltros(): void
    {
        $id = $this->repo->criar(['nome' => 'Distribuição', 'tipo' => 'social', 'data_inicio' => '2026-12-01'], null);
        $this->repo->criar(['nome' => 'Evangelismo', 'tipo' => 'evangelistica', 'status' => 'realizada'], null);

        $this->repo->criarBeneficiario($id, ['nome' => 'Maria', 'contato' => '91999990000']);
        $this->assertCount(1, $this->repo->listarBeneficiarios($id));

        $social = $this->repo->listarPaginado(['tipo' => 'social'], 1, 50);
        $this->assertSame(['Distribuição'], array_column($social['itens'], 'nome'));
        $this->assertSame(1, (int) $social['itens'][0]['total_beneficiarios']);

        $realizadas = $this->repo->listarPaginado(['status' => 'realizada'], 1, 50);
        $this->assertSame(['Evangelismo'], array_column($realizadas['itens'], 'nome'));
    }
}
