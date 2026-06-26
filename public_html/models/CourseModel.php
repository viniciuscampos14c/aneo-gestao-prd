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
    private ?bool $examExternalLinksTableExists = null;
    private ?bool $examInternalLinksTableExists = null;
    private ?bool $examSubmissionsTableExists = null;
    private ?bool $examSubmissionAnswersTableExists = null;

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
        [$whereSql, $params] = $this->buildCourseCatalogFilters($filters);

        $countSql = "SELECT COUNT(*) FROM courses c WHERE {$whereSql}";

        $categoryJoin = ($this->hasCategoryCompanyColumn() && $this->hasCourseCompanyColumn())
            ? 'LEFT JOIN course_categories cat ON cat.id = c.category_id AND cat.company_id = c.company_id'
            : 'LEFT JOIN course_categories cat ON cat.id = c.category_id';

        $modulesSelect = '0 AS modules_total';
        $modulesJoin = '';
        $lessonsSelect = '0 AS lessons_total';
        $lessonsJoin = '';
        if ($this->hasCourseModulesTable()) {
            $modulesSelect = 'COALESCE(cm.modules_total, 0) AS modules_total';
            $modulesJoin = "LEFT JOIN (
                    SELECT course_id, COUNT(*) AS modules_total
                    FROM course_modules
                    GROUP BY course_id
                ) cm ON cm.course_id = c.id";
        }
        if ($this->hasCourseLessonsTable()) {
            $lessonsSelect = 'COALESCE(cl.lessons_total, 0) AS lessons_total';
            $lessonsJoin = "LEFT JOIN (
                    SELECT course_id, COUNT(*) AS lessons_total
                    FROM course_lessons
                    GROUP BY course_id
                ) cl ON cl.course_id = c.id";
        }
        $enrollmentsJoin = "LEFT JOIN (
                SELECT course_id, COUNT(*) AS enrollments_total
                FROM enrollments
                WHERE status IN ('active', 'completed')
                GROUP BY course_id
            ) enr ON enr.course_id = c.id";
        $examsJoin = "LEFT JOIN (
                SELECT course_id, COUNT(*) AS exams_total
                FROM exams
                GROUP BY course_id
            ) exm ON exm.course_id = c.id";
        $commentsJoin = "LEFT JOIN (
                SELECT course_id, COUNT(*) AS comments_new_total
                FROM course_comments
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY course_id
            ) cmt ON cmt.course_id = c.id";

        $dataSql = "SELECT
                c.*,
                cat.name AS category_name,
                {$modulesSelect},
                {$lessonsSelect},
                COALESCE(enr.enrollments_total, 0) AS enrollments_total,
                COALESCE(exm.exams_total, 0) AS exams_total,
                COALESCE(cmt.comments_new_total, 0) AS comments_new_total
            FROM courses c
            {$categoryJoin}
            {$modulesJoin}
            {$lessonsJoin}
            {$enrollmentsJoin}
            {$examsJoin}
            {$commentsJoin}
            WHERE {$whereSql}
            ORDER BY c.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function courseCatalogStats(array $filters): array
    {
        [$whereSql, $params] = $this->buildCourseCatalogFilters($filters);

        $sql = "SELECT
                COUNT(*) AS total_courses,
                SUM(CASE WHEN c.status = 'published' THEN 1 ELSE 0 END) AS published_courses,
                COALESCE(SUM(enr.enrollments_total), 0) AS enrollments_total,
                COALESCE(SUM(cmt.comments_new_total), 0) AS comments_new_total
            FROM courses c
            LEFT JOIN (
                SELECT course_id, COUNT(*) AS enrollments_total
                FROM enrollments
                WHERE status IN ('active', 'completed')
                GROUP BY course_id
            ) enr ON enr.course_id = c.id
            LEFT JOIN (
                SELECT course_id, COUNT(*) AS comments_new_total
                FROM course_comments
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY course_id
            ) cmt ON cmt.course_id = c.id
            WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total_courses' => (int) ($row['total_courses'] ?? 0),
            'published_courses' => (int) ($row['published_courses'] ?? 0),
            'enrollments_total' => (int) ($row['enrollments_total'] ?? 0),
            'comments_new_total' => (int) ($row['comments_new_total'] ?? 0),
        ];
    }

    public function updateCourseStatus(int $courseId, string $status): bool
    {
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            return false;
        }

        $course = $this->findCourse($courseId);
        if (!$course) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE courses
            SET status = :status,
                updated_at = :updated_at
            WHERE id = :id');

        return $stmt->execute([
            ':status' => $status,
            ':updated_at' => now(),
            ':id' => $courseId,
        ]);
    }

    public function duplicateCourse(int $courseId, int $createdBy): int
    {
        $course = $this->findCourse($courseId);
        if (!$course) {
            return 0;
        }

        $categoryId = $this->normalizeCategoryId($course['category_id'] ?? null);

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
                ':name' => $this->buildDuplicateCourseName((string) ($course['name'] ?? 'Curso')),
                ':description' => (string) ($course['description'] ?? ''),
                ':category_id' => $categoryId,
                ':cover_image' => (string) ($course['cover_image'] ?? ''),
                ':status' => 'draft',
                ':workload_hours' => $course['workload_hours'] !== null && $course['workload_hours'] !== '' ? (int) $course['workload_hours'] : null,
                ':curriculum' => (string) ($course['curriculum'] ?? ''),
                ':materials' => (string) ($course['materials'] ?? ''),
                ':live_link' => (string) ($course['live_link'] ?? ''),
                ':live_password' => (string) ($course['live_password'] ?? ''),
                ':live_meeting_id' => (string) ($course['live_meeting_id'] ?? ''),
                ':live_datetime' => $course['live_datetime'] ?: null,
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
                ':name' => $this->buildDuplicateCourseName((string) ($course['name'] ?? 'Curso')),
                ':description' => (string) ($course['description'] ?? ''),
                ':category_id' => $categoryId,
                ':cover_image' => (string) ($course['cover_image'] ?? ''),
                ':status' => 'draft',
                ':workload_hours' => $course['workload_hours'] !== null && $course['workload_hours'] !== '' ? (int) $course['workload_hours'] : null,
                ':curriculum' => (string) ($course['curriculum'] ?? ''),
                ':materials' => (string) ($course['materials'] ?? ''),
                ':live_link' => (string) ($course['live_link'] ?? ''),
                ':live_password' => (string) ($course['live_password'] ?? ''),
                ':live_meeting_id' => (string) ($course['live_meeting_id'] ?? ''),
                ':live_datetime' => $course['live_datetime'] ?: null,
                ':created_by' => $createdBy,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }

        $newCourseId = (int) $this->db->lastInsertId();
        if ($newCourseId > 0) {
            $this->duplicateCourseLearningPath($courseId, $newCourseId, $createdBy);
        }

        return $newCourseId;
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

    public function syncEnrollmentProgressFromLessons(?int $enrollmentId = null): void
    {
        if (!$this->lmsFeatureAvailable()) {
            return;
        }

        $where = ["e.status <> 'cancelled'"];
        $params = [];

        if ($enrollmentId !== null && $enrollmentId > 0) {
            $where[] = 'e.id = :enrollment_id';
            $params[':enrollment_id'] = $enrollmentId;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $where[] = 'c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $sql = 'SELECT
                e.id,
                e.started_at,
                e.completed_at,
                COUNT(cl.id) AS total_lessons,
                SUM(CASE WHEN cl.is_required = 1 THEN 1 ELSE 0 END) AS required_lessons,
                SUM(CASE WHEN slp.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_lessons,
                SUM(CASE WHEN cl.is_required = 1 AND slp.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS required_completed_lessons,
                AVG(COALESCE(slp.progress_percent, 0)) AS avg_progress_all,
                AVG(CASE WHEN cl.is_required = 1 THEN COALESCE(slp.progress_percent, 0) END) AS avg_progress_required
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN course_modules cm ON cm.course_id = e.course_id AND cm.is_active = 1
            LEFT JOIN course_lessons cl ON cl.module_id = cm.id AND cl.course_id = e.course_id AND cl.is_active = 1
            LEFT JOIN student_lesson_progress slp ON slp.lesson_id = cl.id AND slp.student_id = e.student_id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY e.id, e.started_at, e.completed_at';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($rows === []) {
            return;
        }

        $update = $this->db->prepare('UPDATE enrollments SET
            progress_percent = :progress_percent,
            status = :status,
            started_at = :started_at,
            completed_at = :completed_at,
            updated_at = :updated_at
            WHERE id = :id');

        foreach ($rows as $row) {
            $totalLessons = (int) ($row['total_lessons'] ?? 0);
            if ($totalLessons <= 0) {
                continue;
            }

            $requiredLessons = (int) ($row['required_lessons'] ?? 0);
            $requiredCompletedLessons = (int) ($row['required_completed_lessons'] ?? 0);
            $completedLessons = (int) ($row['completed_lessons'] ?? 0);
            $avgProgressRequired = (float) ($row['avg_progress_required'] ?? 0);
            $avgProgressAll = (float) ($row['avg_progress_all'] ?? 0);

            if ($requiredLessons > 0) {
                $progressPercent = (int) round($avgProgressRequired);
                $courseCompleted = $requiredCompletedLessons >= $requiredLessons;
            } else {
                $progressPercent = (int) round($avgProgressAll);
                $courseCompleted = $completedLessons >= $totalLessons;
            }

            $progressPercent = max(0, min(100, $progressPercent));
            $startedAt = $row['started_at'] ?: null;
            if (($startedAt === null || $startedAt === '') && $progressPercent > 0) {
                $startedAt = date('Y-m-d');
            }

            $completedAt = null;
            $status = 'active';
            if ($courseCompleted) {
                $status = 'completed';
                $completedAt = $row['completed_at'] ?: date('Y-m-d');
            }

            $update->execute([
                ':progress_percent' => $progressPercent,
                ':status' => $status,
                ':started_at' => $startedAt,
                ':completed_at' => $completedAt,
                ':updated_at' => now(),
                ':id' => (int) $row['id'],
            ]);
        }
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

        $moduleId = (int) $this->db->lastInsertId();
        $module = $this->findCourseModule($moduleId);
        if ($module) {
            $this->syncCourseModuleAcrossEquivalentCourses($module);
        }

        return $moduleId;
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

        $oldTitle = (string) ($module['title'] ?? '');

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

        $updatedModule = $this->findCourseModule($moduleId);
        if ($updatedModule) {
            $this->syncCourseModuleAcrossEquivalentCourses($updatedModule, $oldTitle);
        }

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

        $this->deleteCourseModuleAcrossEquivalentCourses($module);

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

        $lessonId = (int) $this->db->lastInsertId();
        $lesson = $this->findCourseLesson($lessonId);
        if ($lesson) {
            $this->syncCourseLessonAcrossEquivalentCourses($lesson, $module);
        }

        return $lessonId;
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

        $oldTitle = (string) ($lesson['title'] ?? '');
        $sourceModule = $this->findCourseModule((int) ($lesson['module_id'] ?? 0));

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

        $updatedLesson = $this->findCourseLesson($lessonId);
        if ($updatedLesson && $sourceModule) {
            $this->syncCourseLessonAcrossEquivalentCourses($updatedLesson, $sourceModule, $oldTitle);
        }

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

        $sourceModule = $this->findCourseModule((int) ($lesson['module_id'] ?? 0));

        $stmt = $this->db->prepare('DELETE FROM course_lessons WHERE id = :id AND course_id = :course_id');
        $stmt->execute([
            ':id' => $lessonId,
            ':course_id' => (int) $lesson['course_id'],
        ]);

        if ($sourceModule) {
            $this->deleteCourseLessonAcrossEquivalentCourses($lesson, $sourceModule);
        }

        return true;
    }

    public function listEnrollments(array $filters, int $perPage, int $page): array
    {
        $this->syncEnrollmentProgressFromLessons();

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

        if (!empty($filters['course_id'])) {
            $where[] = 'e.course_id = :course_id';
            $params[':course_id'] = (int) $filters['course_id'];
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

        $status = in_array(($data['status'] ?? 'active'), ['active', 'cancelled'], true)
            ? (string) $data['status']
            : 'active';

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
            ':status' => $status,
            ':progress_percent' => 0,
            ':started_at' => $data['started_at'] ?: null,
            ':completed_at' => null,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function isStudentEnrolledInCourse(int $studentId, int $courseId): bool
    {
        if ($studentId <= 0 || $courseId <= 0 || !$this->studentBelongsCompany($studentId)) {
            return false;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT e.id
                FROM enrollments e
                INNER JOIN courses c ON c.id = e.course_id
                WHERE e.student_id = :student_id
                  AND e.course_id = :course_id
                  AND e.status IN (\'active\', \'completed\')
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':company_id' => $this->companyId(),
            ]);

            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT id
            FROM enrollments
            WHERE student_id = :student_id
              AND course_id = :course_id
              AND status IN (\'active\', \'completed\')
            LIMIT 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
        ]);

        return (bool) $stmt->fetchColumn();
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
            throw new RuntimeException('Tabela de degustação não encontrada no banco.');
        }

        $companyId = $this->companyId();
        if ($companyId <= 0) {
            throw new RuntimeException('Empresa atual não definida.');
        }

        $studentName = trim((string) ($data['student_name'] ?? ''));
        $studentEmail = trim((string) ($data['student_email'] ?? ''));
        $studentPhone = trim((string) ($data['student_phone'] ?? ''));
        $courseId = (int) ($data['course_id'] ?? 0);
        $accessDate = $this->normalizeDate((string) ($data['access_date'] ?? ''));

        if ($studentName === '') {
            throw new RuntimeException('Nome do aluno obrigatório.');
        }

        if ($courseId <= 0 || !$this->findCourse($courseId)) {
            throw new RuntimeException('Curso inválido para esta empresa.');
        }

        if ($accessDate === null) {
            throw new RuntimeException('Data de acesso inválida.');
        }

        $course = $this->findCourse($courseId);
        if (!$course) {
            throw new RuntimeException('Curso não encontrado.');
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
                ':kanban_status_id' => $this->defaultKanbanStatusId(),
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

        if (!empty($filters['course_id'])) {
            $where[] = 'e.course_id = :course_id';
            $params[':course_id'] = (int) $filters['course_id'];
        }

        $whereSql = implode(' AND ', $where);
        $scheduleSelect = $this->hasExamScheduleColumn() ? 'e.scheduled_at AS scheduled_at' : 'NULL AS scheduled_at';
        $externalLinksSelect = '0 AS external_links_total';
        $externalLinksJoin = '';
        if ($this->hasExamExternalLinksTable()) {
            $externalLinksSelect = 'COALESCE(el.external_links_total, 0) AS external_links_total';
            $externalLinksJoin = "LEFT JOIN (
                    SELECT exam_id, COUNT(*) AS external_links_total
                    FROM exam_external_links
                    WHERE is_active = 1
                    GROUP BY exam_id
                ) el ON el.exam_id = e.id";
        }
        $internalLinksSelect = '0 AS internal_links_total';
        $internalLinksJoin = '';
        if ($this->hasExamInternalLinksTable()) {
            $internalLinksSelect = 'COALESCE(il.internal_links_total, 0) AS internal_links_total';
            $internalLinksJoin = "LEFT JOIN (
                    SELECT exam_id, COUNT(*) AS internal_links_total
                    FROM exam_internal_links
                    WHERE is_active = 1
                    GROUP BY exam_id
                ) il ON il.exam_id = e.id";
        }
        $questionsSelect = 'COALESCE(eq.questions_total, 0) AS questions_total';
        $questionsJoin = "LEFT JOIN (
                SELECT exam_id, COUNT(*) AS questions_total
                FROM exam_questions
                GROUP BY exam_id
            ) eq ON eq.exam_id = e.id";
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
                {$questionsSelect},
                {$externalLinksSelect},
                {$internalLinksSelect},
                c.name AS course_name
            FROM exams e
            LEFT JOIN courses c ON c.id = e.course_id
            {$questionsJoin}
            {$externalLinksJoin}
            {$internalLinksJoin}
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
        $requestedExamId = (int) ($data['exam_id'] ?? 0);
        $requestedStudentId = (int) ($data['student_id'] ?? 0);
        $resultId = (int) ($data['result_id'] ?? 0);
        $currentResult = $resultId > 0 ? $this->findExamResultForCompany($resultId) : null;
        $examId = $currentResult !== null ? (int) ($currentResult['exam_id'] ?? 0) : $requestedExamId;
        $studentId = $currentResult !== null ? (int) ($currentResult['student_id'] ?? 0) : $requestedStudentId;
        if (
            !$this->canAccessExam($examId)
            || !$this->studentBelongsCompany($studentId)
            || !$this->studentEnrolledInExamCourse($examId, $studentId)
        ) {
            return;
        }

        $passingScore = $this->resolveExamPassingScore($examId, (float) ($data['passing_score'] ?? 0));
        $score = (float) ($data['score'] ?? 0);
        $submittedAt = trim((string) ($data['submitted_at'] ?? '')) ?: now();
        $status = $score >= $passingScore ? 'approved' : 'failed';

        $existingId = $currentResult !== null ? $resultId : $this->findLatestExamResultId($examId, $studentId);
        if ($existingId > 0) {
            $stmt = $this->db->prepare('UPDATE exam_results SET
                score = :score,
                status = :status,
                submitted_at = :submitted_at,
                created_by = :created_by
                WHERE id = :id');
            $stmt->execute([
                ':score' => $score,
                ':status' => $status,
                ':submitted_at' => $submittedAt,
                ':created_by' => $createdBy,
                ':id' => $existingId,
            ]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO exam_results (
                exam_id, student_id, score, status, submitted_at, created_by, created_at
            ) VALUES (
                :exam_id, :student_id, :score, :status, :submitted_at, :created_by, :created_at
            )');

            $stmt->execute([
                ':exam_id' => $examId,
                ':student_id' => $studentId,
                ':score' => $score,
                ':status' => $status,
                ':submitted_at' => $submittedAt,
                ':created_by' => $createdBy,
                ':created_at' => now(),
            ]);
        }

        if ($this->hasExamExternalLinksTable()) {
            $this->deactivateExternalExamLinkByExamStudent($examId, $studentId);
        }

        if ($this->hasExamSubmissionsTable()) {
            $stmt = $this->db->prepare("UPDATE exam_submissions
                SET status = 'submitted',
                    score = :score,
                    updated_at = :updated_at
                WHERE exam_id = :exam_id
                  AND student_id = :student_id
                  AND status = 'pending_review'");
            $stmt->execute([
                ':score' => $score,
                ':updated_at' => now(),
                ':exam_id' => $examId,
                ':student_id' => $studentId,
            ]);
        }
    }

    public function examSubmissionsFeatureAvailable(): bool
    {
        return $this->hasExamSubmissionsTable() && $this->hasExamSubmissionAnswersTable();
    }

    public function listExamSubmissions(array $filters, int $perPage, int $page): array
    {
        if (!$this->examSubmissionsFeatureAvailable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        [$whereSql, $params] = $this->buildExamSubmissionFilters($filters);

        $countSql = "SELECT COUNT(*)
            FROM exam_submissions sub
            INNER JOIN exams ex ON ex.id = sub.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN students st ON st.id = sub.student_id
            WHERE {$whereSql}";

        $dataSql = "SELECT
                sub.id,
                sub.exam_id,
                sub.student_id,
                sub.status,
                sub.score,
                sub.graded_questions,
                sub.correct_answers,
                sub.submitted_at,
                sub.created_at,
                sub.updated_at,
                ex.title AS exam_title,
                ex.passing_score,
                c.id AS course_id,
                c.name AS course_name,
                st.full_name AS student_name,
                '' AS student_email,
                COALESCE(ans.answers_total, 0) AS answers_total,
                COALESCE(ans.essay_answers_total, 0) AS essay_answers_total,
                COALESCE(ans.objective_answers_total, 0) AS objective_answers_total,
                er.id AS result_id,
                er.score AS result_score,
                er.status AS result_status,
                er.submitted_at AS result_submitted_at
            FROM exam_submissions sub
            INNER JOIN exams ex ON ex.id = sub.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN students st ON st.id = sub.student_id
            LEFT JOIN (
                SELECT
                    esa.submission_id,
                    COUNT(*) AS answers_total,
                    SUM(CASE WHEN eq.question_type = 'essay' THEN 1 ELSE 0 END) AS essay_answers_total,
                    SUM(CASE WHEN eq.question_type = 'objective' THEN 1 ELSE 0 END) AS objective_answers_total
                FROM exam_submission_answers esa
                INNER JOIN exam_questions eq ON eq.id = esa.question_id
                GROUP BY esa.submission_id
            ) ans ON ans.submission_id = sub.id
            LEFT JOIN exam_results er ON er.id = (
                SELECT er2.id
                FROM exam_results er2
                WHERE er2.exam_id = sub.exam_id
                  AND er2.student_id = sub.student_id
                ORDER BY er2.submitted_at DESC, er2.id DESC
                LIMIT 1
            )
            WHERE {$whereSql}
            ORDER BY
                CASE WHEN sub.status = 'pending_review' THEN 0 ELSE 1 END,
                sub.submitted_at DESC,
                sub.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function findExamSubmission(int $submissionId): ?array
    {
        if ($submissionId <= 0 || !$this->examSubmissionsFeatureAvailable()) {
            return null;
        }

        $params = [':id' => $submissionId];
        $companyFilter = '';
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare("SELECT
                sub.id,
                sub.exam_id,
                sub.student_id,
                sub.status,
                sub.score,
                sub.graded_questions,
                sub.correct_answers,
                sub.submitted_at,
                sub.created_at,
                sub.updated_at,
                ex.title AS exam_title,
                ex.description AS exam_description,
                ex.passing_score,
                c.id AS course_id,
                c.name AS course_name,
                st.full_name AS student_name,
                '' AS student_email,
                er.id AS result_id,
                er.score AS result_score,
                er.status AS result_status,
                er.submitted_at AS result_submitted_at
            FROM exam_submissions sub
            INNER JOIN exams ex ON ex.id = sub.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN students st ON st.id = sub.student_id
            LEFT JOIN exam_results er ON er.id = (
                SELECT er2.id
                FROM exam_results er2
                WHERE er2.exam_id = sub.exam_id
                  AND er2.student_id = sub.student_id
                ORDER BY er2.submitted_at DESC, er2.id DESC
                LIMIT 1
            )
            WHERE sub.id = :id{$companyFilter}
            LIMIT 1");
        $stmt->execute($params);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function listExamSubmissionAnswers(int $submissionId): array
    {
        if ($submissionId <= 0 || !$this->examSubmissionsFeatureAvailable()) {
            return [];
        }

        $submission = $this->findExamSubmission($submissionId);
        if ($submission === null) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT
                esa.id,
                esa.submission_id,
                esa.question_id,
                esa.answer_text,
                esa.is_correct,
                esa.created_at,
                eq.question_type,
                eq.question_text,
                eq.options_json,
                eq.correct_answer
            FROM exam_submission_answers esa
            INNER JOIN exam_questions eq ON eq.id = esa.question_id
            WHERE esa.submission_id = :submission_id
            ORDER BY eq.id ASC, esa.id ASC");
        $stmt->execute([':submission_id' => $submissionId]);

        return $stmt->fetchAll();
    }

    public function externalExamFeatureAvailable(): bool
    {
        return $this->hasExamExternalLinksTable();
    }

    public function internalExamAudienceFeatureAvailable(): bool
    {
        return $this->hasExamInternalLinksTable();
    }

    public function listExternalExamLinks(int $limit = 250): array
    {
        if (!$this->hasExamExternalLinksTable()) {
            return [];
        }

        $limit = max(1, min(1000, $limit));

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("SELECT
                    eel.id,
                    eel.exam_id,
                    eel.student_id,
                    eel.external_url,
                    eel.instructions,
                    eel.due_at,
                    eel.is_active,
                    eel.first_opened_at,
                    eel.last_opened_at,
                    eel.open_count,
                    eel.created_at,
                    ex.title AS exam_title,
                    c.name AS course_name,
                    s.full_name AS student_name
                FROM exam_external_links eel
                INNER JOIN exams ex ON ex.id = eel.exam_id
                INNER JOIN courses c ON c.id = ex.course_id
                INNER JOIN students s ON s.id = eel.student_id
                WHERE c.company_id = :company_id
                ORDER BY eel.updated_at DESC, eel.id DESC
                LIMIT {$limit}");
            $stmt->execute([':company_id' => $this->companyId()]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query("SELECT
                eel.id,
                eel.exam_id,
                eel.student_id,
                eel.external_url,
                eel.instructions,
                eel.due_at,
                eel.is_active,
                eel.first_opened_at,
                eel.last_opened_at,
                eel.open_count,
                eel.created_at,
                ex.title AS exam_title,
                c.name AS course_name,
                s.full_name AS student_name
            FROM exam_external_links eel
            INNER JOIN exams ex ON ex.id = eel.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN students s ON s.id = eel.student_id
            ORDER BY eel.updated_at DESC, eel.id DESC
            LIMIT {$limit}");

        return $stmt->fetchAll();
    }

    public function upsertExternalExamLink(array $data, int $createdBy): bool
    {
        if (!$this->hasExamExternalLinksTable()) {
            return false;
        }

        $examId = (int) ($data['exam_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);
        $externalUrl = trim((string) ($data['external_url'] ?? ''));
        $instructions = trim((string) ($data['instructions'] ?? ''));
        $dueAt = $this->normalizeDateTimeOrNull((string) ($data['due_at'] ?? ''));

        if (
            $examId <= 0
            || $studentId <= 0
            || $externalUrl === ''
            || !$this->isHttpUrl($externalUrl)
            || !$this->canAccessExam($examId)
            || !$this->studentBelongsCompany($studentId)
            || !$this->studentEnrolledInExamCourse($examId, $studentId)
        ) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT INTO exam_external_links (
            exam_id,
            student_id,
            external_url,
            instructions,
            due_at,
            is_active,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :exam_id,
            :student_id,
            :external_url,
            :instructions,
            :due_at,
            1,
            :created_by,
            :created_at,
            :updated_at
        )
        ON DUPLICATE KEY UPDATE
            external_url = VALUES(external_url),
            instructions = VALUES(instructions),
            due_at = VALUES(due_at),
            is_active = 1,
            created_by = VALUES(created_by),
            updated_at = VALUES(updated_at)');

        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
            ':external_url' => $externalUrl,
            ':instructions' => $instructions !== '' ? $instructions : null,
            ':due_at' => $dueAt,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return true;
    }

    public function upsertExternalExamLinksForExamCourse(array $data, int $createdBy): array
    {
        if (!$this->hasExamExternalLinksTable()) {
            return [
                'ok' => false,
                'eligible_total' => 0,
                'linked_total' => 0,
            ];
        }

        $examId = (int) ($data['exam_id'] ?? 0);
        $externalUrl = trim((string) ($data['external_url'] ?? ''));

        if (
            $examId <= 0
            || $externalUrl === ''
            || !$this->isHttpUrl($externalUrl)
            || !$this->canAccessExam($examId)
        ) {
            return [
                'ok' => false,
                'eligible_total' => 0,
                'linked_total' => 0,
            ];
        }

        $studentIds = $this->listEnrolledStudentIdsForExam($examId);
        $eligibleTotal = count($studentIds);

        if ($eligibleTotal <= 0) {
            return [
                'ok' => true,
                'eligible_total' => 0,
                'linked_total' => 0,
            ];
        }

        $linkedTotal = 0;
        $payload = [
            'exam_id' => $examId,
            'external_url' => $externalUrl,
            'instructions' => trim((string) ($data['instructions'] ?? '')),
            'due_at' => $this->normalizeDateTimeOrNull((string) ($data['due_at'] ?? '')),
        ];

        foreach ($studentIds as $studentId) {
            $ok = $this->upsertExternalExamLink($payload + [
                'student_id' => $studentId,
            ], $createdBy);

            if ($ok) {
                $linkedTotal++;
            }
        }

        return [
            'ok' => $linkedTotal > 0,
            'eligible_total' => $eligibleTotal,
            'linked_total' => $linkedTotal,
        ];
    }

    public function upsertInternalExamAudienceLink(array $data, int $createdBy): bool
    {
        if (!$this->hasExamInternalLinksTable()) {
            return false;
        }

        $examId = (int) ($data['exam_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);

        if (
            $examId <= 0
            || $studentId <= 0
            || !$this->canAccessExam($examId)
            || !$this->studentBelongsCompany($studentId)
            || !$this->studentEnrolledInExamCourse($examId, $studentId)
        ) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT INTO exam_internal_links (
            exam_id,
            student_id,
            is_active,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :exam_id,
            :student_id,
            1,
            :created_by,
            :created_at,
            :updated_at
        )
        ON DUPLICATE KEY UPDATE
            is_active = 1,
            created_by = VALUES(created_by),
            updated_at = VALUES(updated_at)');

        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return true;
    }

    public function upsertInternalExamAudienceForExamCourse(int $examId, int $createdBy): array
    {
        if (!$this->hasExamInternalLinksTable() || $examId <= 0 || !$this->canAccessExam($examId)) {
            return [
                'ok' => false,
                'eligible_total' => 0,
                'linked_total' => 0,
            ];
        }

        $studentIds = $this->listEnrolledStudentIdsForExam($examId);
        $eligibleTotal = count($studentIds);
        if ($eligibleTotal <= 0) {
            return [
                'ok' => true,
                'eligible_total' => 0,
                'linked_total' => 0,
            ];
        }

        $linkedTotal = 0;
        foreach ($studentIds as $studentId) {
            $ok = $this->upsertInternalExamAudienceLink([
                'exam_id' => $examId,
                'student_id' => $studentId,
            ], $createdBy);

            if ($ok) {
                $linkedTotal++;
            }
        }

        return [
            'ok' => $linkedTotal > 0,
            'eligible_total' => $eligibleTotal,
            'linked_total' => $linkedTotal,
        ];
    }

    public function deactivateExternalExamLink(int $linkId): bool
    {
        if (!$this->hasExamExternalLinksTable() || $linkId <= 0) {
            return false;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("UPDATE exam_external_links eel
                INNER JOIN exams ex ON ex.id = eel.exam_id
                INNER JOIN courses c ON c.id = ex.course_id
                SET eel.is_active = 0,
                    eel.updated_at = :updated_at
                WHERE eel.id = :id
                  AND c.company_id = :company_id");
            $stmt->execute([
                ':updated_at' => now(),
                ':id' => $linkId,
                ':company_id' => $this->companyId(),
            ]);

            return $stmt->rowCount() > 0;
        }

        $stmt = $this->db->prepare('UPDATE exam_external_links
            SET is_active = 0, updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute([
            ':updated_at' => now(),
            ':id' => $linkId,
        ]);

        return $stmt->rowCount() > 0;
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

    public function listExamResultsFeed(int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("SELECT
                    r.id,
                    r.exam_id,
                    r.student_id,
                    r.score,
                    r.status,
                    r.submitted_at,
                    ex.title AS exam_title,
                    ex.passing_score,
                    c.id AS course_id,
                    c.name AS course_name,
                    s.full_name AS student_name
                FROM exam_results r
                INNER JOIN exams ex ON ex.id = r.exam_id
                INNER JOIN courses c ON c.id = ex.course_id
                LEFT JOIN students s ON s.id = r.student_id
                WHERE c.company_id = :company_id
                ORDER BY r.submitted_at DESC, r.id DESC
                LIMIT {$limit}");
            $stmt->execute([':company_id' => $this->companyId()]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query("SELECT
                r.id,
                r.exam_id,
                r.student_id,
                r.score,
                r.status,
                r.submitted_at,
                ex.title AS exam_title,
                ex.passing_score,
                c.id AS course_id,
                c.name AS course_name,
                s.full_name AS student_name
            FROM exam_results r
            INNER JOIN exams ex ON ex.id = r.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            LEFT JOIN students s ON s.id = r.student_id
            ORDER BY r.submitted_at DESC, r.id DESC
            LIMIT {$limit}");

        return $stmt->fetchAll();
    }

    public function findExamNotificationContext(int $examId): ?array
    {
        if ($examId <= 0) {
            return null;
        }

        $params = [':exam_id' => $examId];
        $companyFilter = '';
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare("SELECT
                ex.id,
                ex.course_id,
                ex.title,
                ex.description,
                ex.passing_score,
                " . ($this->hasExamScheduleColumn() ? 'ex.scheduled_at' : 'NULL AS scheduled_at') . ",
                c.company_id,
                c.name AS course_name
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE ex.id = :exam_id{$companyFilter}
            LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listExamNotificationRecipients(int $examId, array $studentIds = []): array
    {
        if ($examId <= 0) {
            return [];
        }

        $params = [':exam_id' => $examId];
        $companyFilter = '';
        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $companyFilter = ' AND c.company_id = :company_id AND s.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $studentFilter = '';
        if ($studentIds !== []) {
            $filteredIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn (int $id): bool => $id > 0)));
            if ($filteredIds === []) {
                return [];
            }

            $placeholders = [];
            foreach ($filteredIds as $index => $studentId) {
                $key = ':student_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $studentId;
            }
            $studentFilter = ' AND s.id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->db->prepare("SELECT DISTINCT
                s.id AS student_id,
                s.full_name AS student_name,
                s.email_primary AS student_email,
                c.company_id,
                c.name AS course_name,
                ex.title AS exam_title,
                ex.passing_score
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = c.id
            INNER JOIN students s ON s.id = e.student_id
            WHERE ex.id = :exam_id
              AND e.status IN ('active', 'completed')
              {$companyFilter}
              {$studentFilter}
            ORDER BY s.full_name ASC, s.id ASC");
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function listComments(int $limit = 200, array $filters = []): array
    {
        $limit = max(1, $limit);
        $courseFilterSql = '';
        $params = [];

        if (!empty($filters['course_id'])) {
            $courseFilterSql = ' AND cm.course_id = :course_id';
            $params[':course_id'] = (int) $filters['course_id'];
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("SELECT cm.*, cr.name AS course_name, u.name AS author_name
                FROM course_comments cm
                INNER JOIN courses cr ON cr.id = cm.course_id
                LEFT JOIN users u ON u.id = cm.created_by
                WHERE cr.company_id = :company_id
                  {$courseFilterSql}
                ORDER BY cm.id DESC
                LIMIT {$limit}");
            $stmt->execute([':company_id' => $this->companyId()] + $params);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare("SELECT cm.*, cr.name AS course_name, u.name AS author_name
            FROM course_comments cm
            LEFT JOIN courses cr ON cr.id = cm.course_id
            LEFT JOIN users u ON u.id = cm.created_by
            WHERE 1=1 {$courseFilterSql}
            ORDER BY cm.id DESC
            LIMIT {$limit}");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function buildCourseCatalogFilters(array $filters): array
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

        return [implode(' AND ', $where), $params];
    }

    private function buildDuplicateCourseName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            $trimmed = 'Curso';
        }

        return $trimmed . ' (Copia)';
    }

    private function duplicateCourseLearningPath(int $sourceCourseId, int $targetCourseId, int $createdBy): void
    {
        if (!$this->hasCourseModulesTable() || !$this->hasCourseLessonsTable()) {
            return;
        }

        $moduleMap = [];
        foreach ($this->listCourseModulesWithLessons($sourceCourseId) as $module) {
            $moduleStmt = $this->db->prepare('INSERT INTO course_modules (
                course_id, title, description, display_order, is_active, created_at, updated_at
            ) VALUES (
                :course_id, :title, :description, :display_order, :is_active, :created_at, :updated_at
            )');
            $moduleStmt->execute([
                ':course_id' => $targetCourseId,
                ':title' => (string) ($module['title'] ?? ''),
                ':description' => (string) ($module['description'] ?? ''),
                ':display_order' => (int) ($module['display_order'] ?? 0),
                ':is_active' => (int) ($module['is_active'] ?? 1),
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);

            $newModuleId = (int) $this->db->lastInsertId();
            $moduleMap[(int) ($module['id'] ?? 0)] = $newModuleId;

            foreach (($module['lessons'] ?? []) as $lesson) {
                $lessonStmt = $this->db->prepare('INSERT INTO course_lessons (
                    course_id, module_id, title, description, lesson_type, video_url, duration_seconds,
                    min_progress_percent, is_required, is_active, display_order, created_at, updated_at
                ) VALUES (
                    :course_id, :module_id, :title, :description, :lesson_type, :video_url, :duration_seconds,
                    :min_progress_percent, :is_required, :is_active, :display_order, :created_at, :updated_at
                )');
                $lessonStmt->execute([
                    ':course_id' => $targetCourseId,
                    ':module_id' => $newModuleId,
                    ':title' => (string) ($lesson['title'] ?? ''),
                    ':description' => (string) ($lesson['description'] ?? ''),
                    ':lesson_type' => (string) ($lesson['lesson_type'] ?? 'video'),
                    ':video_url' => (string) ($lesson['video_url'] ?? ''),
                    ':duration_seconds' => (int) ($lesson['duration_seconds'] ?? 0),
                    ':min_progress_percent' => (int) ($lesson['min_progress_percent'] ?? 70),
                    ':is_required' => (int) ($lesson['is_required'] ?? 1),
                    ':is_active' => (int) ($lesson['is_active'] ?? 1),
                    ':display_order' => (int) ($lesson['display_order'] ?? 0),
                    ':created_at' => now(),
                    ':updated_at' => now(),
                ]);
            }
        }
    }

    private function equivalentPublishedCourses(int $courseId): array
    {
        if ($courseId <= 0 || !$this->hasCourseCompanyColumn()) {
            return [];
        }

        $course = $this->findCourse($courseId);
        if (!$course) {
            return [];
        }

        $courseName = trim((string) ($course['name'] ?? ''));
        if ($courseName === '') {
            return [];
        }

        $stmt = $this->db->prepare("SELECT c.id AS course_id, c.company_id
            FROM courses c
            INNER JOIN companies co ON co.id = c.company_id
            WHERE c.name = :name
              AND c.status = 'published'
              AND co.is_active = 1
              AND c.id <> :course_id
            ORDER BY c.company_id ASC, c.id ASC");
        $stmt->execute([
            ':name' => $courseName,
            ':course_id' => $courseId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function syncCourseModuleAcrossEquivalentCourses(array $sourceModule, ?string $oldTitle = null): void
    {
        if (!$this->hasCourseModulesTable()) {
            return;
        }

        $sourceCourseId = (int) ($sourceModule['course_id'] ?? 0);
        $title = trim((string) ($sourceModule['title'] ?? ''));
        if ($sourceCourseId <= 0 || $title === '') {
            return;
        }

        foreach ($this->equivalentPublishedCourses($sourceCourseId) as $targetCourse) {
            $targetCourseId = (int) ($targetCourse['course_id'] ?? 0);
            if ($targetCourseId <= 0) {
                continue;
            }

            $targetModule = $this->findEquivalentCourseModule($targetCourseId, $title, $oldTitle);
            if ($targetModule) {
                $stmt = $this->db->prepare('UPDATE course_modules SET
                    title = :title,
                    description = :description,
                    display_order = :display_order,
                    is_active = :is_active,
                    updated_at = :updated_at
                    WHERE id = :id');
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $sourceModule['description'] ?? null,
                    ':display_order' => (int) ($sourceModule['display_order'] ?? 0),
                    ':is_active' => (int) ($sourceModule['is_active'] ?? 1),
                    ':updated_at' => now(),
                    ':id' => (int) $targetModule['id'],
                ]);
                continue;
            }

            $stmt = $this->db->prepare('INSERT INTO course_modules (
                course_id, title, description, display_order, is_active, created_by, created_at, updated_at
            ) VALUES (
                :course_id, :title, :description, :display_order, :is_active, :created_by, :created_at, :updated_at
            )');
            $stmt->execute([
                ':course_id' => $targetCourseId,
                ':title' => $title,
                ':description' => $sourceModule['description'] ?? null,
                ':display_order' => (int) ($sourceModule['display_order'] ?? 0),
                ':is_active' => (int) ($sourceModule['is_active'] ?? 1),
                ':created_by' => (int) ($sourceModule['created_by'] ?? 0),
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }
    }

    private function deleteCourseModuleAcrossEquivalentCourses(array $sourceModule): void
    {
        $sourceCourseId = (int) ($sourceModule['course_id'] ?? 0);
        $title = trim((string) ($sourceModule['title'] ?? ''));
        if ($sourceCourseId <= 0 || $title === '') {
            return;
        }

        foreach ($this->equivalentPublishedCourses($sourceCourseId) as $targetCourse) {
            $targetModule = $this->findEquivalentCourseModule((int) $targetCourse['course_id'], $title);
            if (!$targetModule) {
                continue;
            }

            $stmt = $this->db->prepare('DELETE FROM course_modules WHERE id = :id AND course_id = :course_id');
            $stmt->execute([
                ':id' => (int) $targetModule['id'],
                ':course_id' => (int) $targetModule['course_id'],
            ]);
        }
    }

    private function syncCourseLessonAcrossEquivalentCourses(array $sourceLesson, array $sourceModule, ?string $oldTitle = null): void
    {
        if (!$this->hasCourseModulesTable() || !$this->hasCourseLessonsTable()) {
            return;
        }

        $sourceCourseId = (int) ($sourceLesson['course_id'] ?? 0);
        $sourceModuleTitle = trim((string) ($sourceModule['title'] ?? ''));
        $title = trim((string) ($sourceLesson['title'] ?? ''));
        if ($sourceCourseId <= 0 || $sourceModuleTitle === '' || $title === '') {
            return;
        }

        foreach ($this->equivalentPublishedCourses($sourceCourseId) as $targetCourse) {
            $targetCourseId = (int) ($targetCourse['course_id'] ?? 0);
            if ($targetCourseId <= 0) {
                continue;
            }

            $targetModule = $this->findEquivalentCourseModule($targetCourseId, $sourceModuleTitle);
            if (!$targetModule) {
                $targetModule = $this->createEquivalentCourseModule($targetCourseId, $sourceModule);
            }

            $targetLesson = $this->findEquivalentCourseLesson(
                $targetCourseId,
                (int) $targetModule['id'],
                $title,
                $oldTitle
            );

            if ($targetLesson) {
                $stmt = $this->db->prepare('UPDATE course_lessons SET
                    title = :title,
                    description = :description,
                    lesson_type = :lesson_type,
                    video_url = :video_url,
                    duration_seconds = :duration_seconds,
                    min_progress_percent = :min_progress_percent,
                    is_required = :is_required,
                    is_active = :is_active,
                    display_order = :display_order,
                    updated_at = :updated_at
                    WHERE id = :id');
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $sourceLesson['description'] ?? null,
                    ':lesson_type' => (string) ($sourceLesson['lesson_type'] ?? 'video'),
                    ':video_url' => (string) ($sourceLesson['video_url'] ?? ''),
                    ':duration_seconds' => $sourceLesson['duration_seconds'] ?? null,
                    ':min_progress_percent' => (int) ($sourceLesson['min_progress_percent'] ?? 70),
                    ':is_required' => (int) ($sourceLesson['is_required'] ?? 1),
                    ':is_active' => (int) ($sourceLesson['is_active'] ?? 1),
                    ':display_order' => (int) ($sourceLesson['display_order'] ?? 0),
                    ':updated_at' => now(),
                    ':id' => (int) $targetLesson['id'],
                ]);
                continue;
            }

            $stmt = $this->db->prepare('INSERT INTO course_lessons (
                course_id, module_id, title, description, lesson_type, video_url, duration_seconds,
                min_progress_percent, is_required, is_active, display_order, created_by, created_at, updated_at
            ) VALUES (
                :course_id, :module_id, :title, :description, :lesson_type, :video_url, :duration_seconds,
                :min_progress_percent, :is_required, :is_active, :display_order, :created_by, :created_at, :updated_at
            )');
            $stmt->execute([
                ':course_id' => $targetCourseId,
                ':module_id' => (int) $targetModule['id'],
                ':title' => $title,
                ':description' => $sourceLesson['description'] ?? null,
                ':lesson_type' => (string) ($sourceLesson['lesson_type'] ?? 'video'),
                ':video_url' => (string) ($sourceLesson['video_url'] ?? ''),
                ':duration_seconds' => $sourceLesson['duration_seconds'] ?? null,
                ':min_progress_percent' => (int) ($sourceLesson['min_progress_percent'] ?? 70),
                ':is_required' => (int) ($sourceLesson['is_required'] ?? 1),
                ':is_active' => (int) ($sourceLesson['is_active'] ?? 1),
                ':display_order' => (int) ($sourceLesson['display_order'] ?? 0),
                ':created_by' => (int) ($sourceLesson['created_by'] ?? 0),
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }

        $this->syncEnrollmentProgressFromLessons();
    }

    private function deleteCourseLessonAcrossEquivalentCourses(array $sourceLesson, array $sourceModule): void
    {
        $sourceCourseId = (int) ($sourceLesson['course_id'] ?? 0);
        $sourceModuleTitle = trim((string) ($sourceModule['title'] ?? ''));
        $title = trim((string) ($sourceLesson['title'] ?? ''));
        if ($sourceCourseId <= 0 || $sourceModuleTitle === '' || $title === '') {
            return;
        }

        foreach ($this->equivalentPublishedCourses($sourceCourseId) as $targetCourse) {
            $targetModule = $this->findEquivalentCourseModule((int) $targetCourse['course_id'], $sourceModuleTitle);
            if (!$targetModule) {
                continue;
            }

            $targetLesson = $this->findEquivalentCourseLesson(
                (int) $targetCourse['course_id'],
                (int) $targetModule['id'],
                $title
            );
            if (!$targetLesson) {
                continue;
            }

            $stmt = $this->db->prepare('DELETE FROM course_lessons WHERE id = :id AND course_id = :course_id');
            $stmt->execute([
                ':id' => (int) $targetLesson['id'],
                ':course_id' => (int) $targetLesson['course_id'],
            ]);
        }

        $this->syncEnrollmentProgressFromLessons();
    }

    private function createEquivalentCourseModule(int $targetCourseId, array $sourceModule): array
    {
        $stmt = $this->db->prepare('INSERT INTO course_modules (
            course_id, title, description, display_order, is_active, created_by, created_at, updated_at
        ) VALUES (
            :course_id, :title, :description, :display_order, :is_active, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':course_id' => $targetCourseId,
            ':title' => trim((string) ($sourceModule['title'] ?? '')),
            ':description' => $sourceModule['description'] ?? null,
            ':display_order' => (int) ($sourceModule['display_order'] ?? 0),
            ':is_active' => (int) ($sourceModule['is_active'] ?? 1),
            ':created_by' => (int) ($sourceModule['created_by'] ?? 0),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'course_id' => $targetCourseId,
        ];
    }

    private function findEquivalentCourseModule(int $courseId, string $title, ?string $fallbackTitle = null): ?array
    {
        $titles = array_values(array_unique(array_filter([
            trim($fallbackTitle ?? ''),
            trim($title),
        ], static fn (string $value): bool => $value !== '')));

        foreach ($titles as $candidateTitle) {
            $stmt = $this->db->prepare('SELECT *
                FROM course_modules
                WHERE course_id = :course_id
                  AND title = :title
                ORDER BY id ASC
                LIMIT 1');
            $stmt->execute([
                ':course_id' => $courseId,
                ':title' => $candidateTitle,
            ]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function findEquivalentCourseLesson(int $courseId, int $moduleId, string $title, ?string $fallbackTitle = null): ?array
    {
        $titles = array_values(array_unique(array_filter([
            trim($fallbackTitle ?? ''),
            trim($title),
        ], static fn (string $value): bool => $value !== '')));

        foreach ($titles as $candidateTitle) {
            $stmt = $this->db->prepare('SELECT *
                FROM course_lessons
                WHERE course_id = :course_id
                  AND module_id = :module_id
                  AND title = :title
                ORDER BY id ASC
                LIMIT 1');
            $stmt->execute([
                ':course_id' => $courseId,
                ':module_id' => $moduleId,
                ':title' => $candidateTitle,
            ]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        return null;
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

    private function buildExamSubmissionFilters(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $where[] = 'c.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $status = trim((string) ($filters['status'] ?? 'pending_review'));
        if (in_array($status, ['pending_review', 'submitted', 'auto_graded'], true)) {
            $where[] = 'sub.status = :status';
            $params[':status'] = $status;
        }

        $courseId = (int) ($filters['course_id'] ?? 0);
        if ($courseId > 0) {
            $where[] = 'c.id = :course_id';
            $params[':course_id'] = $courseId;
        }

        $examId = (int) ($filters['exam_id'] ?? 0);
        if ($examId > 0) {
            $where[] = 'ex.id = :exam_id';
            $params[':exam_id'] = $examId;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(st.full_name LIKE :q OR ex.title LIKE :q OR c.name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function findLatestExamResultId(int $examId, int $studentId): int
    {
        if ($examId <= 0 || $studentId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT id
            FROM exam_results
            WHERE exam_id = :exam_id
              AND student_id = :student_id
            ORDER BY submitted_at DESC, id DESC
            LIMIT 1');
        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function findExamResultForCompany(int $resultId): ?array
    {
        if ($resultId <= 0) {
            return null;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT r.id, r.exam_id, r.student_id
                FROM exam_results r
                INNER JOIN exams ex ON ex.id = r.exam_id
                INNER JOIN courses c ON c.id = ex.course_id
                WHERE r.id = :id
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':id' => $resultId,
                ':company_id' => $this->companyId(),
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT id, exam_id, student_id
                FROM exam_results
                WHERE id = :id
                LIMIT 1');
            $stmt->execute([':id' => $resultId]);
        }

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function resolveExamPassingScore(int $examId, float $fallback): float
    {
        if ($examId <= 0) {
            return $fallback > 0 ? $fallback : 7.0;
        }

        $stmt = $this->db->prepare('SELECT passing_score FROM exams WHERE id = :exam_id LIMIT 1');
        $stmt->execute([':exam_id' => $examId]);
        $score = $stmt->fetchColumn();
        if ($score !== false && $score !== null) {
            return (float) $score;
        }

        return $fallback > 0 ? $fallback : 7.0;
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

    private function studentEnrolledInExamCourse(int $examId, int $studentId): bool
    {
        if ($examId <= 0 || $studentId <= 0) {
            return false;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT ex.id
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                INNER JOIN enrollments e ON e.course_id = c.id
                WHERE ex.id = :exam_id
                  AND e.student_id = :student_id
                  AND e.status IN (\'active\', \'completed\')
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':exam_id' => $examId,
                ':student_id' => $studentId,
                ':company_id' => $this->companyId(),
            ]);

            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT ex.id
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = c.id
            WHERE ex.id = :exam_id
              AND e.student_id = :student_id
              AND e.status IN (\'active\', \'completed\')
            LIMIT 1');
        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function listEnrolledStudentIdsForExam(int $examId): array
    {
        if ($examId <= 0) {
            return [];
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare('SELECT DISTINCT e.student_id
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                INNER JOIN enrollments e ON e.course_id = c.id
                INNER JOIN students s ON s.id = e.student_id
                WHERE ex.id = :exam_id
                  AND e.status IN (\'active\', \'completed\')
                  AND c.company_id = :company_id
                  AND s.company_id = :company_id');
            $stmt->execute([
                ':exam_id' => $examId,
                ':company_id' => $this->companyId(),
            ]);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }

        $stmt = $this->db->prepare('SELECT DISTINCT e.student_id
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = c.id
            INNER JOIN students s ON s.id = e.student_id
            WHERE ex.id = :exam_id
              AND e.status IN (\'active\', \'completed\')');
        $stmt->execute([
            ':exam_id' => $examId,
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function normalizeDateTimeOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            $normalized .= ':00';
        }

        $ts = strtotime($normalized);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function isHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function deactivateExternalExamLinkByExamStudent(int $examId, int $studentId): void
    {
        if (!$this->hasExamExternalLinksTable() || $examId <= 0 || $studentId <= 0) {
            return;
        }

        if ($this->hasCourseCompanyColumn() && $this->companyId() > 0) {
            $stmt = $this->db->prepare("UPDATE exam_external_links eel
                INNER JOIN exams ex ON ex.id = eel.exam_id
                INNER JOIN courses c ON c.id = ex.course_id
                SET eel.is_active = 0,
                    eel.updated_at = :updated_at
                WHERE eel.exam_id = :exam_id
                  AND eel.student_id = :student_id
                  AND eel.is_active = 1
                  AND c.company_id = :company_id");
            $stmt->execute([
                ':updated_at' => now(),
                ':exam_id' => $examId,
                ':student_id' => $studentId,
                ':company_id' => $this->companyId(),
            ]);
            return;
        }

        $stmt = $this->db->prepare('UPDATE exam_external_links
            SET is_active = 0,
                updated_at = :updated_at
            WHERE exam_id = :exam_id
              AND student_id = :student_id
              AND is_active = 1');
        $stmt->execute([
            ':updated_at' => now(),
            ':exam_id' => $examId,
            ':student_id' => $studentId,
        ]);
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

    private function hasExamExternalLinksTable(): bool
    {
        if ($this->examExternalLinksTableExists !== null) {
            return $this->examExternalLinksTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'exam_external_links'");
        $stmt->execute();
        $this->examExternalLinksTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examExternalLinksTableExists;
    }

    private function hasExamInternalLinksTable(): bool
    {
        if ($this->examInternalLinksTableExists !== null) {
            return $this->examInternalLinksTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'exam_internal_links'");
        $stmt->execute();
        $this->examInternalLinksTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examInternalLinksTableExists;
    }

    private function hasExamSubmissionsTable(): bool
    {
        if ($this->examSubmissionsTableExists !== null) {
            return $this->examSubmissionsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'exam_submissions'");
        $stmt->execute();
        $this->examSubmissionsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examSubmissionsTableExists;
    }

    private function hasExamSubmissionAnswersTable(): bool
    {
        if ($this->examSubmissionAnswersTableExists !== null) {
            return $this->examSubmissionAnswersTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'exam_submission_answers'");
        $stmt->execute();
        $this->examSubmissionAnswersTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examSubmissionAnswersTableExists;
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

    private function defaultKanbanStatusId(): ?int
    {
        $stmt = $this->db->query('SELECT id FROM kanban_status WHERE is_default = 1 ORDER BY id ASC LIMIT 1');
        $value = $stmt ? $stmt->fetchColumn() : false;

        return $value !== false ? (int) $value : null;
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
