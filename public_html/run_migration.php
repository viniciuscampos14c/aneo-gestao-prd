<?php
/**
 * Runner de migrations seguro — uso único via browser.
 *
 * Como usar:
 *   1. Enviar este arquivo para a Hostinger (pasta public_html/erphml/).
 *   2. Acessar: https://erp-hml.aneobrasil.com.br/run_migration.php?token=ANEO_MIGRATE_2026
 *   3. Verificar o resultado na tela.
 *   4. Após confirmado, acessar: ?token=ANEO_MIGRATE_2026&delete=1 para remover o script.
 *
 * ATENÇÃO: Remova este arquivo do servidor após a execução.
 */

define('MIGRATION_TOKEN', 'ANEO_MIGRATE_2026');

$token = $_GET['token'] ?? '';
if ($token !== MIGRATION_TOKEN) {
    http_response_code(403);
    exit('Acesso negado. Token invalido.');
}

// Carrega configuracoes de banco a partir do config.php do projeto
$config = require __DIR__ . '/config.php';
$db     = $config['db'];

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Falha ao conectar ao banco: ' . htmlspecialchars($e->getMessage()));
}

// SQL da migration
$migrationSql = <<<SQL
CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    company_id   INT UNSIGNED     NOT NULL,
    user_id      INT UNSIGNED     NOT NULL,
    name         VARCHAR(100)     NOT NULL,
    token_hash   VARCHAR(64)      NOT NULL UNIQUE COMMENT 'SHA-256 hex do token bruto',
    permissions  JSON             NOT NULL             COMMENT '{"students":["get","search"],...}',
    expires_at   DATE             NULL                 COMMENT 'NULL = sem expiracao',
    last_used_at DATETIME         NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_api_tokens_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_tokens_user    FOREIGN KEY (user_id)    REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$results = [];
$hasError = false;

// Auto-delete depois de executar
if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    @unlink(__FILE__);
    echo '<p style="color:green;font-family:monospace">Script removido do servidor com sucesso.</p>';
    exit;
}

// Executa
try {
    $pdo->exec($migrationSql);
    $results[] = ['ok' => true, 'msg' => 'Tabela api_tokens criada (ou ja existia). OK.'];
} catch (PDOException $e) {
    $hasError = true;
    $results[] = ['ok' => false, 'msg' => 'ERRO: ' . $e->getMessage()];
}

// Verifica se a tabela existe agora
try {
    $count = $pdo->query("SELECT COUNT(*) FROM api_tokens")->fetchColumn();
    $results[] = ['ok' => true, 'msg' => "Verificacao: tabela api_tokens acessivel. Registros atuais: {$count}."];
} catch (PDOException $e) {
    $hasError = true;
    $results[] = ['ok' => false, 'msg' => 'Verificacao falhou: ' . $e->getMessage()];
}

$deleteUrl = '?token=' . MIGRATION_TOKEN . '&delete=1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migration Runner — ANEO</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        h2 { color: #38bdf8; }
        .ok  { color: #4ade80; }
        .err { color: #f87171; }
        .box { background: #1e293b; border-radius: 8px; padding: 1rem 1.5rem; margin: 1rem 0; }
        a.btn { display: inline-block; margin-top: 1rem; padding: .5rem 1.2rem; background: #ef4444; color: white; border-radius: 6px; text-decoration: none; font-size: .9rem; }
        a.btn:hover { background: #dc2626; }
    </style>
</head>
<body>
<h2>ANEO — Migration Runner</h2>
<p>Migration: <strong>20260416_api_tokens</strong></p>
<div class="box">
<?php foreach ($results as $r): ?>
    <p class="<?= $r['ok'] ? 'ok' : 'err'; ?>"><?= $r['ok'] ? '✓' : '✗'; ?> <?= htmlspecialchars($r['msg']); ?></p>
<?php endforeach; ?>
</div>

<?php if (!$hasError): ?>
    <p class="ok">Migration aplicada com sucesso!</p>
    <p>Após confirmar, remova este script do servidor:</p>
    <a class="btn" href="<?= $deleteUrl; ?>" onclick="return confirm('Remover o script run_migration.php do servidor?')">
        Remover script agora
    </a>
<?php else: ?>
    <p class="err">Houve erros. Verifique acima e tente novamente ou aplique manualmente via phpMyAdmin.</p>
<?php endif; ?>
</body>
</html>
