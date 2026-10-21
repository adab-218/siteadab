<?php

declare(strict_types=1);

/**
 * CLI: aplica migrations pendentes (e opcionalmente o seed de dev).
 *
 *   php migrate.php          # aplica api/migrations/*.sql pendentes
 *   php migrate.php --seed   # aplica também api/seed.sql (dev only)
 */

use Adab\Api\Database;
use Adab\Api\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Apenas CLI.');
}

require __DIR__ . '/vendor/autoload.php';

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config.php ausente — copie config.example.php e configure.\n");
    exit(1);
}
$config = require $configFile;

try {
    $pdo = Database::pdo($config['db']);
    $aplicadas = (new Migrator($pdo, __DIR__ . '/migrations'))->run();
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}

if ($aplicadas === []) {
    echo "Nenhuma migration pendente.\n";
} else {
    echo "Aplicadas:\n - " . implode("\n - ", $aplicadas) . "\n";
}

// Seed (dev)
if (in_array('--seed', $argv, true)) {
    $seedFile = __DIR__ . '/seed.sql';
    $sql = file_get_contents($seedFile);
    if ($sql === false) {
        fwrite(STDERR, "seed.sql não encontrado.\n");
        exit(1);
    }
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*[\r\n]+/', $sql) ?: []),
        fn($s) => $s !== '' && !str_starts_with($s, '--')
    );
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
    echo "seed.sql aplicado.\n";
}
