<?php
declare(strict_types=1);
namespace App\Core;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            session_set_cookie_params([
                'lifetime' => 0, 'path' => '/', 'domain' => '',
                'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict'
            ]);
            session_name('FORTEXA_SESSID');
            session_start();
        }
    }

    public static function login(array $usuario): void
    {
        self::startSession();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['tenant_id'] = $usuario['tenant_id'];
        $_SESSION['user_name'] = $usuario['nome'];
        $_SESSION['user_role'] = $usuario['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        if ($usuario['tenant_id']) {
            Database::getConnection()->exec("SET app.tenant_id = '{$usuario['tenant_id']}'");
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO sessoes (id, usuario_id, ip_address, user_agent, payload, last_activity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([session_id(), $usuario['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, '', time()]);
    }

    public static function logout(): void
    {
        self::startSession();
        $db = Database::getConnection();
        $db->prepare("DELETE FROM sessoes WHERE id = ?")->execute([session_id()]);
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        if (!isset($_SESSION['user_id'])) return false;

        // AUTOCORREÇÃO: Se o tenant_id estiver vazio na sessão, busca do banco
        if (empty($_SESSION['tenant_id'])) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT tenant_id FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user && $user['tenant_id']) {
                $_SESSION['tenant_id'] = $user['tenant_id'];
            }
        }

        if (!empty($_SESSION['tenant_id'])) {
            Database::getConnection()->exec("SET app.tenant_id = '{$_SESSION['tenant_id']}'");
        }
        
        return true;
    }

    public static function getTenantId(): ?string 
    { 
        self::check(); // Garante que a sessão seja validada e corrigida
        return $_SESSION['tenant_id'] ?? null; 
    }

    public static function getUserId(): ?string 
    { 
        self::startSession(); 
        return $_SESSION['user_id'] ?? null; 
    }
}
