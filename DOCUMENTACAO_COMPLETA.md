# DOCUMENTACAO COMPLETA - ANEO GESTAO INTEGRADA

## 1) Objetivo deste documento

Este documento foi criado para uma pessoa nova no projeto conseguir:

1. Entender exatamente o que foi construido.
2. Entender a arquitetura e como o sistema funciona.
3. Subir o sistema em ambiente local para testes.
4. Configurar e publicar em producao na Hostinger.
5. Operar os modulos no dia a dia.
6. Fazer manutencao basica e diagnostico de problemas.

## 1.1) Atualizacao critica (11/03/2026)

Atualizacoes aplicadas e validadas nesta data:

1. Menu lateral administrativo atualizado:
   - removidos do menu: `Projetos`, `Tarefas`
   - renomeados: `Solicitacoes`, `Automacoes`, `Chat IA Jully`
2. Rotas de `projects` e `tasks` desativadas em `index.php` para evitar acesso por URL direta.
3. Validacao tecnica completa executada:
   - lint PHP: `102` arquivos, `0` erros
   - rotas mapeadas para controllers: `125`, sem metodos ausentes
   - renders de view: `46`, sem arquivos ausentes
   - smoke test GET admin: `46` rotas ativas OK
   - rotas desativadas (`projects`, `tasks`) retornando `404` como esperado
   - smoke test GET portal aluno: `15` rotas OK
   - smoke test GET central tecnica: OK
   - banco conectado com sucesso (`43` tabelas)
4. Estrutura real do projeto:
   - o codigo local em `C:\xampp\htdocs\aneo` nao usa subpasta `public_html`
   - na Hostinger, o conteudo da raiz local do projeto deve ir para a raiz web remota (normalmente `public_html`)
5. Publicacao das 3 aplicacoes (recomendado):
   - Administrativo: `index.php?route=login`
   - Portal do Aluno: `index.php?route=student/login`
   - Central Tecnica: `support.php?route=support/login`

## 1.2) Atualizacao complementar (16/03/2026) - Licenciamento anual por empresa

Atualizacoes aplicadas e validadas nesta data:

1. Novo item `Licenca` no menu `Cadastro` (apenas perfil `admin`).
2. Estrutura de banco para controle de licenca por empresa:
   - `company_licenses`
   - `company_license_history`
3. Migracao criada e aplicada:
   - `migrations/20260316_company_licenses.sql`
4. Fluxo de ativacao/renovacao anual por chave na tela:
   - `index.php?route=companies/license`
5. Base de enforcement preparada no `core/helpers.php`, controlada por config:
   - `licensing.enabled`
   - `licensing.enforce`
   - `licensing.grace_days`
   - `licensing.fixed_keys`

## 1.3) Atualizacao complementar (16/03/2026) - Degustacao de Cursos EAD

Atualizacoes aplicadas e validadas nesta data:

1. Nova opcao `Degustacao` dentro do modulo `Cursos EAD`.
2. Fluxo de criacao de acesso rapido com login/senha gerados automaticamente.
3. Restricao de degustacao no portal:
   - acesso somente no dia liberado
   - menu restrito a `Inicio` e `Aulas ao Vivo`
4. Migracao criada e aplicada:
   - `migrations/20260316_courses_trial_access.sql`
5. Rotas administrativas adicionadas:
   - `courses/trial-access`
   - `courses/trial-access/store`
   - `courses/trial-access/revoke`

## 1.4) Atualizacao complementar (17/03/2026) - Chamados com codigo ANEO + portal do aluno

Atualizacoes aplicadas e validadas nesta data:

1. Identificador de chamado padronizado para `ANEO` sequencial:
   - exemplos: `ANEO001`, `ANEO002`, ...
2. Exibicao reforcada do codigo nas telas:
   - `Solicitacoes` (administrativo)
   - `Central Tecnica` (suporte)
3. Portal do aluno com abertura de chamados:
   - nova aba `Chamados`
   - rotas `student/requests` e `student/requests/store`
4. Migracao criada para normalizar chamados antigos:
   - `migrations/20260317_support_ticket_codes_aneo.sql`

## 1.5) Atualizacao complementar (17/03/2026) - Historico Academico no Portal do Aluno

Atualizacoes aplicadas e validadas nesta data:

1. Historico Academico removido da tela de `Avaliacoes` e criado em aba dedicada.
2. Nova rota no portal:
   - `student/academic-history`
3. Nova tela com modelo formal de historico escolar:
   - cabecalho institucional
   - dados do aluno (nome, RA, RG e contato)
   - periodos semestrais com disciplinas, notas e situacao final
   - bloco de descricao institucional e area de carimbo/assinatura ANEO
4. Impressao A4 disponivel diretamente na tela do historico.
5. Sem necessidade de migracao de banco (uso de dados ja existentes).

---

## 2) O que foi entregue

Foram entregues os seguintes artefatos principais:

1. Sistema web em PHP puro (MVC simples), sem Composer.
2. Banco MySQL completo em script unico: `database.sql`.
3. Front-end com Tailwind (CDN), layout com:
   - sidebar fixa
   - header fixa
   - botao flutuante de acoes rapidas
4. Modulos:
   - Login e controle por perfil/permissao
   - Selecao de empresa/CNPJ no login administrativo (multiempresa fase 1)
   - Administracao de usuarios (admin/suporte + permissoes por tela/funcao)
   - Empresas (cadastro de CNPJs e status ativo/inativo)
   - Dashboard (operacional + BI Gerencial)
   - Alunos
   - Kanban Cliente (financeiro)
   - Leads (CRM/pipeline)
   - Financeiro (faturas/pagamentos/recorrencia/baixa manual/NF-e)
   - Atendimento (Chatwoot)
   - Assinaturas Eletronicas (D4Sign)
   - Cursos EAD (cursos/categorias/matriculas/comentarios/exames/agenda academica)
   - Degustacao de curso EAD (acesso rapido por data e curso)
   - Portal do Aluno separado (login proprio + cursos + agenda + aulas + materiais + progresso + avaliacoes)
   - Portal do Aluno com abertura de chamados tecnicos
   - Portal do Aluno com Historico Academico em aba separada e impressao A4
   - Licenciamento anual por empresa (Cadastro > Licenca)
   - Solicitacoes, Automacoes e Chat IA Jully (CRUD basico)
   - Projetos e Tarefas desativados por regra de negocio atual
5. Script local automatizado para XAMPP:
   - `setup_local_xampp.ps1`

---

## 3) Estrutura do projeto

Raiz do projeto:

1. `database.sql`
2. `README.md`
3. `DOCUMENTACAO_COMPLETA.md`
4. `setup_local_xampp.ps1`
5. `migrations/20260304_student_portal_accounts.sql`
6. `migrations/20260304_exam_submissions.sql`
7. `migrations/20260304_chatwoot_links.sql`
8. `migrations/20260304_chatwoot_flow_sessions.sql`
9. `migrations/20260305_exam_schedule_calendar.sql`
10. `migrations/20260305_academic_calendar_full.sql`
11. `migrations/20260305_student_profile_photo.sql`
12. `migrations/20260306_d4sign_signatures.sql`
13. `migrations/20260306_phase1_multiempresa.sql`
14. `migrations/20260313_arsenal_digital.sql`
15. `migrations/20260316_company_licenses.sql`
16. `migrations/20260316_courses_trial_access.sql`
17. `migrations/20260317_support_ticket_codes_aneo.sql`
18. Raiz da aplicacao (`index.php`, `config.php`, `controllers/`, `models/`, `views/`, `assets/`, `uploads/`)

Dentro da raiz da aplicacao:

1. `index.php`: roteador principal.
2. `config.php`: configuracoes gerais, banco, perfis/permissoes.
3. `db.php`: singleton de conexao PDO.
4. `core/`: bootstrap, helpers, router, classes base MVC.
5. `controllers/`: regras de fluxo por modulo.
6. `models/`: consultas e regras de negocio por entidade.
7. `views/`: telas HTML/PHP por modulo.
8. `assets/css` e `assets/js`: estilos e JS global.
9. `uploads/`: arquivos enviados (documentos/capas).

