# Relatorio de Auditoria do Projeto ANEO

Data: 2026-05-29

## Objetivo

Registrar o estado atual do repositorio local, da documentacao operacional e do ambiente HML publicado na Hostinger, com foco em:

1. entender onde o projeto esta;
2. validar a estrutura de pastas e arquivos de instrucao;
3. identificar divergencias entre local, GitHub e HML;
4. listar pendencias objetivas.

## 1. Estado geral do projeto

- Repositorio local: `aneo-gestao-prd`
- Branch atual: `main`
- Relacao com remoto `origin/main`: sincronizado em historico (`0 ahead / 0 behind`)
- Ultimo commit local auditado: `f46c5ac docs(certification): document certifier flow and transcript theme`
- Existe trabalho local nao consolidado no git

## 2. Estrutura validada

### Pastas principais encontradas

- `.git`
- `audit`
- `deploy`
- `migrations`
- `mobile`
- `n8n`
- `public_html`
- `qa-e2e`
- `test-results`
- `tests`

### Estrutura funcional principal

- `public_html/controllers`: 37 arquivos
- `public_html/models`: 34 arquivos
- `public_html/views`: 99 views PHP
- `migrations`: 42 scripts SQL
- `mobile/aneo-mobile-app`: app mobile
- `mobile/aneo-pwa`: PWA/mobile web
- `tests/e2e`: 10 arquivos de teste
- `tests/load`: testes de carga/smoke

## 3. Arquivos de instrucao e checkpoint

Arquivos principais presentes e atualizados:

- `README.md` - atualizado em 2026-05-28
- `DOCUMENTACAO_COMPLETA.md` - atualizado em 2026-05-28
- `PENDENCIAS_PRODUTO.md` - atualizado em 2026-05-24
- `PLANO_TESTES_QA_TELA_A_TELA.md`
- `ROTEIRO_VALIDACAO_HML_2026-05-19.md`
- `STATUS_IMPLEMENTACOES_2026-05-20.md`
- `STATUS_SINCRONIZACAO_GIT_HML_2026-05-20.md`
- `VALIDACAO_SISTEMA_2026-03-11.md`
- `MODULOS_SISTEMA.md`
- `GUIA_SOLICITAR_MODULO_INSTALAVEL.md`

Conclusao: a camada de documentacao existe, esta relativamente bem organizada e cobre produto, deploy, validacao, QA, modulos e status operacional.

## 4. Onde o projeto esta hoje

### Entregas documentadas mais recentes

Os documentos `README.md` e `DOCUMENTACAO_COMPLETA.md` indicam que o checkpoint funcional mais recente e:

- perfil administrativo `Certificador`;
- area `certification`;
- historico academico reutilizado no administrativo;
- bloqueio de alunos de degustacao no fluxo de certificacao;
- migration `20260528_add_certificador_role.sql`.

### Estado de validacao conhecido

- `VALIDACAO_SISTEMA_2026-03-11.md`: sistema aprovado para demo
- `ROTEIRO_VALIDACAO_HML_2026-05-19.md`: HML validado em fluxos principais
- `STATUS_IMPLEMENTACOES_2026-05-20.md`: hotfix do modal de alertas do dashboard publicado em HML
- `STATUS_SINCRONIZACAO_GIT_HML_2026-05-20.md`: auditoria parcial confirmou alinhamento em um recorte importante, mas nao no sistema inteiro

### Estado dos testes automatizados

As configuracoes Playwright foram reconhecidas com sucesso em modo `--list`:

- `playwright.config.ts`: 1 teste local
- `playwright.hml.config.ts`: 1 teste HML
- `playwright.hml.mobile.config.ts`: 1 teste HML mobile
- `playwright.hml.jully.config.ts`: 1 teste HML Jully

Conclusao: a estrutura de testes esta montada e operacional no nivel de descoberta de suites.

## 5. Pendencias e trabalho em andamento no local

### Arquivos modificados

- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `tests/e2e/hml-mobile-pwa.spec.ts`

### Arquivos novos nao rastreados

