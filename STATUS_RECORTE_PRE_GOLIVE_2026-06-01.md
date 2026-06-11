# Status recorte pre-go-live - ANEO

Data: 2026-06-01

## Objetivo

Consolidar o que pode continuar em trabalho agora, sem risco de misturar escopos perto do go-live.

## Recorte definido

Entram neste recorte:

- PWA em `mobile/aneo-pwa`
- Financeiro em `public_html/controllers` e `public_html/models`
- Template de e-mail financeiro
- Migration financeira de eventos
- Scripts e specs de QA HML
- Documentacao/checkpoints operacionais

Ficam fora deste recorte:

- App Expo em `mobile/aneo-mobile-app`
- Previews visuais soltos, salvo decisao explicita posterior
- Deploy HML/producao
- Limpeza de base
- Troca de dominio

## Estado validado

- Expo foi removido do recorte atual.
- GitHub `origin/main` esta alinhado com o commit local base.
- O workspace local ainda tem alteracoes nao commitadas, por decisao de seguranca.
- Nenhum arquivo foi publicado no servidor nesta rodada.
- HML foi consultado em modo leitura.

## Validacoes executadas

- `php -l` nos arquivos PHP tocados:
  - `public_html/controllers/BanksController.php`
  - `public_html/controllers/FinanceController.php`
  - `public_html/models/FinanceModel.php`
  - `public_html/models/FinanceNotificationModel.php`
  - `public_html/views/email/finance_event_notification.php`
- `npm run build` no PWA.
- Listagem das suites Playwright:
  - `npm run test:e2e:list`
  - `npm run test:e2e:hml:list`
  - `npm run test:e2e:hml:mobile:list`
  - `npm run test:e2e:hml:jully:list`
- `git diff --check`.

Resultado: validacoes passaram. Houve apenas avisos normais de LF/CRLF no Windows.

## Hostinger/HML

Consultas realizadas sem alterar arquivos:

- SSH OK.
- PHP remoto: `8.2.30`.
- HML sem `.git`.
- `app.public_url`: `https://erp-hml.aneobrasil.com.br`.
- `app.base_url`: vazio.
- Banco HML ativo:
  - alunos: `33`
  - faturas: `288`
  - boletos: `2`
  - logs financeiros: `29`
  - tokens API: `85`
- Lint remoto HML: `188` arquivos PHP verificados sem erro.
- HTTP:
  - login HML: `200`
  - mobile: `200`
  - API sem parametro/token: `400`, esperado.

## Pendencias controladas

- O receptor local do webhook Itau ainda nao foi publicado no HML.
- O HML segue com deploy manual, nao espelho Git.
- Os testes HML mobile/Jully nao foram executados funcionalmente nesta rodada porque podem gerar dados ou consumir IA real.
- O workspace ainda precisa ser separado em commits pequenos antes de qualquer deploy.

## Proxima acao segura

1. Revisar o diff financeiro final.
2. Revisar o diff PWA final.
3. Separar commits por escopo:
   - financeiro/eventos/webhook;
   - PWA/configuracao;
   - QA/documentacao.
4. Somente depois decidir se publica algo em HML.

Conclusao: podemos continuar as correcoes com cuidado. Nao esta liberado para go-live, troca de dominio, limpeza de base ou deploy automatico.

