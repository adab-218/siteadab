<?php

declare(strict_types=1);

namespace Adab\Api;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Runner de migrations: aplica arquivos NNNN_*.sql em ordem,
 * registrando as aplicadas na tabela `migrations`.
 *
 * Usado tanto por POST /api/admin/migrate quanto por `php migrate.php`.
 *
 * Convenções para novas migrations:
 *  - statements separados por ";" ao fim de linha (sem DELIMITER,
 *    procedures ou triggers — o split é por ponto-e-vírgula);
 *  - preferir formas idempotentes (CREATE TABLE IF NOT EXISTS,
 *    CREATE OR REPLACE VIEW, INSERT ... ON DUPLICATE KEY) para que
 *    uma falha no meio do arquivo possa ser re-executada com segurança;
 *  - ALTERs não são idempotentes — verificar no banco antes de re-rodar
 *    uma migration que falhou parcialmente (ver dev/docs/11).
 *
 * Lock: GET_LOCK impede execuções concorrentes (deploy + admin).
 */
final class Migrator
{
    private const LOCK_NAME = 'adab_migrator';
    private const LOCK_TIMEOUT = 15;

    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    /** @return string[] nomes dos arquivos aplicados nesta execução */
    public function run(): array
    {
        $this->garantirTabelaControle();
        $this->obterLock();

        try {
            $aplicadas = $this->pdo->query('SELECT arquivo FROM migrations')
                                   ->fetchAll(PDO::FETCH_COLUMN);
            $arquivos = glob($this->dir . '/*.sql') ?: [];
            sort($arquivos);

            $novas = [];
            foreach ($arquivos as $arquivo) {
                $nome = basename($arquivo);
                if (in_array($nome, $aplicadas, true)) {
                    continue;
                }
                $this->aplicar($arquivo, $nome);
                $novas[] = $nome;
            }
            return $novas;
        } finally {
            $this->pdo->query('SELECT RELEASE_LOCK(' . $this->pdo->quote(self::LOCK_NAME) . ')');
        }
    }

    private function obterLock(): void
    {
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([self::LOCK_NAME, self::LOCK_TIMEOUT]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Não foi possível obter o lock de migração (outra execução em andamento?).');
        }
    }

    private function garantirTabelaControle(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                arquivo VARCHAR(255) NOT NULL UNIQUE,
                aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function aplicar(string $arquivo, string $nome): void
    {
        $sql = file_get_contents($arquivo);
        if ($sql === false) {
            throw new RuntimeException("Não foi possível ler {$arquivo}");
        }

        // Statements separados por ";" no fim de linha
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*[\r\n]+/', $sql) ?: []),
            fn($s) => $s !== ''
        );

        $i = 0;
        foreach ($statements as $stmt) {
            $i++;
            try {
                $this->pdo->exec($stmt);
            } catch (PDOException $e) {
                throw new RuntimeException(
                    "{$nome} falhou no statement #{$i} (banco pode estar parcialmente aplicado — ver dev/docs/11): {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        $this->pdo->prepare('INSERT INTO migrations (arquivo) VALUES (?)')
                  ->execute([$nome]);
    }
}
