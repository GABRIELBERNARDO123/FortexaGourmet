<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'postgres';
            $port = getenv('DB_PORT') ?: '5432';
            $db   = getenv('DB_DATABASE') ?: 'fortexa_db';
            $user = getenv('DB_USERNAME') ?: 'fortexa_user';
            $pass = getenv('DB_PASSWORD') ?: 'senha_super_segura_aqui';

            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=$db";
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // Segurança contra SQL Injection
                ]);
            } catch (PDOException $e) {
                // Log do erro real, mas não expõe ao cliente
                error_log("Database connection error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Erro interno no servidor.']);
                exit;
            }
        }
        return self::$instance;
    }
}
