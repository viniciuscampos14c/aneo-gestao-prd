<?php

class CourseModel extends BaseModel
{
    private ?bool $examScheduleColumnExists = null;
    private ?bool $courseCompanyColumnExists = null;
    private ?bool $categoryCompanyColumnExists = null;
    private ?bool $trialAccessTableExists = null;
    private ?bool $courseModulesTableExists = null;
    private ?bool $courseLessonsTableExists = null;
    private ?bool $studentLessonProgressTableExists = null;

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

    public function lmsFeatureAvailable(): bool
    {
        return $this->hasCourseModulesTable()
            && $this->hasCourseLessonsTable()
            && $this->hasStudentLessonProgressTable();
    }

    public function listCourseModulesWithLessons(int $courseId): array
    {
        if ($courseId <= 0 || !$this->lmsFeatureAvailable() || !$this->findCourse($courseId)) {
            return [];
        }

        $moduleStmt = $this->db->prepare('SELECT
                id,
                course_id,
                title,
                description,
                display_order,
                is_active,
                created_at,
                updated_at
            FROM course_modules
            WHERE course_id = :course_id
            ORDER BY display_order ASC, id ASC');
        $moduleStmt->execute([':course_id' => $courseId]);
        $modules = $moduleStmt->fetchAll();

        if ($modules === []) {
            return [];
        }

        $lessonStmt = $this->db->prepare('SELECT
                id,
                course_id,
                module_id,
                title,
                description,
                lesson_type,
                video_url,
                duration_seconds,
                min_progress_percent,
                is_required,
                is_active,
                display_order,
                created_at,
                updated_at
            FROM course_lessons
            WHERE course_id = :course_id
            ORDER BY module_id ASC, display_order ASC, id ASC');
        $lessonStmt->execute([':course_id' => $courseId]);
        $lessons = $lessonStmt->fetchAll();

        $byModule = [];
        foreach ($lessons as $lesson) {
            $moduleId = (int) ($lesson['module_id'] ?? 0);
            if (!isset($byModule[$moduleId])) {
                $byModule[$moduleId] = [];
            }
            $byModule[$moduleId][] = $lesson;
        }

        foreach ($modules as &$module) {
            $moduleId = (int) ($module['id'] ?? 0);
            $module['lessons'] = $byModule[$moduleId] ?? [];
        }
        unset($module);

        return $modules;
    }

    public function createCourseModule(int $courseId, array $data, int $createdBy): int
    {
        if ($courseId <= 0 || !$this->lmsFeatureAvailable() || !$this->findCourse($courseId)) {
            return 0;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return 0;
        }

        $description = trim((string) ($data['description'] ?? ''));
        $displayOrder = (int) ($data['display_order'] ?? 0);
        if ($displayOrder <= 0) {
            $displayOrder = $this->nextModuleDisplayOrder($courseId);
        }

        $isActive = !empty($data['is_active']) ? 1 : 0;

        $stmt = $this->db->prepare('INSERT INTO course_modules (
            course_id,
            title,
            description,
            display_order,
            is_active,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :course_id,
            :title,
            :description,
            :display_order,
            :is_active,
            :created_by,
            :created_at,
            :updated_at
        )');
        $stmt->execute([
            ':course_id' => $courseId,
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':display_order' => $displayOrder,
            ':is_active' => $isActive,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateCourseModule(int $moduleId, array $data): bool
    {
        if ($moduleId <= 0 || !$this->lmsFeatureAvailable()) {
            return false;
        }

        $module = $this->findCourseModule($moduleId);
        if (!$module) {
            return false;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return false;
        }

        $description = trim((string) ($data['description'] ?? ''));
        $displayOrder = (int) ($data['display_order'] ?? 1);
        if ($displayOrder <= 0) {
            $displayOrder = 1;
        }

        $isActive = !empty($data['is_active']) ? 1 : 0;

        $stmt = $this->db->prepare('UPDATE course_modules SET
            title = :title,
            description = :description,
            display_order = :display_order,
            is_active = :is_active,
            updated_at = :updated_at
            WHERE id = :id
              AND course_id = :course_id');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':display_order' => $displayOrder,
            ':is_active' => $isActive,
            ':updated_at' => now(),
            ':id' => $moduleId,
            ':course_id' => (int) $module['course_id'],
        ]);

        return true;
    }

    public function deleteCourseModule(int $moduleId): bool
    {
        if ($moduleId <= 0 || !$this->lmsFeatureAvailable()) {
            return false;
        }

        $module = $this->findCourseModule($moduleId);
        if (!$module) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM course_modules WHERE id = :id AND course_id = :course_id');
        $stmt->execute([
            ':id' => $moduleId,
            ':course_id' => (int) $module['course_id'],
        ]);

        return true;
    }

    public function createCourseLesson(int $courseId, int $moduleId, array $data, int $createdBy): int
    {
        if ($courseId <= 0 || $moduleId <= 0 || !$this->lmsFeatureAvailable() || !$this->findCourse($courseId)) {
            return 0;
        }

        $module = $this->findCourseModule($moduleId);
        if (!$module || (int) ($module['course_id'] ?? 0) !== $courseId) {
            return 0;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $videoUrl = trim((string) ($data['video_url'] ?? ''));

        if ($title === '' || $videoUrl === '') {
            return 0;
        }

        $description = trim((string) ($data['description'] ?? ''));
        $durationSeconds = (int) ($data['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            $durationSeconds = null;
        }

        $minProgressPercent = (int) ($data['min_progress_percent'] ?? 70);
        if ($minProgressPercent <= 0 || $minProgressPercent > 100) {
            $minProgressPercent = 70;
        }

        $displayOrder = (int) ($data['display_order'] ?? 0);
        if ($displayOrder <= 0) {
            $displayOrder = $this->nextLessonDisplayOrder($moduleId);
        }

        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $isActive = !empty($data['is_active']) ? 1 : 0;

        $stmt = $this->db->prepare('INSERT INTO course_lessons (
            course_id,
            module_id,
            title,
            description,
            lesson_type,
            video_url,
            duration_seconds,
            min_progress_percent,
            is_required,
            is_active,
            display_order,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :course_id,
            :module_id,
            :title,
            :description,
            :lesson_type,
            :video_url,
            :duration_seconds,
            :min_progress_percent,
            :is_required,
            :is_active,
            :display_order,
            :created_by,
            :created_at,
            :updated_at
        )');

        $stmt->execute([
            ':course_id' => $courseId,
            ':module_id' => $moduleId,
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':lesson_type' => 'video',
            ':video_url' => $videoUrl,
            ':duration_seconds' => $durationSeconds,
            ':min_progress_percent' => $minProgressPercent,
            ':is_required' => $isRequired,
            ':is_active' => $isActive,
            ':display_order' => $displayOrder,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateCourseLesson(int $lessonId, array $data): bool
    {
        if ($lessonId <= 0 || !$this->lmsFeatureAvailable()) {
            return false;
        }

        $lesson = $this->findCourseLesson($lessonId);
        if (!$lesson) {
            return false;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $videoUrl = trim((string) ($data['video_url'] ?? ''));

        if ($title === '' || $videoUrl === '') {
            return false;
        }

        $description = trim((string) ($data['description'] ?? ''));
        $durationSeconds = (int) ($data['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            $durationSeconds = null;
        }

        $minProgressPercent = (int) ($data['min_progress_percent'] ?? 70);
        if ($minProgressPercent <= 0 || $minProgressPercent > 100) {
            $minProgressPercent = 70;
        }

        $displayOrder = (int) ($data['display_order'] ?? 1);
        if ($displayOrder <= 0) {
            $displayOrder = 1;
        }

        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $isActive = !empty($data['is_active']) ? 1 : 0;

        $stmt = $this->db->prepare('UPDATE course_lessons SET
            title = :title,
            description = :description,
            video_url = :video_url,
            duration_seconds = :duration_seconds,
            min_progress_percent = :min_progress_percent,
            is_required = :is_required,
            is_active = :is_active,
            display_order = :display_order,
            updated_at = :updated_at
            WHERE id = :id
              AND course_id = :course_id');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':video_url' => $videoUrl,
            ':duration_seconds' => $durationSeconds,
            ':min_progress_percent' => $minProgressPercent,
            ':is_required' => $isRequired,
            ':is_active' => $isActive,
            ':display_order' => $displayOrder,
            ':updated_at' => now(),
            ':id' => $lessonId,
            ':course_id' => (int) $lesson['course_id'],
        ]);

        return true;
    }

    public function deleteCourseLesson(int $lessonId): bool
    {
        if ($lessonId <= 0 || !$this->lmsFeatureAvailable()) {
            return false;
        }

        $lesson = $this->findCourseLesson($lessonId);
        if (!$lesson) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM course_lessons WHERE id = :id AND course_id = :course_id');
        $stmt->execute([
            ':id' => $lessonId,
            ':course_id' => (int) $lesson['course_id'],
        ]);

        return true;
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

    public function trialAccessFeatureAvailable(): bool
    {
        return $this->hasTrialAccessTable();
    }

    public function listTrialAccesses(int $perPage, int $page): array
    {
        if (!$this->hasTrialAccessTable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $countSql = "SELECT COUNT(*)
            FROM course_trial_accesses ta
            INNER JOIN students s ON s.id = ta.student_id
            INNER JOIN courses c ON c.id = ta.course_id
            WHERE ta.company_id = :company_id";

        $dataSql = "SELECT
                ta.id,
                ta.student_id,
                ta.course_id,
                ta.access_date,
                ta.access_scope,
                ta.status,
                ta.last_login_at,
                ta.created_at,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                s.phone AS student_phone,
                spa.login AS portal_login,
                c.name AS course_name
            FROM course_trial_accesses ta
            INNER JOIN students s ON s.id = ta.student_id
            INNER JOIN courses c ON c.id = ta.course_id
            LEFT JOIN student_portal_accounts spa ON spa.student_id = s.id
            WHERE ta.company_id = :company_id
            ORDER BY ta.id DESC";

        return $this->paginate($countSql, $dataSql, [':company_id' => $this->companyId()], $perPage, $page);
    }

    public function findTrialAccess(int $trialAccessId): ?array
    {
        if (!$this->hasTrialAccessTable() || $trialAccessId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT
                ta.id,
                ta.student_id,
                ta.course_id,
                ta.access_date,
                ta.access_scope,
                ta.status,
                ta.last_login_at,
                ta.created_at,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                spa.login AS portal_login,
                c.name AS course_name
            FROM course_trial_accesses ta
            INNER JOIN students s ON s.id = ta.student_id
            INNER JOIN courses c ON c.id = ta.course_id
            LEFT JOIN student_portal_accounts spa ON spa.student_id = s.id
            WHERE ta.id = :id
              AND ta.company_id = :company_id
            LIMIT 1");
        $stmt->execute([
            ':id' => $trialAccessId,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createTrialAccess(array $data, int $createdBy): array
    {
        if (!$this->hasTrialAccessTable()) {
            throw new RuntimeException('Tabela de degustacao nao encontrada no banco.');
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            throw new RuntimeException('Empresa atual nao definida.');
        }

        $studentName = trim((string) ($data['student_name'] ?? ''));
        $studentEmail = trim((string) ($data['student_email'] ?? ''));
        $studentPhone = trim((string) ($data['student_phone'] ?? ''));
        $courseId = (int) ($data['course_id'] ?? 0);
        $accessDate = $this->normalizeDate((string) ($data['access_date'] ?? ''));

        if ($studentName === '') {
            throw new RuntimeException('Nome do aluno obrigatorio.');
        }

        if ($courseId <= 0 || !$this->findCourse($courseId)) {
            throw new RuntimeException('Curso invalido para esta empresa.');
        }

        if ($accessDate === null) {
            throw new RuntimeException('Data de acesso invalida.');
        }

        $course = $this->findCourse($courseId);
        if (!$course) {
            throw new RuntimeException('Curso nao encontrado.');
        }

        $login = $this->generateTrialPortalLogin($studentName);
        $plainPassword = $this->generateTrialPassword();

        try {
            $this->db->beginTransaction();

            $studentStmt = $this->db->prepare('INSERT INTO students (
                company_id, full_name, primary_contact, email_primary, phone, is_active,
                admin_info, notes, monthly_fee, billing_day, kanban_status_id, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :full_name, :primary_contact, :email_primary, :phone, :is_active,
                :admin_info, :notes, :monthly_fee, :billing_day, :kanban_status_id, :created_by, :created_at, :updated_at
            )');

            $studentStmt->execute([
                ':company_id' => $companyId,
                ':full_name' => $studentName,
                ':primary_contact' => $studentName,
                ':email_primary' => $studentEmail !== '' ? $studentEmail : null,
                ':phone' => $studentPhone !== '' ? $studentPhone : null,
                ':is_active' => 1,
                ':admin_info' => 'Acesso degustacao Cursos EAD',
                ':notes' => 'Acesso de degustacao para o curso "' . trim((string) ($course['name'] ?? '')) . '" em ' . $accessDate,
                ':monthly_fee' => 0,
                ':billing_day' => null,
                ':kanban_status_id' => null,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);

            $studentId = (int) $this->db->lastInsertId();

            $portalStmt = $this->db->prepare('INSERT INTO student_portal_accounts (
                student_id, login, password_hash, is_active, created_at, updated_at
            ) VALUES (
                :student_id, :login, :password_hash, :is_active, :created_at, :updated_at
            )');
            $portalStmt->execute([
                ':student_id' => $studentId,
                ':login' => $login,
                ':password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
                ':is_active' => 1,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);

            $enrollmentStmt = $this->db->prepare('INSERT INTO enrollments (
                student_id, course_id, status, progress_percent, started_at, completed_at, created_by, created_at, updated_at
            ) VALUES (
                :student_id, :course_id, :status, :progress_percent, :started_at, :completed_at, :created_by, :created_at, :updated_at
            )');
            $enrollmentStmt->execute([
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':status' => 'active',
                ':progress_percent' => 0,
                ':started_at' => $accessDate,
                ':completed_at' => $accessDate,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);

            $trialStmt = $this->db->prepare('INSERT INTO course_trial_accesses (
                company_id, student_id, course_id, access_date, access_scope, status, last_login_at, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :student_id, :course_id, :access_date, :access_scope, :status, :last_login_at, :created_by, :created_at, :updated_at
            )');
            $trialStmt->execute([
                ':company_id' => $companyId,
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':access_date' => $accessDate,
                ':access_scope' => 'live_only',
                ':status' => 'active',
                ':last_login_at' => null,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);

            $trialAccessId = (int) $this->db->lastInsertId();

            $this->db->commit();

            return [
                'id' => $trialAccessId,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'course_name' => trim((string) ($course['name'] ?? '')),
                'student_name' => $studentName,
                'student_email' => $studentEmail,
                'portal_login' => $login,
                'portal_password' => $plainPassword,
                'access_date' => $accessDate,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function revokeTrialAccess(int $trialAccessId): bool
    {
        if (!$this->hasTrialAccessTable() || $trialAccessId <= 0) {
            return false;
        }

        $current = $this->findTrialAccess($trialAccessId);
        if (!$current) {
            return false;
        }

        $studentId = (int) ($current['student_id'] ?? 0);
        if ($studentId <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $trialStmt = $this->db->prepare('UPDATE course_trial_accesses SET
                status = :status,
                updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');
            $trialStmt->execute([
                ':status' => 'revoked',
                ':updated_at' => now(),
                ':id' => $trialAccessId,
                ':company_id' => $this->companyId(),
            ]);

            $portalStmt = $this->db->prepare("UPDATE student_portal_accounts spa
                INNER JOIN students s ON s.id = spa.student_id
                SET spa.is_active = 0, spa.updated_at = :updated_at
                WHERE spa.student_id = :student_id
                  AND s.company_id = :company_id");
            $portalStmt->execute([
                ':updated_at' => now(),
                ':student_id' => $studentId,
                ':company_id' => $this->companyId(),
            ]);

            $studentStmt = $this->db->prepare('UPDATE students SET
                is_active = 0,
                updated_at = :updated_at
                WHERE id = :student_id
                  AND company_id = :company_id');
            $studentStmt->execute([
                ':updated_at' => now(),
                ':student_id' => $studentId,
                ':company_id' => $this->companyId(),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
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

    private function findCourseModule(int $moduleId): ?array
    {
        if ($moduleId <= 0 || !$this->hasCourseModulesTable()) {
            return null;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT cm.*
                FROM course_modules cm
                INNER JOIN courses c ON c.id = cm.course_id
                WHERE cm.id = :id
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':id' => $moduleId,
                ':company_id' => $this->companyId(),
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM course_modules WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $moduleId]);
        }

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findCourseLesson(int $lessonId): ?array
    {
        if ($lessonId <= 0 || !$this->hasCourseLessonsTable()) {
            return null;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT cl.*
                FROM course_lessons cl
                INNER JOIN courses c ON c.id = cl.course_id
                WHERE cl.id = :id
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':id' => $lessonId,
                ':company_id' => $this->companyId(),
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM course_lessons WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $lessonId]);
        }

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function nextModuleDisplayOrder(int $courseId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(display_order), 0) + 1
            FROM course_modules
            WHERE course_id = :course_id');
        $stmt->execute([':course_id' => $courseId]);
        $next = (int) $stmt->fetchColumn();

        return $next > 0 ? $next : 1;
    }

    private function nextLessonDisplayOrder(int $moduleId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(display_order), 0) + 1
            FROM course_lessons
            WHERE module_id = :module_id');
        $stmt->execute([':module_id' => $moduleId]);
        $next = (int) $stmt->fetchColumn();

        return $next > 0 ? $next : 1;
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

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $ts = strtotime($value . ' 00:00:00');
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function generateTrialPortalLogin(string $studentName): string
    {
        $base = $this->buildLoginBase($studentName);
        for ($i = 0; $i < 30; $i++) {
            $candidate = $base . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!$this->portalLoginExists($candidate)) {
                return $candidate;
            }
        }

        return 'degustacao' . date('YmdHis') . random_int(100, 999);
    }

    private function buildLoginBase(string $studentName): string
    {
        $normalized = strtolower($studentName);
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($converted !== false) {
                $normalized = $converted;
            }
        }
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized) ?: '';
        if ($normalized === '') {
            $normalized = 'degustacao';
        }

        $normalized = substr($normalized, 0, 12);

        return $normalized . '.';
    }

    private function portalLoginExists(string $login): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM student_portal_accounts WHERE login = :login');
        $stmt->execute([':login' => $login]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function generateTrialPassword(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($chars) - 1;
        $length = max(6, $length);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
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

    private function hasTrialAccessTable(): bool
    {
        if ($this->trialAccessTableExists !== null) {
            return $this->trialAccessTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_trial_accesses'");
        $stmt->execute();
        $this->trialAccessTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->trialAccessTableExists;
    }

    private function hasCourseModulesTable(): bool
    {
        if ($this->courseModulesTableExists !== null) {
            return $this->courseModulesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_modules'");
        $stmt->execute();
        $this->courseModulesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseModulesTableExists;
    }

    private function hasCourseLessonsTable(): bool
    {
        if ($this->courseLessonsTableExists !== null) {
            return $this->courseLessonsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_lessons'");
        $stmt->execute();
        $this->courseLessonsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseLessonsTableExists;
    }

    private function hasStudentLessonProgressTable(): bool
    {
        if ($this->studentLessonProgressTableExists !== null) {
            return $this->studentLessonProgressTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'student_lesson_progress'");
        $stmt->execute();
        $this->studentLessonProgressTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->studentLessonProgressTableExists;
    }
}
