<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso a `departamentos` e `membro_departamentos`.
 * D6: vínculo só aceita membros (FK para membros.pessoa_id).
 * D5: líder gerencia apenas os departamentos que lidera.
 */
final class DepartamentoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param int[]|null $ids       restrição a ids (escopo do líder)
     * @param bool|null  $apenasAtivos
     * @return array<int,array<string,mixed>>
     */
    public function listar(?array $ids = null, ?bool $apenasAtivos = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($apenasAtivos !== null) {
            $where[] = 'd.ativo = ?';
            $params[] = $apenasAtivos ? 1 : 0;
        }
        if ($ids !== null) {
            if ($ids === []) {
                return [];
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "d.id IN ({$marks})";
            $params = array_merge($params, $ids);
        }
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.nome, d.descricao, d.lider_id,
                    pl.nome_completo AS lider_nome, d.ativo,
                    (SELECT COUNT(*) FROM membro_departamentos md
                      WHERE md.departamento_id = d.id AND md.data_fim IS NULL
                        AND EXISTS (SELECT 1 FROM membros mx
                                     WHERE mx.pessoa_id = md.pessoa_id AND mx.deleted_at IS NULL)
                    ) AS total_membros
               FROM departamentos d
               LEFT JOIN pessoas pl ON pl.id = d.lider_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY d.nome'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.nome, d.descricao, d.lider_id,
                    pl.nome_completo AS lider_nome, d.ativo,
                    (SELECT COUNT(*) FROM membro_departamentos md
                      WHERE md.departamento_id = d.id AND md.data_fim IS NULL
                        AND EXISTS (SELECT 1 FROM membros mx
                                     WHERE mx.pessoa_id = md.pessoa_id AND mx.deleted_at IS NULL)
                    ) AS total_membros
               FROM departamentos d
               LEFT JOIN pessoas pl ON pl.id = d.lider_id
              WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Membros ativos do departamento. @return array<int,array<string,mixed>> */
    public function membros(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT md.pessoa_id, p.nome_completo, md.funcao, md.data_inicio
               FROM membro_departamentos md
               JOIN pessoas p ON p.id = md.pessoa_id
               JOIN membros mx ON mx.pessoa_id = md.pessoa_id AND mx.deleted_at IS NULL
              WHERE md.departamento_id = ? AND md.data_fim IS NULL
              ORDER BY p.nome_completo'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $dados (nome, descricao, lider_id, ativo) */
    public function criar(array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO departamentos (nome, descricao, lider_id, ativo) VALUES (?, ?, ?, ?)'
        )->execute([
            $dados['nome'],
            $dados['descricao'] ?? null,
            $dados['lider_id'] ?? null,
            array_key_exists('ativo', $dados) ? ($dados['ativo'] ? 1 : 0) : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(int $id, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(['nome', 'descricao', 'lider_id', 'ativo']));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE departamentos SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function desativar(int $id): void
    {
        $this->pdo->prepare('UPDATE departamentos SET ativo = 0 WHERE id = ?')
                  ->execute([$id]);
    }

    public function contarMembrosAtivos(int $id): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM membro_departamentos md
              WHERE departamento_id = ? AND data_fim IS NULL
                AND EXISTS (SELECT 1 FROM membros mx
                             WHERE mx.pessoa_id = md.pessoa_id AND mx.deleted_at IS NULL)'
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /** D6: "membro" em vínculos/escalas = status ativo e não excluído. */
    public function pessoaEhMembro(int $pessoaId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM membros
              WHERE pessoa_id = ? AND deleted_at IS NULL AND status = 'ativo'"
        );
        $stmt->execute([$pessoaId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Vincula um membro ao departamento.
     * Regra D6: só membros — além do check, a FK para membros impõe no banco.
     * uq_md_pessoa_departamento impede nova linha após encerramento, então um
     * vínculo encerrado é reativado (data_fim = NULL) em vez de duplicado.
     *
     * @return string 'inserido' | 'reativado'
     */
    public function adicionarMembro(int $deptoId, int $pessoaId, ?string $funcao, ?string $dataInicio): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, data_fim FROM membro_departamentos
              WHERE pessoa_id = ? AND departamento_id = ?'
        );
        $stmt->execute([$pessoaId, $deptoId]);
        $vinculo = $stmt->fetch();

        if ($vinculo && $vinculo['data_fim'] === null) {
            throw new \RuntimeException('VINCULO_EXISTENTE');
        }

        if ($vinculo) {
            $this->pdo->prepare(
                'UPDATE membro_departamentos
                    SET data_fim = NULL, data_inicio = ?, funcao = ?
                  WHERE id = ?'
            )->execute([$dataInicio ?? date('Y-m-d'), $funcao, $vinculo['id']]);
            return 'reativado';
        }

        $this->pdo->prepare(
            'INSERT INTO membro_departamentos (pessoa_id, departamento_id, funcao, data_inicio)
             VALUES (?, ?, ?, ?)'
        )->execute([$pessoaId, $deptoId, $funcao, $dataInicio ?? date('Y-m-d')]);
        return 'inserido';
    }

    /** Encerra vínculo ativo. @return bool false se não havia vínculo ativo */
    public function removerMembro(int $deptoId, int $pessoaId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE membro_departamentos SET data_fim = CURDATE()
              WHERE pessoa_id = ? AND departamento_id = ? AND data_fim IS NULL'
        );
        $stmt->execute([$pessoaId, $deptoId]);
        return $stmt->rowCount() > 0;
    }

    /** A pessoa é líder deste departamento? (D5) */
    public function lidera(int $deptoId, int $pessoaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM departamentos WHERE id = ? AND lider_id = ? AND ativo = 1'
        );
        $stmt->execute([$deptoId, $pessoaId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
