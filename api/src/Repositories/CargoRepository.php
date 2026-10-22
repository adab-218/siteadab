<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Catálogo de cargos (tabela `cargos`) — usado em selects de formulário.
 */
final class CargoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array{id:int,nome:string,ordem:int}> */
    public function listar(): array
    {
        return $this->pdo
            ->query('SELECT id, nome, ordem FROM cargos ORDER BY ordem, nome')
            ->fetchAll();
    }
}
