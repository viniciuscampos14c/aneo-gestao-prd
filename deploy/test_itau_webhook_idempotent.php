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
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['HTTPS'] = 'on';
require $root . '/core/bootstrap.php';

$db = db();
$stmt = $db->query(
    "SELECT
        bs.external_id,
        bs.nosso_numero,
        bs.paid_at,
        i.id AS invoice_id,
        i.invoice_number,
        i.company_id,
        i.paid_amount,
        ci.settings_json
     FROM bank_slips bs
     INNER JOIN invoices i ON i.id = bs.invoice_id
     INNER JOIN company_integrations ci
        ON ci.company_id = i.company_id
       AND ci.integration_key = 'itau'
       AND ci.is_enabled = 1
     WHERE bs.provider = 'itau'
       AND bs.status IN ('paid', 'received')
     ORDER BY bs.id DESC
     LIMIT 1"
);
$boleto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$boleto) {
    throw new RuntimeException('Nenhum boleto Itaú pago com integração ativa foi encontrado.');
}

$settings = json_decode((string) $boleto['settings_json'], true);
$token = trim((string) ($settings['webhook_token'] ?? ''));
if ($token === '') {
    throw new RuntimeException('Token do webhook indisponível.');
}

$count = $db->prepare('SELECT COUNT(*) FROM payment_items WHERE invoice_id = :invoice_id');
$count->execute([':invoice_id' => (int) $boleto['invoice_id']]);
$before = (int) $count->fetchColumn();

$payload = [
    'id_boleto' => (string) $boleto['external_id'],
    'numero_nosso_numero' => (string) ($boleto['nosso_numero'] ?? ''),
    'situacao_geral_boleto' => 'paga',
    'data_pagamento' => substr((string) ($boleto['paid_at'] ?? date('Y-m-d')), 0, 10),
    'valor_pago' => (float) $boleto['paid_amount'],
];

$url = rtrim((string) config('app.public_url', ''), '/')
    . '/index.php?route=finance/webhook/itau&token=' . rawurlencode($token);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = (string) curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$count->execute([':invoice_id' => (int) $boleto['invoice_id']]);
$after = (int) $count->fetchColumn();

echo json_encode([
    'http_code' => $httpCode,
    'curl_ok' => $curlError === '',
    'invoice_number' => (string) $boleto['invoice_number'],
    'response' => json_decode($body, true),
    'allocations_before' => $before,
    'allocations_after' => $after,
    'duplicate_payment_created' => $before !== $after,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

if ($httpCode !== 200 || $curlError !== '' || $before !== $after) {
    exit(1);
}
