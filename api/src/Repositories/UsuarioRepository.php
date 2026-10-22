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

    // ---------------------------------------------------------------
    // Gestão de usuários (T2.2 — só admin chama; o controller filtra)

    public const PERFIS = ['admin', 'secretaria', 'dirigente', 'ebd_admin', 'ebd_professor', 'lider_departamento'];

    /** @return array<int,array<string,mixed>> */
    public function listar(?string $q = null): array
    {
        $sql = 'SELECT u.id, u.pessoa_id, p.nome_completo, u.email, u.perfil,
                       u.ativo, u.ultimo_login_em
                  FROM usuarios u
                  JOIN pessoas p ON p.id = u.pessoa_id';
        $params = [];
        if ($q !== null && $q !== '') {
            $sql .= ' WHERE p.nome_completo LIKE ? OR u.email LIKE ?';
            $params = ["%{$q}%", "%{$q}%"];
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY p.nome_completo');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.pessoa_id, p.nome_completo, u.email, u.perfil,
                    u.ativo, u.ultimo_login_em
               FROM usuarios u
               JOIN pessoas p ON p.id = u.pessoa_id
              WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function pessoaExiste(int $pessoaId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pessoas WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$pessoaId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function criar(int $pessoaId, string $email, string $senhaHash, string $perfil): int
    {
        $this->pdo->prepare(
            'INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil) VALUES (?, ?, ?, ?)'
        )->execute([$pessoaId, $email, $senhaHash, $perfil]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $dados (email, perfil, ativo) */
    public function atualizar(int $id, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(['email', 'perfil', 'ativo']));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE usuarios SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    /** Troca a senha e invalida qualquer reset pendente. */
    public function definirSenha(int $id, string $senhaHash): void
    {
        $this->pdo->prepare(
            'UPDATE usuarios SET senha_hash = ?, reset_token = NULL, reset_expira = NULL WHERE id = ?'
        )->execute([$senhaHash, $id]);
    }

    public function desativar(int $id): void
    {
        $this->pdo->prepare('UPDATE usuarios SET ativo = 0 WHERE id = ?')
                  ->execute([$id]);
    }
}
