<?php

class ArsenalModel extends BaseModel
{
    private array $tableExistsCache = [];

    public function featureAvailable(): bool
    {
        return $this->hasTable('arsenal_items')
            && $this->hasTable('arsenal_categories')
            && $this->hasTable('arsenal_item_courses')
            && $this->hasTable('arsenal_item_students')
            && $this->hasTable('arsenal_access_logs');
    }

    public function listItems(array $filters, int $perPage, int $page): array
    {
        if (!$this->featureAvailable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = ['ai.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(ai.title LIKE :q OR ai.description LIKE :q OR ai.file_name LIKE :q OR ai.external_url LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'ai.status = :status';
            $params[':status'] = $status;
        }

        $materialType = trim((string) ($filters['material_type'] ?? ''));
        if ($materialType !== '') {
            $where[] = 'ai.material_type = :material_type';
            $params[':material_type'] = $materialType;
        }

        $scope = trim((string) ($filters['visibility_scope'] ?? ''));
        if ($scope !== '') {
            $where[] = 'ai.visibility_scope = :visibility_scope';
            $params[':visibility_scope'] = $scope;
        }

        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'ai.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM arsenal_items ai
            WHERE {$whereSql}";

        $dataSql = "SELECT
                ai.*,
                ac.name AS category_name,
                (SELECT COUNT(*) FROM arsenal_item_courses aic WHERE aic.company_id = ai.company_id AND aic.arsenal_item_id = ai.id) AS linked_courses,
                (SELECT COUNT(*) FROM arsenal_item_students ais WHERE ais.company_id = ai.company_id AND ais.arsenal_item_id = ai.id) AS linked_students
            FROM arsenal_items ai
            LEFT JOIN arsenal_categories ac ON ac.id = ai.category_id AND ac.company_id = ai.company_id
            WHERE {$whereSql}
            ORDER BY ai.updated_at DESC, ai.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function findItem(int $id): ?array
    {
        if (!$this->featureAvailable() || $id <= 0) {
            return null;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT
                ai.*,
                ac.name AS category_name
            FROM arsenal_items ai
            LEFT JOIN arsenal_categories ac ON ac.id = ai.category_id AND ac.company_id = ai.company_id
            WHERE ai.id = :id
              AND ai.company_id = :company_id
            LIMIT 1");
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createItem(array $data, int $createdBy): int
    {
        if (!$this->featureAvailable()) {
            return 0;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO arsenal_items (
            company_id, category_id, title, description, material_type, file_name, file_path, file_type, file_size,
            external_url, visibility_scope, status, publish_start_at, publish_end_at, sort_order, created_by,
            created_at, updated_at
        ) VALUES (
            :company_id, :category_id, :title, :description, :material_type, :file_name, :file_path, :file_type, :file_size,
            :external_url, :visibility_scope, :status, :publish_start_at, :publish_end_at, :sort_order, :created_by,
            :created_at, :updated_at
        )');

        $stmt->execute([
            ':company_id' => $companyId,
            ':category_id' => (int) ($data['category_id'] ?? 0) > 0 ? (int) $data['category_id'] : null,
            ':title' => trim((string) ($data['title'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':material_type' => trim((string) ($data['material_type'] ?? 'file')),
            ':file_name' => trim((string) ($data['file_name'] ?? '')) ?: null,
            ':file_path' => trim((string) ($data['file_path'] ?? '')) ?: null,
            ':file_type' => trim((string) ($data['file_type'] ?? '')) ?: null,
            ':file_size' => (int) ($data['file_size'] ?? 0) > 0 ? (int) $data['file_size'] : null,
            ':external_url' => trim((string) ($data['external_url'] ?? '')) ?: null,
            ':visibility_scope' => trim((string) ($data['visibility_scope'] ?? 'global')),
            ':status' => trim((string) ($data['status'] ?? 'draft')),
            ':publish_start_at' => trim((string) ($data['publish_start_at'] ?? '')) ?: null,
            ':publish_end_at' => trim((string) ($data['publish_end_at'] ?? '')) ?: null,
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateItem(int $id, array $data): bool
    {
        if (!$this->featureAvailable() || $id <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE arsenal_items SET
            category_id = :category_id,
            title = :title,
            description = :description,
            material_type = :material_type,
            file_name = :file_name,
            file_path = :file_path,
            file_type = :file_type,
            file_size = :file_size,
            external_url = :external_url,
            visibility_scope = :visibility_scope,
            status = :status,
            publish_start_at = :publish_start_at,
            publish_end_at = :publish_end_at,
            sort_order = :sort_order,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');

        $stmt->execute([
            ':category_id' => (int) ($data['category_id'] ?? 0) > 0 ? (int) $data['category_id'] : null,
            ':title' => trim((string) ($data['title'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':material_type' => trim((string) ($data['material_type'] ?? 'file')),
            ':file_name' => trim((string) ($data['file_name'] ?? '')) ?: null,
            ':file_path' => trim((string) ($data['file_path'] ?? '')) ?: null,
            ':file_type' => trim((string) ($data['file_type'] ?? '')) ?: null,
            ':file_size' => (int) ($data['file_size'] ?? 0) > 0 ? (int) $data['file_size'] : null,
            ':external_url' => trim((string) ($data['external_url'] ?? '')) ?: null,
            ':visibility_scope' => trim((string) ($data['visibility_scope'] ?? 'global')),
            ':status' => trim((string) ($data['status'] ?? 'draft')),
            ':publish_start_at' => trim((string) ($data['publish_start_at'] ?? '')) ?: null,
            ':publish_end_at' => trim((string) ($data['publish_end_at'] ?? '')) ?: null,
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteItem(int $id): bool
    {
        if (!$this->featureAvailable() || $id <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM arsenal_items
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listCategories(bool $onlyActive = false): array
    {
        if (!$this->featureAvailable()) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $sql = "SELECT
                c.*,
                (SELECT COUNT(*) FROM arsenal_items ai WHERE ai.company_id = c.company_id AND ai.category_id = c.id) AS items_total
            FROM arsenal_categories c
            WHERE c.company_id = :company_id";
        $params = [':company_id' => $companyId];
        if ($onlyActive) {
            $sql .= ' AND c.is_active = 1';
        }
        $sql .= ' ORDER BY c.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findCategory(int $id): ?array
    {
        if (!$this->featureAvailable() || $id <= 0) {
            return null;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM arsenal_categories
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createCategory(array $data, int $createdBy): int
    {
        if (!$this->featureAvailable()) {
            return 0;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO arsenal_categories (
            company_id, name, description, is_active, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :description, :is_active, :created_by, :created_at, :updated_at
        )');

        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => trim((string) ($data['name'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateCategory(int $id, array $data): bool
    {
        if (!$this->featureAvailable() || $id <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE arsenal_categories SET
            name = :name,
            description = :description,
            is_active = :is_active,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');

        $stmt->execute([
            ':name' => trim((string) ($data['name'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteCategory(int $id): bool
    {
        if (!$this->featureAvailable() || $id <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM arsenal_categories
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listCoursesForBinding(): array
    {
        if (!$this->featureAvailable()) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT id, name, status
            FROM courses
            WHERE company_id = :company_id
            ORDER BY name ASC');
        $stmt->execute([':company_id' => $companyId]);

        return $stmt->fetchAll();
    }

    public function listStudentsForBinding(): array
    {
        if (!$this->featureAvailable()) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT id, full_name, email_primary, is_active
            FROM students
            WHERE company_id = :company_id
            ORDER BY full_name ASC');
        $stmt->execute([':company_id' => $companyId]);

        return $stmt->fetchAll();
    }

    public function listItemCourseBindings(int $itemId): array
    {
        if (!$this->featureAvailable() || $itemId <= 0) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT
                aic.course_id,
                c.name AS course_name,
                c.status AS course_status,
                aic.created_at
            FROM arsenal_item_courses aic
            INNER JOIN courses c ON c.id = aic.course_id
            WHERE aic.company_id = :company_id
              AND aic.arsenal_item_id = :item_id
            ORDER BY c.name ASC');
        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
        ]);

        return $stmt->fetchAll();
    }

    public function listItemStudentBindings(int $itemId): array
    {
        if (!$this->featureAvailable() || $itemId <= 0) {
            return [];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT
                ais.student_id,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                s.is_active AS student_active,
                ais.created_at
            FROM arsenal_item_students ais
            INNER JOIN students s ON s.id = ais.student_id
            WHERE ais.company_id = :company_id
              AND ais.arsenal_item_id = :item_id
            ORDER BY s.full_name ASC');
        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
        ]);

        return $stmt->fetchAll();
    }

    public function addCourseBinding(int $itemId, int $courseId): bool
    {
        if (!$this->featureAvailable() || $itemId <= 0 || $courseId <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0 || !$this->itemBelongsCompany($itemId) || !$this->courseBelongsCompany($courseId)) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO arsenal_item_courses (
            company_id, arsenal_item_id, course_id, created_at
        ) VALUES (
            :company_id, :item_id, :course_id, :created_at
        )');

        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
            ':course_id' => $courseId,
            ':created_at' => now(),
        ]);

        return true;
    }

    public function removeCourseBinding(int $itemId, int $courseId): bool
    {
        if (!$this->featureAvailable() || $itemId <= 0 || $courseId <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM arsenal_item_courses
            WHERE company_id = :company_id
              AND arsenal_item_id = :item_id
              AND course_id = :course_id');
        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
            ':course_id' => $courseId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function addStudentBinding(int $itemId, int $studentId): bool
    {
        if (!$this->featureAvailable() || $itemId <= 0 || $studentId <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0 || !$this->itemBelongsCompany($itemId) || !$this->studentBelongsCompany($studentId)) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO arsenal_item_students (
            company_id, arsenal_item_id, student_id, created_at
        ) VALUES (
            :company_id, :item_id, :student_id, :created_at
        )');
        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
            ':student_id' => $studentId,
            ':created_at' => now(),
        ]);

        return true;
    }

    public function removeStudentBinding(int $itemId, int $studentId): bool
    {
        if (!$this->featureAvailable() || $itemId <= 0 || $studentId <= 0) {
            return false;
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM arsenal_item_students
            WHERE company_id = :company_id
              AND arsenal_item_id = :item_id
              AND student_id = :student_id');
        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
            ':student_id' => $studentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listAccessLogs(array $filters, int $perPage, int $page): array
    {
        if (!$this->featureAvailable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = ['l.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(i.title LIKE :q OR s.full_name LIKE :q OR s.email_primary LIKE :q OR l.ip_address LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'l.action = :action';
            $params[':action'] = $action;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM arsenal_access_logs l
            INNER JOIN arsenal_items i ON i.id = l.arsenal_item_id
            INNER JOIN students s ON s.id = l.student_id
            WHERE {$whereSql}";

        $dataSql = "SELECT
                l.*,
                i.title AS item_title,
                i.material_type,
                s.full_name AS student_name,
                s.email_primary AS student_email
            FROM arsenal_access_logs l
            INNER JOIN arsenal_items i ON i.id = l.arsenal_item_id
            INNER JOIN students s ON s.id = l.student_id
            WHERE {$whereSql}
            ORDER BY l.created_at DESC, l.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function stats(): array
    {
        if (!$this->featureAvailable()) {
            return [
                'items_total' => 0,
                'items_published' => 0,
                'categories_total' => 0,
                'links_total' => 0,
                'files_total' => 0,
            ];
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [
                'items_total' => 0,
                'items_published' => 0,
                'categories_total' => 0,
                'links_total' => 0,
                'files_total' => 0,
            ];
        }

        $params = [':company_id' => $companyId];

        return [
            'items_total' => (int) $this->scalar('SELECT COUNT(*) FROM arsenal_items WHERE company_id = :company_id', $params),
            'items_published' => (int) $this->scalar("SELECT COUNT(*) FROM arsenal_items WHERE company_id = :company_id AND status = 'published'", $params),
            'categories_total' => (int) $this->scalar('SELECT COUNT(*) FROM arsenal_categories WHERE company_id = :company_id', $params),
            'links_total' => (int) $this->scalar("SELECT COUNT(*) FROM arsenal_items WHERE company_id = :company_id AND material_type = 'link'", $params),
            'files_total' => (int) $this->scalar("SELECT COUNT(*) FROM arsenal_items WHERE company_id = :company_id AND material_type = 'file'", $params),
        ];
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function itemBelongsCompany(int $itemId): bool
    {
        $companyId = $this->companyId();
        if ($companyId <= 0 || $itemId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id
            FROM arsenal_items
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $itemId,
            ':company_id' => $companyId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function courseBelongsCompany(int $courseId): bool
    {
        $companyId = $this->companyId();
        if ($companyId <= 0 || $courseId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id
            FROM courses
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $courseId,
            ':company_id' => $companyId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function studentBelongsCompany(int $studentId): bool
    {
        $companyId = $this->companyId();
        if ($companyId <= 0 || $studentId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id
            FROM students
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $studentId,
            ':company_id' => $companyId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name");
        $stmt->execute([':table_name' => $table]);
        $exists = ((int) $stmt->fetchColumn()) > 0;
        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }
}

