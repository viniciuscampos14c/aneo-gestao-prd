<?php
/**
 * Template HTML para eventos financeiros.
 *
 * Variaveis esperadas:
 *   string $invoiceNumber
 *   string $studentName
 *   string $companyName
 *   string $dueDateLabel
 *   string $amountLabel
 *   string $paidAmountLabel
 *   string $paidAtLabel
 *   string $bankSlipUrl
 *   string $bankSlipDigitableLine
 *   string $bankSlipBarcode
 *   string $bankSlipPixCopyPaste
 *   string $notificationType
 *   string $recipientType
 *   string $logoUrl
 */

$isIssued = ($notificationType ?? '') === 'invoice_issued';
$isStudent = ($recipientType ?? '') === 'student';
$logoUrl = $logoUrl ?? '';
$headline = $isIssued ? 'Boleto emitido' : 'Pagamento confirmado';
$bannerColor = $isIssued ? '#2563eb' : '#16a34a';
$bankSlipUrl = trim((string) ($bankSlipUrl ?? ''));
$bankSlipDigitableLine = trim((string) ($bankSlipDigitableLine ?? ''));
$bankSlipBarcode = trim((string) ($bankSlipBarcode ?? ''));
$bankSlipPixCopyPaste = trim((string) ($bankSlipPixCopyPaste ?? ''));
$hasPaymentData = $bankSlipUrl !== '' || $bankSlipDigitableLine !== '' || $bankSlipBarcode !== '' || $bankSlipPixCopyPaste !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($headline); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
        <tr>
          <td style="background-color:#0f172a;border-radius:12px 12px 0 0;padding:24px 32px;text-align:center;">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($companyName ?? 'ANEO'); ?>" height="48" style="height:48px;width:auto;display:inline-block;">
            <?php else: ?>
              <span style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.08em;"><?= htmlspecialchars($companyName ?? 'ANEO'); ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td style="background-color:<?= $bannerColor; ?>;padding:12px 32px;text-align:center;">
            <span style="color:#ffffff;font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">
              <?= htmlspecialchars($headline); ?>
            </span>
          </td>
        </tr>
        <tr>
          <td style="background-color:#ffffff;padding:32px;">
            <p style="margin:0 0 20px;font-size:16px;color:#1e293b;line-height:1.5;">
              <?php if ($isStudent): ?>
                Ola, <strong><?= htmlspecialchars($studentName ?? 'Aluno'); ?></strong>!
              <?php else: ?>
                <strong>Copia automatica para acompanhamento financeiro.</strong>
              <?php endif; ?>
            </p>

            <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">
              <?php if ($isIssued): ?>
                <?php if ($isStudent): ?>
                  Seu boleto foi emitido com sucesso. Abaixo estao os dados da fatura para acompanhamento.
                <?php else: ?>
                  Um boleto foi emitido para o aluno abaixo e esta copia foi enviada para controle interno da ANEO.
                <?php endif; ?>
              <?php else: ?>
                <?php if ($isStudent): ?>
                  Confirmamos o pagamento da sua fatura. Seguem os dados para consulta.
                <?php else: ?>
                  O pagamento da fatura abaixo foi confirmado e esta copia foi enviada para controle interno da ANEO.
                <?php endif; ?>
              <?php endif; ?>
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <table width="100%" cellpadding="0" cellspacing="6" border="0">
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;" width="40%">Fatura</td>
                      <td style="font-size:14px;color:#1e293b;font-weight:600;"><?= htmlspecialchars($invoiceNumber ?? ''); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Aluno</td>
                      <td style="font-size:14px;color:#1e293b;"><?= htmlspecialchars($studentName ?? ''); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Vencimento</td>
                      <td style="font-size:14px;color:#1e293b;"><?= htmlspecialchars($dueDateLabel ?? '-'); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Valor da fatura</td>
                      <td style="font-size:16px;color:#0f172a;font-weight:700;"><?= htmlspecialchars($amountLabel ?? ''); ?></td>
                    </tr>
                    <?php if ($isIssued): ?>
                      <?php if ($bankSlipUrl !== ''): ?>
                        <tr>
                          <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Link do boleto</td>
                          <td style="font-size:14px;color:#1d4ed8;">
                            <a href="<?= htmlspecialchars($bankSlipUrl); ?>" style="color:#1d4ed8;text-decoration:none;">Abrir boleto</a>
                          </td>
                        </tr>
                      <?php endif; ?>
                      <?php if ($bankSlipDigitableLine !== ''): ?>
                        <tr>
                          <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;vertical-align:top;">Linha digitavel</td>
                          <td style="font-size:13px;color:#0f172a;font-family:Consolas,Monaco,monospace;word-break:break-all;line-height:1.5;">
                            <?= htmlspecialchars($bankSlipDigitableLine); ?>
                          </td>
                        </tr>
                      <?php endif; ?>
                      <?php if ($bankSlipBarcode !== ''): ?>
                        <tr>
                          <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;vertical-align:top;">Codigo de barras</td>
                          <td style="font-size:13px;color:#0f172a;font-family:Consolas,Monaco,monospace;word-break:break-all;line-height:1.5;">
                            <?= htmlspecialchars($bankSlipBarcode); ?>
                          </td>
                        </tr>
                      <?php endif; ?>
                      <?php if ($bankSlipPixCopyPaste !== ''): ?>
                        <tr>
                          <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;vertical-align:top;">PIX copia e cola</td>
                          <td style="font-size:12px;color:#0f172a;font-family:Consolas,Monaco,monospace;word-break:break-all;line-height:1.5;">
                            <?= htmlspecialchars($bankSlipPixCopyPaste); ?>
                          </td>
                        </tr>
                      <?php endif; ?>
                      <?php if (!$hasPaymentData): ?>
                        <tr>
                          <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Boleto</td>
                          <td style="font-size:14px;color:#475569;">Disponivel no portal financeiro do aluno.</td>
                        </tr>
                      <?php endif; ?>
                    <?php else: ?>
                      <tr>
                        <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Valor pago</td>
                        <td style="font-size:16px;color:#166534;font-weight:700;"><?= htmlspecialchars($paidAmountLabel ?? ''); ?></td>
                      </tr>
                      <tr>
                        <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Data do pagamento</td>
                        <td style="font-size:14px;color:#1e293b;"><?= htmlspecialchars($paidAtLabel ?? '-'); ?></td>
                      </tr>
                    <?php endif; ?>
                  </table>
                </td>
              </tr>
            </table>

            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
              Este e-mail foi gerado automaticamente pelo financeiro da ANEO.
            </p>
          </td>
        </tr>
        <tr>
          <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#94a3b8;"><?= htmlspecialchars($companyName ?? 'ANEO'); ?></p>
            <p style="margin:0;font-size:11px;color:#cbd5e1;">Mensagem automatica de acompanhamento financeiro.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
