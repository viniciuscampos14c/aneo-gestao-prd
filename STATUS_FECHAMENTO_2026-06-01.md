# Fechamento ANEO - 2026-06-01

## Estado geral

- Branch local: `main`.
- GitHub/origin: `main` alinhada com `origin/main` no commit `f46c5acd0428599730156e78572ab7b897feb0e0`.
- HML/Hostinger validado em `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`.
- Nenhum deploy novo pendente foi executado depois do ajuste de layout de resultados das provas.

## Validacoes realizadas

- `git fetch origin --prune`: sem novas divergencias.
- `php -l` local OK nos PHPs modificados e no novo template de email financeiro.
- `php -l` remoto OK nos principais arquivos publicados em HML hoje.
- Smoke HTTP com `GET` OK:
  - `https://erp-hml.aneobrasil.com.br/index.php?route=login` -> 200
  - `https://erp-hml.aneobrasil.com.br/index.php?route=student/login` -> 200
  - `https://erp-hml.aneobrasil.com.br/index.php?route=courses/exams` -> 200
  - `https://mobile.aneobrasil.com.br/` -> 200
- Logo usada nos emails financeiros OK:
  - `https://erp-hml.aneobrasil.com.br/assets/brand/aneo-wordmark-transparente-branco.png?v=20260512-brand-kit-v1` -> 200

## Arquivos HML conferidos

- `views/courses/exams.php`
- `models/GestaoAlunoModel.php`
- `views/gestao_aluno/partials/modal_card.php`
- `models/FinanceNotificationModel.php`
- `controllers/CourseController.php`
- `controllers/StudentPortalController.php`
- `models/CourseModel.php`
- `views/student_portal/exam_take.php`
- `views/student_portal/exams.php`
- `views/layouts/app.php`

## Pontos de atencao

- Workspace local continua com alteracoes nao commitadas. Antes de go-live, consolidar por recorte e commitar.
- `FinanceNotificationModel.php` local nao bate 100% com HML porque o HML recebeu somente o ajuste cirurgico da logo. O arquivo local contem outras mudancas de eventos financeiros ainda nao consolidadas.
- Testes Playwright completos de HML nao foram executados no fechamento porque criam dados reais em HML, como leads, chamados, tokens, negociacoes/aditivos e degustacao.
- `public_html/config.php` local tem diferencas de ambiente. Nao subir arquivo inteiro sem revisar; quando necessario, aplicar patch cirurgico no HML.

## Backups HML recentes importantes

- `exam_results_layout_20260601_195613`
- `exam_objective_essay_logic_20260601_162348`
- `disable_chatwoot_menu_20260601_160933`
- `gda_financial_snapshot_20260601_130823`
- `email_logo_finance_20260601_130510`
- `gda_css_20260601_125917`

## Retomada recomendada amanha

1. Validar visualmente no HML as telas alteradas hoje.
2. Separar as alteracoes locais por recorte: financeiro, gestao do aluno, provas, PWA/docs/testes.
3. Decidir o que entra no commit pre-go-live e o que fica fora.
4. Rodar Playwright completo apenas se aceitarmos gerar dados QA em HML ou se prepararmos limpeza/ambiente controlado.
5. Antes de qualquer producao: backup, diff final, lint remoto e deploy arquivo a arquivo.
