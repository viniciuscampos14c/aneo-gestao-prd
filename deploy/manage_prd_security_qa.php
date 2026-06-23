<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
$action = $argv[2] ?? '';
if ($root === '' || !in_array($action, ['setup', 'cleanup', 'status'], true)) {
    fwrite(STDERR, "Parâmetros inválidos.\n");
    exit(1);
}

require $root . '/core/bootstrap.php';

$db = db();
$statePath = '/home/u674156040/secure/aneo-prd/prd_security_qa_state.json';
$prefix = 'qa_security_';

$cleanup = static function () use ($db, $statePath, $prefix): array {
    $studentIds = $db->query(
        "SELECT id FROM students WHERE admin_info = 'QA-SECURITY-PRE-GO-LIVE'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $userIds = $db->query(
        "SELECT id FROM users WHERE username LIKE " . $db->quote($prefix . '%')
    )->fetchAll(PDO::FETCH_COLUMN);

    $db->beginTransaction();
    try {
        if ($studentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            foreach ([
                'student_lesson_progress',
                'student_portal_notifications',
                'course_question_messages',
                'course_questions',
                'exam_results',
                'exam_submissions',
                'student_portal_accounts',
                'enrollments',
            ] as $table) {
                $exists = (int) $db->query(
                    'SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = ' . $db->quote($table)
                )->fetchColumn();
                if (!$exists) {
                    continue;
                }

                $column = $table === 'course_question_messages' ? 'question_id' : 'student_id';
                if ($table === 'course_question_messages') {
                    $stmt = $db->prepare(
                        "DELETE FROM course_question_messages
                         WHERE question_id IN (
                             SELECT id FROM course_questions WHERE student_id IN ($placeholders)
                         )"
                    );
                } else {
                    $stmt = $db->prepare("DELETE FROM `$table` WHERE `$column` IN ($placeholders)");
                }
                $stmt->execute($studentIds);
            }

            $stmt = $db->prepare("DELETE FROM students WHERE id IN ($placeholders)");
            $stmt->execute($studentIds);
        }

        if ($userIds !== []) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            foreach (['api_tokens', 'admin_ai_sessions', 'user_permissions', 'user_companies'] as $table) {
                $exists = (int) $db->query(
                    'SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = ' . $db->quote($table)
                )->fetchColumn();
                if (!$exists) {
                    continue;
                }
                $stmt = $db->prepare("DELETE FROM `$table` WHERE user_id IN ($placeholders)");
                $stmt->execute($userIds);
            }
            $stmt = $db->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            $stmt->execute($userIds);
        }

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    @unlink($statePath);
    return ['students_removed' => count($studentIds), 'users_removed' => count($userIds)];
};

