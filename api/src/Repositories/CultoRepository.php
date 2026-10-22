<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso a `cultos` e `tipos_culto`.
 * uq_cultos_tipo_data (tipo, data): um culto por tipo/dia (D11).
 */
final class CultoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    private const CAMPOS = [
        'tipo_culto_id', 'data', 'horario_inicio', 'dirigente_id', 'pregador_id',
        'tema', 'total_presentes', 'total_visitantes', 'total_decisoes', 'observacoes',
    ];

    // ----- tipos_culto -----

    /** @return array<int,array<string,mixed>> */
    public function listarTipos(?bool $apenasAtivos = null): array
    {
        $sql = 'SELECT id, nome, dia_semana_sugerido, horario_padrao, ativo FROM tipos_culto';
        if ($apenasAtivos !== null) {
            $sql .= ' WHERE ativo = ' . ($apenasAtivos ? 1 : 0);
        }
        return $this->pdo->query($sql . ' ORDER BY nome')->fetchAll();
    }

    public function tipoExisteEAtivo(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tipos_culto WHERE id = ? AND ativo = 1');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function criarTipo(array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO tipos_culto (nome, dia_semana_sugerido, horario_padrao, ativo) VALUES (?, ?, ?, ?)'
        )->execute([
            $dados['nome'],
            $dados['dia_semana_sugerido'] ?? null,
            $dados['horario_padrao'] ?? null,
            array_key_exists('ativo', $dados) ? ($dados['ativo'] ? 1 : 0) : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function atualizarTipo(int $id, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(['nome', 'dia_semana_sugerido', 'horario_padrao', 'ativo']));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE tipos_culto SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function buscarTipo(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tipos_culto WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ----- cultos -----

    /** @return array{itens: array, total: int} */
    public function listarPaginado(array $filtros, int $pagina, int $porPagina): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filtros['de'] ?? '') !== '') {
            $where[] = 'c.data >= :de';
            $params['de'] = $filtros['de'];
        }
        if (($filtros['ate'] ?? '') !== '') {
            $where[] = 'c.data <= :ate';
            $params['ate'] = $filtros['ate'];
        }
        if (!empty($filtros['tipo_culto_id'])) {
            $where[] = 'c.tipo_culto_id = :tipo';
            $params['tipo'] = (int) $filtros['tipo_culto_id'];
        }
        $cond = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cultos c WHERE {$cond}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($pagina - 1) * $porPagina;
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.tipo_culto_id, t.nome AS tipo_nome, c.data, c.horario_inicio,
                    c.dirigente_id, pd.nome_completo AS dirigente_nome,
                    c.pregador_id, pp.nome_completo AS pregador_nome,
                    c.tema, c.total_presentes, c.total_visitantes, c.total_decisoes,
                    c.observacoes
               FROM cultos c
               JOIN tipos_culto t ON t.id = c.tipo_culto_id
               LEFT JOIN pessoas pd ON pd.id = c.dirigente_id
               LEFT JOIN pessoas pp ON pp.id = c.pregador_id
              WHERE {$cond}
              ORDER BY c.data DESC, c.horario_inicio DESC
              LIMIT {$porPagina} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['itens' => $stmt->fetchAll(), 'total' => $total];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, t.nome AS tipo_nome,
                    pd.nome_completo AS dirigente_nome,
                    pp.nome_completo AS pregador_nome
               FROM cultos c
               JOIN tipos_culto t ON t.id = c.tipo_culto_id
               LEFT JOIN pessoas pd ON pd.id = c.dirigente_id
               LEFT JOIN pessoas pp ON pp.id = c.pregador_id
              WHERE c.id = ?'
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
        $this->pdo->prepare("INSERT INTO cultos ({$cols}) VALUES ({$marks})")
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
        $this->pdo->prepare("UPDATE cultos SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function remover(int $id): void
    {
        $this->pdo->prepare('DELETE FROM cultos WHERE id = ?')->execute([$id]);
    }
}
