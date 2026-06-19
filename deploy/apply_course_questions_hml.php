<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
$migration = $argv[2] ?? '';
if ($root === '' || $migration === '' || !is_file($root . '/core/bootstrap.php') || !is_file($migration)) {
    fwrite(STDERR, "Parametros invalidos.\n");
    exit(1);
}

require $root . '/core/bootstrap.php';

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof PDO) {
    fwrite(STDERR, "Conexao com banco indisponivel.\n");
    exit(1);
}

$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$publicUrl = (string) config('app.public_url', '');
if (!str_contains(strtolower($publicUrl), 'hml') && !str_contains(strtolower($databaseName), 'hml')) {
    fwrite(STDERR, "Protecao de ambiente: destino nao identificado como HML.\n");
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
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    echo $table . '=' . (int) $stmt->fetchColumn() . PHP_EOL;
}

echo 'database=' . $databaseName . PHP_EOL;
