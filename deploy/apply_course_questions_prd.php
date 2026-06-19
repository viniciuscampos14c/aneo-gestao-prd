<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
$migration = $argv[2] ?? '';
$confirmation = $argv[3] ?? '';
if (
    $root === ''
    || $migration === ''
    || $confirmation !== 'APPLY_COURSE_QUESTIONS_PRD'
    || !is_file($root . '/core/bootstrap.php')
    || !is_file($migration)
) {
    fwrite(STDERR, "Parâmetros inválidos ou confirmação ausente.\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require $root . '/core/bootstrap.php';

$db = db();
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$publicUrl = strtolower((string) config('app.public_url', ''));
if (str_contains($publicUrl, 'hml') || !str_contains($databaseName, 'prd')) {
    fwrite(STDERR, "Proteção de ambiente: destino não identificado como produção.\n");
    exit(1);
}

$sql = trim((string) file_get_contents($migration));
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $db->exec($statement);
    }
}

foreach (['course_questions', 'course_question_messages'] as $table) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $stmt->execute([':table' => $table]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Tabela não criada: ' . $table);
    }
}

echo 'database=' . $databaseName . PHP_EOL;
echo 'course_questions=1' . PHP_EOL;
echo 'course_question_messages=1' . PHP_EOL;
