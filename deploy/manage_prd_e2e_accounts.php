<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
$action = $argv[2] ?? '';
if ($root === '' || !in_array($action, ['setup', 'cleanup'], true)) {
    fwrite(STDERR, "Parâmetros inválidos.\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['HTTPS'] = 'on';
require $root . '/core/bootstrap.php';

$db = db();
$usernames = ['qa_release_admin', 'qa_release_professor'];
$studentLogin = 'qa_release_student';
$marker = 'QA-PRD-RELEASE-%';
$statePath = '/home/u674156040/secure/prd_e2e_release_state.json';

$cleanup = static function () use ($db, $usernames, $studentLogin, $marker, $statePath): void {
    $stmt = $db->prepare(
        "DELETE spn FROM student_portal_notifications spn
         WHERE spn.notification_type = 'course_question_answered'
           AND spn.message LIKE :marker"
    );
    $stmt->execute([':marker' => $marker]);

    $stmt = $db->prepare('DELETE FROM course_questions WHERE subject LIKE :marker');
    $stmt->execute([':marker' => $marker]);

    $stmt = $db->prepare('DELETE FROM student_portal_accounts WHERE login = :login');
    $stmt->execute([':login' => $studentLogin]);

    if (is_file($statePath)) {
        $state = json_decode((string) file_get_contents($statePath), true);
        $enrollmentId = (int) ($state['created_enrollment_id'] ?? 0);
        if ($enrollmentId > 0) {
            $stmt = $db->prepare('DELETE FROM enrollments WHERE id = :id');
            $stmt->execute([':id' => $enrollmentId]);
        }
        $reenrollmentId = (int) ($state['created_reenrollment_id'] ?? 0);
        if ($reenrollmentId > 0) {
            $stmt = $db->prepare('DELETE FROM reenrollments WHERE id = :id');
            $stmt->execute([':id' => $reenrollmentId]);
        }
        @unlink($statePath);
    }

    $placeholders = implode(',', array_fill(0, count($usernames), '?'));
    $stmt = $db->prepare("DELETE FROM users WHERE username IN ($placeholders)");
    $stmt->execute($usernames);
};

$cleanup();
if ($action === 'cleanup') {
    echo json_encode(['ok' => true, 'cleanup' => true], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

$candidate = $db->query(
    "SELECT
        s.id AS student_id,
        s.company_id,
        s.full_name,
        s.email_primary,
        s.phone,
        c.id AS course_id,
        c.name AS course_name
     FROM students s
     INNER JOIN enrollments e
        ON e.student_id = s.id
       AND e.status IN ('active', 'completed')
     INNER JOIN courses c
        ON c.id = e.course_id
       AND c.company_id = s.company_id
     LEFT JOIN student_portal_accounts spa ON spa.student_id = s.id
     WHERE s.is_active = 1
       AND spa.id IS NULL
       AND NOT EXISTS (
           SELECT 1
           FROM reenrollments r
           WHERE r.student_id = s.id
             AND r.confirmed_at IS NULL
             AND r.period_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       )
     ORDER BY (s.full_name LIKE '%Aneo Bahia%') DESC, e.id DESC
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$password = 'QaRelease2026!';
$createdEnrollmentId = 0;
if (!$candidate) {
    $candidate = $db->query(
        "SELECT
            s.id AS student_id,
            s.company_id,
            s.full_name,
            s.email_primary,
            s.phone,
            c.id AS course_id,
            c.name AS course_name
         FROM students s
         INNER JOIN courses c ON c.company_id = s.company_id
         LEFT JOIN student_portal_accounts spa ON spa.student_id = s.id
         WHERE s.is_active = 1
           AND spa.id IS NULL
           AND c.status = 'published'
           AND NOT EXISTS (
               SELECT 1
               FROM reenrollments r
               WHERE r.student_id = s.id
                 AND r.confirmed_at IS NULL
                 AND r.period_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           )
         ORDER BY (s.full_name LIKE '%Aneo Bahia%') DESC, s.id DESC, c.id
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
}
if (!$candidate) {
    throw new RuntimeException('Nenhum aluno e curso publicado da mesma empresa foram encontrados.');
}

$companyId = (int) $candidate['company_id'];
$users = new UserModel();
$adminId = $users->createUser([
    'name' => 'QA Release Admin',
    'username' => $usernames[0],
    'email' => 'qa.release.admin@aneo.test',
    'password' => $password,
    'role' => 'admin',
    'is_active' => 1,
], ['dashboard'], [$companyId]);

$professorId = $users->createUser([
    'name' => 'QA Release Professor',
    'username' => $usernames[1],
    'email' => 'qa.release.professor@aneo.test',
    'password' => $password,
    'role' => 'professor',
    'is_active' => 1,
], ['dashboard', 'courses'], [$companyId]);

if (!(bool) $db->query(
    'SELECT COUNT(*) FROM enrollments
     WHERE student_id = ' . (int) $candidate['student_id'] . '
       AND course_id = ' . (int) $candidate['course_id'] . "
       AND status IN ('active', 'completed')"
)->fetchColumn()) {
    $courses = new CourseModel();
    $courses->useCompany($companyId);
    $courses->createEnrollment([
        'student_id' => (int) $candidate['student_id'],
        'course_id' => (int) $candidate['course_id'],
        'status' => 'active',
        'started_at' => date('Y-m-d'),
    ], $adminId);
    $createdEnrollmentId = (int) $db->lastInsertId();
}

$students = new StudentModel();
$students->useCompany($companyId);
$students->upsertPortalAccount((int) $candidate['student_id'], $studentLogin, $password, 1);

$createdReenrollmentId = 0;
$reenrollment = new ReenrollmentModel();
if ($reenrollment->isDue((int) $candidate['student_id'])) {
    $createdReenrollmentId = $reenrollment->confirm(
        (int) $candidate['student_id'],
        $companyId,
        '127.0.0.1'
    );
}

file_put_contents($statePath, json_encode([
    'created_enrollment_id' => $createdEnrollmentId,
    'created_reenrollment_id' => $createdReenrollmentId,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
chmod($statePath, 0600);

echo json_encode([
    'ok' => true,
    'password' => $password,
    'admin' => ['id' => $adminId, 'username' => $usernames[0]],
    'professor' => ['id' => $professorId, 'username' => $usernames[1]],
    'student' => [
        'id' => (int) $candidate['student_id'],
        'login' => $studentLogin,
        'name' => (string) $candidate['full_name'],
        'course_id' => (int) $candidate['course_id'],
        'course_name' => (string) $candidate['course_name'],
    ],
    'company_id' => $companyId,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
