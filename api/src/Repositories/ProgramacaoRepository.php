<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Consultas públicas do site (somente leitura, dados públicos).
 */
final class ProgramacaoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function programacaoPublica(): array
    {
        return $this->pdo->query(
            'SELECT tipo, titulo, data, horario_inicio, data_fim, categoria
               FROM v_programacao_publica
              ORDER BY data, horario_inicio'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function eventosPublicos(): array
    {
        return $this->pdo->query(
            "SELECT id, nome, tipo, data_inicio, data_fim, local, descricao, banner_url
               FROM eventos
              WHERE publico = 1
                AND status <> 'cancelado'
                AND deleted_at IS NULL
                AND COALESCE(data_fim, data_inicio) >= CURDATE()
              ORDER BY data_inicio"
        )->fetchAll();
    }
}
