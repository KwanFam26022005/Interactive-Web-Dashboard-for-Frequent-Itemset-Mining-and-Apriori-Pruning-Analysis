<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;
use PDOException;
use RuntimeException;

class ConnectionFactory
{
    /**
     * Create a PDO connection from a database configuration array.
     *
     * @param array{host: string, port: int, name: string, user: string, password?: string} $config
     */
    public static function create(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $dbName = $config['name'] ?? '';
        $user = $config['user'] ?? '';
        $password = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $password, $options);
            $pdo->exec("SET time_zone = '+00:00'");

            return $pdo;
        } catch (PDOException $e) {
            // Mask password from exception message for security
            $safeMessage = "Database connection failed for DSN: mysql:host={$host};port={$port};dbname={$dbName}. Code: " . $e->getCode();
            throw new RuntimeException($safeMessage, (int)$e->getCode());
        }
    }
}
