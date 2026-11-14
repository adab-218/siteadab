<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * KPIs agregados do painel (Fase 7 — Dashboard). Só contagens e médias,
 * sem dados pessoais — por isso abertos a qualquer perfil autenticado.
 */
final class DashboardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function resumo(): array
    {
        $membros = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(m.status = 'ativo'), 0) AS ativos
               FROM membros m JOIN pessoas p ON p.id = m.pessoa_id
              WHERE m.deleted_at IS NULL AND p.deleted_at IS NULL"
        )->fetch();

        $cultos = $this->pdo->query(
            "SELECT COUNT(*) AS realizados,
                    ROUND(AVG(total_presentes)) AS media_presentes,
                    COALESCE(SUM(total_visitantes), 0) AS visitantes,
                    COALESCE(SUM(total_decisoes), 0) AS decisoes
               FROM cultos
              WHERE data BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()"
        )->fetch();

        // última aula da EBD com chamada lançada (até hoje)
        $ebd = $this->pdo->query(
            "SELECT a.data,
                    COALESCE(SUM(f.presente = 1), 0) AS presentes,
                    COUNT(*) AS registrados
               FROM ebd_aulas a
               JOIN ebd_frequencias f ON f.aula_id = a.id
              WHERE a.data <= CURDATE()
              GROUP BY a.id, a.data
              ORDER BY a.data DESC, a.id DESC
              LIMIT 1"
        )->fetch() ?: null;
        $matriculados = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM ebd_alunos a
               JOIN pessoas p ON p.id = a.pessoa_id AND p.deleted_at IS NULL
               JOIN ebd_classes c ON c.id = a.classe_id AND c.ativo = 1
              WHERE a.status = 'matriculado'"
        )->fetchColumn();

        $eventos = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM eventos
              WHERE deleted_at IS NULL AND status <> 'cancelado'
                AND COALESCE(data_fim, data_inicio) >= CURDATE()
                AND data_inicio <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        $rifas = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM rifas WHERE deleted_at IS NULL AND status = 'ativo'"
        )->fetchColumn();

        $registrados = $ebd ? (int) $ebd['registrados'] : 0;
        // mês de referência do servidor (o título do card usa este, não o do navegador)
        $ref = $this->pdo->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
        return [
            'referencia_mes' => (string) $ref,
            'membros' => ['total' => (int) $membros['total'], 'ativos' => (int) $membros['ativos']],
            'cultos_mes' => [
                'realizados' => (int) $cultos['realizados'],
                'media_presentes' => $cultos['media_presentes'] !== null ? (int) $cultos['media_presentes'] : null,
                'visitantes' => (int) $cultos['visitantes'],
                'decisoes' => (int) $cultos['decisoes'],
            ],
            'ebd' => [
                'matriculados' => $matriculados,
                'ultima_aula' => $ebd ? (string) $ebd['data'] : null,
                'presentes' => $ebd ? (int) $ebd['presentes'] : 0,
                'frequencia_pct' => $registrados > 0
                    ? (int) round(100 * (int) $ebd['presentes'] / $registrados) : null,
            ],
            'eventos_proximos_30d' => $eventos,
            'rifas_ativas' => $rifas,
        ];
    }
}
