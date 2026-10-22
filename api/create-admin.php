<?php

declare(strict_types=1);

/**
 * CLI: provisiona o PRIMEIRO administrador (pessoa + usuário admin em
 * uma transação). Não exige pessoa pré-existente — uso de bootstrap.
 *
 *   php create-admin.php "<nome_completo>" <email> <senha>
 *
 * Segurança: aceita apenas CLI; senha ≥10 caracteres (política).
 * Uso único — falha se já existir usuário com perfil admin ativo.
 */

use Adab\Api\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Apenas CLI.');
}

if ($argc < 4) {
    fwrite(STDERR, "Uso: php create-admin.php \"<nome_completo>\" <email> <senha>\n");
    exit(1);
}

[, $nome, $email, $senha] = $argv;

$nome = trim((string) $nome);
$email = trim((string) $email);

if ($nome === '') {
    fwrite(STDERR, "nome_completo não pode ser vazio.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
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

    $admins = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND ativo = 1"
    )->fetchColumn();
    if ($admins > 0) {
        fwrite(STDERR, "Já existe administrador ativo — use POST /usuarios ou create-user.php.\n");
        exit(1);
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO pessoas (nome_completo) VALUES (?)')->execute([$nome]);
    $pessoaId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil) VALUES (?, ?, ?, "admin")'
    )->execute([$pessoaId, $email, password_hash($senha, PASSWORD_DEFAULT)]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = str_contains($e->getMessage(), 'Duplicate')
        ? "E-mail já cadastrado.\n"
        : "Erro de banco: {$e->getMessage()}\n";
    fwrite(STDERR, $msg);
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}

echo "Administrador criado: {$email} (pessoa #{$pessoaId})\n";
