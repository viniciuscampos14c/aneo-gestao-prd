# Status de Continuidade

Data: 2026-05-29

## Objetivo

Deixar o projeto organizado para a proxima rodada sem alterar comportamento funcional em producao ou HML.

## O que foi colocado em ordem

1. Auditoria do projeto consolidada em:
   - `RELATORIO_AUDITORIA_PROJETO_2026-05-29.md`
2. Plano de acao das pendencias consolidado em:
   - `PLANO_ACAO_PENDENCIAS_2026-05-29.md`
3. Scripts do `package.json` ampliados para suites ja existentes:
   - `test:e2e:hml:mobile`
   - `test:e2e:hml:mobile:list`
   - `test:e2e:hml:jully`
   - `test:e2e:hml:jully:list`
4. `README.md` atualizado para refletir esses comandos.
5. Validacao de sintaxe PHP executada com sucesso em:
   - `public_html/models/FinanceModel.php`
   - `public_html/models/FinanceNotificationModel.php`
   - `public_html/views/email/finance_event_notification.php`
6. Blindagem conservadora aplicada no fluxo de notificacoes financeiras:
   - eventos novos so disparam quando a estrutura de logs suporta `invoice_issued` e `invoice_paid`
   - falhas de envio/log deixam de quebrar o fluxo financeiro principal
   - migration criada: `migrations/20260529_finance_notification_event_types.sql`

## O que continua aberto

### Frente financeira local

- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/views/email/finance_event_notification.php`

Situacao:

- mudancas locais presentes;
- nao publicadas integralmente no HML;
- exigem validacao funcional antes de deploy.

### Frente QA HML

- `tests/e2e/hml-mobile-pwa.spec.ts`
- `tests/e2e/hml-jully.spec.ts`
- `playwright.hml.jully.config.ts`

Situacao:

- suites reconhecidas pelo Playwright;
- execucao funcional ainda nao registrada nesta rodada.

### Arquivos auxiliares de preview

- `PREVIEW_MENU_CADASTRO_HOVER.html`
- `mobile/aneo-pwa/PREVIEW_PWA_SERENO_VISUAL_ONLY.html`

Situacao:

- mantidos como estao;
- nao foram movidos para evitar quebra de contexto nesta rodada.

## Proxima sequencia recomendada

1. Validar funcionalmente a frente financeira.
2. Rodar:
   - `npm run test:e2e:hml:mobile`
   - `npm run test:e2e:hml:jully`
3. Gerar checkpoint curto da rodada de testes.
4. Separar commit funcional de qualquer preview visual.
5. Publicar no HML apenas depois da validacao.

## Observacao importante

Nenhuma alteracao desta rodada mexe em regra de negocio, migracao, banco, deploy ou codigo remoto. O foco foi apenas organizacao, continuidade e reducao de risco para a proxima etapa.
