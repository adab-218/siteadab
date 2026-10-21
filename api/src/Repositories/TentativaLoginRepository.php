<?php

declare(strict_types=1);

namespace Adab\Api\Repositories;

use PDO;

/**
 * Rate limit de autenticação na tabela `login_tentativas`.
 * O e-mail é armazenado como hash SHA-256 (não é dado de consulta,
 * apenas chave de contagem — LGPD).
 */
final class TentativaLoginRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function contarPorEmailEIp(string $emailHash, string $ip, int $janelaMinutos): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_tentativas
              WHERE email_hash = :hash AND ip = :ip
                AND criado_em > DATE_SUB(NOW(), INTERVAL ' . $janelaMinutos . ' MINUTE)'
        );
        $stmt->execute(['hash' => $emailHash, 'ip' => $ip]);
        return (int) $stmt->fetchColumn();
    }

    public function contarPorIp(string $ip, int $janelaMinutos): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_tentativas
              WHERE ip = :ip
                AND criado_em > DATE_SUB(NOW(), INTERVAL ' . $janelaMinutos . ' MINUTE)'
        );
        $stmt->execute(['ip' => $ip]);
        return (int) $stmt->fetchColumn();
    }

    public function registrar(string $emailHash, string $ip): void
    {
        $this->pdo->prepare('INSERT INTO login_tentativas (email_hash, ip) VALUES (?, ?)')
                  ->execute([$emailHash, $ip]);
    }

    public function limpar(string $emailHash, string $ip): void
    {
        $this->pdo->prepare('DELETE FROM login_tentativas WHERE email_hash = ? AND ip = ?')
                  ->execute([$emailHash, $ip]);
    }

    public function purgarAntigas(int $dias = 1): void
    {
        $this->pdo->exec('DELETE FROM login_tentativas WHERE criado_em < DATE_SUB(NOW(), INTERVAL ' . $dias . ' DAY)');
    }
}
