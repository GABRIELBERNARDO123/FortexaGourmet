<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;
use Exception;

class ProductController {
    private function validar(array $data): array {
        $errors = [];
        $nome = trim($data['nome'] ?? '');
        $preco = (float)($data['preco_venda'] ?? 0);
        $custo = (float)($data['preco_custo'] ?? 0);
        $estoque = (float)($data['estoque_atual'] ?? 0);
        $minimo = (float)($data['estoque_minimo'] ?? 0);

        if (strlen($nome) < 2) $errors['nome'] = 'Nome deve ter no mínimo 2 caracteres.';
        if ($preco <= 0) $errors['preco_venda'] = 'Preço deve ser maior que zero.';
        if ($custo < 0) $errors['preco_custo'] = 'Custo não pode ser negativo.';

        if (!empty($errors)) {
            Response::json(['status' => 'error', 'message' => 'Verifique os campos', 'errors' => $errors], 400);
        }

        return [
            'nome' => $nome,
            'preco_venda' => $preco,
            'preco_custo' => $custo,
            'estoque_atual' => $estoque,
            'estoque_minimo' => $minimo,
            'categoria_id' => $data['categoria_id'] ?? null
        ];
    }

    public function index(): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        $isGarcom = ($_SESSION['user_role'] ?? '') === 'garcom';
        $fields = $isGarcom ? 'id, nome, categoria_id, preco_venda' : '*';
        
        $stmt = $db->prepare("SELECT $fields FROM produtos WHERE tenant_id = ? AND ativo = TRUE ORDER BY nome");
        $stmt->execute([$tenant]);
        Response::success($stmt->fetchAll());
    }

    public function show(string $id): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM produtos WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, Auth::getTenantId()]);
        $produto = $stmt->fetch();
        if (!$produto) Response::error('Produto não encontrado.', 404);
        Response::success($produto);
    }

    public function store(): void {
        $data = $this->validar(json_decode(file_get_contents('php://input'), true) ?? []);
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("INSERT INTO produtos (tenant_id, nome, preco_venda, preco_custo, estoque_atual, estoque_minimo, categoria_id) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING *");
        $stmt->execute([$tenant, $data['nome'], $data['preco_venda'], $data['preco_custo'], $data['estoque_atual'], $data['estoque_minimo'], $data['categoria_id']]);
        Response::success($stmt->fetch(), 'Produto criado.');
    }

    public function update(string $id): void {
        $data = $this->validar(json_decode(file_get_contents('php://input'), true) ?? []);
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $stmt = $db->prepare("UPDATE produtos SET nome = ?, preco_venda = ?, preco_custo = ?, estoque_minimo = ?, categoria_id = ? WHERE id = ? AND tenant_id = ? RETURNING *");
        $stmt->execute([$data['nome'], $data['preco_venda'], $data['preco_custo'], $data['estoque_minimo'], $data['categoria_id'], $id, $tenant]);
        $result = $stmt->fetch();
        if (!$result) Response::error('Produto não encontrado.', 404);
        Response::success($result, 'Produto atualizado.');
    }

    public function delete(string $id): void {
        $db = Database::getConnection();
        $tenant = Auth::getTenantId();
        // Soft delete (mantém histórico)
        $stmt = $db->prepare("UPDATE produtos SET ativo = FALSE WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant]);
        if ($stmt->rowCount() === 0) Response::error('Produto não encontrado.', 404);
        Response::success(null, 'Produto removido.');
    }

    public function adjustStock(string $id): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $quantidade = (float)($data['quantidade'] ?? 0);
        $tipo = $data['tipo'] ?? 'entrada';
        $motivo = $data['motivo'] ?? 'ajuste_manual';

        if ($quantidade <= 0) Response::error('Quantidade deve ser maior que zero.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $db->beginTransaction();
        try {
            $op = $tipo === 'entrada' ? '+' : '-';
            $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual {$op} ? WHERE id = ? AND tenant_id = ?")->execute([$quantidade, $id, $tenant]);
            $db->prepare("INSERT INTO movimentacoes_estoque (tenant_id, produto_id, tipo, quantidade, motivo) VALUES (?, ?, ?, ?, ?)")->execute([$tenant, $id, $tipo, $quantidade, $motivo]);
            $db->commit();
            Response::success(null, 'Estoque ajustado.');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Erro ao ajustar estoque.', 500);
        }
    }

    public function categories(): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM categorias_cardapio WHERE tenant_id = ? ORDER BY ordem, nome");
        $stmt->execute([Auth::getTenantId()]);
        Response::success($stmt->fetchAll());
    }

    public function storeCategory(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $nome = trim($data['nome'] ?? '');
        if (strlen($nome) < 2) Response::json(['status' => 'error', 'message' => 'Nome inválido', 'errors' => ['nome' => 'Mínimo 2 caracteres']], 400);

        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("INSERT INTO categorias_cardapio (tenant_id, nome) VALUES (?, ?) RETURNING *");
            $stmt->execute([Auth::getTenantId(), $nome]);
            Response::success($stmt->fetch(), 'Categoria criada.');
        } catch (Exception $e) {
            Response::error('Categoria já existe.', 400);
        }
    }
}
