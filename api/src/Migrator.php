<?php

declare(strict_types=1);

namespace Adab\Api;

use PDO;
use RuntimeException;

/**
 * Runner de migrations: aplica arquivos NNNN_*.sql em ordem,
 * registrando as aplicadas na tabela `migrations`.
 *
 * Usado tanto por POST /api/admin/migrate quanto por `php migrate.php`.
 * As migrations devem conter apenas DDL/DML simples (sem DELIMITER,
 * procedures ou triggers) — os arquivos são divididos em ";" ao fim de linha.
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    /** @return string[] nomes dos arquivos aplicados nesta execução */
    public function run(): array
    {
        $this->garantirTabelaControle();

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

        foreach ($statements as $stmt) {
            $this->pdo->exec($stmt);
        }

        $this->pdo->prepare('INSERT INTO migrations (arquivo) VALUES (?)')
                  ->execute([$nome]);
    }
}