---

## 4) Arquitetura e fluxo tecnico

### 4.1 MVC simplificado

1. Requisicao entra em `index.php`.
2. `core/bootstrap.php` carrega:
   - configuracao
   - sessao
   - conexao PDO
   - autoload de classes
3. Router resolve a rota e chama um controller.
4. Controller:
   - valida autenticacao/permissao
   - chama model para ler/gravar dados
   - renderiza uma view
5. View renderiza dentro do layout (`views/layouts/app.php` ou `guest.php`).

### 4.2 Seguranca implementada

1. Sessao PHP para login.
2. CSRF token para formularios POST.
3. Escape de saida com helper `e()`.
4. Permissao por modulo e funcao com base em perfil (configuravel em `config.php`).

### 4.3 Perfis e permissoes

Definidos em `config.php`:

1. `admin`: acesso total (`*`).
2. `suporte`: acesso limitado por checkboxes de telas e funcoes no modulo `Usuarios`.

---

## 5) Banco de dados

Arquivo principal: `database.sql`.

### 5.1 Tabelas principais

1. `users`
2. `user_permissions`
3. `companies`
4. `user_companies`
5. `students`
6. `student_portal_accounts`
7. `student_contacts`
8. `kanban_status`
9. `student_kanban_history`
10. `leads`
11. `lead_status`
12. `lead_history`
13. `chatwoot_links`
14. `chatwoot_flow_sessions`
15. `signature_requests`
16. `signature_events`
17. `courses`
18. `course_categories`
19. `enrollments`
20. `course_comments`
21. `exams`
22. `exam_questions`
23. `exam_results`
24. `exam_submissions`
25. `exam_submission_answers`
26. `course_activities`
27. `academic_reminders`
28. `invoices`
29. `bank_slips`
30. `fiscal_invoices`
31. `invoice_items`
32. `payments`
33. `payment_items`
34. `tags`
35. `taggables`
36. `uploads`
37. `module_items`
38. `arsenal_categories`
39. `arsenal_items`
40. `arsenal_item_courses`
41. `arsenal_item_students`
42. `arsenal_access_logs`
43. `company_licenses`
44. `company_license_history`
45. `course_trial_accesses`

### 5.2 Seeds iniciais

O SQL cria:

1. Usuario admin:
   - username: `admin`
   - senha inicial: `admin123`
2. Status iniciais do Kanban financeiro.
3. Status iniciais do Pipeline de Leads.
4. Categorias iniciais de curso.

### 5.3 Observacao sobre senha inicial

O seed grava senha em formato legado (`admin123`) para facilitar importacao rapida.
No primeiro login com `admin/admin123`, o sistema converte automaticamente para hash seguro (`password_hash`).

### 5.4 Campos e estrutura novos (agenda academica)

1. `exams.scheduled_at`: data/hora oficial da prova para calendario.
2. `course_activities`: prazos de atividades por curso (com antecedencia de lembrete).
3. `academic_reminders`: fila/historico de lembretes automaticos para aluno/professor.

### 5.5 Campos novos (perfil do aluno)

1. `students.profile_photo`: caminho relativo da foto de perfil do aluno (ex.: `uploads/students/...`).

### 5.6 Estrutura nova (multiempresa - fase 1)

1. `companies`: cadastro de empresas/CNPJs.
2. `user_companies`: vinculo N:N entre usuario administrativo e empresas permitidas.
3. Coluna `company_id` adicionada em:
   - `students`
   - `leads`
   - `invoices`
   - `payments`
4. Indices e chaves estrangeiras adicionados para garantir isolamento por empresa.

---

## 6) Modulos e funcionalidades entregues

## 6.1 Login e Sessao

1. Tela de login com usuario/email + senha.
2. Sessao com dados de perfil.
3. Logout.
4. Restricao de acesso por permissao de modulo.
5. Login separado do aluno (`student/login`) com sessao independente do administrativo.
6. Selecao de empresa/CNPJ obrigatoria no administrativo (`select-company`) quando o usuario possui mais de uma empresa vinculada.
7. Vinculo de empresas por usuario no cadastro/edicao de usuarios.

## 6.2 Dashboard

1. Cards de resumo:
   - alunos
   - leads
   - faturas
   - valor a receber
2. BI Gerencial (visao executiva):
   - conversao de leads
   - receita recebida (30 dias)
   - receita prevista (30 dias)
   - inadimplencia (% e valor)
   - progresso medio de matriculas
   - taxa de aprovacao em provas
3. Serie mensal (ultimos 6 meses):
   - faturado x recebido
4. Desempenho por curso:
   - total de matriculas
   - progresso medio
   - taxa de aprovacao
5. Resumo visual de:
   - pipeline de leads
   - kanban financeiro de alunos

## 6.3 Alunos

1. Listagem com filtros, busca e paginacao (50/100/200).
2. Cards de indicadores.
3. CRUD de aluno.
4. Importacao CSV.
5. Exportacao CSV.
6. Acoes em massa:
   - ativar
   - inativar
   - alterar status kanban
   - excluir
7. Upload de documentos.
8. Historico financeiro do aluno.
9. Historico de mudanca de status no Kanban.
10. Atalho para conversar via WhatsApp no cadastro/listagem do aluno.
11. Configuracao de acesso do portal do aluno (login, senha e status ativo/inativo).
12. Upload de foto de perfil no cadastro do aluno (PNG/JPG/JPEG/WEBP, ate 5MB).

## 6.4 Kanban Cliente (financeiro)

1. Colunas por status configuravel.
2. Drag and drop de cards de aluno.
3. Atualizacao de status no banco.
4. Busca de clientes no topo.
5. Tela de configuracao de status:
   - criar/editar/remover
   - cor
   - ordem
   - status padrao

## 6.5 Leads (CRM)

1. Pipeline com status e contagem.
2. Listagem com filtros, busca e paginacao.
3. Alteracao de status diretamente na tabela.
4. CRUD de lead.
5. Historico de interacoes por lead.
6. Exportacao CSV.
7. Acoes em massa.
8. Conversao de lead em aluno, mantendo vinculo/historico.
9. Tela de configuracao de status do funil.

## 6.6 Financeiro

### Faturas

1. Cards por status:
   - Em aberto (`open`)
   - Pago (`paid`)
   - Parcial (`partial`)
   - Vencido (`overdue`)
   - Rascunho (`draft`)
2. Totais financeiros:
   - pagas
   - vencidas
   - pendentes
3. CRUD de fatura (com tags/projeto/imposto).
4. Exportacao CSV.
5. Geracao de faturas recorrentes.
6. Regra automatica de vencimento (status `overdue` por data).
7. Integracao com Kanban:
   - com atraso => tenta mover para `inadimplente`
   - regularizado => tenta mover para `sem-pendencias`
8. Botao de baixa manual para contas a receber (por fatura).
9. Card visual de quitacao para fatura paga.
10. Botao `Gerar Nota Fiscal de Saida` disponivel apos quitacao.
11. Estrutura preparada para API fiscal futura:
    - tabela `fiscal_invoices`
    - servico `FiscalInvoiceService`
12. Botao `Enviar boleto WhatsApp` na listagem de faturas (abre WhatsApp Web/app com mensagem pronta e link do boleto quando informado).
13. Estrutura preparada para API bancaria de boletos:
    - tabela `bank_slips`
    - servico `BoletoService`
    - botoes `Gerar boleto API` e `Sincronizar status` por fatura.
14. Labels de status padronizadas em portugues na interface de Faturas e Relatorios.

### Pagamentos

1. Registro de pagamento:
   - total
   - parcial
   - em lote
2. Distribuicao automatica do valor nas faturas selecionadas.
3. Recalculo de status da fatura apos pagamento.

## 6.7 Atendimento (Chatwoot)

