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

$pessoaId = filter_var($pessoaId, FILTER_VALIDATE_INT);
if ($pessoaId === false || $pessoaId <= 0) {
    fwrite(STDERR, "pessoa_id deve ser um inteiro positivo.\n");
    exit(1);
}

$email = trim($email);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}

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

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config.php ausente — copie config.example.php e configure.\n");
    exit(1);
}
$config = require $configFile;

try {
    $pdo = Database::pdo($config['db']);

    $existe = $pdo->prepare('SELECT COUNT(*) FROM pessoas WHERE id = ?');
    $existe->execute([$pessoaId]);
    if (!(int) $existe->fetchColumn()) {
        fwrite(STDERR, "Pessoa #{$pessoaId} não existe — cadastre a pessoa primeiro.\n");
        exit(1);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$pessoaId, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil]);
} catch (PDOException $e) {
    $msg = str_contains($e->getMessage(), 'Duplicate')
        ? "E-mail já cadastrado para outro usuário.\n"
        : "Erro de banco: {$e->getMessage()}\n";
    fwrite(STDERR, $msg);
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}

echo "Usuário criado: {$email} (perfil {$perfil})\n";
