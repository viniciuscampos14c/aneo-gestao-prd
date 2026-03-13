<?php

class CourseModel extends BaseModel
{
    private ?bool $examScheduleColumnExists = null;
    private ?bool $courseCompanyColumnExists = null;
    private ?bool $categoryCompanyColumnExists = null;

    public function categories(): array
    {
        if ($this->hasCategoryCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT * FROM course_categories WHERE company_id = :company_id ORDER BY name ASC');
            $stmt->execute([':company_id' => $this->companyId()]);
            return $stmt->fetchAll();
        }

        return $this->db->query('SELECT * FROM course_categories ORDER BY name ASC')->fetchAll();
    }

    public function createCategory(string $name, int $createdBy): void
    {
        if ($this->hasCategoryCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('INSERT INTO course_categories (company_id, name, created_by, created_at, updated_at)
                VALUES (:company_id, :name, :created_by, :created_at, :updated_at)');

            $stmt->execute([
                ':company_id' => $this->companyId(),
                ':name' => $name,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO course_categories (name, created_by, created_at, updated_at)
            VALUES (:name, :created_by, :created_at, :updated_at)');

        $stmt->execute([
            ':name' => $name,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function deleteCategory(int $id): void
    {
        if ($this->hasCategoryCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('DELETE FROM course_categories WHERE id = :id AND company_id = :company_id');
            $stmt->execute([
                ':id' => $id,
                ':company_id' => $this->companyId(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM course_categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function listCourses(array $filters, int $perPage, int $page): array
    {
        $where = ['1=1'];
        $params = [];

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $where[] = 'c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        if (!empty($filters['q'])) {
            $where[] = '(c.name LIKE :q OR c.description LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM courses c WHERE {$whereSql}";

        $categoryJoin = ($this->hasCategoryCompanyColumn() && $this->hasCourseCompanyColumn())
            ? 'LEFT JOIN course_categories cat ON cat.id = c.category_id AND cat.company_id = c.company_id'
            : 'LEFT JOIN course_categories cat ON cat.id = c.category_id';

        $dataSql = "SELECT c.*, cat.name AS category_name
            FROM courses c
            {$categoryJoin}
            WHERE {$whereSql}
            ORDER BY c.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function findCourse(int $id): ?array
    {
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT * FROM courses WHERE id = :id AND company_id = :company_id LIMIT 1');
            $stmt->execute([
                ':id' => $id,
                ':company_id' => $this->companyId(),
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
        }

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createCourse(array $data, int $createdBy): int
    {
        $categoryId = $this->normalizeCategoryId($data['category_id'] ?? null);

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('INSERT INTO courses (
                company_id, name, description, category_id, cover_image, status, workload_hours,
                curriculum, materials, live_link, live_password, live_meeting_id,
                live_datetime, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :name, :description, :category_id, :cover_image, :status, :workload_hours,
                :curriculum, :materials, :live_link, :live_password, :live_meeting_id,
                :live_datetime, :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
                ':company_id' => $this->companyId(),
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':category_id' => $categoryId,
                ':cover_image' => $data['cover_image'],
                ':status' => $data['status'] ?: 'draft',
                ':workload_hours' => $data['workload_hours'] !== '' ? (int) $data['workload_hours'] : null,
                ':curriculum' => $data['curriculum'],
                ':materials' => $data['materials'],
                ':live_link' => $data['live_link'],
                ':live_password' => $data['live_password'],
                ':live_meeting_id' => $data['live_meeting_id'],
                ':live_datetime' => $data['live_datetime'] ?: null,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO courses (
                name, description, category_id, cover_image, status, workload_hours,
                curriculum, materials, live_link, live_password, live_meeting_id,
                live_datetime, created_by, created_at, updated_at
            ) VALUES (
                :name, :description, :category_id, :cover_image, :status, :workload_hours,
                :curriculum, :materials, :live_link, :live_password, :live_meeting_id,
                :live_datetime, :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':category_id' => $categoryId,
                ':cover_image' => $data['cover_image'],
                ':status' => $data['status'] ?: 'draft',
                ':workload_hours' => $data['workload_hours'] !== '' ? (int) $data['workload_hours'] : null,
                ':curriculum' => $data['curriculum'],
                ':materials' => $data['materials'],
                ':live_link' => $data['live_link'],
                ':live_password' => $data['live_password'],
                ':live_meeting_id' => $data['live_meeting_id'],
                ':live_datetime' => $data['live_datetime'] ?: null,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }

        return (int) $this->db->lastInsertId();
    }

    public function updateCourse(int $id, array $data): void
    {
        $categoryId = $this->normalizeCategoryId($data['category_id'] ?? null);

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('UPDATE courses SET
                name = :name,
                description = :description,
                category_id = :category_id,
                cover_image = :cover_image,
                status = :status,
                workload_hours = :workload_hours,
                curriculum = :curriculum,
                materials = :materials,
                live_link = :live_link,
                live_password = :live_password,
                live_meeting_id = :live_meeting_id,
                live_datetime = :live_datetime,
                updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');

            $stmt->execute([
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':category_id' => $categoryId,
                ':cover_image' => $data['cover_image'],
                ':status' => $data['status'] ?: 'draft',
                ':workload_hours' => $data['workload_hours'] !== '' ? (int) $data['workload_hours'] : null,
                ':curriculum' => $data['curriculum'],
                ':materials' => $data['materials'],
                ':live_link' => $data['live_link'],
                ':live_password' => $data['live_password'],
                ':live_meeting_id' => $data['live_meeting_id'],
                ':live_datetime' => $data['live_datetime'] ?: null,
                ':updated_at' => now(),
                ':id' => $id,
                ':company_id' => $this->companyId(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('UPDATE courses SET
            name = :name,
            description = :description,
            category_id = :category_id,
            cover_image = :cover_image,
            status = :status,
            workload_hours = :workload_hours,
            curriculum = :curriculum,
            materials = :materials,
            live_link = :live_link,
            live_password = :live_password,
            live_meeting_id = :live_meeting_id,
            live_datetime = :live_datetime,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':category_id' => $categoryId,
            ':cover_image' => $data['cover_image'],
            ':status' => $data['status'] ?: 'draft',
            ':workload_hours' => $data['workload_hours'] !== '' ? (int) $data['workload_hours'] : null,
            ':curriculum' => $data['curriculum'],
            ':materials' => $data['materials'],
            ':live_link' => $data['live_link'],
            ':live_password' => $data['live_password'],
            ':live_meeting_id' => $data['live_meeting_id'],
            ':live_datetime' => $data['live_datetime'] ?: null,
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function deleteCourse(int $id): void
    {
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('DELETE FROM courses WHERE id = :id AND company_id = :company_id');
            $stmt->execute([
                ':id' => $id,
                ':company_id' => $this->companyId(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM courses WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function listCourseMaterials(int $courseId): array
    {
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT u.id, u.file_name, u.file_path, u.file_type, u.file_size, u.created_at
                FROM uploads u
                INNER JOIN courses c ON c.id = u.entity_id
                WHERE u.entity_type = :entity_type
                  AND u.entity_id = :entity_id
                  AND c.company_id = :company_id
                ORDER BY u.id DESC');
            $stmt->execute([
                ':entity_type' => 'course',
                ':entity_id' => $courseId,
                ':company_id' => $this->companyId(),
            ]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT id, file_name, file_path, file_type, file_size, created_at
            FROM uploads
            WHERE entity_type = :entity_type AND entity_id = :entity_id
            ORDER BY id DESC');
        $stmt->execute([
            ':entity_type' => 'course',
            ':entity_id' => $courseId,
        ]);

        return $stmt->fetchAll();
    }

    public function findCourseMaterial(int $uploadId): ?array
    {
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT u.id, u.entity_id, u.file_name, u.file_path
                FROM uploads u
                INNER JOIN courses c ON c.id = u.entity_id
                WHERE u.id = :id
                  AND u.entity_type = :entity_type
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':id' => $uploadId,
                ':entity_type' => 'course',
                ':company_id' => $this->companyId(),
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT id, entity_id, file_name, file_path
                FROM uploads
                WHERE id = :id AND entity_type = :entity_type
                LIMIT 1');
            $stmt->execute([
                ':id' => $uploadId,
                ':entity_type' => 'course',
            ]);
        }
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function addCourseMaterial(int $courseId, string $fileName, string $filePath, string $fileType, int $fileSize, int $createdBy): void
    {
        if (!$this->findCourse($courseId)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO uploads (
            entity_type, entity_id, file_name, file_path, file_type, file_size, created_by, created_at
        ) VALUES (
            :entity_type, :entity_id, :file_name, :file_path, :file_type, :file_size, :created_by, :created_at
        )');

        $stmt->execute([
            ':entity_type' => 'course',
            ':entity_id' => $courseId,
            ':file_name' => $fileName,
            ':file_path' => $filePath,
            ':file_type' => $fileType,
            ':file_size' => $fileSize,
            ':created_by' => $createdBy,
            ':created_at' => now(),
        ]);
    }

    public function deleteCourseMaterial(int $uploadId): void
    {
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("DELETE u
                FROM uploads u
                INNER JOIN courses c ON c.id = u.entity_id
                WHERE u.id = :id
                  AND u.entity_type = :entity_type
                  AND c.company_id = :company_id");
            $stmt->execute([
                ':id' => $uploadId,
                ':entity_type' => 'course',
                ':company_id' => $this->companyId(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM uploads WHERE id = :id AND entity_type = :entity_type');
        $stmt->execute([
            ':id' => $uploadId,
            ':entity_type' => 'course',
        ]);
    }

    public function listEnrollments(array $filters, int $perPage, int $page): array
    {
        $where = ['1=1'];
        $params = [];

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $where[] = 'c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        if (!empty($filters['q'])) {
            $where[] = '(s.full_name LIKE :q OR c.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'e.status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM enrollments e
            LEFT JOIN students s ON s.id = e.student_id
            LEFT JOIN courses c ON c.id = e.course_id
            WHERE {$whereSql}";

        $dataSql = "SELECT e.*, s.full_name AS student_name, c.name AS course_name
            FROM enrollments e
            LEFT JOIN students s ON s.id = e.student_id
            LEFT JOIN courses c ON c.id = e.course_id
            WHERE {$whereSql}
            ORDER BY e.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function createEnrollment(array $data, int $createdBy): void
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $courseId = (int) ($data['course_id'] ?? 0);

        if (!$this->studentBelongsCompany($studentId) || !$this->findCourse($courseId)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO enrollments (
            student_id, course_id, status, progress_percent, started_at,
            completed_at, created_by, created_at, updated_at
        ) VALUES (
            :student_id, :course_id, :status, :progress_percent, :started_at,
            :completed_at, :created_by, :created_at, :updated_at
        )');

        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':status' => $data['status'] ?: 'active',
            ':progress_percent' => (int) ($data['progress_percent'] ?: 0),
            ':started_at' => $data['started_at'] ?: null,
            ':completed_at' => $data['completed_at'] ?: null,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function listExams(array $filters, int $perPage, int $page): array
    {
        $where = ['1=1'];
        $params = [];

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $where[] = 'c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        if (!empty($filters['q'])) {
            $where[] = '(e.title LIKE :q OR c.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $scheduleSelect = $this->hasExamScheduleColumn() ? 'e.scheduled_at AS scheduled_at' : 'NULL AS scheduled_at';
        $orderSql = $this->hasExamScheduleColumn()
            ? 'ORDER BY (e.scheduled_at IS NULL) ASC, e.scheduled_at ASC, e.id DESC'
            : 'ORDER BY e.id DESC';

        $countSql = "SELECT COUNT(*) FROM exams e LEFT JOIN courses c ON c.id = e.course_id WHERE {$whereSql}";
        $dataSql = "SELECT
                e.id,
                e.course_id,
                e.title,
                e.description,
                e.passing_score,
                {$scheduleSelect},
                e.created_by,
                e.created_at,
                e.updated_at,
                c.name AS course_name
            FROM exams e
            LEFT JOIN courses c ON c.id = e.course_id
            WHERE {$whereSql}
            {$orderSql}";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function createExam(array $data, int $createdBy): int
    {
        $courseId = (int) ($data['course_id'] ?? 0);
        if (!$this->findCourse($courseId)) {
            return 0;
        }

        if ($this->hasExamScheduleColumn()) {
            $stmt = $this->db->prepare('INSERT INTO exams (
                course_id, title, description, passing_score, scheduled_at, created_by, created_at, updated_at
            ) VALUES (
                :course_id, :title, :description, :passing_score, :scheduled_at, :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
                ':course_id' => $courseId,
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':passing_score' => (float) ($data['passing_score'] ?: 7),
                ':scheduled_at' => $data['scheduled_at'] ?? null,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO exams (
                course_id, title, description, passing_score, created_by, created_at, updated_at
            ) VALUES (
                :course_id, :title, :description, :passing_score, :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
                ':course_id' => $courseId,
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':passing_score' => (float) ($data['passing_score'] ?: 7),
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }

        return (int) $this->db->lastInsertId();
    }

    public function examScheduleFeatureAvailable(): bool
    {
        return $this->hasExamScheduleColumn();
    }

    public function upcomingExamCalendar(int $daysAhead = 90, int $limit = 12): array
    {
        if (!$this->hasExamScheduleColumn()) {
            return [];
        }

        $daysAhead = max(1, $daysAhead);
        $limit = max(1, $limit);
        $until = date('Y-m-d H:i:s', strtotime('+' . $daysAhead . ' days'));

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("SELECT
                    e.id,
                    e.title,
                    e.scheduled_at,
                    c.name AS course_name
                FROM exams e
                INNER JOIN courses c ON c.id = e.course_id
                WHERE c.company_id = :company_id
                  AND e.scheduled_at IS NOT NULL
                  AND e.scheduled_at >= NOW()
                  AND e.scheduled_at <= :until
                ORDER BY e.scheduled_at ASC
                LIMIT {$limit}");
            $stmt->execute([
                ':company_id' => $this->companyId(),
                ':until' => $until,
            ]);
        } else {
            $stmt = $this->db->prepare("SELECT
                    e.id,
                    e.title,
                    e.scheduled_at,
                    c.name AS course_name
                FROM exams e
                INNER JOIN courses c ON c.id = e.course_id
                WHERE e.scheduled_at IS NOT NULL
                  AND e.scheduled_at >= NOW()
                  AND e.scheduled_at <= :until
                ORDER BY e.scheduled_at ASC
                LIMIT {$limit}");
            $stmt->execute([':until' => $until]);
        }

        return $stmt->fetchAll();
    }

    public function createQuestion(int $examId, string $type, string $question, ?string $options, ?string $answer, int $createdBy): void
    {
        if (!$this->canAccessExam($examId)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO exam_questions (
            exam_id, question_type, question_text, options_json, correct_answer,
            created_by, created_at, updated_at
        ) VALUES (
            :exam_id, :question_type, :question_text, :options_json, :correct_answer,
            :created_by, :created_at, :updated_at
        )');

        $stmt->execute([
            ':exam_id' => $examId,
            ':question_type' => $type,
            ':question_text' => $question,
            ':options_json' => $options,
            ':correct_answer' => $answer,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function registerExamResult(array $data, int $createdBy): void
    {
        $examId = (int) ($data['exam_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);
        if (!$this->canAccessExam($examId) || !$this->studentBelongsCompany($studentId)) {
            return;
        }

        $status = ((float) $data['score'] >= (float) $data['passing_score']) ? 'approved' : 'failed';

        $stmt = $this->db->prepare('INSERT INTO exam_results (
            exam_id, student_id, score, status, submitted_at, created_by, created_at
        ) VALUES (
            :exam_id, :student_id, :score, :status, :submitted_at, :created_by, :created_at
        )');

        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
            ':score' => (float) $data['score'],
            ':status' => $status,
            ':submitted_at' => $data['submitted_at'] ?: now(),
            ':created_by' => $createdBy,
            ':created_at' => now(),
        ]);
    }

    public function listExamResults(int $examId): array
    {
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT r.*, s.full_name AS student_name
                FROM exam_results r
                INNER JOIN exams ex ON ex.id = r.exam_id
                INNER JOIN courses c ON c.id = ex.course_id
                LEFT JOIN students s ON s.id = r.student_id
                WHERE r.exam_id = :exam_id
                  AND c.company_id = :company_id
                ORDER BY r.id DESC');
            $stmt->execute([
                ':exam_id' => $examId,
                ':company_id' => $this->companyId(),
            ]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT r.*, s.full_name AS student_name
            FROM exam_results r
            LEFT JOIN students s ON s.id = r.student_id
            WHERE r.exam_id = :exam_id
            ORDER BY r.id DESC');

        $stmt->execute([':exam_id' => $examId]);
        return $stmt->fetchAll();
    }

    public function listComments(int $limit = 200): array
    {
        $limit = max(1, $limit);

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("SELECT cm.*, cr.name AS course_name, u.name AS author_name
                FROM course_comments cm
                INNER JOIN courses cr ON cr.id = cm.course_id
                LEFT JOIN users u ON u.id = cm.created_by
                WHERE cr.company_id = :company_id
                ORDER BY cm.id DESC
                LIMIT {$limit}");
            $stmt->execute([':company_id' => $this->companyId()]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query("SELECT cm.*, cr.name AS course_name, u.name AS author_name
            FROM course_comments cm
            LEFT JOIN courses cr ON cr.id = cm.course_id
            LEFT JOIN users u ON u.id = cm.created_by
            ORDER BY cm.id DESC
            LIMIT {$limit}");

        return $stmt->fetchAll();
    }

    public function createComment(int $courseId, string $comment, int $createdBy): bool
    {
        if (!$this->findCourse($courseId)) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT INTO course_comments (course_id, comment, created_by, created_at)
            VALUES (:course_id, :comment, :created_by, :created_at)');

        $stmt->execute([
            ':course_id' => $courseId,
            ':comment' => $comment,
            ':created_by' => $createdBy,
            ':created_at' => now(),
        ]);

        return true;
    }

    private function normalizeCategoryId($rawCategoryId): ?int
    {
        $categoryId = (int) ($rawCategoryId ?? 0);
        if ($categoryId <= 0) {
            return null;
        }

        if ($this->hasCategoryCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT id
                FROM course_categories
                WHERE id = :id
                  AND company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':id' => $categoryId,
                ':company_id' => $this->companyId(),
            ]);
            return $stmt->fetchColumn() ? $categoryId : null;
        }

        $stmt = $this->db->prepare('SELECT id FROM course_categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $categoryId]);

        return $stmt->fetchColumn() ? $categoryId : null;
    }

    private function canAccessExam(int $examId): bool
    {
        if ($examId <= 0) {
            return false;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT ex.id
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                WHERE ex.id = :exam_id
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':exam_id' => $examId,
                ':company_id' => $this->companyId(),
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT id FROM exams WHERE id = :exam_id LIMIT 1');
        $stmt->execute([':exam_id' => $examId]);
        return (bool) $stmt->fetchColumn();
    }

    private function studentBelongsCompany(int $studentId): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        if ($this->companyId() <= 0) {
            $stmt = $this->db->prepare('SELECT id FROM students WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $studentId]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT id
            FROM students
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $studentId,
            ':company_id' => $this->companyId(),
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function hasExamScheduleColumn(): bool
    {
        if ($this->examScheduleColumnExists !== null) {
            return $this->examScheduleColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'exams'
              AND column_name = 'scheduled_at'");
        $stmt->execute();
        $this->examScheduleColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examScheduleColumnExists;
    }

    private function hasCourseCompanyColumn(): bool
    {
        if ($this->courseCompanyColumnExists !== null) {
            return $this->courseCompanyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'courses'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->courseCompanyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseCompanyColumnExists;
    }

    private function hasCategoryCompanyColumn(): bool
    {
        if ($this->categoryCompanyColumnExists !== null) {
            return $this->categoryCompanyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'course_categories'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->categoryCompanyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->categoryCompanyColumnExists;
    }
}
