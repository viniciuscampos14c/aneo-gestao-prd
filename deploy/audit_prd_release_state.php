<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
if ($root === '' || !is_file($root . '/core/bootstrap.php')) {
    fwrite(STDERR, "Raiz da aplicação inválida.\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'on';
require $root . '/core/bootstrap.php';

$db = db();
$tableExists = static function (string $table) use ($db): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$result = [
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'course_questions_exists' => $tableExists('course_questions'),
    'course_question_messages_exists' => $tableExists('course_question_messages'),
    'professors_active' => (int) $db->query(
        "SELECT COUNT(*) FROM users WHERE role = 'professor' AND is_active = 1"
    )->fetchColumn(),
    'students_with_portal' => (int) $db->query(
        'SELECT COUNT(*) FROM student_portal_accounts WHERE is_active = 1'
    )->fetchColumn(),
    'itau_integrations_active' => (int) $db->query(
        "SELECT COUNT(*) FROM company_integrations
         WHERE integration_key = 'itau' AND is_enabled = 1"
    )->fetchColumn(),
    'itau_payment_methods_active' => (int) $db->query(
        "SELECT COUNT(*) FROM payment_methods
         WHERE provider_key = 'itau' AND is_active = 1"
    )->fetchColumn(),
    'qa_users' => (int) $db->query(
        "SELECT COUNT(*) FROM users WHERE username LIKE 'qa_release_%'"
    )->fetchColumn(),
    'qa_accounts' => (int) $db->query(
        "SELECT COUNT(*) FROM student_portal_accounts WHERE login = 'qa_release_student'"
    )->fetchColumn(),
    'qa_questions' => $tableExists('course_questions')
        ? (int) $db->query(
            "SELECT COUNT(*) FROM course_questions WHERE subject LIKE 'QA-PRD-RELEASE-%'"
        )->fetchColumn()
        : 0,
    'jobs' => $db->query(
        "SELECT job_key, enabled, last_status, last_run_at
         FROM cron_jobs
         WHERE job_key IN ('boleto_issue_due', 'boleto_sync', 'finance_billing_notifications')
         ORDER BY job_key"
    )->fetchAll(PDO::FETCH_ASSOC),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
