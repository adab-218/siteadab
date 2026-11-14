<?php

declare(strict_types=1);

/**
 * CLI: backup diário do banco (OPS.3). Agendar no cPanel → Cron Jobs:
 *
 *   15 3 * * *  /usr/local/bin/php -f $HOME/public_html/api/backup-db.php
 *
 * Grava adab_AAAAMMDD_HHMMSS.sql.gz em config['backup_dir'] (padrão
 * ~/adab_backups, fora do public_html) e remove arquivos com mais de
 * config['backup_retencao_dias'] (padrão 14). Saída curta → e-mail do cron.
 */

use Adab\Api\Backup;
use Adab\Api\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Apenas CLI.');
}

require __DIR__ . '/vendor/autoload.php';

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config.php ausente.\n");
    exit(1);
}
$config = require $configFile;

$dir = (string) ($config['backup_dir'] ?? dirname(__DIR__, 2) . '/adab_backups');
$dias = max(1, (int) ($config['backup_retencao_dias'] ?? 14));

try {
    $r = (new Backup(Database::pdo($config['db'])))->executar($dir, $dias);
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup FALHOU: ' . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "Backup OK: %s (%.1f KB, %d tabelas, %d views)%s\n",
    basename($r['arquivo']),
    $r['bytes'] / 1024,
    $r['tabelas'],
    $r['views'],
    $r['removidos'] ? '; removidos: ' . implode(', ', $r['removidos']) : ''
);
