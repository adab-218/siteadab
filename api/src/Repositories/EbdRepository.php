<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso às tabelas EBD (classes, professores, alunos, períodos,
 * aulas, frequências).
 *
 * Decisões:
 * - D2: um aluno tem no máximo UMA matrícula 'matriculado' por vez
 *   (enforcement em código — matricular() recusa se já ativo).
 * - D3: só presença é registrada; falta = ausência de linha.
 * - D4: períodos = ano+trimestre (uq_periodo).
 * - D8: aulas geradas nos domingos do período; aulas pontuais/
 *   transferidas criadas em qualquer data.
 * - Re-matrícula na mesma classe reativa a linha terminal
 *   (status anterior vira 'matriculado') em vez de violar a
 *   uq (classe,pessoa,status).
 */
final class EbdRepository
{
    public const PAPEIS_PROF = ['titular', 'auxiliar', 'substituto'];
    public const STATUS_ALUNO = ['matriculado', 'transferido', 'desistente'];

    public function __construct(private PDO $pdo)
    {
    }

    // ===== classes =====

    /** @return array<int,array<string,mixed>> */
    public function listarClasses(?bool $apenasAtivas = null): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM ebd_professores p WHERE p.classe_id = c.id AND p.ativo = 1) AS total_professores,
                       (SELECT COUNT(*) FROM ebd_alunos a WHERE a.classe_id = c.id AND a.status = "matriculado") AS total_alunos
                  FROM ebd_classes c';
        if ($apenasAtivas !== null) {
            $sql .= ' WHERE c.ativo = ' . ($apenasAtivas ? 1 : 0);
        }
        return $this->pdo->query($sql . ' ORDER BY c.nome')->fetchAll();
    }

    public function buscarClasse(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ebd_classes WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function criarClasse(array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO ebd_classes (nome, faixa_etaria, sala) VALUES (?, ?, ?)'
        )->execute([$dados['nome'], $dados['faixa_etaria'] ?? null, $dados['sala'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function atualizarClasse(int $id, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(['nome', 'faixa_etaria', 'sala', 'ativo']));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE ebd_classes SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    // ===== professores =====

    /** @return array<int,array<string,mixed>> */
    public function listarProfessores(int $classeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ep.id, ep.pessoa_id, p.nome_completo, ep.papel, ep.data_inicio, ep.data_fim, ep.ativo
               FROM ebd_professores ep
               JOIN pessoas p ON p.id = ep.pessoa_id
              WHERE ep.classe_id = ?
              ORDER BY ep.ativo DESC, ep.papel = "titular" DESC, p.nome_completo'
        );
        $stmt->execute([$classeId]);
        return $stmt->fetchAll();
    }

    /** Liga professor à classe; se já ativo, só atualiza o papel. */
    public function vincularProfessor(int $classeId, int $pessoaId, string $papel): void
    {
        $existe = $this->pdo->prepare(
            'SELECT id FROM ebd_professores WHERE classe_id = ? AND pessoa_id = ? AND ativo = 1'
        );
        $existe->execute([$classeId, $pessoaId]);
        if ($id = $existe->fetchColumn()) {
            $this->pdo->prepare('UPDATE ebd_professores SET papel = ? WHERE id = ?')
                      ->execute([$papel, $id]);
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO ebd_professores (classe_id, pessoa_id, papel, data_inicio) VALUES (?, ?, ?, CURDATE())'
        )->execute([$classeId, $pessoaId, $papel]);
    }

    public function desvincularProfessor(int $id): void
    {
        $this->pdo->prepare('UPDATE ebd_professores SET ativo = 0, data_fim = CURDATE() WHERE id = ?')
                  ->execute([$id]);
    }

    /** Professor (ebd_professor) é docente ativo da classe? */
    public function pessoaLecionaNaClasse(int $pessoaId, int $classeId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ebd_professores WHERE pessoa_id = ? AND classe_id = ? AND ativo = 1'
        );
        $stmt->execute([$pessoaId, $classeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // ===== alunos (matrícula) =====

    /** Matrícula ativa do aluno em QUALQUER classe (D2). */
    public function matriculaAtiva(int $pessoaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ea.*, c.nome AS classe_nome
               FROM ebd_alunos ea
               JOIN ebd_classes c ON c.id = ea.classe_id
              WHERE ea.pessoa_id = ? AND ea.status = "matriculado"'
        );
        $stmt->execute([$pessoaId]);
        return $stmt->fetch() ?: null;
    }

    public function buscarAluno(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ea.*, p.nome_completo, c.nome AS classe_nome
               FROM ebd_alunos ea
               JOIN pessoas p ON p.id = ea.pessoa_id
               JOIN ebd_classes c ON c.id = ea.classe_id
              WHERE ea.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listarAlunos(array $filtros): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if (!empty($filtros['classe_id'])) {
            $where[] = 'ea.classe_id = ?';
            $params[] = (int) $filtros['classe_id'];
        }
        if (($filtros['status'] ?? '') !== '') {
            $where[] = 'ea.status = ?';
            $params[] = $filtros['status'];
        }
        if (($filtros['q'] ?? '') !== '') {
            $where[] = 'p.nome_completo LIKE ?';
            $params[] = '%' . $filtros['q'] . '%';
        }
        $stmt = $this->pdo->prepare(
            'SELECT ea.id, ea.classe_id, c.nome AS classe_nome, ea.pessoa_id,
                    p.nome_completo, ea.data_matricula, ea.data_desistencia,
                    ea.status, ea.observacao
               FROM ebd_alunos ea
               JOIN pessoas p ON p.id = ea.pessoa_id
               JOIN ebd_classes c ON c.id = ea.classe_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.nome, p.nome_completo'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Matricula pessoa na classe. Reativa linha terminal existente
     * (mesma classe) ou insere nova. Não verifica D2 — fica no
     * controller (erro dedicado JA_MATRICULADO).
     */
    public function matricular(int $classeId, int $pessoaId, ?string $observacao, ?int $createdBy): int
    {
        // Reativa linha terminal existente (uq classe+pessoa+status)
        $stmt = $this->pdo->prepare(
            'SELECT id FROM ebd_alunos WHERE classe_id = ? AND pessoa_id = ? AND status <> "matriculado"
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$classeId, $pessoaId]);
        if ($id = $stmt->fetchColumn()) {
            $this->pdo->prepare(
                'UPDATE ebd_alunos
                    SET status = "matriculado", data_matricula = CURDATE(), data_desistencia = NULL,
                        observacao = COALESCE(?, observacao)
                  WHERE id = ?'
            )->execute([$observacao, $id]);
            return (int) $id;
        }
        $this->pdo->prepare(
            'INSERT INTO ebd_alunos (classe_id, pessoa_id, data_matricula, observacao, created_by)
             VALUES (?, ?, CURDATE(), ?, ?)'
        )->execute([$classeId, $pessoaId, $observacao, $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Encerra matrícula ativa (transação no caller quando transfere). */
    public function encerrarMatricula(int $alunoId, string $statusFinal): void
    {
        $this->pdo->prepare(
            'UPDATE ebd_alunos SET status = ?, data_desistencia = CURDATE() WHERE id = ?'
        )->execute([$statusFinal, $alunoId]);
    }

    /** Transfere: encerra atual + matricula na classe destino. */
    public function transferir(int $alunoId, int $classeDestinoId, ?int $createdBy): int
    {
        $atual = $this->buscarAluno($alunoId);
        if (!$atual || $atual['status'] !== 'matriculado') {
            return 0;
        }
        $this->pdo->beginTransaction();
        try {
            $this->encerrarMatricula($alunoId, 'transferido');
            $novoId = $this->matricular($classeDestinoId, (int) $atual['pessoa_id'], null, $createdBy);
            $this->pdo->commit();
            return $novoId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ===== períodos =====

    /** @return array<int,array<string,mixed>> */
    public function listarPeriodos(): array
    {
        return $this->pdo->query(
            'SELECT p.*, (SELECT COUNT(*) FROM ebd_aulas a WHERE a.periodo_id = p.id) AS total_aulas
               FROM ebd_periodos p ORDER BY p.ano DESC, p.trimestre DESC'
        )->fetchAll();
    }

    public function buscarPeriodo(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ebd_periodos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function criarPeriodo(array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO ebd_periodos (ano, trimestre, tema_revista, data_inicio, data_fim)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $dados['ano'], $dados['trimestre'], $dados['tema_revista'] ?? null,
            $dados['data_inicio'], $dados['data_fim'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ===== aulas =====

    /** @return array<int,array<string,mixed>> */
    public function listarAulas(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ebd_aulas WHERE periodo_id = ? ORDER BY numero_licao'
        );
        $stmt->execute([$periodoId]);
        return $stmt->fetchAll();
    }

    public function buscarAula(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, p.ano, p.trimestre FROM ebd_aulas a
             JOIN ebd_periodos p ON p.id = a.periodo_id WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Cria aula pontual/transferida (numeração = max+1). */
    public function criarAula(int $periodoId, array $dados): int
    {
        $num = $this->proximoNumeroLicao($periodoId);
        $this->pdo->prepare(
            'INSERT INTO ebd_aulas (periodo_id, numero_licao, titulo_licao, data, texto_aureo, leitura_biblica)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $periodoId, $num,
            $dados['titulo_licao'] ?? null, $dados['data'],
            $dados['texto_aureo'] ?? null, $dados['leitura_biblica'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Gera aulas nos domingos do período, pulando datas já existentes. Retorna criadas. */
    public function gerarDomingos(int $periodoId): int
    {
        $per = $this->buscarPeriodo($periodoId);
        if (!$per) {
            return 0;
        }
        $exist = $this->pdo->prepare('SELECT data FROM ebd_aulas WHERE periodo_id = ?');
        $exist->execute([$periodoId]);
        $datas = array_column($exist->fetchAll(), 'data');

        $num = $this->proximoNumeroLicao($periodoId);
        $criadas = 0;
        $ins = $this->pdo->prepare(
            'INSERT INTO ebd_aulas (periodo_id, numero_licao, data) VALUES (?, ?, ?)'
        );
        for ($d = new \DateTime($per['data_inicio']); $d <= new \DateTime($per['data_fim']); $d->modify('+1 day')) {
            if ((int) $d->format('w') !== 0) { // só domingo
                continue;
            }
            $data = $d->format('Y-m-d');
            if (in_array($data, $datas, true)) {
                continue;
            }
            $ins->execute([$periodoId, $num, $data]);
            $num++;
            $criadas++;
            $datas[] = $data;
        }
        return $criadas;
    }

    private function proximoNumeroLicao(int $periodoId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(numero_licao), 0) + 1 FROM ebd_aulas WHERE periodo_id = ?');
        $stmt->execute([$periodoId]);
        return (int) $stmt->fetchColumn();
    }

    // ===== frequências (chamada) =====

    /**
     * Alunos matriculados da classe + frequência (se houver) na aula.
     * @return array<int,array<string,mixed>>
     */
    public function chamada(int $aulaId, int $classeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ea.id AS aluno_id, p.nome_completo,
                    f.presente, f.trouxe_biblia, f.trouxe_revista, f.oferta, f.visitante,
                    (f.id IS NOT NULL) AS registrado
               FROM ebd_alunos ea
               JOIN pessoas p ON p.id = ea.pessoa_id
               LEFT JOIN ebd_frequencias f ON f.aula_id = ? AND f.aluno_id = ea.id
              WHERE ea.classe_id = ? AND ea.status = "matriculado" AND p.deleted_at IS NULL
              ORDER BY p.nome_completo'
        );
        $stmt->execute([$aulaId, $classeId]);
        return $stmt->fetchAll();
    }

    /** Upsert por (aula_id, aluno_id) — uq_freq_aula_aluno. */
    public function registrarFrequencia(int $aulaId, int $alunoId, array $f, ?int $createdBy): void
    {
        $this->pdo->prepare(
            'INSERT INTO ebd_frequencias (aula_id, aluno_id, presente, trouxe_biblia, trouxe_revista, oferta, visitante, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE presente = VALUES(presente), trouxe_biblia = VALUES(trouxe_biblia),
                     trouxe_revista = VALUES(trouxe_revista), oferta = VALUES(oferta), visitante = VALUES(visitante)'
        )->execute([
            $aulaId, $alunoId,
            !empty($f['presente']) ? 1 : 0,
            !empty($f['trouxe_biblia']) ? 1 : 0,
            !empty($f['trouxe_revista']) ? 1 : 0,
            $f['oferta'] ?? null,
            !empty($f['visitante']) ? 1 : 0,
            $createdBy,
        ]);
    }

    // ===== relatórios =====

    /** @return array<int,array<string,mixed>> */
    public function frequenciaResumo(?int $classeId, ?int $periodoId): array
    {
        $where = ['1=1'];
        $params = [];
        if ($classeId) {
            $where[] = 'r.classe_id = ?';
            $params[] = $classeId;
        }
        if ($periodoId) {
            $where[] = 'r.periodo_id = ?';
            $params[] = $periodoId;
        }
        $stmt = $this->pdo->prepare(
            'SELECT r.*, c.nome AS classe_nome
               FROM v_ebd_frequencia_resumo r
               JOIN ebd_classes c ON c.id = r.classe_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY r.data DESC, c.nome'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Alunos matriculados sem presença nas últimas N aulas (data ≤ hoje). */
    public function faltosos(int $semanas): array
    {
        $limite = (new \DateTime('today'))->modify("-{$semanas} weeks")->format('Y-m-d');
        return $this->pdo->query(
            "SELECT ea.id AS aluno_id, p.nome_completo, c.nome AS classe_nome, ea.classe_id,
                    (SELECT MAX(f.created_at) FROM ebd_frequencias f
                      WHERE f.aluno_id = ea.id AND f.presente = 1) AS ultima_presenca
               FROM ebd_alunos ea
               JOIN pessoas p ON p.id = ea.pessoa_id
               JOIN ebd_classes c ON c.id = ea.classe_id
              WHERE ea.status = 'matriculado' AND p.deleted_at IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM ebd_frequencias f
                     JOIN ebd_aulas a ON a.id = f.aula_id
                     WHERE f.aluno_id = ea.id AND f.presente = 1
                       AND a.data >= '{$limite}'
                )
              ORDER BY c.nome, p.nome_completo"
        )->fetchAll();
    }
}
