<?php

if (ob_get_level() === 0) {
    ob_start();
}

require __DIR__ . '/core/bootstrap.php';

$router = new Router();
$supportDesk = new SupportDeskController();

$router->get('', function (): void {
    header('Location: support.php?route=support/login');
    exit;
});

$router->get('support', fn () => $supportDesk->index());
$router->get('support/login', fn () => $supportDesk->showLogin());
$router->post('support/login', fn () => $supportDesk->login());
$router->get('support/logout', fn () => $supportDesk->logout());
$router->post('support/comment', fn () => $supportDesk->addComment());
$router->post('support/status', fn () => $supportDesk->updateStatus());

$route = trim((string) ($_GET['route'] ?? 'support/login'), '/');
if ($route === '') {
    $route = 'support/login';
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $route);