1. Modulo administrativo `Atendimento` no menu lateral.
2. Status da integracao com `enabled`, `account_id`, `inbox_id` e URL do painel.
3. Abertura de conversa com Chatwoot a partir de:
   - cadastro de aluno
   - cadastro de lead
   - listagem de faturas (financeiro)
4. Busca/criacao de contato via API Chatwoot.
5. Reuso de conversa existente por contato quando disponivel.
6. Persistencia do vinculo local em `chatwoot_links` (entidade, contato, conversa e URL).
7. Formulario de abertura manual por nome/telefone/email no modulo de atendimento.
8. Preparado para evoluir com automacoes e webhooks sem quebrar os modulos atuais.
9. Endpoint webhook pronto em `chatwoot/webhook` para automacao inicial:
   - menu 1/2
   - pergunta nome/cidade
   - mensagem de encaminhamento por cidade
10. Estado da automacao salvo em `chatwoot_flow_sessions`.

## 6.8 Cursos EAD

1. Cursos:
   - nome, descricao, categoria, capa, status, carga horaria, grade, materiais
   - dados de aula ao vivo (link manual/senha/id/data)
2. Categorias:
   - CRUD simples
3. Matriculas:
   - vinculo aluno x curso
   - status (ativa/cancelada/concluida)
   - progresso
4. Comentarios:
   - registro e listagem
5. Exames:
   - cadastro de prova
   - agendamento de data/hora da prova (`scheduled_at`)
   - calendario de proximas provas no administrativo
   - questao inicial (objetiva/dissertativa)
   - registro de nota e status aprovado/reprovado
6. Agenda Academica unificada:
   - tela `Cursos > Agenda Academica`
   - calendario unico com provas + aulas ao vivo + atividades
   - cadastro de atividade com prazo por curso
   - lembretes automaticos para aluno e professor (fila interna)
   - historico de lembretes enviados
7. Materiais por arquivo:
   - upload de anexos no cadastro/edicao do curso
   - listagem e remocao de anexos por curso
   - arquivos aparecem na aba Materiais do portal do aluno
8. Degustacao de aula ao vivo:
   - tela `Cursos EAD > Degustacao`
   - cria acesso rapido de aluno convidado com curso e data
   - gera login/senha automaticamente para teste
   - permite revogar acesso criado

## 6.9 Portal do Aluno (area separada)

1. Login proprio em `student/login`.
2. Tela `Inicio` com:
   - cards de cursos/progresso
   - proximas aulas ao vivo
   - ultimas avaliacoes
3. Tela `Meus Cursos`:
   - lista de cursos matriculados publicados
   - progresso por curso
4. Tela `Proximas Aulas ao Vivo`:
   - data/hora, meeting id, senha e link de entrada
5. Tela `Materiais`:
   - texto de materiais por curso
   - arquivos anexados (uploads do tipo `course`)
6. Tela `Progresso`:
   - resumo total/ativos/concluidos/cancelados
   - barra de progresso por curso
7. Tela `Historico de Avaliacoes`:
   - prova, nota minima, nota do aluno, status aprovado/reprovado
8. Provas disponiveis para responder:
   - lista de provas disponiveis por matricula
   - bloqueio por agendamento (prova aparece como agendada ate a data/hora)
   - envio de respostas pelo portal
   - auto-correcao quando houver gabarito em questoes objetivas
   - status pendente quando depender de correcao manual
9. Tela `Agenda`:
   - calendario unificado com provas, aulas ao vivo e atividades
   - historico de lembretes automaticos recebidos
10. Ajuste visual:
   - interface com paleta sutil Tailwind para melhor contraste e foco no estudo
11. Foto do aluno:
   - avatar exibido no topo do portal quando houver foto cadastrada no administrativo
12. Acesso de degustacao:
   - login permitido somente no dia cadastrado
   - menu restrito a `Inicio` e `Aulas ao Vivo`
   - bloqueio automatico quando acesso estiver expirado/revogado
13. Chamados tecnicos:
   - aba `Chamados` no menu do aluno
   - criacao de chamado pelo proprio aluno
   - exibicao de codigo unico do chamado (`ANEO...`)
   - acompanhamento de status e comentarios
14. Historico Academico:
   - aba dedicada `Historico Academico`
   - rota `student/academic-history`
   - layout formal inspirado em historico escolar
   - impressao em A4 com bloco para assinatura e carimbo ANEO

## 6.10 Modulos estruturais

CRUD basico para:

1. Solicitacoes
2. Automacoes
3. Chat IA Jully

Campos basicos: titulo, status, responsavel, prioridade, prazo, observacoes.

Observacao:

1. `Projetos` e `Tarefas` foram desativados no menu e nas rotas para evitar uso na operacao atual.

## 6.11 Identidade visual (logo)

1. Logo da ANEO adicionada no topo do layout administrativo.
2. Logo da ANEO adicionada no topo do layout do Portal do Aluno.
3. Texto institucional existente (`ANEO`) foi mantido sem alteracao.
4. Arquivo da logo em `assets/img/logo_aneo.png`.

## 6.12 Assinaturas Eletronicas (D4Sign)

1. Modulo administrativo `Assinaturas`.
2. Cadastro de solicitacao de assinatura por aluno com upload de contrato.
3. Envio para assinatura eletronica via API D4Sign.
4. Registro de webhook para retorno de eventos do documento.
5. Sincronizacao manual de status com D4Sign.
6. Download automatico do contrato assinado para `uploads/signatures/signed`.
7. Historico de eventos recebidos no webhook.

---

## 7) Rotas (mapa funcional)

As rotas estao centralizadas em `index.php` (administrativo/aluno) e `support.php` (central tecnica).
Referencia rapida:

1. Auth admin: `login`, `logout`, `select-company`, `set-company`
2. Portal aluno: `student/login`, `student/logout`, `student/dashboard`, `student/courses`, `student/calendar`, `student/live`, `student/materials`, `student/arsenal`, `student/arsenal/open`, `student/requests`, `student/requests/store`, `student/progress`, `student/exams`, `student/academic-history`, `student/exams/take`, `student/exams/submit`
3. Usuarios: `users/*`
4. Empresas: `companies`, `companies/store`, `companies/update`, `companies/toggle`
5. Licenca: `companies/license`, `companies/license/activate`
6. Dashboard: `dashboard`
7. Alunos: `students/*`
8. Kanban: `kanban/*`
9. Leads: `leads/*`
10. Financeiro: `finance/invoices/*`, `finance/payments/*`
11. Atendimento: `chatwoot`, `chatwoot/open-student`, `chatwoot/open-lead`, `chatwoot/open-phone`, `chatwoot/webhook`
12. Assinaturas: `signatures`, `signatures/store`, `signatures/send`, `signatures/sync`, `signatures/delete`, `signatures/webhook`
13. Arsenal Digital: `arsenal`, `arsenal/item/*`, `arsenal/category/*`, `arsenal/bind/*`, `arsenal/unbind/*`, `arsenal/download`
14. Cursos: `courses/*` (inclui `courses/materials/upload`, `courses/materials/delete`, `courses/calendar`, `courses/activities/store`, `courses/activities/delete`, `courses/trial-access`, `courses/trial-access/store`, `courses/trial-access/revoke`)
15. Modulos basicos ativos: `requests/*`, `automations/*`, `help/*` (projects/tasks desativados)
16. Busca global: `search`

---

## 8) Configuracao local (XAMPP)

## 8.1 Instalacao automatizada (ja pronta)

Script criado:

`setup_local_xampp.ps1`

Ele faz:

1. Inicia Apache e MySQL.
2. Copia a pasta do projeto para `C:\xampp\htdocs\aneo`.
3. Preserva `uploads` existentes da copia local (`capas`, `documentos`, `materiais`) para evitar perda de arquivos ao atualizar.
4. Ajusta `config.php` para ambiente local:
   - host `localhost`
   - banco `aneo_gestao`
   - usuario `root`
   - senha vazia
5. Importa `database.sql`.

### Execucao

```powershell
powershell -ExecutionPolicy Bypass -File "C:\...\Projeto ANEO\setup_local_xampp.ps1"
```

