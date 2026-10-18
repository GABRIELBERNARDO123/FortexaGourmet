<?php
declare(strict_types=1);
namespace App\Controllers;

use PDO;

class TabController {
    public function __construct(private PDO $db) {}

    public function getOpenTabs(): void {
        $stmt = $this->db->query("
            SELECT t.id, t.total, t.opened_at, tb.number as table_number 
            FROM tabs t
            JOIN tables tb ON t.table_id = tb.id
            WHERE t.status = 'aberta'
            ORDER BY t.opened_at DESC
        ");
        echo json_encode($stmt->fetchAll());
    }

    public function openTab(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        $tableId = $data['table_id'] ?? null;

        if (!$tableId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da mesa é obrigatório']);
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO tabs (tenant_id, table_id, status) VALUES ('00000000-0000-0000-0000-000000000000', ?, 'aberta') RETURNING id");
        $stmt->execute([$tableId]);
        $tab = $stmt->fetch();

        $this->db->query("UPDATE tables SET status = 'ocupada' WHERE id = $tableId");

        echo json_encode(['success' => true, 'tab_id' => $tab['id']]);
    }
}
