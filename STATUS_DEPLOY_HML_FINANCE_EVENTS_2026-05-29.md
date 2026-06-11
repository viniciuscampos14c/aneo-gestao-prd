# Status Deploy HML - Finance Events

Data: 2026-05-29

## Objetivo

Publicar com seguranca a frente de notificacoes financeiras por evento no HML, sem quebrar os fluxos ja validados.

## Escopo publicado

- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/views/email/finance_event_notification.php`
- `migrations/20260529_finance_notification_event_types.sql`

## Ordem executada

1. Conferencia do ambiente remoto:
   - SSH OK
   - PHP CLI OK
   - MySQL CLI OK
2. Backup remoto criado antes da troca em:
   - `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/finance_events_20260529_154843`
3. Schema conferido antes da migration:
   - `finance_notification_logs.notification_type = enum('reminder','due_today')`
4. Migration publicada no servidor.
5. Migration executada no banco HML com sucesso.
6. Schema conferido apos migration:
   - `finance_notification_logs.notification_type = enum('reminder','due_today','invoice_issued','invoice_paid')`
7. Arquivos PHP publicados no HML.
8. Lint remoto executado com sucesso nos 3 arquivos publicados.

## Validacoes objetivas

- `FinanceModel.php` publicado no HML com timestamp novo.
- `FinanceNotificationModel.php` publicado no HML com timestamp novo.
- `finance_event_notification.php` passou a existir no HML.
- `php -l` remoto:
  - sem erro em `FinanceModel.php`
  - sem erro em `FinanceNotificationModel.php`
  - sem erro em `finance_event_notification.php`

## Observacao de seguranca

O codigo foi publicado com blindagem conservadora:

- se a estrutura de logs nao suportasse os tipos novos, o fluxo financeiro nao quebraria;
- falha de envio/log de notificacao nao derruba o fluxo principal.

Mesmo assim, a migration foi aplicada para liberar o comportamento completo dos eventos:

- `invoice_issued`
- `invoice_paid`

## Proximo passo recomendado

Executar validacao funcional dirigida no HML para:

1. gerar boleto em uma fatura de teste;
2. efetuar baixa manual em uma fatura de teste;
3. confirmar no banco/fluxo que os logs aceitam:
   - `invoice_issued`
   - `invoice_paid`

## Rollback

Backup disponivel em:

- `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/finance_events_20260529_154843`
