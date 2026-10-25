<?php
declare(strict_types=1);
namespace App\Core;

class Security
{
    public static function verifyCsrf(): void
    {
        Auth::startSession();
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') return;

        // Tenta pegar do $_SERVER (padrão do PHP-FPM)
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        // Fallback seguro para getallheaders()
        if (!$csrfToken && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-csrf-token') {
                    $csrfToken = $value;
                    break;
                }
            }
        }

        if (!$csrfToken || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            Response::error('Token de segurança (CSRF) inválido ou ausente.', 403);
        }
    }

    public static function sanitize(string $data): string
    {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
