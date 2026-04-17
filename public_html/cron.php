<?php

/**
 * Entry point do Cron do ANEO Gestão.
 *
 * Autenticação: token via query string ou header X-Cron-Token.
 *
 * Exemplos de uso:
 *   # Executa todos os jobs habilitados
 *   curl "https://erp-hml.aneobrasil.com.br/cron.php?token=SEU_TOKEN&job=all"
 *
 *   # Executa apenas notificações financeiras
 *   curl "https://erp-hml.aneobrasil.com.br/cron.php?token=SEU_TOKEN&job=finance_billing_notifications"
 *
 * Jobs disponíveis:
 *   all                           — executa todos os jobs habilitados
 *   finance_billing_notifications — envia e-mails de cobrança
 *   boleto_sync                   — sincroniza status de boletos pendentes
 *   signatures_sync               — sincroniza assinaturas D4Sign pendentes
 *
 * Configuração do cron na Hostinger (hPanel → Avançado → Cron Jobs):
 *   0 * * * *   curl -s "https://erp-hml.aneobrasil.com.br/cron.php?token=SEU_TOKEN&job=finance_billing_notifications" > /dev/null 2>&1
 */

if (ob_get_level() === 0) {
    ob_start();
}

require __DIR__ . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------
// Validação do token
// -----------------------------------------------------------------

if (!(bool) config('cron.enabled', true)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Cron desativado no config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$configToken = trim((string) config('cron.secret_token', ''));

$providedToken = trim((string) ($_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));

if ($configToken === '' || !hash_equals($configToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Token invalido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------
// Execução do job
// -----------------------------------------------------------------

$jobKey = trim((string) ($_GET['job'] ?? 'all'));

$runner = new CronRunner();

$startedAt = date('Y-m-d H:i:s');

if ($jobKey === 'all') {
    $results = $runner->runAll();
    echo json_encode([
        'ok'         => true,
        'started_at' => $startedAt,
        'results'    => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$result = $runner->run($jobKey);

http_response_code(($result['ok'] ?? false) ? 200 : 500);
echo json_encode(array_merge(
    ['started_at' => $startedAt],
    $result
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
