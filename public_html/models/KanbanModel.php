<?php

class KanbanModel extends BaseModel
{
    private StudentModel $students;

    public function __construct()
    {
        parent::__construct();
        $this->students = new StudentModel();
    }

    public function statuses(): array
    {
        return $this->db->query('SELECT ks.*, COUNT(s.id) AS total_students
            FROM kanban_status ks
            LEFT JOIN students s ON s.kanban_status_id = ks.id
            GROUP BY ks.id
            ORDER BY ks.display_order ASC, ks.id ASC')->fetchAll();
    }

    public function board(string $search = ''): array
    {
        $statuses = $this->statuses();

        foreach ($statuses as &$status) {
            $params = [':status_id' => $status['id']];
            $sql = 'SELECT id, full_name, primary_contact, email_primary, phone
                FROM students
                WHERE kanban_status_id = :status_id';

            if ($search !== '') {
                $sql .= ' AND (full_name LIKE :q OR email_primary LIKE :q OR phone LIKE :q)';
                $params[':q'] = '%' . $search . '%';
            }

            $sql .= ' ORDER BY full_name ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $status['students'] = $stmt->fetchAll();
            $status['total_students'] = count($status['students']);
        }

        return $statuses;
    }

    public function createStatus(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO kanban_status (
            name, slug, color, display_order, is_default, created_at, updated_at
        ) VALUES (
            :name, :slug, :color, :display_order, :is_default, :created_at, :updated_at
        )');

        if (!empty($data['is_default'])) {
            $this->clearDefault();
        }

        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $this->slug($data['name']),
            ':color' => $data['color'],
            ':display_order' => (int) $data['display_order'],
            ':is_default' => !empty($data['is_default']) ? 1 : 0,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, array $data): void
    {
        if (!empty($data['is_default'])) {
            $this->clearDefault();
        }

        $stmt = $this->db->prepare('UPDATE kanban_status SET
            name = :name,
            slug = :slug,
            color = :color,
            display_order = :display_order,
            is_default = :is_default,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $this->slug($data['name']),
            ':color' => $data['color'],
            ':display_order' => (int) $data['display_order'],
            ':is_default' => !empty($data['is_default']) ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function deleteStatus(int $id): void
    {
        $default = $this->students->defaultKanbanStatusId();
        if (!$default || $default === $id) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE students SET kanban_status_id = :default_id WHERE kanban_status_id = :removed_id');
        $stmt->execute([
            ':default_id' => $default,
            ':removed_id' => $id,
        ]);

        $del = $this->db->prepare('DELETE FROM kanban_status WHERE id = :id');
        $del->execute([':id' => $id]);
    }

    public function moveStudent(int $studentId, int $statusId, int $changedBy): void
    {
        $current = $this->db->prepare('SELECT id, kanban_status_id FROM students WHERE id = :id LIMIT 1');
        $current->execute([':id' => $studentId]);
        $row = $current->fetch();

        if (!$row) {
            throw new RuntimeException('Aluno não encontrado para mover no Kanban.');
        }

        $currentStatusId = $row['kanban_status_id'] !== null ? (int) $row['kanban_status_id'] : null;

        if ($currentStatusId === $statusId) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE students SET kanban_status_id = :status_id, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':status_id' => $statusId,
            ':updated_at' => now(),
            ':id' => $studentId,
        ]);

        $this->students->registerKanbanHistory($studentId, $currentStatusId, $statusId, $changedBy, 'Movido no Kanban');
    }

    public function findStatus(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kanban_status WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function clearDefault(): void
    {
        $this->db->exec('UPDATE kanban_status SET is_default = 0');
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        return trim((string) $slug, '-');
    }
}
