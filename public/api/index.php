<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://localhost', 'https://fortexagourmet.com', 'http://localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://localhost');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Router;
use App\Core\Response;
use App\Core\Security;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RateLimiter;
use App\Middlewares\RoleMiddleware;
use App\Controllers\AuthController;
use App\Controllers\TabController;
use App\Controllers\ProductController;
use App\Controllers\ClientController;
use App\Controllers\CashFlowController;
use App\Controllers\ReportController;
use App\Controllers\DashboardController;
use App\Controllers\AccountController;

try {
    $router = new Router();
    $auth = new AuthController();
    $tabs = new TabController();
    $products = new ProductController();
    $clients = new ClientController();
    $cashflow = new CashFlowController();
    $reports = new ReportController();
    $dashboard = new DashboardController();
$account = new AccountController();

    $protected = function(callable $action, bool $write = true) {
        return function(...$args) use ($action, $write) {
            AuthMiddleware::handle();
            if ($write) Security::verifyCsrf();
            $action(...$args);
        };
    };

    // ========== ROTAS PÚBLICAS ==========
    $router->get('/status', function() { Response::success(['api' => 'online']); });
    $router->post('/login', function() use ($auth) { RateLimiter::checkLogin($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'); $auth->login(); });
    $router->post('/logout', [$auth, 'logout']);

    // ========== SESSÃO E USUÁRIOS ==========
    $router->get('/me', $protected(function() use ($auth) { $auth->me(); }, false));
    $router->get('/usuarios', $protected(function() use ($auth) { $auth->listUsers(); }, false));
    $router->post('/usuarios', $protected(function() use ($auth) { RoleMiddleware::requireDono(); $auth->createUser(); }));
    $router->put('/usuarios/{id}', $protected(function($id) use ($auth) { RoleMiddleware::requireDono(); $auth->updateUser($id); }));
    $router->delete('/usuarios/{id}', $protected(function($id) use ($auth) { RoleMiddleware::requireDono(); $auth->deleteUser($id); }));
    $router->get('/overview', $protected(function() use ($dashboard) { $dashboard->overview(); }, false));
$router->get('/account', $protected(function() use ($account) { $account->show(); }, false));
$router->put('/account', $protected(function() use ($account) { RoleMiddleware::requireDono(); $account->update(); }));

    // ========== PRODUTOS ==========
    $router->get('/produtos', $protected(function() use ($products) { $products->index(); }, false));
    $router->get('/produtos/{id}', $protected(function($id) use ($products) { $products->show($id); }, false));
    $router->post('/produtos', $protected(function() use ($products) { RoleMiddleware::requireDono(); $products->store(); }));
    $router->put('/produtos/{id}', $protected(function($id) use ($products) { RoleMiddleware::requireDono(); $products->update($id); }));
    $router->delete('/produtos/{id}', $protected(function($id) use ($products) { RoleMiddleware::requireDono(); $products->delete($id); }));
    $router->post('/produtos/{id}/estoque', $protected(function($id) use ($products) { RoleMiddleware::requireDono(); $products->adjustStock($id); }));
    $router->get('/categorias', $protected(function() use ($products) { $products->categories(); }, false));
    $router->post('/categorias', $protected(function() use ($products) { RoleMiddleware::requireDono(); $products->storeCategory(); }));

    // ========== CLIENTES ==========
    $router->get('/clientes', $protected(function() use ($clients) { $clients->index(); }, false));
    $router->get('/clientes/{id}', $protected(function($id) use ($clients) { $clients->show($id); }, false));
    $router->post('/clientes', $protected(function() use ($clients) { RoleMiddleware::requireDono(); $clients->store(); }));
    $router->put('/clientes/{id}', $protected(function($id) use ($clients) { RoleMiddleware::requireDono(); $clients->update($id); }));
    $router->delete('/clientes/{id}', $protected(function($id) use ($clients) { RoleMiddleware::requireDono(); $clients->delete($id); }));
    $router->post('/clientes/{id}/pontos', $protected(function($id) use ($clients) { RoleMiddleware::requireDono(); $clients->addPoints($id); }));

    // ========== COMANDAS ==========
    $router->get('/comandas', $protected(function() use ($tabs) { $tabs->index(); }, false));
    $router->post('/comandas', $protected(function() use ($tabs) { $tabs->open(); }));
    $router->post('/comandas/{id}/itens', $protected(function($id) use ($tabs) { $tabs->addItem($id); }));
    $router->put('/comandas/{id}/status', $protected(function($id) use ($tabs) { $tabs->updateStatus($id); }));
    $router->post('/comandas/{id}/fechar', $protected(function($id) use ($tabs) { RoleMiddleware::requireDono(); $tabs->close($id); }));
    $router->post('/comandas/{id}/cancelar', $protected(function($id) use ($tabs) { RoleMiddleware::requireDono(); $tabs->cancel($id); }));

    // ========== CAIXA ==========
    $router->get('/caixa', $protected(function() use ($cashflow) { $cashflow->index(); }, false));
    $router->post('/caixa', $protected(function() use ($cashflow) { RoleMiddleware::requireDono(); $cashflow->store(); }));
    $router->post('/caixa/fechar-turno', $protected(function() use ($cashflow) { RoleMiddleware::requireDono(); $cashflow->closeShift(); }));

    // ========== RELATÓRIOS ==========
    $router->get('/relatorios/vendas', $protected(function() use ($reports) { RoleMiddleware::requireDono(); $reports->sales(); }, false));

    // ========== DISPATCH ==========
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = str_replace('/api', '', $uri);
    $router->dispatch($_SERVER['REQUEST_METHOD'], $uri);

} catch (Throwable $e) {
    error_log("API Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
    exit;
}
