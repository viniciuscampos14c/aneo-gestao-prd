<?php
/**
 * Template HTML para e-mails de notificação de cobrança.
 *
 * Variáveis esperadas:
 *   string $invoiceNumber    — número da fatura
 *   string $studentName      — nome do aluno
 *   string $companyName      — nome da empresa/instituição
 *   string $dueDateLabel     — vencimento formatado (dd/mm/yyyy)
 *   string $outstanding      — valor em aberto formatado
 *   string $notificationType — 'due_today' ou 'reminder'
 *   string $recipientType    — 'student' ou 'admin'
 *   string $logoUrl          — URL absoluta da logo
 *   string $accentColor      — cor principal (#hex)
 */

$isDueToday  = ($notificationType ?? '') === 'due_today';
$isStudent   = ($recipientType   ?? '') === 'student';
$accent      = $accentColor ?? '#0ea5e9';
$accentDark  = '#0284c7';
$logoUrl     = $logoUrl ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($isDueToday ? 'Aviso de Vencimento' : 'Lembrete de Vencimento'); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

        <!-- Cabeçalho -->
        <tr>
          <td style="background-color:#0f172a;border-radius:12px 12px 0 0;padding:24px 32px;text-align:center;">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($companyName ?? 'ANEO'); ?>" height="48" style="height:48px;width:auto;display:inline-block;">
            <?php else: ?>
              <span style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.08em;"><?= htmlspecialchars($companyName ?? 'ANEO'); ?></span>
            <?php endif; ?>
          </td>
        </tr>

        <!-- Faixa de status -->
        <tr>
          <td style="background-color:<?= $isDueToday ? '#dc2626' : $accent; ?>;padding:12px 32px;text-align:center;">
            <span style="color:#ffffff;font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">
              <?= $isDueToday ? '⚠ Fatura vence hoje' : '🔔 Lembrete de vencimento'; ?>
            </span>
          </td>
        </tr>

        <!-- Corpo principal -->
        <tr>
          <td style="background-color:#ffffff;padding:32px;">

            <!-- Saudação -->
            <p style="margin:0 0 20px;font-size:16px;color:#1e293b;line-height:1.5;">
              <?php if ($isStudent): ?>
                Olá, <strong><?= htmlspecialchars($studentName ?? ''); ?></strong>!
              <?php else: ?>
                <strong>Alerta financeiro — acompanhamento interno.</strong>
              <?php endif; ?>
            </p>

            <!-- Mensagem principal -->
            <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">
              <?php if ($isStudent): ?>
                <?php if ($isDueToday): ?>
                  Sua fatura com a <strong><?= htmlspecialchars($companyName ?? ''); ?></strong> vence <strong>hoje</strong>. Regularize o pagamento para evitar juros ou bloqueio de acesso.
                <?php else: ?>
                  Sua fatura com a <strong><?= htmlspecialchars($companyName ?? ''); ?></strong> vence em breve. Fique atento para não perder o prazo.
                <?php endif; ?>
              <?php else: ?>
                <?php if ($isDueToday): ?>
                  A fatura abaixo vence <strong>hoje</strong> e ainda não foi liquidada.
                <?php else: ?>
                  A fatura abaixo vence em breve e ainda está em aberto.
                <?php endif; ?>
              <?php endif; ?>
            </p>

            <!-- Card de detalhes da fatura -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <table width="100%" cellpadding="0" cellspacing="6" border="0">
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;padding-bottom:2px;" width="40%">Fatura</td>
                      <td style="font-size:14px;color:#1e293b;font-weight:600;"><?= htmlspecialchars($invoiceNumber ?? ''); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;padding-bottom:2px;">Aluno</td>
                      <td style="font-size:14px;color:#1e293b;"><?= htmlspecialchars($studentName ?? ''); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;padding-bottom:2px;">Vencimento</td>
                      <td style="font-size:14px;color:<?= $isDueToday ? '#dc2626' : '#1e293b'; ?>;font-weight:<?= $isDueToday ? '700' : '400'; ?>;">
                        <?= htmlspecialchars($dueDateLabel ?? ''); ?>
                        <?= $isDueToday ? ' &nbsp;<span style="background:#fef2f2;color:#dc2626;font-size:11px;padding:1px 6px;border-radius:4px;font-weight:700;">HOJE</span>' : ''; ?>
                      </td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Valor em aberto</td>
                      <td style="font-size:18px;color:#0f172a;font-weight:800;"><?= htmlspecialchars($outstanding ?? ''); ?></td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- Mensagem de rodapé do corpo -->
            <?php if ($isStudent): ?>
              <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">
                Em caso de dúvidas ou se já efetuou o pagamento, entre em contato com a nossa equipe.
              </p>
              <p style="margin:0;font-size:13px;color:#94a3b8;">
                Caso já tenha efetuado o pagamento, desconsidere esta mensagem.
              </p>
            <?php else: ?>
              <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
                Mensagem gerada automaticamente pelo sistema financeiro ANEO para acompanhamento interno.
              </p>
            <?php endif; ?>

          </td>
        </tr>

        <!-- Rodapé -->
        <tr>
          <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#94a3b8;"><?= htmlspecialchars($companyName ?? 'ANEO'); ?></p>
            <p style="margin:0;font-size:11px;color:#cbd5e1;">Este e-mail foi gerado automaticamente. Por favor, não responda.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
