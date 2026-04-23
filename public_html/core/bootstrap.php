<?php

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}


$config = require __DIR__ . '/../config.php';

// Sobrescreve com credenciais/ajustes locais se existir (nunca versionado nem sincronizado via deploy).
$_localCfg = __DIR__ . '/../config.local.php';
if (file_exists($_localCfg)) {
    $config = array_replace_recursive($config, require $_localCfg);
}
unset($_localCfg);

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

