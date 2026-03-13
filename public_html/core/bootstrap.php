<?php

$config = require __DIR__ . '/../config.php';

date_default_timezone_set($config['app']['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__);
    $paths = [
        $base . '/core/' . $class . '.php',
        $base . '/controllers/' . $class . '.php',
        $base . '/models/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

$GLOBALS['config'] = $config;
$GLOBALS['db'] = Database::connection($config['db']);
