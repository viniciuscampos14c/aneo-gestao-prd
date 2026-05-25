<?php
/**
 * Template HTML para confirmacao de rematricula automatica.
 */

$companyName = trim((string) ($companyName ?? 'ANEO'));
$studentName = trim((string) ($studentName ?? 'Aluno'));
$periodStartLabel = trim((string) ($periodStartLabel ?? '-'));
$periodEndLabel = trim((string) ($periodEndLabel ?? '-'));
$confirmedAtLabel = trim((string) ($confirmedAtLabel ?? ''));
$portalUrl = trim((string) ($portalUrl ?? ''));
$logoUrl = trim((string) ($logoUrl ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmacao de rematricula</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
        <tr>
          <td style="background-color:#0f172a;border-radius:12px 12px 0 0;padding:24px 32px;text-align:center;">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($companyName); ?>" height="48" style="height:48px;width:auto;display:inline-block;">
            <?php else: ?>
              <span style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.08em;"><?= htmlspecialchars($companyName); ?></span>
            <?php endif; ?>
          </td>
        </tr>

        <tr>
          <td style="background-color:#10b981;padding:12px 32px;text-align:center;">
            <span style="color:#ffffff;font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">
              Rematricula confirmada
            </span>
          </td>
        </tr>

        <tr>
          <td style="background-color:#ffffff;padding:32px;">
            <p style="margin:0 0 10px;font-size:16px;color:#1e293b;line-height:1.5;">
              Ola, <strong><?= htmlspecialchars($studentName); ?></strong>!
            </p>

            <h1 style="margin:0 0 14px;font-size:24px;line-height:1.25;color:#0f172a;font-weight:800;">
              Sua rematricula foi confirmada com sucesso
            </h1>

            <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">
              O acesso ao portal foi liberado para o novo periodo. A confirmacao tambem ficou registrada para acompanhamento da equipe administrativa.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <table width="100%" cellpadding="0" cellspacing="6" border="0">
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;padding-bottom:2px;" width="40%">Aluno</td>
                      <td style="font-size:14px;color:#1e293b;font-weight:600;"><?= htmlspecialchars($studentName); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;padding-bottom:2px;">Periodo</td>
                      <td style="font-size:14px;color:#1e293b;"><?= htmlspecialchars($periodStartLabel); ?> ate <?= htmlspecialchars($periodEndLabel); ?></td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Confirmado em</td>
                      <td style="font-size:14px;color:#1e293b;"><?= htmlspecialchars($confirmedAtLabel); ?></td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <?php if ($portalUrl !== ''): ?>
              <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
                <tr>
                  <td style="border-radius:10px;background-color:#047857;">
                    <a href="<?= htmlspecialchars($portalUrl); ?>" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;border-radius:10px;">
                      Abrir portal do aluno
                    </a>
                  </td>
                </tr>
              </table>
            <?php endif; ?>

            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
              Esta mensagem foi gerada automaticamente pelo sistema ANEO. Em caso de duvidas, fale com a equipe da escola.
            </p>
          </td>
        </tr>

        <tr>
          <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#94a3b8;"><?= htmlspecialchars($companyName); ?></p>
            <p style="margin:0;font-size:11px;color:#cbd5e1;">Este e-mail foi gerado automaticamente. Por favor, nao responda.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

</body>
</html>
