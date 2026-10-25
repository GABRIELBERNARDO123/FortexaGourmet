<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;

class DashboardController {
    public function overview(): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("SELECT COUNT(*) as qtd, COALESCE(SUM(valor_total),0) as total FROM comandas WHERE tenant_id = ? AND status != 'fechada'");
        $stmt->execute([$tenant]);
        $comandas = $stmt->fetch();

        $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) as total FROM fluxo_caixa WHERE tenant_id = ? AND tipo = 'entrada' AND DATE(created_at) = CURRENT_DATE");
        $stmt->execute([$tenant]);
        $vendasHoje = $stmt->fetch();

        $stmt = $db->prepare("SELECT COUNT(*) as qtd FROM produtos WHERE tenant_id = ? AND estoque_atual < estoque_minimo AND ativo = TRUE");
        $stmt->execute([$tenant]);
        $estoqueBaixo = $stmt->fetch();

        $stmt = $db->prepare("SELECT COUNT(*) as qtd FROM clientes WHERE tenant_id = ?");
        $stmt->execute([$tenant]);
        $clientes = $stmt->fetch();

        Response::success([
            'comandas_abertas' => (int)$comandas['qtd'],
            'valor_aberto' => (float)$comandas['total'],
            'vendas_hoje' => (float)$vendasHoje['total'],
            'estoque_baixo' => (int)$estoqueBaixo['qtd'],
            'total_clientes' => (int)$clientes['qtd']
        ]);
    }
}
