<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso à tabela `pessoas` (soft delete via deleted_at).
 */
final class PessoaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    private const CAMPOS = [
        'nome_completo', 'nome_social', 'data_nascimento', 'sexo',
        'estado_civil', 'conjuge_id', 'cpf', 'telefone', 'email',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro',
        'cidade', 'uf', 'foto_url', 'consentimento_lgpd_em', 'observacoes',
    ];

    /** @return array<string,mixed>|false */
    public function buscar(int $id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pessoas WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados, ?int $createdBy): int
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        $campos['created_by'] = $createdBy;
        $cols = implode(', ', array_keys($campos));
        $marks = implode(', ', array_fill(0, count($campos), '?'));
        $this->pdo->prepare("INSERT INTO pessoas ({$cols}) VALUES ({$marks})")
                  ->execute(array_values($campos));
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(int $id, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE pessoas SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare('UPDATE pessoas SET deleted_at = NOW() WHERE id = ?')
                  ->execute([$id]);
    }
}
