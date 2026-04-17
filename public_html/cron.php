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

// -----------------------------------------------------------------
// Suporte a CLI (Hostinger Cron Jobs via PHP)
// Exemplo: php cron.php token=SEU_TOKEN job=all
// -----------------------------------------------------------------
$isCli = PHP_SAPI === 'cli';

if ($isCli) {
    // Converte argumentos "chave=valor" do CLI para $_GET
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (str_contains($arg, '=')) {
            [$k, $v] = explode('=', $arg, 2);
            $_GET[trim($k)] = trim($v);
        }
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
}

// -----------------------------------------------------------------
// Validação do token
// -----------------------------------------------------------------

if (!(bool) config('cron.enabled', true)) {
    if (!$isCli) { http_response_code(503); }
    echo $isCli ? "[ERRO] Cron desativado no config.php.\n"
                : json_encode(['ok' => false, 'message' => 'Cron desativado no config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$configToken   = trim((string) config('cron.secret_token', ''));
$providedToken = trim((string) ($_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));

if ($configToken === '' || !hash_equals($configToken, $providedToken)) {
    if (!$isCli) { http_response_code(401); }
    echo $isCli ? "[ERRO] Token invalido.\n"
                : json_encode(['ok' => false, 'message' => 'Token invalido.'], JSON_UNESCAPED_UNICODE);
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
    if ($isCli) {
        foreach ($results as $key => $res) {
            $status = ($res['ok'] ?? false) ? 'OK' : 'ERRO';
            echo "[{$startedAt}] {$key}: [{$status}] " . ($res['message'] ?? '') . "\n";
        }
    } else {
        echo json_encode([
            'ok'         => true,
            'started_at' => $startedAt,
            'results'    => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

$result = $runner->run($jobKey);

if ($isCli) {
    $status = ($result['ok'] ?? false) ? 'OK' : 'ERRO';
    echo "[{$startedAt}] {$jobKey}: [{$status}] " . ($result['message'] ?? '') . "\n";
} else {
    http_response_code(($result['ok'] ?? false) ? 200 : 500);
    echo json_encode(array_merge(
        ['started_at' => $startedAt],
        $result
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
exit;
