<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso a `eventos` e `evento_atividades`.
 * publico=1 (não cancelado/passado) alimenta /public/eventos e
 * v_programacao_publica automaticamente. Soft delete via deleted_at.
 */
final class EventoRepository
{
    public const TIPOS = ['congresso', 'festividade', 'culto_especial', 'data_comemorativa', 'outro'];
    public const STATUS = ['planejado', 'confirmado', 'realizado', 'cancelado'];

    private const CAMPOS = [
        'nome', 'tipo', 'data_inicio', 'data_fim', 'local', 'descricao',
        'banner_url', 'publico', 'status',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{itens: array, total: int} */
    public function listarPaginado(array $filtros, int $pagina, int $porPagina): array
    {
        $where = ['e.deleted_at IS NULL'];
        $params = [];
        if (($filtros['de'] ?? '') !== '') {
            $where[] = 'COALESCE(e.data_fim, e.data_inicio) >= :de';
            $params['de'] = $filtros['de'];
        }
        if (($filtros['ate'] ?? '') !== '') {
            $where[] = 'e.data_inicio <= :ate';
            $params['ate'] = $filtros['ate'];
        }
        if (($filtros['tipo'] ?? '') !== '') {
            $where[] = 'e.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if (($filtros['status'] ?? '') !== '') {
            $where[] = 'e.status = :status';
            $params['status'] = $filtros['status'];
        }
        if (isset($filtros['publico'])) {
            $where[] = 'e.publico = :publico';
            $params['publico'] = (int) $filtros['publico'];
        }
        $cond = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM eventos e WHERE {$cond}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($pagina - 1) * $porPagina;
        $stmt = $this->pdo->prepare(
            "SELECT e.*, (SELECT COUNT(*) FROM evento_atividades a WHERE a.evento_id = e.id) AS total_atividades
               FROM eventos e
              WHERE {$cond}
              ORDER BY e.data_inicio DESC
              LIMIT {$porPagina} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['itens' => $stmt->fetchAll(), 'total' => $total];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM eventos WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados, ?int $createdBy): int
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        $campos['created_by'] = $createdBy;
        $cols = implode(', ', array_keys($campos));
        $marks = implode(', ', array_fill(0, count($campos), '?'));
        $this->pdo->prepare("INSERT INTO eventos ({$cols}) VALUES ({$marks})")
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
        $this->pdo->prepare("UPDATE eventos SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function remover(int $id): void
    {
        $this->pdo->prepare('UPDATE eventos SET deleted_at = NOW() WHERE id = ?')
                  ->execute([$id]);
    }

    // ----- evento_atividades -----

    /** @return array<int,array<string,mixed>> */
    public function listarAtividades(int $eventoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, p.nome_completo AS responsavel_nome
               FROM evento_atividades a
               LEFT JOIN pessoas p ON p.id = a.responsavel_id
              WHERE a.evento_id = ?
              ORDER BY a.data, a.horario_inicio'
        );
        $stmt->execute([$eventoId]);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $dados */
    public function criarAtividade(int $eventoId, array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO evento_atividades (evento_id, data, horario_inicio, horario_fim, titulo, responsavel_id, descricao)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventoId,
            $dados['data'],
            $dados['horario_inicio'] ?? null,
            $dados['horario_fim'] ?? null,
            $dados['titulo'],
            $dados['responsavel_id'] ?? null,
            $dados['descricao'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $dados */
    public function atualizarAtividade(int $id, array $dados): void
    {
        $campos = array_intersect_key(
            $dados,
            array_flip(['data', 'horario_inicio', 'horario_fim', 'titulo', 'responsavel_id', 'descricao'])
        );
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE evento_atividades SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function removerAtividade(int $id): void
    {
        $this->pdo->prepare('DELETE FROM evento_atividades WHERE id = ?')
                  ->execute([$id]);
    }

    public function buscarAtividade(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM evento_atividades WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
