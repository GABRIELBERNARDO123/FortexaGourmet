<?php
declare(strict_types=1);
namespace App\Middlewares;

use App\Core\Response;
use App\Core\Auth;

class RoleMiddleware {
    public static function requireDono(): void {
        Auth::startSession();
        if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'dono' && $_SESSION['user_role'] !== 'admin_plataforma')) {
            Response::error('Acesso negado. Apenas o dono pode acessar este recurso.', 403);
        }
    }
}
