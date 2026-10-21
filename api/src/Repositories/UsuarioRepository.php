<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso à tabela `usuarios`. Toda query deste domínio fica aqui —
 * controllers nunca falam SQL diretamente.
 */
final class UsuarioRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|false */
    public function buscarPorEmail(string $email)
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.pessoa_id, u.email, u.senha_hash, u.perfil, u.ativo, p.nome_completo
               FROM usuarios u
               JOIN pessoas p ON p.id = u.pessoa_id
              WHERE u.email = :email
              LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /** @return int|false */
    public function idAtivoPorEmail(string $email)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND ativo = 1');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        return $id === false ? false : (int) $id;
    }

    public function registrarLogin(int $id): void
    {
        $this->pdo->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = ?')
                  ->execute([$id]);
    }

    /** Guarda o token como hash SHA-256 — nunca em claro. */
    public function definirResetToken(int $id, string $tokenHash): void
    {
        $this->pdo->prepare(
            'UPDATE usuarios SET reset_token = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?'
        )->execute([$tokenHash, $id]);
    }

    /** Consome o token (uso único). Retorna se havia token válido. */
    public function consumirResetToken(string $tokenHash, string $senhaHash): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios
                SET senha_hash = ?, reset_token = NULL, reset_expira = NULL
              WHERE reset_token = ? AND reset_expira > NOW()'
        );
        $stmt->execute([$senhaHash, $tokenHash]);
        return $stmt->rowCount() > 0;
    }
}
