<?php

declare(strict_types=1);

namespace Adab\Api;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Backup lógico do banco em SQL compactado (.sql.gz) usando só PDO —
 * a hospedagem compartilhada pode não oferecer `mysqldump`/`exec`.
 *
 * Formato: um statement por linha terminado em ";\n" (CREATE TABLE pode
 * ocupar várias linhas, mas nunca contém ";\n" interno). Restaurável por
 * `gunzip -c arquivo | mysql banco` ou importação no phpMyAdmin.
 * Views são gravadas sem DEFINER (restore em outro usuário do cPanel).
 *
 * OPS.3 — ver dev/docs/11-deploy-e-operacao.md.
 */
final class Backup
{
    public const PREFIXO = 'adab_';
    private const LOTE = 200;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Gera o dump em $dir e remove backups com mais de $retencaoDias.
     *
     * @return array{arquivo: string, bytes: int, tabelas: int, views: int, removidos: string[]}
     */
    public function executar(string $dir, int $retencaoDias = 14): array
    {
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar o diretório de backup: {$dir}");
        }
        $final = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . self::PREFIXO . date('Ymd_His') . '.sql.gz';
        $tmp = $final . '.parcial';

        // contém senha_hash e dados pessoais (LGPD): 0600 desde a criação
        $umask = umask(0077);
        $gz = gzopen($tmp, 'wb6');
        umask($umask);
        if ($gz === false) {
            throw new RuntimeException("Não foi possível gravar {$tmp}");
        }
        @chmod($tmp, 0600);
        // leitura de todas as tabelas no mesmo instante lógico (InnoDB)
        $this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            [$tabelas, $views] = $this->escrever($gz);
            $this->pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            gzclose($gz);
            @unlink($tmp);
            throw $e;
        }
        gzclose($gz);
        if (!rename($tmp, $final)) {
            @unlink($tmp);
            throw new RuntimeException("Não foi possível finalizar {$final}");
        }
        @chmod($final, 0600);

        return [
            'arquivo' => $final,
            'bytes' => (int) filesize($final),
            'tabelas' => $tabelas,
            'views' => $views,
            'removidos' => $this->aplicarRetencao($dir, $retencaoDias, $final),
        ];
    }

    /**
     * @param resource $gz
     * @return array{0:int,1:int} [tabelas, views]
     */
    private function escrever($gz): array
    {
        $banco = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $this->linha($gz, '-- Backup ADAB — banco `' . $banco . '` — ' . date('c'));
        $this->linha($gz, 'SET NAMES utf8mb4;');
        $this->linha($gz, 'SET FOREIGN_KEY_CHECKS = 0;');
        $this->linha($gz, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

        $tabelas = $this->objetos('BASE TABLE');
        foreach ($tabelas as $t) {
            $q = $this->ident($t);
            $create = $this->pdo->query("SHOW CREATE TABLE {$q}")->fetch(PDO::FETCH_NUM);
            $this->linha($gz, '');
            $this->linha($gz, "DROP TABLE IF EXISTS {$q};");
            $this->linha($gz, $create[1] . ';');
            $this->dados($gz, $t);
        }

        $views = $this->ordenarViews($this->objetos('VIEW'));
        foreach ($views as $v) {
            $q = $this->ident($v);
            $create = $this->pdo->query("SHOW CREATE VIEW {$q}")->fetch(PDO::FETCH_NUM);
            $sql = preg_replace('/\sDEFINER=`[^`]*`@`[^`]*`/', '', (string) $create[1]);
            $this->linha($gz, '');
            $this->linha($gz, "DROP VIEW IF EXISTS {$q};");
            $this->linha($gz, str_replace(["\r", "\n"], ' ', (string) $sql) . ';');
        }

        $this->linha($gz, '');
        $this->linha($gz, 'SET FOREIGN_KEY_CHECKS = 1;');
        return [count($tabelas), count($views)];
    }

    /** @param resource $gz */
    private function dados($gz, string $tabela): void
    {
        $q = $this->ident($tabela);
        // colunas geradas (ex.: ebd_alunos.ativo_flag) não aceitam valor
        // em INSERT (erro 3105) — ficam fora do dump e são recalculadas
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                AND EXTRA NOT LIKE '%GENERATED%'
              ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$tabela]);
        $nomes = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($nomes === []) {
            return;
        }
        $colunas = implode(', ', array_map(fn($c) => $this->ident($c), $nomes));
        $stmt = $this->pdo->query("SELECT {$colunas} FROM {$q}");
        $lote = [];
        while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
            $lote[] = '(' . implode(', ', array_map(fn($v) => $this->valor($v), $row)) . ')';
            if (count($lote) >= self::LOTE) {
                $this->linha($gz, "INSERT INTO {$q} ({$colunas}) VALUES " . implode(', ', $lote) . ';');
                $lote = [];
            }
        }
        if ($lote !== []) {
            $this->linha($gz, "INSERT INTO {$q} ({$colunas}) VALUES " . implode(', ', $lote) . ';');
        }
    }

    /** @return string[] nomes de tabelas ou views do banco atual */
    private function objetos(string $tipo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?
              ORDER BY TABLE_NAME'
        );
        $stmt->execute([$tipo]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Ordena views para que uma view que usa outra venha depois dela
     * (information_schema.VIEW_TABLE_USAGE, MySQL ≥ 8.0.13). Sem a
     * tabela de dependências, mantém a ordem alfabética.
     *
     * @param string[] $views
     * @return string[]
     */
    private function ordenarViews(array $views): array
    {
        try {
            $deps = $this->pdo->query(
                'SELECT VIEW_NAME, TABLE_NAME FROM information_schema.VIEW_TABLE_USAGE
                  WHERE VIEW_SCHEMA = DATABASE() AND TABLE_SCHEMA = DATABASE()'
            )->fetchAll(PDO::FETCH_NUM);
        } catch (Throwable) {
            return $views;
        }
        $usa = array_fill_keys($views, []);
        foreach ($deps as [$view, $tabela]) {
            if (isset($usa[$view], $usa[$tabela]) && $view !== $tabela) {
                $usa[$view][] = $tabela;
            }
        }
        $ordem = [];
        $visitando = [];
        $visitar = function (string $v) use (&$visitar, &$ordem, &$visitando, $usa): void {
            if (in_array($v, $ordem, true) || isset($visitando[$v])) {
                return;
            }
            $visitando[$v] = true;
            foreach ($usa[$v] as $dep) {
                $visitar($dep);
            }
            $ordem[] = $v;
        };
        foreach ($views as $v) {
            $visitar($v);
        }
        return $ordem;
    }

    private function valor(mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        // quote() escapa \n, \r, \0, aspas e \ — cada INSERT fica em uma linha
        $q = $this->pdo->quote((string) $v);
        if ($q === false) {
            throw new RuntimeException('Driver não suporta quote().');
        }
        return $q;
    }

    private function ident(string $nome): string
    {
        return '`' . str_replace('`', '``', $nome) . '`';
    }

    /** @param resource $gz */
    private function linha($gz, string $texto): void
    {
        if (gzwrite($gz, $texto . "\n") === false) {
            throw new RuntimeException('Falha ao gravar o backup (disco cheio?).');
        }
    }

    /** @return string[] arquivos removidos */
    private function aplicarRetencao(string $dir, int $dias, string $manter): array
    {
        $limite = time() - $dias * 86400;
        $removidos = [];
        foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . self::PREFIXO . '*.sql.gz') ?: [] as $f) {
            if ($f !== $manter && filemtime($f) < $limite && @unlink($f)) {
                $removidos[] = basename($f);
            }
        }
        // dumps interrompidos (processo morto no meio) com mais de 1 dia
        foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . self::PREFIXO . '*.sql.gz.parcial') ?: [] as $f) {
            if (filemtime($f) < time() - 86400 && @unlink($f)) {
                $removidos[] = basename($f);
            }
        }
        return $removidos;
    }
}