if ($action === 'cleanup') {
    echo json_encode(['ok' => true, 'cleanup' => $cleanup()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

if ($action === 'status') {
    echo json_encode([
        'students' => (int) $db->query(
            "SELECT COUNT(*) FROM students WHERE admin_info = 'QA-SECURITY-PRE-GO-LIVE'"
        )->fetchColumn(),
        'users' => (int) $db->query(
            "SELECT COUNT(*) FROM users WHERE username LIKE " . $db->quote($prefix . '%')
        )->fetchColumn(),
        'state_file' => is_file($statePath),
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

$cleanup();

$companyId = 5;
$courseId = 6;
$total = 100;
$password = 'Qa!' . bin2hex(random_bytes(12));
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$studentIds = [];
$credentials = [];
$userIds = [];

$db->beginTransaction();
try {
    $studentInsert = $db->prepare(
        'INSERT INTO students (
            company_id, full_name, primary_contact, email_primary, phone, city, is_active,
            admin_info, ra, enrolled_at, notes, monthly_fee, financial_plan_auto_generate,
            financial_plan_boleto_days_before, created_by, created_at, updated_at
         ) VALUES (
            :company_id, :full_name, :primary_contact, :email_primary, :phone, :city, 1,
            :admin_info, :ra, :enrolled_at, :notes, 0, 0, 10, NULL, :created_at, :updated_at
         )'
    );
    $portalInsert = $db->prepare(
        'INSERT INTO student_portal_accounts (
            student_id, login, password_hash, is_active, created_at, updated_at
         ) VALUES (:student_id, :login, :password_hash, 1, :created_at, :updated_at)'
    );
    $enrollmentInsert = $db->prepare(
        'INSERT INTO enrollments (
            student_id, course_id, status, progress_percent, started_at, created_by, created_at, updated_at
         ) VALUES (:student_id, :course_id, \'active\', 0, :started_at, NULL, :created_at, :updated_at)'
    );

    for ($index = 1; $index <= $total; $index++) {
        $sequence = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $login = $prefix . 'student_' . $sequence;
        $studentInsert->execute([
            ':company_id' => $companyId,
            ':full_name' => 'QA Segurança ' . $sequence,
            ':primary_contact' => 'QA Segurança',
            ':email_primary' => $login . '@aneo.test',
            ':phone' => '00000000000',
            ':city' => 'QA',
            ':admin_info' => 'QA-SECURITY-PRE-GO-LIVE',
            ':ra' => 'QA-SEC-' . $sequence,
            ':enrolled_at' => $today,
            ':notes' => 'Massa temporária autorizada para validação pré-go-live.',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $studentId = (int) $db->lastInsertId();
        $studentIds[] = $studentId;

        $portalInsert->execute([
            ':student_id' => $studentId,
            ':login' => $login,
            ':password_hash' => $passwordHash,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $enrollmentInsert->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':started_at' => $today,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $credentials[] = ['username' => $login, 'password' => $password];
    }

    $userInsert = $db->prepare(
        'INSERT INTO users (
            name, username, email, password_hash, role, is_active, created_at, updated_at
         ) VALUES (
            :name, :username, :email, :password_hash, :role, 1, :created_at, :updated_at
         )'
    );
    $companyInsert = $db->prepare(
        'INSERT INTO user_companies (
            user_id, company_id, is_default, created_at, updated_at
         ) VALUES (:user_id, :company_id, 1, :created_at, :updated_at)'
    );
    $permissionInsert = $db->prepare(
        'INSERT INTO user_permissions (
            user_id, permission_key, allowed, created_at, updated_at
         ) VALUES (:user_id, :permission_key, 1, :created_at, :updated_at)'
    );
    $definitions = [
        'admin' => ['name' => 'QA Segurança Admin', 'role' => 'admin', 'permissions' => ['dashboard']],
        'professor' => ['name' => 'QA Segurança Professor', 'role' => 'professor', 'permissions' => ['dashboard', 'courses']],
        'support' => ['name' => 'QA Segurança Suporte', 'role' => 'suporte', 'permissions' => ['dashboard']],
    ];
    foreach ($definitions as $key => $definition) {
        $username = $prefix . $key;
        $userInsert->execute([
            ':name' => $definition['name'],
            ':username' => $username,
            ':email' => $username . '@aneo.test',
            ':password_hash' => $passwordHash,
            ':role' => $definition['role'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $userId = (int) $db->lastInsertId();
        $userIds[$key] = $userId;
        $companyInsert->execute([
            ':user_id' => $userId,
            ':company_id' => $companyId,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        foreach ($definition['permissions'] as $permission) {
            $permissionInsert->execute([
                ':user_id' => $userId,
                ':permission_key' => $permission,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}

$state = [
    'created_at' => $now,
    'company_id' => $companyId,
    'course_id' => $courseId,
    'password' => $password,
    'student_ids' => $studentIds,
    'user_ids' => $userIds,
    'credentials' => $credentials,
];
file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
chmod($statePath, 0600);

echo json_encode([
    'ok' => true,
    'students_created' => count($studentIds),
    'users_created' => count($userIds),
    'company_id' => $companyId,
    'course_id' => $courseId,
    'state_path' => $statePath,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