### URL local

`http://localhost/aneo/index.php?route=login`
`http://localhost/aneo/index.php?route=student/login`

---

## 9) Deploy em producao na Hostinger (passo a passo)

Esta secao e a principal para a pessoa responsavel por subir em producao.

## 9.1 Pre-requisitos

1. Hosting ativo na Hostinger.
2. Dominio apontando para a hospedagem.
3. Acesso ao hPanel (File Manager + MySQL + phpMyAdmin).
4. Projeto local validado.

## 9.2 Passo 1 - Preparar arquivos

No projeto local, voce precisa de:

1. Pasta raiz do projeto (conteudo do site).
2. Arquivo `database.sql`.

Importante: na Hostinger, a raiz web tambem se chama `public_html`.
Voce deve copiar o CONTEUDO da raiz local do projeto para a pasta remota `public_html`.

## 9.3 Passo 2 - Criar banco na Hostinger

No hPanel:

1. Acesse `Databases > MySQL Databases`.
2. Crie:
   - nome do banco
   - usuario
   - senha forte
3. Guarde os 4 dados:
   - host do banco
   - nome do banco
   - usuario
   - senha

## 9.4 Passo 3 - Importar o SQL

1. Abra `phpMyAdmin` pelo hPanel.
2. Selecione o banco criado.
3. Clique `Import`.
4. Selecione `database.sql`.
5. Execute.

Se aparecer erro de tamanho/timeout:

1. Compacte ou divida o SQL (normalmente nao deve ser necessario neste projeto).
2. Tente importar em horario de baixo uso.

## 9.5 Passo 4 - Upload dos arquivos

1. Acesse `Files > File Manager`.
2. Entre em `public_html`.
3. Apague placeholder inicial se existir (`default.php`, etc.).
4. Envie o conteudo da pasta local do projeto.

Ao final, a raiz remota deve conter:

1. `index.php`
2. `config.php`
3. `db.php`
4. `assets/`, `core/`, `controllers/`, `models/`, `views/`, `uploads/`

## 9.6 Passo 5 - Ajustar `config.php` em producao

Edite `config.php` com dados reais do banco da Hostinger:

```php
'db' => [
    'host' => 'HOST_DA_HOSTINGER',
    'port' => 3306,
    'name' => 'NOME_DO_BANCO',
    'user' => 'USUARIO_DO_BANCO',
    'pass' => 'SENHA_DO_BANCO',
    'charset' => 'utf8mb4',
],
```

Tambem ajustar (opcional) em `app`:

1. `base_url` (se desejar usar)
2. `timezone` (ja esta `America/Sao_Paulo`)

Para futura integracao de NF-e, ja existe bloco `fiscal` no `config.php`:

1. `enabled`: manter `false` ate definir API.
2. `provider`: nome do fornecedor escolhido.
3. `environment`: `sandbox` ou `production`.
4. `base_url` e `api_token`: credenciais da API fiscal.
5. `company_document` e `company_name`: dados do emitente.

Para futura integracao bancaria de boletos, ja existe bloco `bank_slip` no `config.php`:

1. `enabled`: manter `false` ate definir API.
2. `provider`: nome do provedor escolhido (ex.: banco/API gateway).
3. `environment`: `sandbox` ou `production`.
4. `base_url` e `api_token`: credenciais da API de boletos.
5. `wallet`, `beneficiary_document`, `beneficiary_name`: dados da carteira/cedente.
6. `webhook_secret`: token para validar callbacks do provedor.

Para integrar atendimento com Chatwoot, ja existe bloco `chatwoot` no `config.php`:

1. `enabled`: `true` para ativar.
2. `base_url`: URL do Chatwoot (ex.: `https://app.chatwoot.com` ou URL self-hosted).
3. `account_id`: ID da conta no Chatwoot.
4. `inbox_id`: ID da inbox usada para criar conversas.
5. `api_access_token`: token de API da conta (Application API).
6. `webhook_token`: token de seguranca do webhook de automacao.
7. `bot_enabled`: ativa/desativa respostas automaticas do webhook.
8. `bot_message_*`: textos padrao do fluxo automatico.
9. `bot_city_team_map`: cidades mapeadas para encaminhamento.

### 9.6.1 Como obter os dados no Chatwoot (passo a passo)

1. Criar conta no Chatwoot:
   - Cloud: `https://app.chatwoot.com`
   - ou instancia self-hosted do cliente.
2. Entrar no workspace/conta do cliente.
3. Criar ou abrir uma Inbox:
   - `Settings > Inboxes > Add Inbox`.
4. Copiar `account_id` e `inbox_id` pela URL da inbox aberta:
   - exemplo real de formato:
     `https://app.chatwoot.com/app/accounts/154715/settings/inboxes/98949`
   - nesse exemplo:
     - `account_id = 154715`
     - `inbox_id = 98949`
5. Copiar `api_access_token`:
   - avatar do usuario (canto inferior esquerdo) > `Profile Settings` (ou `Preferences`) > `Access Token / API Access Token`.
6. Definir `base_url`:
   - Cloud: `https://app.chatwoot.com`
   - self-hosted: URL do servidor do cliente (ex.: `https://chat.cliente.com.br`).
7. Definir `webhook_token` forte (nao reutilizar token antigo).
8. Preencher o bloco no `config.php`:

```php
'chatwoot' => [
    'enabled' => true,
    'base_url' => 'https://app.chatwoot.com',
    'account_id' => '154715',
    'inbox_id' => '98949',
    'api_access_token' => 'SEU_TOKEN_AQUI',
    'webhook_token' => 'TOKEN_WEBHOOK_FORTE',
],
```

### 9.6.2 Simulacao com seus dados e substituicao para o cliente

Fluxo recomendado para demonstracao comercial e troca posterior:

1. Simular usando sua conta Chatwoot (dados temporarios no `config.php`).
2. Ao fechar com cliente, coletar os dados reais dele (`base_url`, `account_id`, `inbox_id`, `api_access_token`, `webhook_token`).
3. Substituir apenas esses 5 campos no `config.php` (nao precisa alterar codigo).
4. Validar no sistema:
   - abrir `Atendimento`,
   - clicar `Atender no Chatwoot` em um aluno/lead,
   - conferir abertura da conversa no painel correto.
5. Seguranca apos troca:
   - revogar/rotacionar token antigo usado na simulacao.
   - evitar publicar token em print, chat ou repositorio.

Opcional de limpeza antes de entregar para o cliente:

1. Se quiser remover vinculos antigos de simulacao:
   - `TRUNCATE TABLE chatwoot_links;`
2. Isso apenas limpa o vinculo local; nao apaga dados do Chatwoot do cliente.

### 9.6.3 Script executavel para substituicao automatica

Para facilitar, o projeto inclui:

1. Template JSON:
   - `chatwoot_config.template.json`
2. Script PowerShell:
   - `set_chatwoot_config.ps1`

Passo a passo recomendado:

1. Copiar o template e criar arquivo de trabalho:
   - `chatwoot_config.json`
2. Preencher os dados reais do cliente no JSON.
3. Executar o script para atualizar automaticamente o `config.php`.

Exemplo 1 - usando arquivo JSON:

```powershell
Copy-Item .\chatwoot_config.template.json .\chatwoot_config.json
# editar chatwoot_config.json com os dados do cliente
powershell -ExecutionPolicy Bypass -File .\set_chatwoot_config.ps1 -TemplatePath .\chatwoot_config.json -AlsoUpdateXamppCopy
```

Exemplo 2 - passando parametros direto:

```powershell
powershell -ExecutionPolicy Bypass -File .\set_chatwoot_config.ps1 `
  -Enabled true `
  -BaseUrl "https://app.chatwoot.com" `
  -AccountId "154715" `
  -InboxId "98949" `
  -ApiAccessToken "TOKEN_DO_CLIENTE" `
  -WebhookToken "TOKEN_WEBHOOK_FORTE" `
  -AlsoUpdateXamppCopy
