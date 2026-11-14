<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Rate limit genérico por (ação, IP) em `rate_limit_eventos` (migration
 * 0011). Usado nas rotas públicas de sorteio; o login segue em
 * `login_tentativas`. Não guarda dado pessoal além do IP (retido 1 dia).
 */
final class RateLimitRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Soma dos pesos da ação para o IP na janela (minutos). */
    public function somar(string $acao, string $ip, int $janelaMinutos): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(peso), 0) FROM rate_limit_eventos
              WHERE acao = ? AND ip = ?
                AND criado_em > DATE_SUB(NOW(), INTERVAL ' . (int) $janelaMinutos . ' MINUTE)'
        );
        $stmt->execute([$acao, $ip]);
        return (int) $stmt->fetchColumn();
    }

    public function registrar(string $acao, string $ip, int $peso = 1): void
    {
        $this->pdo->prepare('INSERT INTO rate_limit_eventos (acao, ip, peso) VALUES (?, ?, ?)')
                  ->execute([$acao, $ip, max(1, $peso)]);
        // purge probabilístico (~5%) — mantém a tabela pequena
        if (random_int(1, 20) === 1) {
            $this->pdo->exec('DELETE FROM rate_limit_eventos WHERE criado_em < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
    }
}
