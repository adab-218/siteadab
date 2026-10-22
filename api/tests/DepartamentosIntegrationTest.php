<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use Adab\Api\Repositories\DepartamentoRepository;
use Adab\Api\Repositories\MembroRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M2 — vínculo membro↔departamento (D6), reativação após
 * encerramento (uq_md_pessoa_departamento), escopo de líder (D5) e
 * view de aniversariantes. Só roda com MySQL de teste (ADAB_TEST_DSN).
 */
final class DepartamentosIntegrationTest extends TestCase
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
        // Garante schema independente da ordem dos testes
        (new Migrator($this->pdo, dirname(__DIR__) . '/migrations'))->run();
    }

    private function membroAtivo(string $nome): int
    {
        $this->pdo->exec("INSERT INTO pessoas (nome_completo) VALUES ('{$nome}')");
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO membros (pessoa_id, status) VALUES ({$id}, 'ativo')");
        return $id;
    }

    public function testVinculoMembroInsereEncerraEReativa(): void
    {
        $pdo = $this->pdo;
        $repo = new DepartamentoRepository($pdo);

        $pessoaId = $this->membroAtivo('Membro do Depto');
        $pdo->exec("INSERT INTO departamentos (nome) VALUES ('Infantil')");
        $deptoId = (int) $pdo->lastInsertId();

        $this->assertSame('inserido', $repo->adicionarMembro($deptoId, $pessoaId, 'Professor', '2026-01-01'));

        try {
            $repo->adicionarMembro($deptoId, $pessoaId, null, null);
            $this->fail('Esperava VINCULO_EXISTENTE');
        } catch (\RuntimeException $e) {
            $this->assertSame('VINCULO_EXISTENTE', $e->getMessage());
        }

        $this->assertTrue($repo->removerMembro($deptoId, $pessoaId));
        $this->assertFalse($repo->removerMembro($deptoId, $pessoaId), 'segundo encerramento não deve afetar linhas');

        // uq (pessoa_id, departamento_id): re-vínculo reativa a mesma linha
        $this->assertSame('reativado', $repo->adicionarMembro($deptoId, $pessoaId, null, '2026-10-01'));
        $this->assertSame(1, $repo->contarMembrosAtivos($deptoId));
    }

    public function testVinculoExigeMembroD6(): void
    {
        $pdo = $this->pdo;
        $repo = new DepartamentoRepository($pdo);

        // Pessoa sem registro de membro — D6 proíbe vínculo
        $pdo->exec("INSERT INTO pessoas (nome_completo) VALUES ('So Pessoa')");
        $pessoaId = (int) $pdo->lastInsertId();
        $this->assertFalse($repo->pessoaEhMembro($pessoaId));

        $pdo->exec("INSERT INTO departamentos (nome) VALUES ('Recepção')");
        $deptoId = (int) $pdo->lastInsertId();

        // A FK para membros também bloqueia direto no banco
        $this->expectException(\PDOException::class);
        $repo->adicionarMembro($deptoId, $pessoaId, null, null);
    }

    public function testEscopoLiderD5(): void
    {
        $pdo = $this->pdo;
        $repo = new DepartamentoRepository($pdo);
        $membros = new MembroRepository($pdo);

        $liderId = $this->membroAtivo('Lider Tal');
        $pdo->exec("INSERT INTO departamentos (nome, lider_id) VALUES ('Dele', {$liderId}), ('Nao Dele', NULL)");
        $deleId = (int) $pdo->lastInsertId();

        $this->assertTrue($repo->lidera($deleId, $liderId));
        $this->assertFalse($repo->lidera($deleId + 1, $liderId));

        $liderados = $membros->departamentosLiderados($liderId);
        $this->assertContains($deleId, $liderados);
        $this->assertNotContains($deleId + 1, $liderados);

        $lista = $repo->listar($liderados, true);
        $this->assertSame(['Dele'], array_column($lista, 'nome'));
    }

    public function testAniversariantesFiltraMesEAtivos(): void
    {
        $pdo = $this->pdo;
        $membros = new MembroRepository($pdo);

        $pdo->exec("INSERT INTO pessoas (nome_completo, data_nascimento)
                    VALUES ('Niver Outubro', '1990-10-05'), ('Niver Março', '1985-03-10')");
        $o1 = (int) $pdo->lastInsertId();
        $o2 = $o1 + 1;
        $pdo->exec("INSERT INTO membros (pessoa_id, status) VALUES ({$o1}, 'ativo'), ({$o2}, 'ativo')");

        $outubro = array_column($membros->aniversariantes(10), 'nome_completo');
        $this->assertContains('Niver Outubro', $outubro);
        $this->assertNotContains('Niver Março', $outubro);

        $marco = array_column($membros->aniversariantes(3), 'nome_completo');
        $this->assertContains('Niver Março', $marco);
        $this->assertNotContains('Niver Outubro', $marco);

        // Membro fora de 'ativo' não aparece
        $membros->mudarStatus($o1, 'transferido', '2026-10-21');
        $this->assertNotContains('Niver Outubro',
            array_column($membros->aniversariantes(10), 'nome_completo'));
    }
}
