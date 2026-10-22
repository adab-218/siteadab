<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso a `acoes`, `acao_voluntarios` e `acao_beneficiarios`.
 * Voluntário = pessoa cadastrada; beneficiário = texto livre (externo).
 * Soft delete via deleted_at.
 */
final class AcaoRepository
{
    public const TIPOS = ['social', 'evangelistica', 'outra'];
    public const STATUS = ['planejada', 'em_andamento', 'realizada', 'cancelada'];
    public const PAPEIS = ['coordenador', 'voluntario'];

    private const CAMPOS = [
        'nome', 'tipo', 'data_inicio', 'data_fim', 'local', 'objetivo',
        'resultado', 'departamento_id', 'status',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{itens: array, total: int} */
    public function listarPaginado(array $filtros, int $pagina, int $porPagina): array
    {
        $where = ['a.deleted_at IS NULL'];
        $params = [];
        if (($filtros['de'] ?? '') !== '') {
            $where[] = 'COALESCE(a.data_fim, a.data_inicio) >= :de';
            $params['de'] = $filtros['de'];
        }
        if (($filtros['ate'] ?? '') !== '') {
            $where[] = 'a.data_inicio <= :ate';
            $params['ate'] = $filtros['ate'];
        }
        if (($filtros['tipo'] ?? '') !== '') {
            $where[] = 'a.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if (($filtros['status'] ?? '') !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $filtros['status'];
        }
        if (!empty($filtros['departamento_id'])) {
            $where[] = 'a.departamento_id = :depto';
            $params['depto'] = (int) $filtros['departamento_id'];
        }
        $cond = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM acoes a WHERE {$cond}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($pagina - 1) * $porPagina;
        $stmt = $this->pdo->prepare(
            "SELECT a.*, d.nome AS departamento_nome,
                    (SELECT COUNT(*) FROM acao_voluntarios v WHERE v.acao_id = a.id) AS total_voluntarios,
                    (SELECT COUNT(*) FROM acao_beneficiarios b WHERE b.acao_id = a.id) AS total_beneficiarios
               FROM acoes a
               LEFT JOIN departamentos d ON d.id = a.departamento_id
              WHERE {$cond}
              ORDER BY a.data_inicio DESC, a.id DESC
              LIMIT {$porPagina} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['itens' => $stmt->fetchAll(), 'total' => $total];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, d.nome AS departamento_nome
               FROM acoes a
               LEFT JOIN departamentos d ON d.id = a.departamento_id
              WHERE a.id = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados, ?int $createdBy): int
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        $campos['created_by'] = $createdBy;
        $cols = implode(', ', array_keys($campos));
        $marks = implode(', ', array_fill(0, count($campos), '?'));
        $this->pdo->prepare("INSERT INTO acoes ({$cols}) VALUES ({$marks})")
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
        $this->pdo->prepare("UPDATE acoes SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function remover(int $id): void
    {
        $this->pdo->prepare('UPDATE acoes SET deleted_at = NOW() WHERE id = ?')
                  ->execute([$id]);
    }

    // ----- voluntários -----

    /** @return array<int,array<string,mixed>> */
    public function listarVoluntarios(int $acaoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.pessoa_id, p.nome_completo, v.papel
               FROM acao_voluntarios v
               JOIN pessoas p ON p.id = v.pessoa_id
              WHERE v.acao_id = ?
              ORDER BY v.papel = "coordenador" DESC, p.nome_completo'
        );
        $stmt->execute([$acaoId]);
        return $stmt->fetchAll();
    }

    /** Insere ou promove (uq_acao_pessoa). */
    public function vincularVoluntario(int $acaoId, int $pessoaId, string $papel): void
    {
        $this->pdo->prepare(
            'INSERT INTO acao_voluntarios (acao_id, pessoa_id, papel) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE papel = VALUES(papel)'
        )->execute([$acaoId, $pessoaId, $papel]);
    }

    public function desvincularVoluntario(int $acaoId, int $pessoaId): void
    {
        $this->pdo->prepare('DELETE FROM acao_voluntarios WHERE acao_id = ? AND pessoa_id = ?')
                  ->execute([$acaoId, $pessoaId]);
    }

    // ----- beneficiários -----

    /** @return array<int,array<string,mixed>> */
    public function listarBeneficiarios(int $acaoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, contato, observacao FROM acao_beneficiarios WHERE acao_id = ? ORDER BY nome'
        );
        $stmt->execute([$acaoId]);
        return $stmt->fetchAll();
    }

    public function criarBeneficiario(int $acaoId, array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO acao_beneficiarios (acao_id, nome, contato, observacao) VALUES (?, ?, ?, ?)'
        )->execute([$acaoId, $dados['nome'], $dados['contato'] ?? null, $dados['observacao'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function removerBeneficiario(int $id): void
    {
        $this->pdo->prepare('DELETE FROM acao_beneficiarios WHERE id = ?')->execute([$id]);
    }
}
