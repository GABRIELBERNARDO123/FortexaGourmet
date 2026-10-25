<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;
use Exception;

class AuthController {
    public function login(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $senha = $data['senha'] ?? '';
        if (!$email || !$senha) Response::error('E-mail e senha obrigatórios.', 400);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            Response::error('Credenciais inválidas.', 401);
        }
        Auth::login($user);
        Response::success(['id' => $user['id'], 'nome' => $user['nome'], 'email' => $user['email'], 'role' => $user['role']], 'Login realizado com sucesso.');
    }

    public function logout(): void {
        Auth::logout();
        Response::success(null, 'Logout realizado.');
    }

    public function me(): void {
        Auth::startSession();
        Response::success([
            'id' => $_SESSION['user_id'],
            'nome' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
            'tenant_id' => $_SESSION['tenant_id'],
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    public function listUsers(): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, nome, email, role, status, ultimo_acesso, created_at FROM usuarios WHERE tenant_id = ? ORDER BY nome");
        $stmt->execute([Auth::getTenantId()]);
        Response::success($stmt->fetchAll());
    }

    public function createUser(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $nome = trim($data['nome'] ?? '');
        $email = trim($data['email'] ?? '');
        $senha = $data['senha'] ?? '';
        $role = $data['role'] ?? 'garcom';
        if (!$nome || !$email || !$senha) Response::error('Dados incompletos.', 400);
        if (!in_array($role, ['garcom', 'dono'])) Response::error('Perfil inválido.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("SELECT role, COUNT(*) as qtd FROM usuarios WHERE tenant_id = ? GROUP BY role");
        $stmt->execute([$tenant]);
        $counts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        if ($role === 'garcom' && isset($counts['garcom']) && $counts['garcom'] >= 7) {
            Response::error('Limite de 7 garçons atingido.', 400);
        }
        if ($role === 'dono' && isset($counts['dono']) && $counts['dono'] >= 1) {
            Response::error('Já existe um dono nesta conta.', 400);
        }

        try {
            $hash = password_hash($senha, PASSWORD_ARGON2ID);
            $stmt = $db->prepare("INSERT INTO usuarios (tenant_id, nome, email, senha_hash, role) VALUES (?, ?, ?, ?, ?) RETURNING id, nome, email, role, status");
            $stmt->execute([$tenant, $nome, $email, $hash, $role]);
            Response::success($stmt->fetch(), 'Usuário criado com sucesso.');
        } catch (Exception $e) {
            Response::error('E-mail já cadastrado.', 400);
        }
    }

    public function updateUser(string $id): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuário não encontrado.', 404);

        $nome = trim($data['nome'] ?? $user['nome']);
        $email = trim($data['email'] ?? $user['email']);
        $status = $data['status'] ?? $user['status'];
        $senha = $data['senha'] ?? null;

        $sql = "UPDATE usuarios SET nome = ?, email = ?, status = ?";
        $params = [$nome, $email, $status];
        if ($senha) {
            $sql .= ", senha_hash = ?";
            $params[] = password_hash($senha, PASSWORD_ARGON2ID);
        }
        $sql .= " WHERE id = ? AND tenant_id = ? RETURNING id, nome, email, role, status";
        $params[] = $id;
        $params[] = $tenant;

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            Response::success($stmt->fetch(), 'Usuário atualizado.');
        } catch (Exception $e) {
            Response::error('E-mail já cadastrado.', 400);
        }
    }

    public function deleteUser(string $id): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        $currentUser = Auth::getUserId();
        if ($id === $currentUser) Response::error('Você não pode excluir a si mesmo.', 400);

        $stmt = $db->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant]);
        if ($stmt->rowCount() === 0) Response::error('Usuário não encontrado.', 404);
        Response::success(null, 'Usuário desativado.');
    }
}
