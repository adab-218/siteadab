<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso à tabela `membros` (1:1 com pessoas) e seus vínculos.
 * Desvinculação ao sair de 'ativo' é regra D1 (ver CHECKLIST).
 */
final class MembroRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    private const STATUS_VALIDOS = ['ativo', 'afastado', 'transferido', 'falecido', 'disciplinado'];
    private const CAMPOS = [
        'numero_carteirinha', 'data_conversao', 'data_batismo',
        'batizado_espirito_santo', 'congregacao', 'procedencia', 'cargo_id',
    ];

    /** @return array<int,string> */
    public static function statusValidos(): array
    {
        return self::STATUS_VALIDOS;
    }

    /** Membro + dados da pessoa (null se não existir/deletado). */
    public function buscarComPessoa(int $pessoaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id AS pessoa_id, p.nome_completo, p.nome_social, p.data_nascimento,
                    p.sexo, p.estado_civil, p.conjuge_id, p.cpf, p.telefone, p.email,
                    p.cep, p.logradouro, p.numero, p.complemento, p.bairro,
                    p.cidade, p.uf, p.foto_url, p.consentimento_lgpd_em, p.observacoes,
                    m.numero_carteirinha, m.data_conversao, m.data_batismo,
                    m.batizado_espirito_santo, m.congregacao, m.procedencia,
                    m.cargo_id, c.nome AS cargo_nome, m.status, m.data_status
               FROM membros m
               JOIN pessoas p ON p.id = m.pessoa_id
               LEFT JOIN cargos c ON c.id = m.cargo_id
              WHERE m.pessoa_id = ? AND m.deleted_at IS NULL AND p.deleted_at IS NULL'
        );
        $stmt->execute([$pessoaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lista paginada. Filtros: q (nome/email), status, cargo_id,
     * departamento_id (ou departamento_ids[] p/ escopo de líder).
     * @return array{itens: array, total: int}
     */
    public function listarPaginado(array $filtros, int $pagina, int $porPagina): array
    {
        $where = ['m.deleted_at IS NULL', 'p.deleted_at IS NULL'];
        $params = [];

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(p.nome_completo LIKE :q OR p.email LIKE :q2)';
            $params['q'] = '%' . $filtros['q'] . '%';
            $params['q2'] = '%' . $filtros['q'] . '%';
        }
        if (($filtros['status'] ?? '') !== '') {
            $where[] = 'm.status = :status';
            $params['status'] = $filtros['status'];
        }
        if (!empty($filtros['cargo_id'])) {
            $where[] = 'm.cargo_id = :cargo_id';
            $params['cargo_id'] = (int) $filtros['cargo_id'];
        }
        if (!empty($filtros['departamento_id'])) {
            $where[] = 'm.pessoa_id IN (SELECT pessoa_id FROM membro_departamentos
                       WHERE departamento_id = :depto AND data_fim IS NULL)';
            $params['depto'] = (int) $filtros['departamento_id'];
        }
        if (!empty($filtros['departamento_ids'])) {
            $marks = implode(',', array_map('intval', $filtros['departamento_ids']));
            $where[] = "m.pessoa_id IN (SELECT pessoa_id FROM membro_departamentos
                       WHERE departamento_id IN ({$marks}) AND data_fim IS NULL)";
        }

        $cond = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM membros m JOIN pessoas p ON p.id = m.pessoa_id WHERE {$cond}"
        );
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($pagina - 1) * $porPagina;
        $stmt = $this->pdo->prepare(
            "SELECT m.pessoa_id, p.nome_completo, p.telefone, p.email,
                    m.status, m.data_status, m.congregacao, c.nome AS cargo_nome
               FROM membros m
               JOIN pessoas p ON p.id = m.pessoa_id
               LEFT JOIN cargos c ON c.id = m.cargo_id
              WHERE {$cond}
              ORDER BY p.nome_completo
              LIMIT {$porPagina} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['itens' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @param array<string,mixed> $dados */
    public function criar(int $pessoaId, array $dados, ?int $createdBy): void
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        $campos['status'] = $dados['status'] ?? 'ativo';
        $campos['data_status'] = $dados['data_status'] ?? date('Y-m-d');
        $campos['pessoa_id'] = $pessoaId;
        $campos['created_by'] = $createdBy;
        $cols = implode(', ', array_keys($campos));
        $marks = implode(', ', array_fill(0, count($campos), '?'));
        $this->pdo->prepare("INSERT INTO membros ({$cols}) VALUES ({$marks})")
                  ->execute(array_values($campos));
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(int $pessoaId, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE membros SET {$sets} WHERE pessoa_id = ?")
                  ->execute([...array_values($campos), $pessoaId]);
    }

    /**
     * Muda status do membro. Regra D1: ao sair de 'ativo', desvincula
     * departamentos (data_fim), matrículas EBD (desistente) e acesso
     * de usuário (ativo=0) — tudo na mesma transação.
     */
    public function mudarStatus(int $pessoaId, string $status, ?string $dataStatus): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE membros SET status = ?, data_status = ? WHERE pessoa_id = ?')
                      ->execute([$status, $dataStatus ?? date('Y-m-d'), $pessoaId]);

            if ($status !== 'ativo') {
                $this->desvincular($pessoaId);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Soft delete aplica a mesma regra D1 (desvincula departamentos,
     * encerra matrículas EBD e desativa acesso) — membro excluído não
     * pode continuar contando como ativo nem seu usuário logando.
     */
    public function softDelete(int $pessoaId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE membros SET deleted_at = NOW() WHERE pessoa_id = ?')
                      ->execute([$pessoaId]);
            $this->desvincular($pessoaId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Regra D1: encerra vínculos ao sair de 'ativo' ou ao excluir. */
    private function desvincular(int $pessoaId): void
    {
        $this->pdo->prepare(
            'UPDATE membro_departamentos SET data_fim = CURDATE()
              WHERE pessoa_id = ? AND data_fim IS NULL'
        )->execute([$pessoaId]);
        $this->pdo->prepare(
            "UPDATE ebd_alunos SET status = 'desistente', data_desistencia = CURDATE()
              WHERE pessoa_id = ? AND status = 'matriculado'"
        )->execute([$pessoaId]);
        $this->pdo->prepare('UPDATE usuarios SET ativo = 0 WHERE pessoa_id = ?')
                  ->execute([$pessoaId]);
    }

    public function cargoExiste(int $cargoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cargos WHERE id = ?');
        $stmt->execute([$cargoId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Aniversariantes do mês (membros ativos). @return array<int,array<string,mixed>> */
    public function aniversariantes(int $mes): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pessoa_id, nome_completo, data_nascimento, dia
               FROM v_aniversariantes_mes
              WHERE mes = ?
              ORDER BY dia, nome_completo'
        );
        $stmt->execute([$mes]);
        return $stmt->fetchAll();
    }

    /** Departamentos liderados por uma pessoa (escopo D5). @return int[] */
    public function departamentosLiderados(int $pessoaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM departamentos WHERE lider_id = ? AND ativo = 1'
        );
        $stmt->execute([$pessoaId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int,array<string,mixed>> departamentos ativos de um membro */
    public function departamentosDoMembro(int $pessoaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT md.departamento_id, d.nome, md.funcao, md.data_inicio
               FROM membro_departamentos md
               JOIN departamentos d ON d.id = md.departamento_id
              WHERE md.pessoa_id = ? AND md.data_fim IS NULL
              ORDER BY d.nome'
        );
        $stmt->execute([$pessoaId]);
        return $stmt->fetchAll();
    }

    /** O membro está em algum dos departamentos informados? */
    public function pertenceAAlgumDepartamento(int $pessoaId, array $deptoIds): bool
    {
        if ($deptoIds === []) {
            return false;
        }
        $marks = implode(',', array_map('intval', $deptoIds));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM membro_departamentos
              WHERE pessoa_id = ? AND departamento_id IN ({$marks}) AND data_fim IS NULL"
        );
        $stmt->execute([$pessoaId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