- `PREVIEW_MENU_CADASTRO_HOVER.html`
- `mobile/aneo-pwa/PREVIEW_PWA_SERENO_VISUAL_ONLY.html`
- `playwright.hml.jully.config.ts`
- `public_html/views/email/finance_event_notification.php`
- `tests/e2e/hml-jully.spec.ts`

### Leitura pratica dessas mudancas

O repositorio local tem uma frente nova ainda nao consolidada, com foco em:

- notificacoes financeiras por evento:
  - emissao de boleto;
  - confirmacao de pagamento;
- novo template de email financeiro;
- ampliacao da validacao mobile PWA;
- nova suite dedicada para validar a Jully em HML.

Essas mudancas ainda nao aparecem nos checkpoints de status publicados em markdown.

## 6. Pendencia formal de produto

No arquivo `PENDENCIAS_PRODUTO.md`, a pendencia de produto explicitamente registrada hoje e:

- `Financeiro > Contas a Pagar > Agenda de Pagamentos`

Status documentado:

- pendente para refinamento futuro

Escopo sugerido no proprio documento:

- cards de vencidos, hoje, 7 dias e 30 dias;
- lista operacional por urgencia;
- saldo pendente por fornecedor;
- atalhos de baixa, edicao e anexos;
- integracao com despesas fixas/recorrentes.

## 7. Auditoria do HML na Hostinger

### Confirmacoes objetivas

- acesso SSH validado em `149.62.37.84:65002`
- raiz auditada do HML: `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
- o HML **nao** e um clone Git (`.git` ausente)

### Observacao estrutural importante

No repositorio, a aplicacao mora dentro de `public_html/`.

No HML, a aplicacao esta publicada de forma achatada, com:

- `index.php`, `api.php`, `support.php`, `cron.php`
- `controllers/`, `models/`, `views/`, `assets/`

direto na raiz de `erphml`.

Isso confirma que o deploy do HML e manual/publicado, e nao um espelho Git da estrutura do repositorio.

### Divergencias encontradas no HML

Foi confirmada divergencia relevante na frente financeira:

1. `models/FinanceNotificationModel.php`
   - HML com timestamp de `2026-04-23`
   - hash remoto diferente do arquivo local
2. `views/email/finance_event_notification.php`
   - ausente no HML
3. `models/FinanceModel.php`
   - hash remoto diferente do arquivo local

Leitura pratica:

- a frente de notificacoes financeiras por evento ainda nao esta publicada integralmente no HML;
- o ambiente homologado esta atrasado em relacao ao que hoje existe no workspace local.

## 8. Riscos atuais

1. O HML continua fora de Git e depende de deploy manual.
2. Existe diferenca real entre o codigo local e o publicado em HML.
3. As novas mudancas locais ainda nao viraram commit nem checkpoint documental.
4. A documentacao diz bastante sobre o estado ate 2026-05-28, mas ainda nao reflete claramente a frente financeira e Jully em andamento.
5. Arquivos preview/apoio estao misturados na raiz e podem confundir o recorte do que e entregavel.

## 9. Proximo passo recomendado

1. Consolidar a frente local atual em checkpoint tecnico:
   - commit;
   - markdown de status;
   - resumo do objetivo funcional.
2. Decidir se a frente financeira deve ir para HML agora.
3. Se for publicar em HML, subir em conjunto:
   - `public_html/models/FinanceModel.php`
   - `public_html/models/FinanceNotificationModel.php`
   - `public_html/views/email/finance_event_notification.php`
4. Atualizar a documentacao de status para refletir:
   - notificacoes financeiras por evento;
   - validacao Jully;
   - ajuste do teste mobile PWA.
5. Organizar melhor artefatos de preview para pasta dedicada, evitando ruido na raiz.

## 10. Resumo executivo

Hoje o projeto ANEO esta em um estado funcional e bem documentado, com checkpoint de produto claro ate 2026-05-28 e cobertura de QA/HML registrada ate 2026-05-20. A principal pendencia formal de produto e a `Agenda de Pagamentos` em `Contas a Pagar`.

Ao mesmo tempo, existe uma nova frente de trabalho local ainda nao consolidada, focada em notificacoes financeiras por evento e validacoes adicionais de mobile e Jully. Essa frente ainda nao esta refletida por completo na documentacao nem no ambiente HML, que permanece fora de Git e com deploy manual.
