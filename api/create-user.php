<?php

declare(strict_types=1);

/**
 * CLI: cria usuário do painel vinculado a uma pessoa.
 *
 *   php create-user.php <pessoa_id> <email> <senha> <perfil>
 *
 * Perfis: admin, secretaria, dirigente, ebd_admin, ebd_professor,
 * lider_departamento
 */

use Adab\Api\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Apenas CLI.');
}

if ($argc < 5) {
    fwrite(STDERR, "Uso: php create-user.php <pessoa_id> <email> <senha> <perfil>\n");
    exit(1);
}

[, $pessoaId, $email, $senha, $perfil] = $argv;

$perfis = ['admin', 'secretaria', 'dirigente', 'ebd_admin', 'ebd_professor', 'lider_departamento'];
if (!in_array($perfil, $perfis, true)) {
    fwrite(STDERR, 'Perfil inválido. Use: ' . implode(', ', $perfis) . "\n");
    exit(1);
}
if (strlen($senha) < 10) {
    fwrite(STDERR, "Senha deve ter pelo menos 10 caracteres.\n");
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';
$pdo = Database::pdo($config['db']);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil)
     VALUES (?, ?, ?, ?)'
);
$stmt->execute([$pessoaId, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil]);

echo "Usuário criado: {$email} (perfil {$perfil})\n";