```

Exemplo 3 - modo interativo (pergunta campo por campo):

```powershell
powershell -ExecutionPolicy Bypass -File .\set_chatwoot_config.ps1 -Interactive -AlsoUpdateXamppCopy
```

O que o script faz:

1. Localiza o bloco `chatwoot` no `config.php`.
2. Atualiza automaticamente os campos:
   - `enabled`
   - `base_url`
   - `account_id`
   - `inbox_id`
   - `api_access_token`
   - `webhook_token`
3. Cria backup automatico antes de salvar:
   - `config.php.bak.AAAAMMDD_HHMMSS`
4. Opcionalmente copia o config atualizado para:
   - `C:\xampp\htdocs\aneo\config.php` (ou outro projeto XAMPP informado).

## 9.7 Passo 6 - Permissoes de pasta

Garantir que servidor escreve em:

1. `uploads/`
2. `uploads/documents/`
3. `uploads/courses/`

Permissao recomendada:

1. pastas: `755` (ou `775` se necessario)
2. arquivos: `644`

## 9.8 Passo 7 - Configurar PHP

No hPanel:

1. `Advanced > PHP Configuration`
2. Selecionar PHP 8.2 (ou 8.1+ compativel)
3. Habilitar extensoes padrao (PDO, pdo_mysql)

## 9.9 Passo 8 - Testes de smoke em producao

Acessar:

1. `https://SEU-DOMINIO/index.php?route=login`

Validar:

1. Login com `admin/admin123`.
2. Abrir Dashboard.
3. Criar aluno.
4. Criar lead e converter.
5. Criar fatura.
6. Registrar pagamento.
7. Testar upload de documento.
8. Testar Kanban drag-and-drop.
9. Cadastrar login de aluno e testar acesso em `student/login`.
10. Cadastrar atividade em `Cursos > Agenda Academica`.
11. Validar calendario no admin (provas + aulas + atividades).
12. Validar calendario no portal do aluno (`student/calendar`) e recebimento de lembrete automatico.

---

## 10) Checklist final de publicacao

Antes de liberar para usuarios finais:

1. Trocar senha do usuario admin.
2. Criar usuarios por perfil (admin/suporte).
3. Validar permissoes de telas e funcoes para cada usuario de suporte.
4. Confirmar SSL ativo no dominio.
5. Confirmar backup automatico da Hostinger habilitado.
6. Confirmar upload funcionando.
7. Confirmar import/export CSV.
8. Confirmar pagamento em lote.
9. Confirmar geracao de recorrencia.
10. Confirmar Portal do Aluno com usuario real de aluno.
11. Confirmar modulo `Atendimento` abrindo conversa no Chatwoot correto.
12. Se houve simulacao, substituir credenciais Chatwoot pelos dados do cliente.
13. Rotacionar/revogar token antigo de simulacao.
14. Confirmar BI Gerencial visivel no Dashboard administrativo.
15. Confirmar Agenda Academica no admin e no portal do aluno.

---

## 11) Operacao no dia a dia

## 11.1 Rotina comercial

1. Cadastrar novos leads.
2. Atualizar status no pipeline.
3. Registrar interacoes.
4. Converter em aluno quando necessario.

## 11.2 Rotina operacional

1. Manter cadastro de alunos.
2. Anexar documentos.
3. Acompanhar status no Kanban.
4. Matricular alunos em cursos.

## 11.3 Rotina financeira

1. Gerar faturas (avulsas ou recorrentes).
2. Registrar pagamentos (inclusive em lote).
3. Monitorar vencimentos.
4. Revisar cards de inadimplencia e pendencias.

## 11.4 Rotina academica do aluno

1. Liberar acesso do portal no cadastro do aluno.
2. Matricular em cursos publicados.
3. Conferir Agenda Academica (provas, aulas ao vivo e atividades com prazo).
4. Conferir se ha link/aula ao vivo e materiais.
5. Lancar resultados de prova para aparecer no historico do aluno.

---

## 12) Manutencao e evolucao

## 12.1 Backups recomendados

1. Backup diario do banco MySQL.
2. Backup semanal de arquivos (codigo + `uploads`).
3. Antes de qualquer atualizacao, gerar backup completo.

## 12.2 Atualizacoes de codigo

Fluxo seguro:

1. Atualizar primeiro em homologacao/local.
2. Testar modulos criticos.
3. Fazer deploy em producao fora de horario de pico.
4. Manter rollback pronto (backup de arquivos + banco).

## 12.3 Ponto de atencao sobre recorrencia

Atualmente a recorrencia e disparada manualmente pela tela de faturas (`Gerar Recorrentes`).
Se quiser automatizar totalmente (cron), o ideal e criar um endpoint/script dedicado sem CSRF de formulario.

---

## 13) Troubleshooting (problemas comuns)

## 13.1 Erro de conexao com banco

Sintomas:

1. tela branca/erro 500
2. mensagem de conexao PDO

Conferir:

1. host/usuario/senha/banco em `config.php`
2. banco foi importado?
3. usuario tem permissao total no banco?

## 13.2 Apache nao inicia local

Conferir:

1. porta 80 ocupada por outro servico
2. iniciar pelo `xampp-control.exe` como admin
3. ver `C:\xampp\apache\logs\error.log`

## 13.3 Upload nao funciona

Conferir:

1. permissao de escrita nas pastas de `uploads`
2. limites de upload do PHP (`upload_max_filesize`, `post_max_size`)

## 13.4 Rota 404

Conferir:

1. URL correta com `index.php?route=...`
2. arquivo `index.php` na raiz web
3. upload de todos os arquivos em `controllers`, `views`, `models`

## 13.5 Login nao funciona com admin

Conferir:

1. se `database.sql` foi importado sem erro
2. se a tabela `users` contem usuario `admin`
3. tentar resetar senha direto no banco (se necessario)

## 13.6 Login do aluno nao funciona

Conferir:

1. tabela `student_portal_accounts` existe (se nao, rodar `migrations/20260304_student_portal_accounts.sql`)
2. aluno esta `ativo` no cadastro
3. acesso do portal esta `ativo` no cadastro do aluno
4. login digitado igual ao cadastrado

## 13.7 Prova enviada nao aparece no historico do aluno

Conferir:

1. se a prova foi apenas criada (sem envio), ela nao entra no historico.
2. para aparecer no historico, precisa:
   - envio da prova no portal do aluno, ou
   - registro manual em `Cursos > Exames > Registrar Resultado`.
3. se usar envio no portal e houver erro de tabela, rodar `migrations/20260304_exam_submissions.sql`.

## 13.8 Atendimento Chatwoot nao abre conversa

Conferir:

1. `chatwoot.enabled = true` no `config.php`.
2. `base_url`, `account_id`, `inbox_id` e `api_access_token` preenchidos corretamente.
3. IDs conferem com a URL da inbox:
   - formato: `/app/accounts/{account_id}/settings/inboxes/{inbox_id}`.
4. token nao expirou e tem permissao para a conta/inbox.
5. se trocou de cliente, confirmar que o token antigo foi removido e o novo foi aplicado.
6. conferir se o webhook do Chatwoot aponta para:
   - `https://SEU-DOMINIO/index.php?route=chatwoot/webhook&token=SEU_WEBHOOK_TOKEN`

## 13.9 Capa do curso aparece quebrada

Conferir:

