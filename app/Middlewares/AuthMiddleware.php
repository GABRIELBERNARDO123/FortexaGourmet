<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Response;

class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            Response::error('Não autenticado.', 401);
        }
        
        // Opcional: Validar se a conta (tenant) está ativa no banco de dados
        // Para evitar queries extras em todas as requisições, podemos validar o status na tabela sessoes.
    }
}
