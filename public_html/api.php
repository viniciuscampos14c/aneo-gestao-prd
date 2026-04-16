<?php

/**
 * Entry point da API REST do ANEO Gestão.
 *
 * Autenticação: Bearer Token via header Authorization.
 * Exemplo:
 *   curl -H "Authorization: Bearer <token>" "https://erp-hml.aneobrasil.com.br/api.php?r=students"
 *
 * Recursos disponíveis: students, leads, invoices, courses, users, tickets
 * Documentação completa: index.php?route=api-management/manual
 */

if (ob_get_level() === 0) {
    ob_start();
}

require __DIR__ . '/core/bootstrap.php';

// Headers CORS — permite chamadas de qualquer origem
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Responde preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 1. Resolver token — aborta com 401 se inválido
$token = ApiAuth::resolve();

// 2. Injetar company_id do token como company ativa para os models
//    (os models usam current_company_id() via $_SESSION['company'])
$_SESSION['company'] = ['id' => (int) $token['company_id']];

// 3. Roteamento por recurso + método HTTP
$resource = strtolower(trim((string) ($_GET['r'] ?? '')));
$id       = isset($_GET['id']) && $_GET['id'] !== '' ? (int) $_GET['id'] : null;
$method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($resource === '') {
    ApiAuth::abort(400, 'Informe o recurso via parametro "r". Ex: api.php?r=students');
}

$allowed = array_keys(ApiTokenModel::RESOURCES);
if (!in_array($resource, $allowed, true)) {
    ApiAuth::abort(404, "Recurso desconhecido: {$resource}. Disponiveis: " . implode(', ', $allowed));
}

$api = new ApiEndpointController($token);

try {
    match (true) {
        // students
        $resource === 'students' && $method === 'GET'    && $id === null => $api->listStudents(),
        $resource === 'students' && $method === 'GET'    && $id !== null => $api->getStudent($id),
        $resource === 'students' && $method === 'POST'                   => $api->createStudent(),
        $resource === 'students' && $method === 'PUT'    && $id !== null => $api->updateStudent($id),
        $resource === 'students' && $method === 'DELETE' && $id !== null => $api->deleteStudent($id),

        // leads
        $resource === 'leads' && $method === 'GET'    && $id === null => $api->listLeads(),
        $resource === 'leads' && $method === 'GET'    && $id !== null => $api->getLead($id),
        $resource === 'leads' && $method === 'POST'                   => $api->createLead(),
        $resource === 'leads' && $method === 'PUT'    && $id !== null => $api->updateLead($id),
        $resource === 'leads' && $method === 'DELETE' && $id !== null => $api->deleteLead($id),

        // invoices
        $resource === 'invoices' && $method === 'GET'    && $id === null => $api->listInvoices(),
        $resource === 'invoices' && $method === 'GET'    && $id !== null => $api->getInvoice($id),
        $resource === 'invoices' && $method === 'POST'                   => $api->createInvoice(),
        $resource === 'invoices' && $method === 'DELETE' && $id !== null => $api->deleteInvoice($id),

        // courses
        $resource === 'courses' && $method === 'GET' && $id === null => $api->listCourses(),
        $resource === 'courses' && $method === 'GET' && $id !== null => $api->getCourse($id),

        // users
        $resource === 'users' && $method === 'GET' && $id === null => $api->listUsers(),
        $resource === 'users' && $method === 'GET' && $id !== null => $api->getUser($id),

        // tickets
        $resource === 'tickets' && $method === 'GET'  && $id === null => $api->listTickets(),
        $resource === 'tickets' && $method === 'GET'  && $id !== null => $api->getTicket($id),
        $resource === 'tickets' && $method === 'POST'                  => $api->createTicket(),

        default => ApiAuth::abort(405, "Metodo {$method} nao suportado para o recurso {$resource}."),
    };
} catch (Throwable $e) {
    error_log('[API_ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ApiAuth::abort(500, 'Erro interno no servidor. Contate o suporte.');
}