1. se o arquivo da capa existe fisicamente em `uploads/courses/` (ou `C:\xampp\htdocs\aneo\uploads\courses\` no local).
2. se o campo `courses.cover_image` aponta para o caminho correto (ex.: `uploads/courses/nome_arquivo.png`).
3. se foi executado `setup_local_xampp.ps1` em versao antiga (sem preservacao de `uploads`), reenvie a capa.
4. se houver ambiente local e codigo-fonte em pastas diferentes, manter sincronia do conteudo de `uploads`.

## 13.10 Agenda academica nao aparece

Conferir:

1. tabela `course_activities` existe (rodar `migrations/20260305_academic_calendar_full.sql` se faltar).
2. tabela `academic_reminders` existe (mesma migracao acima).
3. coluna `exams.scheduled_at` existe (rodar `migrations/20260305_exam_schedule_calendar.sql` se faltar).
4. rota administrativa abre em `courses/calendar`.
5. rota do aluno abre em `student/calendar`.
6. se a tela abrir sem eventos, validar se existem provas com data, aulas ao vivo com data e atividades com prazo no periodo filtrado.

## 13.11 Foto do aluno nao aparece no portal

Conferir:

1. se a coluna `students.profile_photo` existe (rodar `migrations/20260305_student_profile_photo.sql` se faltar).
2. se o arquivo existe em `uploads/students/` (ou `C:\xampp\htdocs\aneo\uploads\students\` no local).
3. se o aluno fez novo login no portal apos alterar a foto.
4. se o caminho salvo no banco comeca com `uploads/students/`.

## 13.12 Assinatura D4Sign nao envia ou nao retorna assinada

Conferir:

1. `d4sign.enabled = true` e credenciais preenchidas (`token_api`, `crypt_key`, `safe_uuid`) no `config.php`.
2. migracao `migrations/20260306_d4sign_signatures.sql` executada no banco.
3. URL de webhook publicada no painel D4Sign apontando para `signatures/webhook`.
4. se configurado, token de webhook (`d4sign.webhook_token`) e HMAC (`d4sign.webhook_hmac_secret`) conferem com o painel D4Sign.
5. aluno com email valido cadastrado (D4Sign depende do email para assinatura remota).

## 13.13 Login admin sem acesso a empresa

Conferir:

1. migracao `migrations/20260306_phase1_multiempresa.sql` executada no banco.
2. tabela `companies` possui ao menos 1 empresa ativa.
3. usuario esta vinculado na tabela `user_companies` para a empresa correta.
4. apos login, validar se aparece a rota `select-company` para escolha do CNPJ.

## 13.14 Arsenal Digital indisponivel no admin ou portal do aluno

Conferir:

1. se a migracao `migrations/20260313_arsenal_digital.sql` foi executada no banco alvo.
2. se as 5 tabelas existem: `arsenal_categories`, `arsenal_items`, `arsenal_item_courses`, `arsenal_item_students`, `arsenal_access_logs`.
3. se o ambiente em execucao usa a pasta correta (local costuma ser `C:\\xampp\\htdocs\\aneo`).
4. se o usuario de suporte possui permissao de modulo `arsenal` e funcao `arsenal.manage` (quando aplicavel).
5. apos migracao/publicacao, recarregar com `Ctrl + F5` e, se necessario, reiniciar Apache.

---

## 14) Arquivos de referencia rapida

1. Guia resumido: `README.md`
2. Banco completo: `database.sql`
3. Migracao incremental portal aluno: `migrations/20260304_student_portal_accounts.sql`
4. Migracao incremental provas portal: `migrations/20260304_exam_submissions.sql`
5. Migracao incremental atendimento Chatwoot: `migrations/20260304_chatwoot_links.sql`
6. Migracao incremental estado do bot Chatwoot: `migrations/20260304_chatwoot_flow_sessions.sql`
7. Migracao incremental agendamento de provas: `migrations/20260305_exam_schedule_calendar.sql`
8. Migracao incremental agenda academica: `migrations/20260305_academic_calendar_full.sql`
9. Migracao incremental foto de perfil de aluno: `migrations/20260305_student_profile_photo.sql`
10. Migracao incremental modulo de assinatura D4Sign: `migrations/20260306_d4sign_signatures.sql`
11. Migracao incremental fase 1 multiempresa: `migrations/20260306_phase1_multiempresa.sql`
12. Migracao incremental modulo Arsenal Digital: `migrations/20260313_arsenal_digital.sql`
13. Migracao incremental licenciamento: `migrations/20260316_company_licenses.sql`
14. Migracao incremental degustacao de cursos: `migrations/20260316_courses_trial_access.sql`
15. Config local automatica: `setup_local_xampp.ps1`
16. Rotas: `index.php`
17. Permissoes e config geral: `config.php`
18. Relatorio de validacao da entrega: `VALIDACAO_SISTEMA_2026-03-11.md`

---

## 15) Estado atual confirmado

No ambiente local validado em 11/03/2026:

1. XAMPP instalado com sucesso.
2. Apache e MySQL iniciando.
3. Projeto copiado para `C:\xampp\htdocs\aneo`.
4. Banco importado.
5. URL local validada com retorno HTTP 200:
   - `http://localhost/aneo/index.php?route=login`
6. Lint PHP completo sem erro:
   - `102` arquivos
   - `0` falhas
7. Rotas e views consistentes:
   - `125` rotas mapeadas sem metodo ausente
   - `46` renders sem view ausente
8. Smoke tests executados:
   - admin: `46` rotas GET ativas OK
   - `projects` e `tasks`: `404` esperado (desativadas)
   - aluno: `15` rotas GET OK
   - suporte: rotas GET OK
9. Ajustes de menu/rule em producao local:
   - `Projetos` e `Tarefas` removidos do menu e desativados nas rotas
   - nomes atualizados para `Solicitações`, `Automações` e `Chat IA Jully`

---

## 16) Proxima acao recomendada para producao

1. Fazer upload para Hostinger.
2. Ajustar `config.php` com credenciais reais do banco da hospedagem.
3. Rodar checklist da secao 10.
4. Trocar senha do admin imediatamente apos primeira entrada.

---

## 17) Automacao de entrada de aluno via n8n

Esta versao inclui endpoint dedicado para n8n (Cloud ou Community), sem login de usuario e sem CSRF:

1. Rota:
   - `POST index.php?route=automations/webhook/enrollment`
2. Arquivo:
   - `controllers/AutomationWebhookController.php`
3. Configuracao:
   - `config.php` -> bloco `automation`
   - `enrollment_webhook_token` (obrigatorio em producao)
   - status aceitos para pagamento/contrato

### 17.1 Regras padrao de disparo

O endpoint ativa a jornada quando:

1. `payment_status` estiver em `confirmed|received|paid`
2. `contract_status` estiver em `signed|completed|concluded|done`

Ou quando:

1. `force_activate=true`

### 17.2 Payload minimo

```json
{
  "company_id": 1,
  "lead_id": 10,
  "course_id": 3,
  "payment_status": "confirmed",
  "contract_status": "signed"
}
```

### 17.3 Campos opcionais relevantes

1. `enrollment_status`: `active|paused|completed|cancelled`
2. `create_portal_account`: `true|false`
3. `portal_login`
4. `portal_password`
5. `portal_is_active`
6. `activate_student` (padrao true)
7. `actor_user_id` (para auditoria quando aplicavel)

### 17.4 Efeitos da automacao

Quando as regras sao atendidas:

1. Converte lead em aluno (se ainda nao convertido).
2. Marca o lead como convertido.
3. Ativa aluno (`students.is_active=1` por padrao).
4. Cria ou atualiza matricula no curso informado.
5. Cria ou atualiza acesso do portal do aluno (quando solicitado).

### 17.5 Teste local com n8n Cloud

1. Expor o ANEO local com ngrok (exemplo): `ngrok http 80`
2. Base publica final:
   - `https://SEU-NGROK.ngrok-free.app/aneo`
3. No n8n, node `HTTP Request` para:
   - `POST https://SEU-NGROK.ngrok-free.app/aneo/index.php?route=automations/webhook/enrollment`
4. Header:
   - `X-ANEO-TOKEN: <token configurado em config.php>`
5. Body JSON:
   - usar o payload da secao 17.2

### 17.6 Arquivos de apoio para o fluxo n8n

1. `n8n/README_N8N_CLOUD_TESTE.md`
2. `n8n/payload_teste_enrollment.json`


---

## 18) Atualizacao complementar (13/03/2026) - Modulo Arsenal Digital

### 18.1) Resumo da entrega

Foi implementado o modulo completo de Arsenal Digital com duas frentes:

1. Painel administrativo:
   - gestao de itens (arquivo/link)
   - categorias
   - vinculos por curso e por aluno
   - log de acessos
2. Portal do aluno:
   - listagem de materiais liberados
   - abertura segura de arquivo/link
   - registro de acesso na tabela de log

### 18.2) Arquivos criados/alterados

Novos arquivos principais:

1. `controllers/ArsenalController.php`
2. `models/ArsenalModel.php`
3. `views/arsenal/index.php`
4. `views/student_portal/arsenal.php`
5. `migrations/20260313_arsenal_digital.sql`

Arquivos alterados para integrar o modulo:

1. `index.php` (rotas admin + aluno do Arsenal)
2. `config.php` (permissoes `arsenal` e `arsenal.manage`)
3. `views/layouts/app.php` (menu admin)
4. `views/layouts/student.php` (menu aluno)
5. `controllers/StudentPortalController.php` (acoes do Arsenal no portal)
6. `models/StudentPortalModel.php` (consulta de itens acessiveis + log)
7. `database.sql` (schema base com tabelas do Arsenal)

### 18.3) Estrutura de banco adicionada

Tabelas criadas pela migracao `20260313_arsenal_digital.sql`:

1. `arsenal_categories`
2. `arsenal_items`
3. `arsenal_item_courses`
4. `arsenal_item_students`
5. `arsenal_access_logs`

Objetivo de cada tabela:

1. `arsenal_categories`: categorias do acervo por empresa.
2. `arsenal_items`: itens do acervo (arquivo/link, status, escopo, janela de publicacao).
3. `arsenal_item_courses`: vinculo N:N entre item e curso.
4. `arsenal_item_students`: vinculo N:N entre item e aluno.
5. `arsenal_access_logs`: auditoria de acessos do aluno ao acervo.

### 18.4) Rotas adicionadas

Rotas administrativas:

1. `GET arsenal`
2. `POST arsenal/item/store`
3. `POST arsenal/item/update`
4. `POST arsenal/item/delete`
5. `POST arsenal/category/store`
6. `POST arsenal/category/update`
7. `POST arsenal/category/delete`
8. `POST arsenal/bind/course`
9. `POST arsenal/unbind/course`
10. `POST arsenal/bind/student`
11. `POST arsenal/unbind/student`
12. `GET arsenal/download`

Rotas do portal do aluno:

1. `GET student/arsenal`
2. `GET student/arsenal/open`

### 18.5) Regras de acesso (aluno)

O aluno so visualiza item que atende simultaneamente:

1. item `published`
2. dentro da janela de publicacao (`publish_start_at`/`publish_end_at`)
3. pertence a mesma empresa do aluno
4. regra de escopo:
   - `global`: todos alunos da empresa
   - `course`: aluno matriculado em curso vinculado
   - `student`: aluno explicitamente vinculado ao item

### 18.6) Erro identificado e resolucao

Erro observado no admin e no portal:

1. Mensagem: modulo Arsenal indisponivel/nao habilitado no banco.
2. Causa: migracao do Arsenal nao executada no banco local existente.

Resolucao aplicada:

1. Execucao da migracao: `migrations/20260313_arsenal_digital.sql`.
2. Confirmacao das 5 tabelas `arsenal_*` criadas em `aneo_gestao`.

### 18.7) Validacao executada em 13/03/2026

Validacoes tecnicas realizadas apos ajuste:

1. Lint PHP dos arquivos alterados: sem erro de sintaxe.
2. Confirmacao de tabelas no MySQL local: OK.
3. Smoke HTTP local:
   - `http://localhost/aneo/index.php?route=arsenal` -> `200`
   - `http://localhost/aneo/index.php?route=student%2Farsenal` -> `200`
   - `http://localhost/aneo/index.php?route=student%2Flogin` -> `200`
4. Total de tabelas no banco local apos migracao: `48`.

### 18.8) Checklist de ativacao do Arsenal em qualquer ambiente

Ao atualizar um ambiente ja existente (local/producao), executar nesta ordem:

1. publicar arquivos do codigo do Arsenal
2. executar `migrations/20260313_arsenal_digital.sql`
3. validar acesso admin em `route=arsenal`
4. validar acesso aluno em `route=student/arsenal`
5. revisar permissao de usuarios de suporte para `arsenal` e `arsenal.manage`

### 18.9) Troubleshooting especifico do Arsenal

Se aparecer `Arsenal Digital nao habilitado no banco`:

1. verificar se as 5 tabelas `arsenal_*` existem
2. se nao existirem, rodar a migracao `20260313_arsenal_digital.sql`
3. recarregar tela com `Ctrl + F5`
4. se persistir, reiniciar Apache/PHP-FPM e validar `config.php` do ambiente correto

Se o aluno nao visualizar material esperado:

1. confirmar item com status `published`
2. confirmar janela de publicacao valida
3. confirmar escopo correto (`global/course/student`)
4. confirmar vinculo do curso/aluno quando aplicavel
5. confirmar matricula ativa/concluida do aluno no curso vinculado


---

## 19) Atualizacao complementar (16/03/2026) - Licenciamento anual por empresa

### 19.1) Resumo da entrega

Foi implementada a estrutura inicial de licenciamento por empresa com renovacao anual:

1. Tela administrativa para ativar/renovar licenca com chave.
2. Persistencia de licenca atual por empresa.
3. Historico de eventos de licenca para auditoria.
4. Base de enforcement preparada por configuracao.

### 19.2) Arquivos criados/alterados

Novos arquivos principais:

1. `migrations/20260316_company_licenses.sql`
2. `controllers/LicenseController.php`
3. `models/CompanyLicenseModel.php`
4. `core/LicenseService.php`
5. `views/companies/license.php`

Arquivos alterados para integrar o modulo:

1. `config.php` (bloco `licensing`)
2. `index.php` (rotas `companies/license` e `companies/license/activate`)
3. `views/layouts/app.php` (menu `Cadastro > Licenca`)
4. `core/helpers.php` (hook de enforcement opcional)

### 19.3) Estrutura de banco adicionada

Tabelas criadas pela migracao `20260316_company_licenses.sql`:

1. `company_licenses`: estado atual da licenca por empresa.
2. `company_license_history`: historico de ativacoes/renovacoes e eventos tecnicos.

### 19.4) Configuracao no `config.php`

Bloco de configuracao:

1. `licensing.enabled`: habilita o modulo de licenciamento.
2. `licensing.enforce`: aplica bloqueio de acesso quando a licenca estiver invalida.
3. `licensing.grace_days`: dias de tolerancia apos vencimento.
4. `licensing.fixed_keys`: chaves aceitas na fase inicial.

Exemplo de chave fixa inicial:

`ANEO-LICENCA-2026-BASE`

### 19.5) Fluxo operacional (admin)

1. Acessar `Cadastro > Licenca`.
2. Selecionar a empresa.
3. Informar uma chave valida.
4. Confirmar ativacao/renovacao.
5. Validar status e vencimento na propria tela.
6. Validar o historico gravado em `company_license_history`.

### 19.6) Ativacao em qualquer ambiente

Ordem recomendada:

1. Publicar os arquivos de codigo da funcionalidade.
2. Executar `migrations/20260316_company_licenses.sql`.
3. Ajustar o bloco `licensing` no `config.php`.
4. Validar acesso admin em `route=companies/license`.
5. Testar ativacao com uma chave valida.
6. Somente quando desejado, alterar `licensing.enforce` para `true`.

### 19.7) Observacao de rollout

No estado atual, o modulo pode operar sem bloqueio global enquanto a equipe cadastra e testa licencas:

1. manter `licensing.enabled=true`
2. manter `licensing.enforce=false`
3. apos validacao operacional, habilitar enforcement em producao


---

## 20) Atualizacao complementar (16/03/2026) - Degustacao de Cursos EAD

### 20.1) Resumo da entrega

Foi implementado o fluxo de degustacao para liberar aula ao vivo em data especifica:

1. Tela administrativa para criar acesso rapido de aluno convidado.
2. Geracao automatica de login e senha do portal.
3. Vinculo da degustacao a um curso especifico e a uma data especifica.
4. Revogacao administrativa do acesso criado.
5. Restricao do portal para degustacao (somente `Inicio` e `Aulas ao Vivo`).

### 20.2) Arquivos criados/alterados

Novo arquivo principal:

1. `migrations/20260316_courses_trial_access.sql`
2. `views/courses/trial_access.php`

Arquivos alterados para integrar o fluxo:

1. `controllers/CourseController.php`
2. `controllers/StudentAuthController.php`
3. `core/helpers.php`
4. `index.php`
5. `models/CourseModel.php`
6. `models/StudentPortalModel.php`
7. `views/courses/index.php`
8. `views/layouts/student.php`
9. `views/student_portal/live.php`

### 20.3) Estrutura de banco adicionada

Tabela criada pela migracao `20260316_courses_trial_access.sql`:

1. `course_trial_accesses`: controle de acessos de degustacao por empresa/aluno/curso/data.

Campos relevantes:

1. `access_date`: dia permitido para login.
2. `access_scope`: escopo atual (`live_only`).
3. `status`: `active`, `expired`, `revoked`.
4. `last_login_at`: ultima tentativa/login do acesso de degustacao.

### 20.4) Rotas adicionadas

Rotas administrativas:

1. `GET courses/trial-access`
2. `POST courses/trial-access/store`
3. `POST courses/trial-access/revoke`

### 20.5) Fluxo operacional (admin)

1. Acessar `Cursos EAD > Degustacao`.
2. Informar nome do aluno, curso publicado e data liberada.
3. Clicar em `Criar acesso rapido`.
4. Copiar login e senha exibidos no alerta de sucesso.
5. Entregar credenciais ao aluno convidado.
6. Revogar o acesso quando necessario.

Observacao:

1. A senha e armazenada com hash e nao pode ser lida depois.
2. Se perder a senha, o fluxo recomendado e revogar e criar novo acesso.

### 20.6) Regras de acesso no portal

1. Login de degustacao so e aceito no dia `access_date`.
2. Se a data passar, o acesso e marcado como `expired`.
3. Se o admin revogar, o status vira `revoked` e o login e bloqueado.
4. Durante degustacao, o menu do portal e reduzido para:
   - `Inicio`
   - `Aulas ao Vivo`
5. Rotas fora desse escopo redirecionam para `student/live`.

### 20.7) Permissao administrativa

Para operar degustacao no admin:

1. permissao `courses.enrollment` (mesma familia de permissao de matriculas)

### 20.8) Ativacao em qualquer ambiente

Ordem recomendada:

1. Publicar os arquivos de codigo da funcionalidade.
2. Executar `migrations/20260316_courses_trial_access.sql`.
3. Validar menu `Cursos EAD > Degustacao`.
4. Criar um acesso de teste para data atual.
5. Validar login no portal com o usuario criado.

## 21) LMS modular (modulos + aulas + regra de 70%)

### 21.1) Objetivo

Disponibilizar cursos sob demanda no portal do aluno com:

1. estrutura por modulo e aula;
2. bloqueio de progressao por ordem de modulo;
3. regra minima de conclusao por aula em video (padrao `70%`).

### 21.2) Arquivos criados/alterados

Novo arquivo principal:

1. `migrations/20260317_lms_learning_path.sql`
2. `views/student_portal/course_player.php`

Arquivos alterados:

1. `controllers/CourseController.php`
2. `controllers/StudentPortalController.php`
3. `index.php`
4. `models/CourseModel.php`
5. `models/StudentPortalModel.php`
6. `views/courses/form.php`
7. `views/student_portal/courses.php`
8. `database.sql`

### 21.3) Estrutura de banco adicionada

Tabelas novas (migration `20260317_lms_learning_path.sql`):

1. `course_modules`: modulos por curso e ordem de exibicao.
2. `course_lessons`: aulas por modulo (video, percentual minimo, obrigatoriedade).
3. `student_lesson_progress`: progresso por aluno/aula (segundos assistidos, percentual, conclusao).

### 21.4) Rotas adicionadas

Portal do aluno:

1. `GET student/course`
2. `POST student/course/progress`

Admin (Cursos EAD):

1. `POST courses/modules/store`
2. `POST courses/modules/update`
3. `POST courses/modules/delete`
4. `POST courses/lessons/store`
5. `POST courses/lessons/update`
6. `POST courses/lessons/delete`

### 21.5) Fluxo operacional

Admin:

1. Acessar `Cursos EAD > Editar curso`.
2. Ir para bloco `Trilha LMS (Modulos e Aulas)`.
3. Criar modulos com ordem.
4. Criar aulas com URL de video e `% minimo` (padrao 70).

Aluno:

1. Acessar `Meus Cursos`.
2. Clicar em `Continuar curso`.
3. Assistir a aula no player.
4. O sistema salva progresso automaticamente e libera o proximo modulo quando aplicavel.

### 21.6) Regras de progressao

1. O primeiro modulo inicia desbloqueado.
2. O modulo seguinte so fica desbloqueado quando o modulo anterior for concluido.
3. Aula concluida quando `progress_percent >= min_progress_percent`.
4. O progresso da matricula (`enrollments.progress_percent`) e sincronizado automaticamente conforme avancos das aulas.

### 21.7) Observacao tecnica

1. O tracking automatico de progresso depende de URL direta de video (exemplo: MP4/WebM).

### 21.8) Formato da URL de video (importante)

1. A URL da aula deve ser HTTP/HTTPS e apontar para o arquivo de video diretamente.
2. Exemplo local (XAMPP):
   - `http://localhost/aneo/uploads/videos/aula-01.mp4`
3. Exemplo em producao:
   - `https://seu-dominio.com/uploads/videos/aula-01.mp4`
4. Caminho local de disco nao e suportado no campo da aula:
   - `C:\...`
   - `file:///...`
5. Link de pagina do YouTube nao e suportado no player atual:
   - `https://youtube.com/watch?...`
6. Validacao rapida:
   - se a URL tocar o video diretamente em uma aba do navegador, ela e valida para o LMS.

## 22) Atualizacao complementar (17/03/2026) - Historico Academico em aba separada

### 22.1) Objetivo

Separar o documento de historico escolar da tela de provas, mantendo `Avaliacoes` focada em execucao/resultado de exames e criando uma tela formal para emissao e impressao.

### 22.2) Arquivos criados/alterados

Arquivo novo:

1. `views/student_portal/academic_history.php`

Arquivos alterados:

1. `controllers/StudentPortalController.php`
2. `models/StudentPortalModel.php`
3. `views/student_portal/exams.php`
4. `views/layouts/student.php`
5. `index.php`

### 22.3) Rotas

Portal do aluno:

1. `GET student/academic-history`

### 22.4) Estrutura da tela

1. Cabecalho institucional (modelo de historico escolar).
2. Dados cadastrais do aluno:
   - nome
   - RA
   - RG
   - data de nascimento
   - e-mail e telefone
3. Disciplinas agrupadas por periodo semestral, com:
   - C/H
   - media final
   - faltas
   - situacao final
4. Rodape com:
   - descricao institucional
   - area de assinatura do responsavel ANEO
   - area para carimbo oficial ANEO

### 22.5) Impressao A4

1. Botao `Imprimir A4` na propria tela.
2. Estilo `@page` e `@media print` para formatacao em folha A4.
3. Impressao restrita ao bloco do historico (sem menu/header do portal).

### 22.6) Fonte dos dados

1. Perfil do aluno: tabela `students`.
2. Resultados: `exam_results`, `exams` e `courses`.
3. Carga horaria consolidada: dados de `courses` vinculados ao aluno.

### 22.7) Ativacao

1. Publicar arquivos.
2. Limpar cache do navegador (`Ctrl+F5`).
3. Acessar `index.php?route=student/academic-history`.
4. Validar impressao A4 e bloco de carimbo/assinatura.
