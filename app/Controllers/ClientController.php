<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Auth;
use App\Core\Encryption;
use Exception;

class ClientController {
    private function validar(array $data, bool $isUpdate = false): array {
        $errors = [];
        $nome = trim($data['nome'] ?? '');
        $whatsapp = trim($data['whatsapp'] ?? '');
        // Normaliza: remove tudo que não é dígito
        $whatsapp = preg_replace('/\D/', '', $whatsapp);

        if (!$isUpdate || isset($data['nome'])) {
            if (strlen($nome) < 2) $errors['nome'] = 'Nome deve ter no mínimo 2 caracteres.';
        }
        if ($whatsapp && !preg_match('/^\d{10,11}$/', $whatsapp)) {
            $errors['whatsapp'] = 'WhatsApp inválido. Use DDD + número (10 ou 11 dígitos).';
        }

        if (!empty($errors)) {
            Response::json(['status' => 'error', 'message' => 'Verifique os campos abaixo', 'errors' => $errors], 400);
        }

        return [
            'nome' => $nome,
            'whatsapp' => $whatsapp,
            'data_nascimento' => $data['data_nascimento'] ?? null
        ];
    }

    public function index(): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM clientes WHERE tenant_id = ? ORDER BY nome");
        $stmt->execute([Auth::getTenantId()]);
        $clientes = $stmt->fetchAll();
        foreach ($clientes as &$c) {
            $c['whatsapp'] = Encryption::decrypt($c['whatsapp'] ?? '');
        }
        Response::success($clientes);
    }

    public function show(string $id): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, Auth::getTenantId()]);
        $cliente = $stmt->fetch();
        if (!$cliente) Response::error('Cliente não encontrado.', 404);
        $cliente['whatsapp'] = Encryption::decrypt($cliente['whatsapp'] ?? '');
        Response::success($cliente);
    }

    public function store(): void {
        $data = $this->validar(json_decode(file_get_contents('php://input'), true) ?? []);
        $db = Database::getConnection();
        $whatsEncrypted = Encryption::encrypt($data['whatsapp']);
        $stmt = $db->prepare("INSERT INTO clientes (tenant_id, nome, whatsapp, data_nascimento) VALUES (?, ?, ?, ?) RETURNING *");
        $stmt->execute([Auth::getTenantId(), $data['nome'], $whatsEncrypted, $data['data_nascimento']]);
        $result = $stmt->fetch();
        $result['whatsapp'] = Encryption::decrypt($result['whatsapp']);
        Response::success($result, 'Cliente cadastrado.');
    }

    public function update(string $id): void {
        $data = $this->validar(json_decode(file_get_contents('php://input'), true) ?? [], true);
        $db = Database::getConnection();
        $whatsEncrypted = $data['whatsapp'] ? Encryption::encrypt($data['whatsapp']) : null;

        $stmt = $db->prepare("UPDATE clientes SET nome = COALESCE(?, nome), whatsapp = COALESCE(?, whatsapp), data_nascimento = COALESCE(?, data_nascimento) WHERE id = ? AND tenant_id = ? RETURNING *");
        $stmt->execute([$data['nome'] ?: null, $whatsEncrypted, $data['data_nascimento'], $id, Auth::getTenantId()]);
        $result = $stmt->fetch();
        if (!$result) Response::error('Cliente não encontrado.', 404);
        $result['whatsapp'] = Encryption::decrypt($result['whatsapp'] ?? '');
        Response::success($result, 'Cliente atualizado.');
    }

    public function delete(string $id): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM clientes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, Auth::getTenantId()]);
        if ($stmt->rowCount() === 0) Response::error('Cliente não encontrado.', 404);
        Response::success(null, 'Cliente removido.');
    }

    public function addPoints(string $id): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $pontos = (int)($data['pontos'] ?? 0);
        $motivo = $data['motivo'] ?? 'Ajuste manual';
        if ($pontos === 0) Response::error('Informe a quantidade de pontos.', 400);

        $db = Database::getConnection();
        $tenant = Auth::getTenantId();

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE clientes SET pontos_fidelidade = pontos_fidelidade + ? WHERE id = ? AND tenant_id = ?")->execute([$pontos, $id, $tenant]);
            $db->prepare("INSERT INTO historico_fidelidade (tenant_id, cliente_id, pontos, motivo) VALUES (?, ?, ?, ?)")->execute([$tenant, $id, $pontos, $motivo]);
            $db->commit();
            Response::success(null, 'Pontos atualizados.');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Erro ao atualizar pontos.', 500);
        }
    }
}
