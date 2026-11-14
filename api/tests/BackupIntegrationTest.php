<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Backup;
use Adab\Api\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * OPS.3 — backup lógico: gera .sql.gz, restaura em um banco separado e
 * compara contagens; retenção remove só arquivos antigos do padrão.
 * Só com ADAB_TEST_DSN (usuário precisa de CREATE/DROP DATABASE).
 */
final class BackupIntegrationTest extends TestCase
{
    private const BANCO_RESTORE = 'adab_restore_test';

    private ?PDO $pdo = null;
    private string $dir = '';

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
        $this->dir = sys_get_temp_dir() . '/adab_backup_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('DROP DATABASE IF EXISTS ' . self::BANCO_RESTORE);
        }
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testDumpRestauraEmOutroBanco(): void
    {
        // dado com aspas, quebra de linha e ";\n" — não pode quebrar o split
        $this->pdo->prepare('INSERT INTO pessoas (nome_completo, observacoes) VALUES (?, ?)')
            ->execute(["O'Neil \"Teste\"", "linha1;\nlinha2 \\ fim"]);
        $pessoaId = (int) $this->pdo->lastInsertId();
        // ebd_alunos tem coluna gerada (ativo_flag) — não pode ir no INSERT
        $this->pdo->prepare('INSERT INTO ebd_classes (nome) VALUES (?)')
            ->execute(['Classe Backup ' . bin2hex(random_bytes(3))]);
        $this->pdo->prepare(
            "INSERT INTO ebd_alunos (classe_id, pessoa_id, data_matricula, status) VALUES (?, ?, CURDATE(), 'matriculado')"
        )->execute([(int) $this->pdo->lastInsertId(), $pessoaId]);

        $r = (new Backup($this->pdo))->executar($this->dir);
        $this->assertFileExists($r['arquivo']);
        $this->assertGreaterThan(0, $r['tabelas']);
        $this->assertGreaterThan(0, $r['views']);

        $sql = gzdecode((string) file_get_contents($r['arquivo']));
        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE `pessoas`', $sql);
        $this->assertStringNotContainsString('DEFINER=', $sql);

        $origem = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $this->pdo->exec('DROP DATABASE IF EXISTS ' . self::BANCO_RESTORE);
        $this->pdo->exec('CREATE DATABASE ' . self::BANCO_RESTORE . ' CHARACTER SET utf8mb4');
        $this->pdo->exec('USE ' . self::BANCO_RESTORE);
        try {
            foreach (preg_split('/;\n/', $sql) ?: [] as $stmt) {
                // remove só as linhas de comentário (como o cliente mysql)
                $stmt = trim((string) preg_replace('/^--.*$/m', '', $stmt));
                if ($stmt === '') {
                    continue;
                }
                $this->pdo->exec($stmt);
            }
            foreach (['pessoas', 'cargos', 'tipos_culto', 'migrations', 'ebd_alunos'] as $t) {
                $restaurado = (int) $this->pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
                $original = (int) $this->pdo->query("SELECT COUNT(*) FROM `{$origem}`.`{$t}`")->fetchColumn();
                $this->assertSame($original, $restaurado, "contagem de {$t}");
            }
            $obs = $this->pdo->query(
                "SELECT observacoes FROM pessoas WHERE nome_completo = 'O''Neil \"Teste\"'"
            )->fetchColumn();
            $this->assertSame("linha1;\nlinha2 \\ fim", $obs);
            // coluna gerada recalculada no restore
            $this->assertSame(1, (int) $this->pdo->query(
                "SELECT ativo_flag FROM ebd_alunos WHERE pessoa_id = {$pessoaId}"
            )->fetchColumn());
            // view restaurada e consultável
            $this->pdo->query('SELECT COUNT(*) FROM v_membros_ativos')->fetchColumn();
        } finally {
            $this->pdo->exec("USE `{$origem}`");
        }
    }

    public function testRetencaoRemoveSoArquivosAntigosDoPadrao(): void
    {
        mkdir($this->dir, 0700, true);
        $antigo = $this->dir . '/adab_20200101_000000.sql.gz';
        $recente = $this->dir . '/adab_20990101_000000.sql.gz';
        $outro = $this->dir . '/outro_arquivo.sql.gz';
        foreach ([$antigo, $recente, $outro] as $f) {
            file_put_contents($f, 'x');
        }
        touch($antigo, time() - 20 * 86400);
        touch($outro, time() - 20 * 86400);

        $r = (new Backup($this->pdo))->executar($this->dir, 14);

        $this->assertSame(['adab_20200101_000000.sql.gz'], $r['removidos']);
        $this->assertFileDoesNotExist($antigo);
        $this->assertFileExists($recente);
        $this->assertFileExists($outro);
    }
}
