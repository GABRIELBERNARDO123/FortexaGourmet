<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;
use Exception;

class AccountController {
    public function show(): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, nome_fantasia, cnpj, created_at FROM contas WHERE id = ?");
        $stmt->execute([Auth::getTenantId()]);
        Response::success($stmt->fetch());
    }

    public function update(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $nome = trim($data['nome_fantasia'] ?? '');
        if (strlen($nome) < 2) Response::error('Nome deve ter no mínimo 2 caracteres.', 400);

        $db = Database::getConnection();
        $db->prepare("UPDATE contas SET nome_fantasia = ?, cnpj = ? WHERE id = ?")
           ->execute([$nome, $data['cnpj'] ?? null, Auth::getTenantId()]);
        Response::success(null, 'Dados atualizados.');
    }
}
