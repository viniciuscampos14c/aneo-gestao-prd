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
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $cookiePath = rtrim($scriptDir, '/');
    if ($cookiePath === '') {
        $cookiePath = '/';
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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

