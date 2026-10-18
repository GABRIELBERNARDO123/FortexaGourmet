<?php
declare(strict_types=1);
namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // ATENÇÃO: Altere 'user' e 'pass' para o seu usuário do PostgreSQL
            $host = 'localhost';
            $db   = 'fortexa_gourmet';
            $user = 'postgres'; 
            $pass = 'postgres'; 

            try {
                self::$instance = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]);
                exit;
            }
        }
        return self::$instance;
    }
}
