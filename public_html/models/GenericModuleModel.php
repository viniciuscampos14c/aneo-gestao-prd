<?php

class GenericModuleModel extends BaseModel
{
    public function list(string $module, array $filters, int $perPage, int $page): array
    {
        $where = ['module_name = :module_name'];
        $params = [':module_name' => $module];

        if (!empty($filters['q'])) {
            $where[] = '(title LIKE :q OR responsible LIKE :q OR notes LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM module_items WHERE {$whereSql}";
        $dataSql = "SELECT * FROM module_items WHERE {$whereSql} ORDER BY id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function create(string $module, array $data, int $createdBy): void
    {
        $stmt = $this->db->prepare('INSERT INTO module_items (
            module_name, title, status, responsible, priority, due_date, notes,
            created_by, created_at, updated_at
        ) VALUES (
            :module_name, :title, :status, :responsible, :priority, :due_date, :notes,
            :created_by, :created_at, :updated_at
        )');

        $stmt->execute([
            ':module_name' => $module,
            ':title' => $data['title'],
            ':status' => $data['status'],
            ':responsible' => $data['responsible'],
            ':priority' => $data['priority'],
            ':due_date' => $data['due_date'] ?: null,
            ':notes' => $data['notes'],
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE module_items SET
            title = :title,
            status = :status,
            responsible = :responsible,
            priority = :priority,
            due_date = :due_date,
            notes = :notes,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute([
            ':title' => $data['title'],
            ':status' => $data['status'],
            ':responsible' => $data['responsible'],
            ':priority' => $data['priority'],
            ':due_date' => $data['due_date'] ?: null,
            ':notes' => $data['notes'],
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM module_items WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM module_items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
