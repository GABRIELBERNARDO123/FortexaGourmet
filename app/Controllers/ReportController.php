<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;

class ReportController {
    public function sales(): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        
        // Busca todas as entradas com snapshot
        $stmt = $db->prepare("SELECT valor, items_snapshot FROM fluxo_caixa WHERE tenant_id = ? AND tipo = 'entrada' AND categoria = 'Vendas'");
        $stmt->execute([$tenant]);
        $vendas = $stmt->fetchAll();

        $faturamento = 0;
        $cmv = 0;
        $comandas = count($vendas);

        foreach($vendas as $v) {
            $faturamento += $v['valor'];
            $itens = json_decode($v['items_snapshot'] ?? '[]', true);
            foreach($itens as $item) {
                $cmv += ($item['custo_unitario'] ?? 0) * $item['quantidade'];
            }
        }

        $lucro = $faturamento - $cmv;
        $margem = $faturamento > 0 ? ($lucro / $faturamento) * 100 : 0;
        $ticket = $comandas > 0 ? $faturamento / $comandas : 0;

        Response::success([
            'faturamento' => $faturamento,
            'cmv' => $cmv,
            'lucro' => $lucro,
            'margem' => $margem,
            'ticket_medio' => $ticket,
            'comandas' => $comandas
        ]);
    }
}
