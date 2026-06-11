# Plano de Acao das Pendencias

Data: 2026-05-29

## Objetivo

Consolidar o trabalho local em andamento da ANEO em uma trilha curta e segura de:

1. validacao tecnica;
2. consolidacao em commit;
3. atualizacao documental;
4. preparo para publicacao em HML.

## 1. Frente aberta hoje

### Escopo identificado no workspace local

Arquivos alterados:

- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `tests/e2e/hml-mobile-pwa.spec.ts`

Arquivos novos:

- `public_html/views/email/finance_event_notification.php`
- `tests/e2e/hml-jully.spec.ts`
- `playwright.hml.jully.config.ts`

Arquivos auxiliares de preview:

- `PREVIEW_MENU_CADASTRO_HOVER.html`
- `mobile/aneo-pwa/PREVIEW_PWA_SERENO_VISUAL_ONLY.html`

### Leitura funcional da frente

Hoje a frente aberta parece agrupar tres entregas:

1. notificacoes financeiras por evento:
   - boleto emitido;
   - pagamento confirmado;
2. ajuste da validacao mobile PWA para refletir o fluxo atual de aditivos;
3. nova suite de validacao da Jully em HML.

## 2. Pendencias reais a consolidar

### Bloco A - Financeiro por evento

Itens ja presentes no codigo local:

- disparo de notificacao ao emitir/registrar fatura;
- disparo de notificacao ao quitar fatura;
- template HTML especifico para evento financeiro;
- reuso do modelo de notificacao existente.

Pendencias deste bloco:

- validar se o fluxo nao duplica envio em cenarios de atualizacao de status;
- validar fallback quando nao houver email do aluno;
- validar comportamento sem tabela de logs;
- publicar no HML os arquivos desta frente.

### Bloco B - QA mobile PWA

Itens ja presentes no codigo local:

- ajuste do teste `hml-mobile-pwa.spec.ts` para refletir busca por aluno/codigo do aditivo;
- expectativa alinhada a status de acompanhamento de aditivo.

Pendencias deste bloco:

- executar a suite HML mobile real;
- confirmar que o placeholder e os estados esperados estao publicados no HML.

### Bloco C - QA Jully

Itens ja presentes no codigo local:

- nova spec `tests/e2e/hml-jully.spec.ts`;
- nova config `playwright.hml.jully.config.ts`;
- persistencia de transcricao em `test-results/`.

Pendencias deste bloco:

- decidir se a config deve ganhar script no `package.json`;
- executar a suite HML Jully real;
- registrar evidencias/resultados em documento de status.

### Bloco D - Documentacao

Pendencias deste bloco:

- atualizar `README.md` com o novo checkpoint funcional;
- atualizar `DOCUMENTACAO_COMPLETA.md` com a frente de notificacoes financeiras e QA Jully;
- criar um status curto da rodada, no mesmo padrao dos checkpoints anteriores;
- manter `PENDENCIAS_PRODUTO.md` separado das frentes tecnicas em andamento.

## 3. Ordem recomendada de execucao

### Etapa 1 - Validacao tecnica local

Checklist:

- [ ] revisar se `FinanceModel.php` dispara eventos apenas nos momentos corretos;
- [ ] revisar se `FinanceNotificationModel.php` continua compativel com o fluxo de lembretes ja existente;
- [ ] validar sintaxe PHP dos arquivos alterados;
- [ ] validar descoberta das suites Playwright;
- [ ] decidir se os previews ficam fora desta entrega.

### Etapa 2 - Validacao funcional HML

Checklist:

- [ ] executar validacao HML mobile;
- [ ] executar validacao HML Jully;
- [ ] registrar falhas, evidencias e ajustes necessarios;
- [ ] confirmar se a frente financeira depende de publicacao antes de qualquer teste funcional em HML.

### Etapa 3 - Consolidacao de codigo

Checklist:

- [ ] revisar arquivos que pertencem de fato a entrega;
- [ ] separar arquivos de preview/apoio da entrega principal;
- [ ] preparar commit com escopo coeso;
- [ ] evitar misturar preview visual com frente financeira/QA.

Sugestao de agrupamento:

- commit 1: financeiro por evento
- commit 2: QA HML mobile + Jully
- commit 3: previews, apenas se realmente precisarem ficar versionados

### Etapa 4 - Atualizacao documental

Checklist:

- [ ] atualizar `README.md`
- [ ] atualizar `DOCUMENTACAO_COMPLETA.md`
- [ ] criar/atualizar um checkpoint de status datado
- [ ] registrar impacto no HML e arquivos publicados

### Etapa 5 - Publicacao HML

Checklist:

- [ ] subir `models/FinanceModel.php`
- [ ] subir `models/FinanceNotificationModel.php`
- [ ] subir `views/email/finance_event_notification.php`
- [ ] subir arquivos de teste apenas se o HML realmente armazenar esse material no servidor de apoio
- [ ] validar no servidor se os arquivos ficaram com timestamps atualizados

## 4. Checklist de commit

Antes do commit:

- [ ] `git diff` revisado nos arquivos da entrega
- [ ] arquivos de preview confirmados como dentro ou fora do commit
- [ ] sem credenciais novas em codigo versionado
- [ ] sem artefatos de teste desnecessarios em `test-results/`

Commit recomendado para a frente principal:

- tipo sugerido: `feat(finance)` ou `feat(qa)`

Mensagens sugeridas:

- `feat(finance): send notifications for invoice issued and paid events`
- `test(hml): add Jully validation flow and update mobile PWA assertions`

Depois do commit:

- [ ] `git status` limpo para os arquivos da entrega
- [ ] documentacao consistente com o codigo
- [ ] pronto para push e/ou publicacao manual no HML

## 5. Checklist de documentacao

### README

Adicionar um novo checkpoint datado com:

- objetivo da frente;
- arquivos principais impactados;
- comportamento novo entregue;
- dependencia de publicacao no HML, se houver.

### DOCUMENTACAO_COMPLETA

Adicionar:

- secao de notificacoes financeiras por evento;
- observacao sobre template dedicado de e-mail;
- observacao sobre fallback de destinatario e log;
- secao curta sobre validacao Jully em HML.

### Status datado

Criar um novo markdown de status curto contendo:

- data da rodada;
- problema/objetivo;
- arquivos alterados;
- validacoes executadas;
- resultado;
- se foi publicado ou nao em HML.

## 6. Definicao de pronto

Esta rodada pode ser considerada concluida quando:

1. os arquivos da frente estiverem revisados e coerentes;
2. a documentacao refletir a entrega;
3. o commit estiver separado de previews soltos;
4. o HML tiver sido atualizado ou explicitamente marcado como pendente;
5. houver um checkpoint datado registrando o resultado.

## 7. Acao mais indicada agora

A acao mais indicada para a proxima rodada e:

1. validar tecnicamente a frente financeira;
2. executar os testes HML mobile e Jully;
3. criar o checkpoint datado da rodada;
4. so depois publicar no HML o bloco financeiro.
