<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Acesso a rifas (sorteios beneficentes) — doc 13 (M7).
 * Tabelas: rifas, rifa_premios, rifa_reparticao, rifa_divulgadores,
 * rifa_bilhetes. Soft delete em rifas; demais cascateiam.
 */
final class RifaRepository
{
    public const STATUS = ['rascunho', 'ativo', 'encerrado', 'sorteado', 'cancelado'];
    public const METODOS = ['sistema', 'federal'];
    public const STATUS_PAG = ['pendente', 'pago', 'gratuito', 'expirado', 'falhou'];

    /** Status de bilhete que contam como arrecadação. */
    private const PAGOS = "'pago'";

    private const CAMPOS = [
        'titulo', 'descricao', 'regulamento', 'capa_url', 'valor_numero',
        'total_numeros', 'max_por_pessoa', 'data_sorteio', 'metodo',
        'pix_instrucoes', 'status',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    // ==================== rifas ====================

    /** @return array{itens: array, total: int} */
    public function listarPaginado(array $filtros, int $pagina, int $porPagina): array
    {
        $where = ['r.deleted_at IS NULL'];
        $params = [];
        if (($filtros['status'] ?? '') !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $filtros['status'];
        }
        if (($filtros['q'] ?? '') !== '') {
            $where[] = 'r.titulo LIKE :q';
            $params['q'] = '%' . $filtros['q'] . '%';
        }
        $cond = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rifas r WHERE {$cond}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($pagina - 1) * $porPagina;
        $stmt = $this->pdo->prepare(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM rifa_bilhetes b
                      WHERE b.rifa_id = r.id AND b.status_pag = 'pago') AS bilhetes_pagos,
                    (SELECT COUNT(*) FROM rifa_bilhetes b
                      WHERE b.rifa_id = r.id AND b.status_pag = 'pendente') AS bilhetes_pendentes,
                    (SELECT COUNT(*) FROM rifa_premios p WHERE p.rifa_id = r.id) AS total_premios
               FROM rifas r
              WHERE {$cond}
              ORDER BY r.created_at DESC
              LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->execute();
        return ['itens' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<string,mixed>|false */
    public function buscar(int $id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rifas WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** Rifa completa para o painel: prêmios, repartição, divulgadores. */
    public function detalhe(int $id): array|false
    {
        $rifa = $this->buscar($id);
        if (!$rifa) {
            return false;
        }
        $rifa['premios'] = $this->listarPremios($id);
        $rifa['reparticao'] = $this->listarReparticao($id);
        $rifa['divulgadores'] = $this->listarDivulgadores($id);
        return $rifa;
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados, ?int $uid): int
    {
        $campos = array_intersect_key($dados, array_flip(self::CAMPOS));
        $campos['slug'] = $this->slugUnico((string) $dados['titulo']);
        $campos['created_by'] = $uid;
        $cols = implode(', ', array_keys($campos));
        $marks = implode(', ', array_fill(0, count($campos), '?'));
        $this->pdo->prepare("INSERT INTO rifas ({$cols}) VALUES ({$marks})")
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
        $this->pdo->prepare("UPDATE rifas SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare('UPDATE rifas SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    private function slugUnico(string $titulo): string
    {
        $base = mb_strtolower($titulo);
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base;
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?: '';
        $base = trim($base, '-') ?: 'sorteio';
        $base = substr($base, 0, 90);
        for ($i = 0; $i < 50; $i++) {
            $slug = $i === 0 ? $base : "{$base}-{$i}";
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rifas WHERE slug = ?');
            $stmt->execute([$slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
        }
        return $base . '-' . bin2hex(random_bytes(4));
    }

    // ==================== ciclo de vida ====================

    public function mudarStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE rifas SET status = ? WHERE id = ?')
                  ->execute([$status, $id]);
    }

    public function salvarCancelamento(int $id, string $motivo): void
    {
        $this->pdo->prepare(
            "UPDATE rifas SET status = 'cancelado', cancelado_motivo = ? WHERE id = ?"
        )->execute([$motivo, $id]);
    }

    /** Ativação exige ≥1 prêmio + regulamento + repartição Σ=100%. */
    public function podeAtivar(int $id): array
    {
        $rifa = $this->buscar($id);
        if (!$rifa) {
            return ['ok' => false, 'erro' => 'Rifa não encontrada.'];
        }
        if (trim((string) ($rifa['regulamento'] ?? '')) === '') {
            return ['ok' => false, 'code' => 'SEM_REGULAMENTO',
                    'erro' => 'Preencha o regulamento público antes de ativar.'];
        }
        if (count($this->listarPremios($id)) === 0) {
            return ['ok' => false, 'code' => 'SEM_PREMIO',
                    'erro' => 'Cadastre ao menos 1 prêmio antes de ativar.'];
        }
        $soma = array_sum(array_map(fn($r) => (float) $r['percentual'], $this->listarReparticao($id)));
        if (count($this->listarReparticao($id)) === 0) {
            return ['ok' => false, 'code' => 'SEM_REPARTICAO',
                    'erro' => 'Defina a repartição da arrecadação antes de ativar.'];
        }
        if (abs($soma - 100.0) > 0.01) {
            return ['ok' => false, 'code' => 'REPARTICAO_INVALIDA',
                    'erro' => "Repartição deve somar 100% (atual: {$soma}%)."];
        }
        return ['ok' => true];
    }

    // ==================== prêmios ====================

    /** @return array<int,array> */
    public function listarPremios(int $rifaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, d.nome AS departamento_nome,
                    b.numero AS bilhete_vencedor_numero, b.nome AS vencedor_nome
               FROM rifa_premios p
               JOIN departamentos d ON d.id = p.departamento_id
               LEFT JOIN rifa_bilhetes b ON b.id = p.bilhete_vencedor_id
              WHERE p.rifa_id = ?
              ORDER BY p.ordem, p.id'
        );
        $stmt->execute([$rifaId]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|false */
    public function buscarPremio(int $id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rifa_premios WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** @param array<string,mixed> $dados */
    public function criarPremio(int $rifaId, array $dados): int
    {
        $this->pdo->prepare(
            'INSERT INTO rifa_premios (rifa_id, ordem, titulo, descricao, valor_estimado, departamento_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $rifaId,
            (int) ($dados['ordem'] ?? 1),
            $dados['titulo'],
            $dados['descricao'] ?? null,
            $dados['valor_estimado'] ?? null,
            (int) $dados['departamento_id'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $dados */
    public function atualizarPremio(int $id, array $dados): void
    {
        $campos = array_intersect_key($dados, array_flip(
            ['ordem', 'titulo', 'descricao', 'valor_estimado', 'imagem_url', 'departamento_id']
        ));
        if ($campos === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
        $this->pdo->prepare("UPDATE rifa_premios SET {$sets} WHERE id = ?")
                  ->execute([...array_values($campos), $id]);
    }

    public function removerPremio(int $id): void
    {
        $this->pdo->prepare('DELETE FROM rifa_premios WHERE id = ?')->execute([$id]);
    }

    // ==================== repartição ====================

    /** @return array<int,array> */
    public function listarReparticao(int $rifaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rifa_reparticao WHERE rifa_id = ? ORDER BY id'
        );
        $stmt->execute([$rifaId]);
        return $stmt->fetchAll();
    }

    /** Replace-all idempotente dentro de transação chamada pelo controller. */
    public function salvarReparticao(int $rifaId, array $linhas): void
    {
        $this->pdo->prepare('DELETE FROM rifa_reparticao WHERE rifa_id = ?')->execute([$rifaId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO rifa_reparticao (rifa_id, destino, tipo, percentual) VALUES (?, ?, ?, ?)'
        );
        foreach ($linhas as $l) {
            $ins->execute([
                $rifaId,
                $l['destino'],
                ($l['tipo'] ?? '') === 'departamentos' ? 'departamentos' : 'livre',
                $l['percentual'],
            ]);
        }
    }

    // ==================== divulgadores ====================

    /** @return array<int,array> */
    public function listarDivulgadores(int $rifaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, p.nome_completo,
                    (SELECT COUNT(*) FROM rifa_bilhetes b
                      WHERE b.divulgador_id = d.id AND b.status_pag IN (\'pago\',\'pendente\',\'gratuito\')) AS vendas,
                    (SELECT COUNT(*) FROM rifa_bilhetes b
                      WHERE b.divulgador_id = d.id AND b.status_pag = \'pago\') AS vendas_pagas
               FROM rifa_divulgadores d
               JOIN pessoas p ON p.id = d.pessoa_id
              WHERE d.rifa_id = ?
              ORDER BY p.nome_completo'
        );
        $stmt->execute([$rifaId]);
        return $stmt->fetchAll();
    }

    /** Cria (ou reativa) o link de divulgação do membro; retorna a linha. */
    public function criarDivulgador(int $rifaId, int $pessoaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rifa_divulgadores WHERE rifa_id = ? AND pessoa_id = ?'
        );
        $stmt->execute([$rifaId, $pessoaId]);
        $existe = $stmt->fetch();
        if ($existe) {
            if (!$existe['ativo']) {
                $this->pdo->prepare('UPDATE rifa_divulgadores SET ativo = 1 WHERE id = ?')
                          ->execute([$existe['id']]);
            }
            $existe['ativo'] = 1;
            return $existe;
        }
        $token = bin2hex(random_bytes(16));
        $this->pdo->prepare(
            'INSERT INTO rifa_divulgadores (rifa_id, pessoa_id, token) VALUES (?, ?, ?)'
        )->execute([$rifaId, $pessoaId, $token]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'rifa_id' => $rifaId,
            'pessoa_id' => $pessoaId,
            'token' => $token,
            'ativo' => 1,
        ];
    }

    public function desativarDivulgador(int $rifaId, int $id): void
    {
        $this->pdo->prepare(
            'UPDATE rifa_divulgadores SET ativo = 0 WHERE id = ? AND rifa_id = ?'
        )->execute([$id, $rifaId]);
    }

    /** Resolve token de divulgação (ativo) → divulgador + depto do membro. */
    public function divulgadorPorToken(string $token): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, md.departamento_id
               FROM rifa_divulgadores d
               LEFT JOIN (SELECT pessoa_id, MIN(departamento_id) AS departamento_id
                            FROM membro_departamentos WHERE data_fim IS NULL
                           GROUP BY pessoa_id) md
                      ON md.pessoa_id = d.pessoa_id
              WHERE d.token = ? AND d.ativo = 1
              LIMIT 1'
        );
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    // ==================== bilhetes ====================

    /** Libera preguiçosamente reservas vencidas (D-R11: pendente > 24h). */
    public function expirarPendentes(int $rifaId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rifa_bilhetes
                SET status_pag = 'expirado', numero = NULL, expira_em = NULL
              WHERE rifa_id = ? AND status_pag = 'pendente' AND expira_em < NOW()"
        );
        $stmt->execute([$rifaId]);
        return $stmt->rowCount();
    }

    /**
     * Reserva N números sequenciais disponíveis. Transação + lock na rifa.
     * Retorna os números alocados; lança RuntimeException('SEM_NUMEROS').
     */
    public function reservarNumeros(int $rifaId, int $quantidade, array $comprador, ?int $divulgadorId): array
    {
        $this->expirarPendentes($rifaId);

        $this->pdo->beginTransaction();
        try {
            // Lock na linha da rifa serializa alocações concorrentes
            $stmt = $this->pdo->prepare('SELECT total_numeros FROM rifas WHERE id = ? FOR UPDATE');
            $stmt->execute([$rifaId]);
            $total = (int) $stmt->fetchColumn();

            $usados = $this->pdo->prepare(
                'SELECT numero FROM rifa_bilhetes WHERE rifa_id = ? AND numero IS NOT NULL'
            );
            $usados->execute([$rifaId]);
            $ocupados = array_flip(array_map('intval', $usados->fetchAll(PDO::FETCH_COLUMN)));

            $livres = [];
            for ($n = 1; $n <= $total && count($livres) < $quantidade; $n++) {
                if (!isset($ocupados[$n])) {
                    $livres[] = $n;
                }
            }
            if (count($livres) < $quantidade) {
                throw new \RuntimeException('SEM_NUMEROS');
            }

            $ins = $this->pdo->prepare(
                "INSERT INTO rifa_bilhetes
                 (rifa_id, numero, nome, telefone, email, status_pag, divulgador_id, expira_em)
                 VALUES (?, ?, ?, ?, ?, 'pendente', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
            );
            $ids = [];
            foreach ($livres as $num) {
                $ins->execute([
                    $rifaId, $num, $comprador['nome'], $comprador['telefone'],
                    $comprador['email'] ?? null, $divulgadorId,
                ]);
                $ids[] = ['id' => (int) $this->pdo->lastInsertId(), 'numero' => $num];
            }
            $this->pdo->commit();
            return $ids;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{itens: array, total: int} */
    public function listarBilhetes(int $rifaId, array $filtros, int $pagina, int $porPagina): array
    {
        $where = ['b.rifa_id = :rifa'];
        $params = ['rifa' => $rifaId];
        if (($filtros['status_pag'] ?? '') !== '') {
            $where[] = 'b.status_pag = :sp';
            $params['sp'] = $filtros['status_pag'];
        }
        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(b.nome LIKE :q OR b.telefone LIKE :q OR b.numero = :qn)';
            $params['q'] = '%' . $filtros['q'] . '%';
            $params['qn'] = is_numeric($filtros['q']) ? (int) $filtros['q'] : -1;
        }
        if (!empty($filtros['divulgador_id'])) {
            $where[] = 'b.divulgador_id = :dv';
            $params['dv'] = (int) $filtros['divulgador_id'];
        }
        if (!empty($filtros['departamento_id'])) {
            $where[] = 'ddep.id = :dep';
            $params['dep'] = (int) $filtros['departamento_id'];
        }
        $cond = implode(' AND ', $where);
        $joinDiv = 'LEFT JOIN rifa_divulgadores rd ON rd.id = b.divulgador_id
                    LEFT JOIN pessoas pd ON pd.id = rd.pessoa_id
                    LEFT JOIN (SELECT pessoa_id, MIN(departamento_id) AS departamento_id
                                 FROM membro_departamentos WHERE data_fim IS NULL
                                GROUP BY pessoa_id) md ON md.pessoa_id = rd.pessoa_id
                    LEFT JOIN departamentos ddep ON ddep.id = md.departamento_id';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rifa_bilhetes b {$joinDiv} WHERE {$cond}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($pagina - 1) * $porPagina;
        $stmt = $this->pdo->prepare(
            "SELECT b.*, pd.nome_completo AS divulgador_nome, ddep.nome AS departamento_nome
               FROM rifa_bilhetes b
               {$joinDiv}
              WHERE {$cond}
              ORDER BY b.numero
              LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->execute();
        return ['itens' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<string,mixed>|false */
    public function buscarBilhete(int $id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rifa_bilhetes WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function marcarPago(int $bilheteId, ?int $uid): void
    {
        $this->pdo->prepare(
            "UPDATE rifa_bilhetes
                SET status_pag = 'pago', pago_em = NOW(), pago_por = ?, expira_em = NULL
              WHERE id = ? AND status_pag = 'pendente'"
        )->execute([$uid, $bilheteId]);
    }

    /** Estorna pago → pendente(24h) ou libera número se pendente/expirado. */
    public function estornar(int $bilheteId): void
    {
        $this->pdo->prepare(
            "UPDATE rifa_bilhetes
                SET status_pag = CASE WHEN status_pag IN ('pendente','falhou','expirado')
                                      THEN 'expirado' ELSE 'pendente' END,
                    numero = CASE WHEN status_pag IN ('pendente','falhou','expirado')
                                  THEN NULL ELSE numero END,
                    pago_em = NULL, pago_por = NULL,
                    expira_em = CASE WHEN status_pag = 'pago'
                                     THEN DATE_ADD(NOW(), INTERVAL 24 HOUR) ELSE NULL END
              WHERE id = ?"
        )->execute([$bilheteId]);
    }

    /** Bilhetes do telefone na rifa (consulta pública — sem nome). */
    public function bilhetesPorTelefone(int $rifaId, string $telefone): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.numero, b.status_pag,
                    (SELECT COUNT(*) FROM rifa_premios p WHERE p.bilhete_vencedor_id = b.id) AS vencedor
               FROM rifa_bilhetes b
              WHERE b.rifa_id = ? AND b.telefone = ? AND b.numero IS NOT NULL
              ORDER BY b.numero'
        );
        $stmt->execute([$rifaId, $telefone]);
        return $stmt->fetchAll();
    }

    // ==================== ranking & extrato ====================

    /** Vendas por membro (divulgador) — ranking. */
    public function ranking(int $rifaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rd.id AS divulgador_id, p.nome_completo,
                    COALESCE(ddep.id, 0) AS departamento_id,
                    COALESCE(ddep.nome, 'Sem departamento') AS departamento_nome,
                    COUNT(*) AS reservas,
                    SUM(b.status_pag = 'pago') AS pagos,
                    SUM(b.status_pag = 'pendente') AS pendentes,
                    ROUND(SUM(b.status_pag = 'pago') * (SELECT valor_numero FROM rifas WHERE id = ?), 2) AS arrecadado
               FROM rifa_bilhetes b
               JOIN rifa_divulgadores rd ON rd.id = b.divulgador_id
               JOIN pessoas p ON p.id = rd.pessoa_id
               LEFT JOIN (SELECT pessoa_id, MIN(departamento_id) AS departamento_id
                            FROM membro_departamentos WHERE data_fim IS NULL
                           GROUP BY pessoa_id) md ON md.pessoa_id = rd.pessoa_id
               LEFT JOIN departamentos ddep ON ddep.id = md.departamento_id
              WHERE b.rifa_id = ? AND b.status_pag IN ('pago','pendente','gratuito')
              GROUP BY rd.id, p.nome_completo, ddep.id, ddep.nome
              ORDER BY pagos DESC, reservas DESC"
        );
        $stmt->execute([$rifaId, $rifaId]);
        return $stmt->fetchAll();
    }

    /**
     * Extrato de repartição: arrecadado (só 'pago') × % por destino;
     * tipo 'departamentos' rateia proporcional às vendas por depto (D-R8).
     */
    public function extratoReparticao(int $rifaId): array
    {
        $rifa = $this->buscar($rifaId);
        $valor = (float) ($rifa['valor_numero'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT status_pag, COUNT(*) AS qtd FROM rifa_bilhetes WHERE rifa_id = ? GROUP BY status_pag"
        );
        $stmt->execute([$rifaId]);
        $qtd = array_column($stmt->fetchAll(), 'qtd', 'status_pag');
        $pagos = (int) ($qtd['pago'] ?? 0);
        $arrecadado = round($pagos * $valor, 2);

        // vendas por departamento (via depto atual do divulgador)
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(ddep.id, 0) AS departamento_id,
                    COALESCE(ddep.nome, 'Sem departamento') AS departamento_nome,
                    COUNT(*) AS pagos
               FROM rifa_bilhetes b
               JOIN rifa_divulgadores rd ON rd.id = b.divulgador_id
               LEFT JOIN (SELECT pessoa_id, MIN(departamento_id) AS departamento_id
                            FROM membro_departamentos WHERE data_fim IS NULL
                           GROUP BY pessoa_id) md ON md.pessoa_id = rd.pessoa_id
               LEFT JOIN departamentos ddep ON ddep.id = md.departamento_id
              WHERE b.rifa_id = ? AND b.status_pag = 'pago'
              GROUP BY ddep.id, ddep.nome"
        );
        $stmt->execute([$rifaId]);
        $porDepto = $stmt->fetchAll();
        $totalPorDepto = array_sum(array_column($porDepto, 'pagos'));

        $destinos = [];
        foreach ($this->listarReparticao($rifaId) as $r) {
            $item = [
                'destino' => $r['destino'],
                'tipo' => $r['tipo'],
                'percentual' => (float) $r['percentual'],
                'valor' => round($arrecadado * (float) $r['percentual'] / 100, 2),
            ];
            if ($r['tipo'] === 'departamentos') {
                $item['rateio'] = array_map(function ($d) use ($totalPorDepto, $item) {
                    $part = $totalPorDepto > 0 ? $d['pagos'] / $totalPorDepto : 0;
                    return [
                        'departamento_id' => (int) $d['departamento_id'],
                        'departamento_nome' => $d['departamento_nome'],
                        'bilhetes_pagos' => (int) $d['pagos'],
                        'valor' => round($item['valor'] * $part, 2),
                    ];
                }, $porDepto);
            }
            $destinos[] = $item;
        }

        return [
            'valor_numero' => $valor,
            'bilhetes' => $qtd,
            'arrecadado' => $arrecadado,
            'destinos' => $destinos,
        ];
    }

    // ==================== apuração ====================

    /**
     * Sorteia vencedores (status → 'sorteado').
     * metodo 'sistema': SHA-256(seed:premioId:i) → índice no pool elegível.
     * metodo 'federal': últimos N dígitos dos números informados; fallback
     * determinístico. Transacional.
     */
    public function sortear(int $rifaId, ?string $seed, ?string $federalNumeros): array
    {
        $rifa = $this->buscar($rifaId);
        $premios = $this->listarPremios($rifaId);

        $eleg = $this->pdo->prepare(
            "SELECT numero FROM rifa_bilhetes
              WHERE rifa_id = ? AND status_pag IN ('pago','gratuito')
              ORDER BY numero"
        );
        $eleg->execute([$rifaId]);
        $pool = array_map('intval', $eleg->fetchAll(PDO::FETCH_COLUMN));
        if ($pool === []) {
            throw new \RuntimeException('SEM_ELEGIVEIS');
        }

        $this->pdo->beginTransaction();
        try {
            $vencedores = [];
            if ($rifa['metodo'] === 'federal') {
                $seedFinal = 'federal:' . $federalNumeros;
                $numeros = array_values(array_filter(array_map('trim', explode(',', (string) $federalNumeros)), fn($n) => $n !== ''));
                $digitos = strlen((string) $rifa['total_numeros']);
                $idxFed = 0;
                foreach ($premios as $premio) {
                    $vencedor = null;
                    for (; $idxFed < count($numeros); $idxFed++) {
                        $extraido = ((int) substr(preg_replace('/\D/', '', $numeros[$idxFed]), -$digitos));
                        if (in_array($extraido, $pool, true)) {
                            $vencedor = $extraido;
                            $idxFed++;
                            break;
                        }
                    }
                    if ($vencedor === null) {
                        // fallback determinístico
                        $ultimo = (int) substr(preg_replace('/\D/', '', end($numeros) ?: '0'), -$digitos);
                        $vencedor = $pool[($ultimo + count($vencedores)) % count($pool)];
                    }
                    $pool = array_values(array_diff($pool, [$vencedor]));
                    $vencedores[] = $vencedor;
                }
            } else {
                $seedFinal = $seed ?: bin2hex(random_bytes(32));
                foreach ($premios as $i => $premio) {
                    if ($pool === []) {
                        break;
                    }
                    $h = hash('sha256', "{$seedFinal}:{$premio['id']}:{$i}");
                    $idx = hexdec(substr($h, 0, 15)) % count($pool);
                    $vencedor = $pool[$idx];
                    array_splice($pool, $idx, 1);
                    $vencedores[] = $vencedor;
                }
            }

            // bilhete id pelo número
            $prova = hash('sha256', $seedFinal);
            $this->pdo->prepare(
                'UPDATE rifas SET status = ?, seed = ?, prova_hash = ?, federal_numeros = ? WHERE id = ?'
            )->execute(['sorteado', $seedFinal, $prova, $federalNumeros, $rifaId]);

            $porNumero = $this->pdo->prepare(
                'SELECT id FROM rifa_bilhetes WHERE rifa_id = ? AND numero = ?'
            );
            $marcaVencedor = $this->pdo->prepare(
                'UPDATE rifa_premios SET bilhete_vencedor_id = ? WHERE id = ?'
            );
            foreach ($premios as $i => $premio) {
                if (!isset($vencedores[$i])) {
                    break;
                }
                $porNumero->execute([$rifaId, $vencedores[$i]]);
                $marcaVencedor->execute([(int) $porNumero->fetchColumn(), (int) $premio['id']]);
            }
            $this->pdo->commit();
            return [
                'vencedores' => $vencedores,
                'seed' => $seedFinal,
                'prova' => $prova,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ==================== público ====================

    /** Lista para o site (view v_rifas_publicas). */
    public function listarPublico(): array
    {
        return $this->pdo->query('SELECT * FROM v_rifas_publicas ORDER BY data_sorteio ASC')->fetchAll();
    }

    /** Detalhe público por slug (sem dados de compradores/divulgadores). */
    public function buscarPublicoPorSlug(string $slug): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM v_rifas_publicas WHERE slug = ?');
        $stmt->execute([$slug]);
        $rifa = $stmt->fetch();
        if (!$rifa) {
            return false;
        }
        // prêmios públicos (depto doador + título, sem vencedor_nome se não sorteado)
        $stmt = $this->pdo->prepare(
            'SELECT p.ordem, p.titulo, p.descricao, p.valor_estimado, p.imagem_url,
                    d.nome AS departamento_nome,
                    CASE WHEN r.status = \'sorteado\' THEN b.numero ELSE NULL END AS numero_vencedor
               FROM rifa_premios p
               JOIN rifas r ON r.id = p.rifa_id
               JOIN departamentos d ON d.id = p.departamento_id
               LEFT JOIN rifa_bilhetes b ON b.id = p.bilhete_vencedor_id
              WHERE p.rifa_id = ?
              ORDER BY p.ordem, p.id'
        );
        $stmt->execute([(int) $rifa['id']]);
        $rifa['premios'] = $stmt->fetchAll();
        unset($rifa['id']); // id interno não é necessário no público? (mantido? removido p/ higiene)
        return $rifa;
    }
}
