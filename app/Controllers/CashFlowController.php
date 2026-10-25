<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;
use Exception;

class CashFlowController {
    public function index(): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM fluxo_caixa WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([Auth::getTenantId()]);
        Response::success($stmt->fetchAll());
    }

    public function store(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $descricao = trim($data['descricao'] ?? '');
        $valor = (float)($data['valor'] ?? 0);
        $tipo = $data['tipo'] ?? 'entrada';
        
        if (!$descricao || $valor <= 0) Response::error('Preencha descrição e valor.', 400);
        if (!in_array($tipo, ['entrada', 'saida'])) Response::error('Tipo inválido.', 400);

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO fluxo_caixa (tenant_id, tipo, valor, descricao, categoria, forma_pagamento) VALUES (?, ?, ?, ?, ?, ?) RETURNING *");
        $stmt->execute([
            Auth::getTenantId(), $tipo, $valor, $descricao,
            $data['categoria'] ?? 'Geral', $data['forma_pagamento'] ?? 'Dinheiro'
        ]);
        Response::success($stmt->fetch(), 'Lançamento registrado.');
    }

    public function closeShift(): void {
        \App\Middlewares\RoleMiddleware::requireDono();
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT tipo, forma_pagamento, SUM(valor) as total FROM fluxo_caixa WHERE tenant_id = ? GROUP BY tipo, forma_pagamento");
            $stmt->execute([$tenant]);
            $dados = $stmt->fetchAll();

            $totalIn = 0; $totalOut = 0; $byMethod = [];
            foreach($dados as $d) {
                if($d['tipo'] == 'entrada') { 
                    $totalIn += (float)$d['total']; 
                    $byMethod[$d['forma_pagamento']] = ($byMethod[$d['forma_pagamento']] ?? 0) + (float)$d['total']; 
                }
                else { $totalOut += (float)$d['total']; }
            }
            $saldo = $totalIn - $totalOut;

            if ($totalIn == 0 && $totalOut == 0) {
                $db->rollBack();
                Response::error('Não há lançamentos para fechar.', 400);
            }

            // INSERT do fechamento
            $stmt = $db->prepare("INSERT INTO fechamentos_caixa (tenant_id, total_entradas, total_saidas, saldo, por_metodo) VALUES (?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$tenant, $totalIn, $totalOut, $saldo, json_encode($byMethod)]);
            $fechamento = $stmt->fetch();

            // DELETE dos lançamentos
            $db->prepare("DELETE FROM fluxo_caixa WHERE tenant_id = ?")->execute([$tenant]);

            $db->commit();
            Response::success([
                'id' => $fechamento['id'],
                'resumo' => compact('totalIn', 'totalOut', 'saldo', 'byMethod')
            ], 'Turno encerrado com sucesso.');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Erro ao encerrar turno.', 500);
        }
    }
}
