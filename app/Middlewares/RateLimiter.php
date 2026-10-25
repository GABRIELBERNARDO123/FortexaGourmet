<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Database;
use App\Core\Response;

class RateLimiter
{
    // Limita a 5 tentativas de login por minuto por IP
    public static function checkLogin(string $ip): void
    {
        $db = Database::getConnection();
        
        // Como não temos tabela de rate limit, usamos um arquivo simples no diretório temporário do sistema.
        // Em produção robusta, usaríamos Redis.
        $cacheFile = sys_get_temp_dir() . '/fortexa_rl_' . md5($ip);
        
        $attempts = [];
        if (file_exists($cacheFile)) {
            $attempts = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        // Filtra tentativas mais antigas que 1 minuto
        $now = time();
        $attempts = array_filter($attempts, fn($timestamp) => $now - $timestamp < 60);

        if (count($attempts) >= 5) {
            Response::error('Muitas tentativas de login. Aguarde 1 minuto.', 429);
        }

        // Registra nova tentativa
        $attempts[] = $now;
        file_put_contents($cacheFile, json_encode($attempts));
    }
}
