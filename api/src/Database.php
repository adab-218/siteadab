<?php

declare(strict_types=1);

namespace Adab\Api;

use PDO;
use PDOException;

/**
 * Fábrica de conexão PDO (singleton por request).
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * @param array{host:string,name:string,user:string,pass:string,charset:string} $db
     */
    public static function pdo(array $db): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['name'],
                $db['charset'] ?? 'utf8mb4'
            );
            try {
                self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log('[adab-api] Falha na conexão com o banco: ' . $e->getMessage());
                throw new \RuntimeException('Banco de dados indisponível.', 0, $e);
            }
        }
        return self::$pdo;
    }
}
