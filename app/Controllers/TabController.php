<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;
use Exception;

class TabController {
    public function index(): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        $userId = Auth::getUserId();
        $isGarcom = ($_SESSION['user_role'] ?? '') === 'garcom';

        $sql = "SELECT c.*, c.mesa_id as mesa_numero FROM comandas c WHERE c.tenant_id = ? AND c.status != 'fechada'";
        $params = [$tenant];
        if ($isGarcom) { $sql .= " AND c.usuario_id = ?"; $params[] = $userId; }
        $sql .= " ORDER BY c.aberta_em DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    public function open(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $mesa_id = (string)($data['mesa_id'] ?? '');
        $cliente_id = $data['cliente_id'] ?? null;
        
        if (!$mesa_id) Response::error('Identificação da mesa é obrigatória.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        $userId = Auth::getUserId();

        $stmt = $db->prepare("SELECT id FROM comandas WHERE mesa_id = ? AND tenant_id = ? AND status != 'fechada'");
        $stmt->execute([$mesa_id, $tenant]);
        if ($stmt->fetch()) Response::error('Mesa já possui comanda aberta.', 400);

        $stmt = $db->prepare("INSERT INTO comandas (tenant_id, mesa_id, turno, usuario_id, cliente_id) VALUES (?, ?, ?, ?, ?) RETURNING *");
        $stmt->execute([$tenant, $mesa_id, date('H') >= 18 ? 'noite' : 'dia', $userId, $cliente_id]);
        Response::success($stmt->fetch(), 'Comanda aberta.');
    }

    public function addItem(string $id): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $produto_id = $data['produto_id'] ?? null;
        $quantidade = (float)($data['quantidade'] ?? 1);
        $obs = $data['obs'] ?? '';

        if (!$produto_id || $quantidade <= 0) Response::error('Dados inválidos.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        $userId = Auth::getUserId();

        if (($_SESSION['user_role'] ?? '') === 'garcom') {
            $stmt = $db->prepare("SELECT id FROM comandas WHERE id = ? AND tenant_id = ? AND usuario_id = ? AND status != 'fechada'");
            $stmt->execute([$id, $tenant, $userId]);
            if (!$stmt->fetch()) Response::error('Comanda não pertence a você.', 404);
        }

        $stmt = $db->prepare("SELECT nome, preco_venda, preco_custo, estoque_atual FROM produtos WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$produto_id, $tenant]);
        $prod = $stmt->fetch();
        if (!$prod) Response::error('Produto não encontrado.', 404);
        if ($prod['estoque_atual'] < $quantidade) Response::error('Estoque insuficiente.', 400);

        $valor_total = $prod['preco_venda'] * $quantidade;

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO itens_comanda (tenant_id, comanda_id, produto_id, quantidade, preco_unitario, valor_total, obs, custo_unitario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant, $id, $produto_id, $quantidade, $prod['preco_venda'], $valor_total, $obs, $prod['preco_custo']]);
            $db->prepare("UPDATE comandas SET valor_total = valor_total + ? WHERE id = ? AND tenant_id = ?")->execute([$valor_total, $id, $tenant]);
            $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?")->execute([$quantidade, $produto_id]);
            $db->prepare("INSERT INTO movimentacoes_estoque (tenant_id, produto_id, tipo, quantidade, motivo, referencia_id) VALUES (?, ?, 'saida', ?, 'venda', ?)")->execute([$tenant, $produto_id, $quantidade, $id]);
            $db->commit();
            Response::success(null, 'Item adicionado.');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Erro ao adicionar item.', 500);
        }
    }

    public function updateStatus(string $id): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $data['status'] ?? '';
        if (!in_array($status, ['aberta', 'pediu_conta', 'cancelada'])) Response::error('Status inválido.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        $db->prepare("UPDATE comandas SET status = ? WHERE id = ? AND tenant_id = ?")->execute([$status, $id, $tenant]);
        Response::success(null, 'Status atualizado.');
    }

    public function close(string $id): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $forma = $data['forma_pagamento'] ?? 'Dinheiro';
        if (!in_array($forma, ['Dinheiro', 'Pix', 'Crédito', 'Débito'])) Response::error('Forma de pagamento inválida.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("SELECT valor_total, mesa_id, cliente_id FROM comandas WHERE id = ? AND tenant_id = ? AND status != 'fechada'");
        $stmt->execute([$id, $tenant]);
        $comanda = $stmt->fetch();
        if (!$comanda) Response::error('Comanda não encontrada.', 404);

        $stmt = $db->prepare("SELECT * FROM itens_comanda WHERE comanda_id = ?");
        $stmt->execute([$id]);
        $itens = $stmt->fetchAll();

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE comandas SET status = 'fechada', forma_pagamento = ?, fechada_em = NOW() WHERE id = ?")->execute([$forma, $id]);
            $db->prepare("INSERT INTO fluxo_caixa (tenant_id, tipo, valor, descricao, categoria, forma_pagamento, comanda_id, items_snapshot) VALUES (?, 'entrada', ?, 'Fechamento de Comanda', 'Vendas', ?, ?, ?)")
               ->execute([$tenant, $comanda['valor_total'], $forma, $id, json_encode($itens)]);
            if ($comanda['cliente_id']) {
                $db->prepare("UPDATE clientes SET total_gasto = total_gasto + ?, visitas = COALESCE(visitas,0) + 1 WHERE id = ?")->execute([$comanda['valor_total'], $comanda['cliente_id']]);
            }
            $db->commit();
            Response::success(null, 'Comanda fechada.');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Erro ao fechar comanda.', 500);
        }
    }

    public function cancel(string $id): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("SELECT id FROM comandas WHERE id = ? AND tenant_id = ? AND status != 'fechada'");
        $stmt->execute([$id, $tenant]);
        if (!$stmt->fetch()) Response::error('Comanda não encontrada.', 404);

        $db->beginTransaction();
        try {
            // Devolve estoque dos itens
            $stmt = $db->prepare("SELECT produto_id, quantidade FROM itens_comanda WHERE comanda_id = ?");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $item) {
                $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?")->execute([$item['quantidade'], $item['produto_id']]);
            }
            $db->prepare("UPDATE comandas SET status = 'cancelada' WHERE id = ?")->execute([$id]);
            $db->commit();
            Response::success(null, 'Comanda cancelada e estoque devolvido.');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Erro ao cancelar comanda.', 500);
        }
    }
}
