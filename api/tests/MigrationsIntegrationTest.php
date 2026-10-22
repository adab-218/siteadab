<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração: aplica TODAS as migrations num MySQL real (service do CI,
 * via ADAB_TEST_DSN). Sem DSN, o teste é pulado — nunca quebra dev local.
 */
final class MigrationsIntegrationTest extends TestCase
{
    private function pdo(): ?PDO
    {
        $dsn = getenv('ADAB_TEST_DSN');
        if (!$dsn) {
            return null;
        }
        $pdo = new PDO($dsn, getenv('ADAB_TEST_USER') ?: null, getenv('ADAB_TEST_PASS') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        // Banco limpo para a migração "zerada" ser reproduzível
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
            $pdo->exec("DROP VIEW IF EXISTS `{$t}`");
        }
        return $pdo;
    }

    public function testTodasAsMigrationsAplicamEmBancoVazio(): void
    {
        $pdo = $this->pdo();
        if (!$pdo) {
            $this->markTestSkipped('ADAB_TEST_DSN não configurado.');
        }

        $dir = dirname(__DIR__) . '/migrations';
        $aplicadas = (new Migrator($pdo, $dir))->run();

        $arquivos = glob($dir . '/*.sql') ?: [];
        $this->assertCount(count($arquivos), $aplicadas, 'Migrations devem aplicar sem erro');

        $objetos = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ([
            'pessoas', 'membros', 'usuarios', 'membro_departamentos',
            'login_tentativas', 'audit_log', 'migrations',
            'ebd_classes', 'ebd_alunos', 'ebd_frequencias',
            'v_membros_ativos', 'v_programacao_publica',
            'v_aniversariantes_mes', 'v_ebd_frequencia_resumo',
        ] as $obj) {
            $this->assertContains($obj, $objetos, "{$obj} deve existir após migrations");
        }

        // Coluna hash em login_tentativas (migration 0007)
        $colunas = $pdo->query('SHOW COLUMNS FROM login_tentativas')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('email_hash', $colunas);
        $this->assertNotContains('email', $colunas);

        // Segunda execução: nada pendente (registro respeitado)
        $this->assertSame([], (new Migrator($pdo, $dir))->run());
    }
}
