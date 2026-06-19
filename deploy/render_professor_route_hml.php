<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
$target = $argv[2] ?? '';
if ($root === '' || !in_array($target, ['dashboard', 'questions', 'zoom', 'zoom-create'], true)) {
    fwrite(STDERR, "Parametros invalidos.\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'on';
require $root . '/core/bootstrap.php';

$db = $GLOBALS['db'];
$professor = $db->query(
    "SELECT u.*, uc.company_id
     FROM users u
     INNER JOIN user_companies uc ON uc.user_id = u.id
     WHERE u.role = 'professor' AND u.is_active = 1
     ORDER BY u.id
     LIMIT 1"
)->fetch();
if (!$professor) {
    fwrite(STDERR, "Professor HML nao encontrado.\n");
    exit(1);
}

$companyStmt = $db->prepare('SELECT * FROM companies WHERE id = :id');
$companyStmt->execute([':id' => (int) $professor['company_id']]);
$company = $companyStmt->fetch();

$_SESSION['user'] = [
    'id' => (int) $professor['id'],
    'name' => (string) $professor['name'],
    'email' => (string) $professor['email'],
    'username' => (string) $professor['username'],
    'role' => 'professor',
    'permission_keys' => ['dashboard', 'courses'],
];
set_current_company($company ?: ['id' => (int) $professor['company_id']]);

if ($target === 'dashboard') {
    $_GET = ['route' => 'dashboard'];
    (new DashboardController())->index();
}

if ($target === 'questions') {
    $_GET = ['route' => 'courses/questions'];
    (new CourseQuestionController())->professorIndex();
}

if ($target === 'zoom-create') {
    $_GET = ['route' => 'courses/live-sessions/create'];
    (new CourseLiveSessionController())->create();
}

$_GET = ['route' => 'courses/live-sessions'];
(new CourseLiveSessionController())->index();
