<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
$backupDir = $argv[2] ?? '';
if ($root === '' || $backupDir === '' || !is_file($root . '/config.php')) {
    fwrite(STDERR, "Parâmetros de backup inválidos.\n");
    exit(1);
}

$config = require $root . '/config.php';
if (is_file($root . '/config.local.php')) {
    $config = array_replace_recursive($config, require $root . '/config.local.php');
}

$db = $config['db'] ?? [];
foreach (['name', 'user', 'pass'] as $field) {
    if (!array_key_exists($field, $db)) {
        throw new RuntimeException('Configuração de banco incompleta.');
    }
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Não foi possível criar a pasta de backup.');
}

$backupPath = $backupDir . '/database.sql';
$command = [
    '/usr/bin/mysqldump',
    '--single-transaction',
    '--routines',
    '--triggers',
    '-u',
    (string) $db['user'],
    '--password=' . (string) $db['pass'],
];

$host = strtolower(trim((string) ($db['host'] ?? 'localhost')));
if (!in_array($host, ['', 'localhost'], true)) {
    $command[] = '-h';
    $command[] = (string) $db['host'];
    $command[] = '-P';
    $command[] = (string) ($db['port'] ?? 3306);
}
$command[] = (string) $db['name'];

$process = proc_open($command, [
    0 => ['pipe', 'r'],
    1 => ['file', $backupPath, 'w'],
    2 => ['pipe', 'w'],
], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Não foi possível iniciar o mysqldump.');
}

fclose($pipes[0]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0 || !is_file($backupPath) || filesize($backupPath) < 1000) {
    @unlink($backupPath);
    throw new RuntimeException('Backup do banco falhou: ' . trim((string) $error));
}

chmod($backupPath, 0600);
echo json_encode([
    'ok' => true,
    'path' => $backupPath,
    'size' => filesize($backupPath),
    'sha256' => hash_file('sha256', $backupPath),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
