# Plano de recortes pre-go-live - ANEO

Data: 2026-06-08

## Objetivo

Organizar o workspace local antes de qualquer novo deploy, troca de URL ou limpeza de base. A regra e publicar apenas recortes pequenos, revisados e validados.

## Estado atual

- Git local e `origin/main` estao alinhados no commit base `f46c5acd0428599730156e78572ab7b897feb0e0`.
- Existem alteracoes locais ainda nao commitadas.
- HML esta no ar e deve ser preservado.
- Producao deve nascer como nova instancia limpa, em paralelo.
- Itau nao entra na primeira migracao de URL.

## Recorte 1 - Documentacao e operacao

Arquivos:

- `PLANO_MIGRACAO_DOMINIO_PRODUCAO_2026-05-29.md`
- `STATUS_VARREDURA_PRE_MIGRACAO_2026-06-08.md`
- demais `STATUS_*` e `RELATORIO_*` novos
- `README.md`

Acao sugerida:

- Revisar texto.
- Commitar como documentacao.
- Nao publicar no HML, salvo se quisermos manter copia operacional no servidor.

Risco:

- Baixo. Nao altera comportamento do sistema.

## Recorte 2 - PWA diretoria e QA

Arquivos:

- `mobile/aneo-pwa/src/config/constants.ts`
- `tests/e2e/hml-mobile-pwa.spec.ts`
- `playwright.hml.jully.config.ts`
- `tests/e2e/hml-jully.spec.ts`
- `package.json`

Acao sugerida:

- Manter PWA configuravel por `VITE_ANEO_API_BASE_URL`.
- Para HML, fallback continua em `https://erp-hml.aneobrasil.com.br/api.php`.
- Para producao, build deve receber `VITE_ANEO_API_BASE_URL=https://<SUBDOMINIO-PROD>.aneobrasil.com.br/api.php`.
- Apenas listar testes HML quando houver risco de criar dados.

Risco:

- Medio se publicar PWA sem variavel correta.

## Recorte 3 - Gestao do Aluno e Atendimento

Arquivos:

- `public_html/assets/css/gestao_aluno.css`
- `public_html/models/GestaoAlunoModel.php`
- `public_html/views/gestao_aluno/partials/modal_card.php`
- `public_html/views/layouts/app.php`
- `public_html/config.php`

Estado:

- HML ja possui os hashes iguais para os principais arquivos deste recorte.
- Chatwoot permanece oculto no menu por `chatwoot.show_in_menu=false`.

Acao sugerida:

- Tratar como ja publicado/validado em HML.
- Nao mexer novamente antes da migracao, salvo nova correcao especifica.

Risco:

- Baixo, desde que `config.php` nao seja publicado inteiro em cima do `config.local.php`.

## Recorte 4 - Provas internas e resultados

Arquivos:

- `public_html/controllers/CourseController.php`
- `public_html/controllers/StudentPortalController.php`
- `public_html/models/CourseModel.php`
- `public_html/views/courses/exams.php`
- `public_html/views/student_portal/exam_take.php`
- `public_html/views/student_portal/exams.php`

Estado:

- Layout de resultados em HML bate com local.
- Logica de prova objetiva/dissertativa precisa ser validada por fluxo funcional antes de go-live.

Acao sugerida:

- Validar manualmente em HML ou ambiente controlado:
  - prova objetiva publica nota automaticamente;
  - prova dissertativa fica aguardando correcao;
  - aluno recebe mensagem correta ao finalizar;
  - professor/admin registra nota posteriormente.

Risco:

- Medio, porque envolve fluxo academico e dados de prova.

## Recorte 5 - Financeiro e notificacoes

Arquivos:

- `public_html/controllers/FinanceController.php`
- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/views/email/finance_event_notification.php`
- `migrations/20260529_finance_notification_event_types.sql`

Estado:

- HML nao bate com local em `FinanceController.php`, `FinanceModel.php` e `FinanceNotificationModel.php`.
- Local possui webhook Itau e eventos de notificacao financeira.
- HML ainda nao possui `FinanceController::itauWebhook()`.

Acao sugerida:

- Separar o ajuste dos cards/filtros financeiros do restante de Itau/notificacoes, se possivel.
- Nao ativar Itau nesta fase.
- Antes de publicar financeiro em HML, revisar diff cuidadosamente e rodar lint.
- Aplicar migration financeira somente com backup e validacao da coluna/tabela.

Risco:

- Alto. Envolve faturas, notificacoes, boletos, baixa e potenciais disparos de email.

## Recorte 6 - Itau pos-migracao

Arquivos relacionados:

- `public_html/controllers/BanksController.php`
- `public_html/controllers/FinanceController.php`
- `public_html/models/FinanceModel.php`
- `public_html/core/ItauService.php`
- migrations de boleto/Itau

Decisao:

- Fora do go-live inicial da nova URL.
- Sera ativado somente depois da producao estabilizada.

Checklist futuro:

- Validar credenciais definitivas.
- Gerar token de webhook.
- Confirmar URL publica final.
- Registrar webhook no Itau.
- Testar emissao/registro de boleto controlado.
- Testar callback/baixa em uma fatura de teste.
- Confirmar logs antes de liberar operacao real.

## Sequencia recomendada

1. Commitar documentacao/status.
2. Revisar e commitar recorte PWA/QA.
3. Confirmar que Gestao do Aluno/Atendimento nao precisa novo deploy.
4. Validar recorte de provas.
5. Revisar financeiro separando filtros/cards de Itau/notificacoes.
6. Criar nova producao em paralelo.
7. Subir codigo aprovado na nova producao.
8. Criar banco limpo.
9. Configurar `config.local.php` de producao.
10. Rodar smoke tests nao destrutivos.
11. Cadastrar dados reais somente depois do aceite.

## Regra de seguranca

Nao excluir HML no dia do go-live. Primeiro bloquear ou pausar acessos/rotinas, manter backup e usar como referencia ate a producao estabilizar.
