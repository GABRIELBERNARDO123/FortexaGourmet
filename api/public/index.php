<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Controllers/TabController.php';

use App\Config\Database;
use App\Controllers\TabController;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$tabController = new TabController($db);

match (true) {
    $uri === '/api/tabs' && $method === 'GET' => $tabController->getOpenTabs(),
    $uri === '/api/tabs/open' && $method === 'POST' => $tabController->openTab(),
    default => (function() { http_response_code(404); echo json_encode(['error' => 'Rota não encontrada']); })()
};
