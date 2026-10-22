<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Repositories\MembroRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M1 — regra D1: sair de 'ativo' desvincula departamentos,
 * matrícula EBD e acesso de usuário numa só transação.
 * Só roda com MySQL de teste (ADAB_TEST_DSN — service do CI).
 */
final class MembrosIntegrationTest extends TestCase
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
    }

    public function testMudarStatusParaForaDeAtivoDesvinculaTudo(): void
    {
        $pdo = $this->pdo;

        // Base: pessoa + membro ativo + depto + vínculo + matrícula EBD + usuário
        $pdo->exec("INSERT INTO pessoas (nome_completo) VALUES ('Fulano de Tal')");
        $pessoaId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO membros (pessoa_id, status) VALUES ({$pessoaId}, 'ativo')");
        $pdo->exec("INSERT INTO departamentos (nome) VALUES ('Mocidade')");
        $deptoId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO membro_departamentos (pessoa_id, departamento_id) VALUES ({$pessoaId}, {$deptoId})");
        $pdo->exec("INSERT INTO ebd_classes (nome) VALUES ('Jovens')");
        $classeId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO ebd_alunos (classe_id, pessoa_id, data_matricula, status)
                    VALUES ({$classeId}, {$pessoaId}, '2026-01-01', 'matriculado')");
        $pdo->exec("INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil)
                    VALUES ({$pessoaId}, 'lider@test.local', 'x', 'lider_departamento')");

        $repo = new MembroRepository($pdo);
        $repo->mudarStatus($pessoaId, 'transferido', '2026-10-21');

        $this->assertSame('transferido',
            $pdo->query("SELECT status FROM membros WHERE pessoa_id = {$pessoaId}")->fetchColumn());
        $this->assertNotNull(
            $pdo->query("SELECT data_fim FROM membro_departamentos WHERE pessoa_id = {$pessoaId}")->fetchColumn(),
            'vínculo de departamento deve ser encerrado'
        );
        $this->assertSame('desistente',
            $pdo->query("SELECT status FROM ebd_alunos WHERE pessoa_id = {$pessoaId}")->fetchColumn());
        $this->assertSame(0,
            (int) $pdo->query("SELECT ativo FROM usuarios WHERE pessoa_id = {$pessoaId}")->fetchColumn());
    }

    public function testListarPaginadoFiltraEscopoDepartamento(): void
    {
        $pdo = $this->pdo;

        $pdo->exec("INSERT INTO pessoas (nome_completo) VALUES ('No Depto'), ('Fora Depto')");
        $de1 = (int) $pdo->lastInsertId() - 1;
        $de2 = $de1 + 1;
        $pdo->exec("INSERT INTO membros (pessoa_id) VALUES ({$de1}), ({$de2})");
        $pdo->exec("INSERT INTO departamentos (nome) VALUES ('Louvor')");
        $deptoId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO membro_departamentos (pessoa_id, departamento_id) VALUES ({$de1}, {$deptoId})");

        $repo = new MembroRepository($pdo);
        $r = $repo->listarPaginado(['departamento_ids' => [$deptoId]], 1, 20);

        $nomes = array_column($r['itens'], 'nome_completo');
        $this->assertContains('No Depto', $nomes);
        $this->assertNotContains('Fora Depto', $nomes);
    }
}
