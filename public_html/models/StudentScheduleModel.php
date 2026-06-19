<?php

class StudentScheduleModel extends BaseModel
{
    public function unitsFeatureAvailable(): bool
    {
        return $this->schemaTableExists('student_practice_units');
    }

    public function featureAvailable(): bool
    {
        return $this->unitsFeatureAvailable()
            && $this->schemaTableExists('student_duty_schedules')
            && $this->schemaTableExists('student_duty_schedule_weeks')
            && $this->schemaTableExists('student_duty_assignments')
            && $this->schemaColumnExists('students', 'practice_unit_id')
            && $this->schemaColumnExists('students', 'residency_level');
    }

    public function listUnits(): array
    {
        if (!$this->unitsFeatureAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT u.*,
                (
                    SELECT COUNT(*)
                    FROM students s
                    WHERE s.company_id = u.company_id
                      AND s.practice_unit_id = u.id
                ) AS linked_students
            FROM student_practice_units u
            WHERE u.company_id = :company_id
            ORDER BY u.is_active DESC, u.name ASC');
        $stmt->execute([':company_id' => $this->companyId()]);
        return $stmt->fetchAll();
    }

    public function findUnit(int $id): ?array
    {
        if (!$this->unitsFeatureAvailable()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT u.*,
                (
                    SELECT COUNT(*)
                    FROM students s
                    WHERE s.company_id = u.company_id
                      AND s.practice_unit_id = u.id
                ) AS linked_students
            FROM student_practice_units u
            WHERE u.id = :id
              AND u.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUnit(array $data, int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO student_practice_units (
            company_id, name, city, state, is_active, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :city, :state, :is_active, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':name' => $data['name'],
            ':city' => $data['city'] !== '' ? $data['city'] : null,
            ':state' => $data['state'] !== '' ? strtoupper($data['state']) : null,
            ':is_active' => (int) ($data['is_active'] ?? 1),
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateUnit(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE student_practice_units
            SET name = :name,
                city = :city,
                state = :state,
                updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':name' => $data['name'],
            ':city' => $data['city'] !== '' ? $data['city'] : null,
            ':state' => $data['state'] !== '' ? strtoupper($data['state']) : null,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function toggleUnit(int $id, int $active): void
    {
        $stmt = $this->db->prepare('UPDATE student_practice_units
            SET is_active = :is_active,
                updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function listSchedules(array $filters, int $perPage, int $page): array
    {
        $where = ['s.company_id = :company_id'];
        $params = [':company_id' => $this->companyId()];

        if (!empty($filters['unit_id'])) {
            $where[] = 's.unit_id = :unit_id';
            $params[':unit_id'] = (int) $filters['unit_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(s.title LIKE :q OR u.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM student_duty_schedules s
            INNER JOIN student_practice_units u ON u.id = s.unit_id
            WHERE {$whereSql}";

        $dataSql = "SELECT s.*, u.name AS unit_name, u.city AS unit_city, u.state AS unit_state,
                (SELECT COUNT(*) FROM student_duty_schedule_weeks w WHERE w.schedule_id = s.id) AS total_weeks
            FROM student_duty_schedules s
            INNER JOIN student_practice_units u ON u.id = s.unit_id
            WHERE {$whereSql}
            ORDER BY s.start_date DESC, s.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function findSchedule(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT s.*, u.name AS unit_name, u.city AS unit_city, u.state AS unit_state
            FROM student_duty_schedules s
            INNER JOIN student_practice_units u ON u.id = s.unit_id
            WHERE s.id = :id
              AND s.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createSchedule(array $data, int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO student_duty_schedules (
            company_id, unit_id, title, start_date, end_date, status, notes, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :unit_id, :title, :start_date, :end_date, :status, :notes, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':unit_id' => $data['unit_id'],
            ':title' => $data['title'],
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':status' => $data['status'] ?: 'draft',
            ':notes' => $data['notes'],
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateSchedule(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE student_duty_schedules SET
            unit_id = :unit_id,
            title = :title,
            start_date = :start_date,
            end_date = :end_date,
            notes = :notes,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':unit_id' => $data['unit_id'],
            ':title' => $data['title'],
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':notes' => $data['notes'],
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function setScheduleStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE student_duty_schedules SET
            status = :status,
            published_at = :published_at,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $stmt->execute([
            ':status' => $status,
            ':published_at' => $status === 'published' ? now() : null,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function replaceWeeks(int $scheduleId, array $weeks): void
    {
        $this->db->beginTransaction();

        try {
            $weekIdsStmt = $this->db->prepare('SELECT id FROM student_duty_schedule_weeks WHERE schedule_id = :schedule_id');
            $weekIdsStmt->execute([':schedule_id' => $scheduleId]);
            $weekIds = array_map('intval', array_column($weekIdsStmt->fetchAll(), 'id'));

            if ($weekIds !== []) {
                $placeholders = implode(',', array_fill(0, count($weekIds), '?'));
                $deleteAssignments = $this->db->prepare("DELETE FROM student_duty_assignments WHERE schedule_week_id IN ({$placeholders})");
                $deleteAssignments->execute($weekIds);
            }

            $deleteWeeks = $this->db->prepare('DELETE FROM student_duty_schedule_weeks WHERE schedule_id = :schedule_id');
            $deleteWeeks->execute([':schedule_id' => $scheduleId]);

            $insert = $this->db->prepare('INSERT INTO student_duty_schedule_weeks (
                schedule_id, month_ref, week_order, start_date, end_date, r3_slots, r2_slots, r1_slots, notes, created_at, updated_at
            ) VALUES (
                :schedule_id, :month_ref, :week_order, :start_date, :end_date, :r3_slots, :r2_slots, :r1_slots, :notes, :created_at, :updated_at
            )');

            foreach ($weeks as $week) {
                $insert->execute([
                    ':schedule_id' => $scheduleId,
                    ':month_ref' => $week['month_ref'],
                    ':week_order' => $week['week_order'],
                    ':start_date' => $week['start_date'],
                    ':end_date' => $week['end_date'],
                    ':r3_slots' => $week['r3_slots'],
                    ':r2_slots' => $week['r2_slots'],
                    ':r1_slots' => $week['r1_slots'],
                    ':notes' => $week['notes'],
                    ':created_at' => now(),
                    ':updated_at' => now(),
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function syncWeeksPreservingAssignments(int $scheduleId, array $weeks): array
    {
        $this->db->beginTransaction();

        try {
            $existingStmt = $this->db->prepare("SELECT w.*,
                    (SELECT COUNT(*) FROM student_duty_assignments a WHERE a.schedule_week_id = w.id) AS assigned_total,
                    (SELECT COUNT(*) FROM student_duty_assignments a WHERE a.schedule_week_id = w.id AND a.slot_group = 'R3') AS r3_assigned,
                    (SELECT COUNT(*) FROM student_duty_assignments a WHERE a.schedule_week_id = w.id AND a.slot_group = 'R2') AS r2_assigned,
                    (SELECT COUNT(*) FROM student_duty_assignments a WHERE a.schedule_week_id = w.id AND a.slot_group = 'R1') AS r1_assigned
                FROM student_duty_schedule_weeks w
                WHERE w.schedule_id = :schedule_id
                ORDER BY w.week_order ASC");
            $existingStmt->execute([':schedule_id' => $scheduleId]);

            $existingByRange = [];
            $existingRows = [];
            foreach ($existingStmt->fetchAll() as $row) {
                $row['assigned_total'] = (int) ($row['assigned_total'] ?? 0);
                $row['r3_assigned'] = (int) ($row['r3_assigned'] ?? 0);
                $row['r2_assigned'] = (int) ($row['r2_assigned'] ?? 0);
                $row['r1_assigned'] = (int) ($row['r1_assigned'] ?? 0);
                $rangeKey = (string) $row['start_date'] . '|' . (string) $row['end_date'];
                $existingByRange[$rangeKey] = $row;
                $existingRows[(int) $row['id']] = $row;
            }

            $matchedIds = [];
            foreach ($weeks as $week) {
                $rangeKey = (string) $week['start_date'] . '|' . (string) $week['end_date'];
                if (isset($existingByRange[$rangeKey])) {
                    $matchedIds[] = (int) $existingByRange[$rangeKey]['id'];
                }
            }
            $matchedIds = array_values(array_unique($matchedIds));

            $blockedWeeks = [];
            foreach ($existingRows as $existingId => $existing) {
                if (in_array($existingId, $matchedIds, true)) {
                    continue;
                }

                if ((int) ($existing['assigned_total'] ?? 0) > 0) {
                    $blockedWeeks[] = date('d/m', strtotime((string) $existing['start_date']))
                        . ' - '
                        . date('d/m', strtotime((string) $existing['end_date']));
                }
            }

            if ($blockedWeeks !== []) {
                throw new RuntimeException(
                    'Não foi possível atualizar a grade porque existem alunos alocados em semana(s) que sairiam do período: '
                    . implode(', ', $blockedWeeks)
                    . '. Remova essas alocacoes manualmente ou ajuste o periodo antes de atualizar.'
                );
            }

            $deleteIds = array_values(array_filter(
                array_keys($existingRows),
                static fn (int $existingId): bool => !in_array($existingId, $matchedIds, true)
            ));
            if ($deleteIds !== []) {
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $delete = $this->db->prepare("DELETE FROM student_duty_schedule_weeks WHERE id IN ({$placeholders})");
                $delete->execute($deleteIds);
            }

            if ($matchedIds !== []) {
                $temporaryOrder = $this->db->prepare('UPDATE student_duty_schedule_weeks
                    SET week_order = :week_order
                    WHERE id = :id');
                foreach ($matchedIds as $matchedId) {
                    $temporaryOrder->execute([
                        ':week_order' => 1000000 + $matchedId,
                        ':id' => $matchedId,
                    ]);
                }
            }

            $insert = $this->db->prepare('INSERT INTO student_duty_schedule_weeks (
                schedule_id, month_ref, week_order, start_date, end_date, r3_slots, r2_slots, r1_slots, notes, created_at, updated_at
            ) VALUES (
                :schedule_id, :month_ref, :week_order, :start_date, :end_date, :r3_slots, :r2_slots, :r1_slots, :notes, :created_at, :updated_at
            )');

            $update = $this->db->prepare('UPDATE student_duty_schedule_weeks SET
                month_ref = :month_ref,
                week_order = :week_order,
                r3_slots = :r3_slots,
                r2_slots = :r2_slots,
                r1_slots = :r1_slots,
                notes = :notes,
                updated_at = :updated_at
                WHERE id = :id');

            $summary = ['created' => 0, 'updated' => 0, 'deleted_empty' => count($deleteIds)];
            foreach ($weeks as $week) {
                $rangeKey = (string) $week['start_date'] . '|' . (string) $week['end_date'];
                $existing = $existingByRange[$rangeKey] ?? null;

                if ($existing !== null) {
                    foreach (['r3' => 'R3', 'r2' => 'R2', 'r1' => 'R1'] as $fieldPrefix => $groupLabel) {
                        $slotField = $fieldPrefix . '_slots';
                        $assignedField = $fieldPrefix . '_assigned';
                        if ((int) $week[$slotField] < (int) ($existing[$assignedField] ?? 0)) {
                            throw new RuntimeException(sprintf(
                                'Não é possível reduzir %s na semana %s - %s para %d vaga(s), pois existem %d aluno(s) alocados.',
                                $groupLabel,
                                date('d/m', strtotime((string) $week['start_date'])),
                                date('d/m', strtotime((string) $week['end_date'])),
                                (int) $week[$slotField],
                                (int) ($existing[$assignedField] ?? 0)
                            ));
                        }
                    }

                    $notes = trim((string) ($week['notes'] ?? ''));
                    if ($notes === '') {
                        $notes = (string) ($existing['notes'] ?? '');
                    }

                    $update->execute([
                        ':month_ref' => $week['month_ref'],
                        ':week_order' => $week['week_order'],
                        ':r3_slots' => $week['r3_slots'],
                        ':r2_slots' => $week['r2_slots'],
                        ':r1_slots' => $week['r1_slots'],
                        ':notes' => $notes,
                        ':updated_at' => now(),
                        ':id' => (int) $existing['id'],
                    ]);
                    $summary['updated']++;
                    continue;
                }

                $insert->execute([
                    ':schedule_id' => $scheduleId,
                    ':month_ref' => $week['month_ref'],
                    ':week_order' => $week['week_order'],
                    ':start_date' => $week['start_date'],
                    ':end_date' => $week['end_date'],
                    ':r3_slots' => $week['r3_slots'],
                    ':r2_slots' => $week['r2_slots'],
                    ':r1_slots' => $week['r1_slots'],
                    ':notes' => $week['notes'],
                    ':created_at' => now(),
                    ':updated_at' => now(),
                ]);
                $summary['created']++;
            }

            $this->db->commit();
            return $summary;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateWeek(int $weekId, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE student_duty_schedule_weeks SET
            r3_slots = :r3_slots,
            r2_slots = :r2_slots,
            r1_slots = :r1_slots,
            notes = :notes,
            updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute([
            ':r3_slots' => $data['r3_slots'],
            ':r2_slots' => $data['r2_slots'],
            ':r1_slots' => $data['r1_slots'],
            ':notes' => $data['notes'],
            ':updated_at' => now(),
            ':id' => $weekId,
        ]);
    }

    public function assignmentCountsForWeek(int $weekId): array
    {
        $stmt = $this->db->prepare('SELECT slot_group, COUNT(*) AS total
            FROM student_duty_assignments
            WHERE schedule_week_id = :week_id
            GROUP BY slot_group');
        $stmt->execute([':week_id' => $weekId]);

        $counts = ['R3' => 0, 'R2' => 0, 'R1' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $group = strtoupper((string) ($row['slot_group'] ?? ''));
            if (isset($counts[$group])) {
                $counts[$group] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    public function listWeeks(int $scheduleId): array
    {
        $stmt = $this->db->prepare('SELECT w.*,
                a.id AS assignment_id,
                a.student_id,
                a.residency_level_snapshot,
                a.slot_group,
                a.position_order,
                s.full_name AS student_name,
                s.enrolled_at AS student_enrolled_at,
                s.residency_level AS student_current_level
            FROM student_duty_schedule_weeks w
            LEFT JOIN student_duty_assignments a ON a.schedule_week_id = w.id
            LEFT JOIN students s ON s.id = a.student_id
            WHERE w.schedule_id = :schedule_id
            ORDER BY w.week_order ASC, a.slot_group ASC, a.position_order ASC');
        $stmt->execute([':schedule_id' => $scheduleId]);

        $weeks = [];
        foreach ($stmt->fetchAll() as $row) {
            $weekId = (int) $row['id'];
            if (!isset($weeks[$weekId])) {
                $weeks[$weekId] = [
                    'id' => $weekId,
                    'schedule_id' => (int) $row['schedule_id'],
                    'month_ref' => (string) $row['month_ref'],
                    'week_order' => (int) $row['week_order'],
                    'start_date' => (string) $row['start_date'],
                    'end_date' => (string) $row['end_date'],
                    'r3_slots' => (int) $row['r3_slots'],
                    'r2_slots' => (int) $row['r2_slots'],
                    'r1_slots' => (int) $row['r1_slots'],
                    'notes' => (string) ($row['notes'] ?? ''),
                    'assignments' => ['R3' => [], 'R2' => [], 'R1' => []],
                ];
            }

            if (!empty($row['assignment_id'])) {
                $slotGroup = strtoupper((string) $row['slot_group']);
                if (!isset($weeks[$weekId]['assignments'][$slotGroup])) {
                    $weeks[$weekId]['assignments'][$slotGroup] = [];
                }
                $weeks[$weekId]['assignments'][$slotGroup][] = [
                    'id' => (int) $row['assignment_id'],
                    'student_id' => (int) $row['student_id'],
                    'student_name' => (string) ($row['student_name'] ?? ''),
                    'residency_level_snapshot' => (string) ($row['residency_level_snapshot'] ?? ''),
                    'position_order' => (int) ($row['position_order'] ?? 0),
                ];
            }
        }

        return array_values($weeks);
    }

    public function findWeek(int $weekId): ?array
    {
        $stmt = $this->db->prepare('SELECT w.*, ds.company_id, ds.unit_id, ds.status,
                ds.title AS schedule_title,
                ds.start_date AS schedule_start_date,
                ds.end_date AS schedule_end_date,
                u.name AS unit_name,
                u.city AS unit_city,
                u.state AS unit_state
            FROM student_duty_schedule_weeks w
            INNER JOIN student_duty_schedules ds ON ds.id = w.schedule_id
            INNER JOIN student_practice_units u ON u.id = ds.unit_id
            WHERE w.id = :id
              AND ds.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $weekId,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function assignmentNotificationContext(int $weekId, int $studentId): ?array
    {
        $week = $this->findWeek($weekId);
        if (!$week) {
            return null;
        }

        $student = $this->findStudentForAssignment($studentId, (int) $week['unit_id'], true);
        if (!$student) {
            return null;
        }

        return [
            'company_id' => (int) ($week['company_id'] ?? $student['company_id'] ?? 0),
            'student_id' => (int) ($student['id'] ?? 0),
            'student_name' => (string) ($student['full_name'] ?? ''),
            'student_email' => trim((string) ($student['email_primary'] ?? '')),
            'student_level' => strtoupper((string) ($student['residency_level'] ?? 'R1')),
            'schedule_id' => (int) ($week['schedule_id'] ?? 0),
            'schedule_title' => (string) ($week['schedule_title'] ?? ''),
            'schedule_status' => (string) ($week['status'] ?? ''),
            'schedule_start_date' => (string) ($week['schedule_start_date'] ?? ''),
            'schedule_end_date' => (string) ($week['schedule_end_date'] ?? ''),
            'week_id' => (int) ($week['id'] ?? 0),
            'week_start_date' => (string) ($week['start_date'] ?? ''),
            'week_end_date' => (string) ($week['end_date'] ?? ''),
            'week_notes' => (string) ($week['notes'] ?? ''),
            'unit_id' => (int) ($week['unit_id'] ?? 0),
            'unit_name' => (string) ($week['unit_name'] ?? ''),
            'unit_city' => (string) ($week['unit_city'] ?? ''),
            'unit_state' => (string) ($week['unit_state'] ?? ''),
        ];
    }

    public function listEligibleStudentsForWeek(array $week): array
    {
        $stmt = $this->db->prepare('SELECT s.id, s.full_name, s.enrolled_at, s.residency_level, s.practice_unit_id
            FROM students s
            WHERE s.company_id = :company_id
              AND s.is_active = 1
              AND s.practice_unit_id = :practice_unit_id
            ORDER BY s.residency_level DESC, s.full_name ASC');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':practice_unit_id' => (int) $week['unit_id'],
        ]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $eligibleSince = $this->eligibleSince((string) ($row['enrolled_at'] ?? ''));
            $conflict = $this->findWeeklyConflict(
                (int) ($row['id'] ?? 0),
                (int) ($week['unit_id'] ?? 0),
                (string) ($week['start_date'] ?? ''),
                (string) ($week['end_date'] ?? ''),
                (int) ($week['id'] ?? 0)
            );
            $rows[] = [
                'id' => (int) $row['id'],
                'full_name' => (string) $row['full_name'],
                'residency_level' => strtoupper((string) ($row['residency_level'] ?? 'R1')),
                'enrolled_at' => (string) ($row['enrolled_at'] ?? ''),
                'eligible_since' => $eligibleSince,
                'is_eligible' => $eligibleSince !== null && $eligibleSince <= (string) $week['start_date'],
                'has_conflict' => $conflict !== null,
                'conflict_schedule_id' => (int) ($conflict['schedule_id'] ?? 0),
                'conflict_schedule_title' => (string) ($conflict['schedule_title'] ?? ''),
                'conflict_start_date' => (string) ($conflict['start_date'] ?? ''),
                'conflict_end_date' => (string) ($conflict['end_date'] ?? ''),
            ];
        }

        return $rows;
    }

    public function createAssignment(int $weekId, int $studentId, string $slotGroup, int $assignedBy): void
    {
        $week = $this->findWeek($weekId);
        if (!$week) {
            throw new RuntimeException('Semana da escala não encontrada.');
        }

        if ((string) ($week['status'] ?? '') === 'archived') {
            throw new RuntimeException('Escala arquivada não pode receber alteracoes.');
        }

        $student = $this->findStudentForAssignment($studentId, (int) $week['unit_id']);
        if (!$student) {
            throw new RuntimeException('Aluno inválido para esta unidade.');
        }

        $studentLevel = strtoupper((string) ($student['residency_level'] ?? 'R1'));
        $slotGroup = strtoupper($slotGroup);
        if ($studentLevel !== $slotGroup) {
            throw new RuntimeException('O aluno precisa estar no mesmo nivel da coluna selecionada.');
        }

        $eligibleSince = $this->eligibleSince((string) ($student['enrolled_at'] ?? ''));
        if ($eligibleSince === null || $eligibleSince > (string) $week['start_date']) {
            throw new RuntimeException('Aluno ainda não elegível para escala. Aguardando os 40 dias da entrada.');
        }

        $existing = $this->db->prepare('SELECT id FROM student_duty_assignments WHERE schedule_week_id = :schedule_week_id AND student_id = :student_id LIMIT 1');
        $existing->execute([
            ':schedule_week_id' => $weekId,
            ':student_id' => $studentId,
        ]);
        if ($existing->fetch()) {
            throw new RuntimeException('Aluno já escalado nesta semana.');
        }

        $crossScheduleConflict = $this->findWeeklyConflict(
            $studentId,
            (int) ($week['unit_id'] ?? 0),
            (string) ($week['start_date'] ?? ''),
            (string) ($week['end_date'] ?? ''),
            $weekId
        );
        if ($crossScheduleConflict) {
            $conflictTitle = trim((string) ($crossScheduleConflict['schedule_title'] ?? ''));
            $message = 'Aluno já escalado nesta mesma semana em outra escala da unidade.';
            if ($conflictTitle !== '') {
                $message .= ' Escala: ' . $conflictTitle . '.';
            }
            throw new RuntimeException($message);
        }

        $slotLimit = $this->slotLimitForWeek($week, $slotGroup);
        $position = $this->nextSlotPosition($weekId, $slotGroup);
        if ($position > $slotLimit) {
            throw new RuntimeException('Não há mais vagas disponíveis nesta coluna para a semana.');
        }

        $stmt = $this->db->prepare('INSERT INTO student_duty_assignments (
            schedule_week_id, student_id, residency_level_snapshot, slot_group, position_order, assigned_by, created_at
        ) VALUES (
            :schedule_week_id, :student_id, :residency_level_snapshot, :slot_group, :position_order, :assigned_by, :created_at
        )');
        $stmt->execute([
            ':schedule_week_id' => $weekId,
            ':student_id' => $studentId,
            ':residency_level_snapshot' => $studentLevel,
            ':slot_group' => $slotGroup,
            ':position_order' => $position,
            ':assigned_by' => $assignedBy,
            ':created_at' => now(),
        ]);
    }

    public function deleteAssignment(int $assignmentId): void
    {
        $stmt = $this->db->prepare('DELETE a
            FROM student_duty_assignments a
            INNER JOIN student_duty_schedule_weeks w ON w.id = a.schedule_week_id
            INNER JOIN student_duty_schedules s ON s.id = w.schedule_id
            WHERE a.id = :id
              AND s.company_id = :company_id');
        $stmt->execute([
            ':id' => $assignmentId,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function groupedWeeksForSchedule(int $scheduleId): array
    {
        $weeks = $this->listWeeks($scheduleId);
        $grouped = [];
        foreach ($weeks as $week) {
            $grouped[$week['month_ref']][] = $week;
        }
        return $grouped;
    }

    public function formatMonthRef(string $date): string
    {
        static $map = [
            '01' => 'JANEIRO',
            '02' => 'FEVEREIRO',
            '03' => 'MARCO',
            '04' => 'ABRIL',
            '05' => 'MAIO',
            '06' => 'JUNHO',
            '07' => 'JULHO',
            '08' => 'AGOSTO',
            '09' => 'SETEMBRO',
            '10' => 'OUTUBRO',
            '11' => 'NOVEMBRO',
            '12' => 'DEZEMBRO',
        ];

        $month = date('m', strtotime($date));
        return $map[$month] ?? strtoupper($month);
    }

    public function buildWeekRange(string $startDate, string $endDate): string
    {
        return date('d', strtotime($startDate)) . ' a ' . date('d', strtotime($endDate));
    }

    private function findStudentForAssignment(int $studentId, int $unitId, bool $includeEmail = false): ?array
    {
        $emailSelect = $includeEmail ? ', email_primary, company_id' : '';
        $stmt = $this->db->prepare("SELECT id, full_name, enrolled_at, residency_level, practice_unit_id{$emailSelect}
            FROM students
            WHERE id = :id
              AND company_id = :company_id
              AND is_active = 1
              AND practice_unit_id = :practice_unit_id
            LIMIT 1");
        $stmt->execute([
            ':id' => $studentId,
            ':company_id' => $this->companyId(),
            ':practice_unit_id' => $unitId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function slotLimitForWeek(array $week, string $slotGroup): int
    {
        return match ($slotGroup) {
            'R3' => max(0, (int) ($week['r3_slots'] ?? 0)),
            'R2' => max(0, (int) ($week['r2_slots'] ?? 0)),
            default => max(0, (int) ($week['r1_slots'] ?? 0)),
        };
    }

    private function nextSlotPosition(int $weekId, string $slotGroup): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(position_order), 0) + 1
            FROM student_duty_assignments
            WHERE schedule_week_id = :schedule_week_id
              AND slot_group = :slot_group');
        $stmt->execute([
            ':schedule_week_id' => $weekId,
            ':slot_group' => $slotGroup,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function listAssignmentNotificationRows(int $scheduleId): array
    {
        $stmt = $this->db->prepare('SELECT
                ds.company_id,
                ds.id AS schedule_id,
                ds.title AS schedule_title,
                ds.status AS schedule_status,
                u.name AS unit_name,
                u.city AS unit_city,
                u.state AS unit_state,
                w.id AS week_id,
                w.start_date AS week_start_date,
                w.end_date AS week_end_date,
                w.notes AS week_notes,
                a.slot_group,
                s.id AS student_id,
                s.full_name AS student_name,
                s.email_primary AS student_email
            FROM student_duty_assignments a
            INNER JOIN student_duty_schedule_weeks w ON w.id = a.schedule_week_id
            INNER JOIN student_duty_schedules ds ON ds.id = w.schedule_id
            INNER JOIN student_practice_units u ON u.id = ds.unit_id
            INNER JOIN students s ON s.id = a.student_id
            WHERE ds.id = :schedule_id
              AND ds.company_id = :company_id
            ORDER BY w.start_date ASC, a.slot_group ASC, a.position_order ASC');
        $stmt->execute([
            ':schedule_id' => $scheduleId,
            ':company_id' => $this->companyId(),
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function findWeeklyConflict(int $studentId, int $unitId, string $startDate, string $endDate, int $currentWeekId): ?array
    {
        if ($studentId <= 0 || $unitId <= 0 || $startDate === '' || $endDate === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT
                a.id,
                ds.id AS schedule_id,
                ds.title AS schedule_title,
                w.start_date,
                w.end_date
            FROM student_duty_assignments a
            INNER JOIN student_duty_schedule_weeks w ON w.id = a.schedule_week_id
            INNER JOIN student_duty_schedules ds ON ds.id = w.schedule_id
            WHERE a.student_id = :student_id
              AND ds.company_id = :company_id
              AND ds.unit_id = :unit_id
              AND w.id <> :current_week_id
              AND w.start_date <= :end_date
              AND w.end_date >= :start_date
            LIMIT 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':company_id' => $this->companyId(),
            ':unit_id' => $unitId,
            ':current_week_id' => $currentWeekId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function eligibleSince(string $enrolledAt): ?string
    {
        if ($enrolledAt === '') {
            return null;
        }

        try {
            $base = new DateTimeImmutable($enrolledAt);
            return $base->modify('+40 days')->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }
}
