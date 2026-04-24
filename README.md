# ANEO Gestao Integrada (PHP + MySQL + Tailwind)

Sistema MVC em PHP puro para operacao administrativa, portal do aluno e central tecnica.

Documento detalhado: `DOCUMENTACAO_COMPLETA.md`.
Relatorio de validacao anterior: `VALIDACAO_SISTEMA_2026-03-11.md`.

## 1) Estado Atual

### 1.1) Base validada em 11/03/2026

1. Menu lateral administrativo atualizado:
   - removidos: `Projetos`, `Tarefas`
   - renomeados: `Solicitacoes`, `Automacoes`, `Chat IA Jully`
2. Rotas de `projects` e `tasks` desativadas em `index.php` para bloquear acesso por URL direta.
3. Titulos internos alinhados com a nova nomenclatura.

### 1.2) Atualizacao complementar em 13/03/2026

1. Modulo `Arsenal Digital` implementado (admin + portal do aluno).
2. Menus atualizados:
   - painel admin: item `Arsenal Digital`
   - portal do aluno: aba `Arsenal`
3. Novas permissoes:
   - modulo: `arsenal`
   - funcao: `arsenal.manage`
4. Novas rotas:
   - admin: `arsenal`, `arsenal/item/*`, `arsenal/category/*`, `arsenal/bind/*`, `arsenal/unbind/*`, `arsenal/download`
   - aluno: `student/arsenal`, `student/arsenal/open`
5. Migracao criada e aplicada no local:
   - `migrations/20260313_arsenal_digital.sql`
6. Correcao de ambiente local:
   - o `localhost/aneo` executa em `C:\xampp\htdocs\aneo`
   - o repositorio fonte esta em `...\Estudos\Projeto ANEO\public_html`

### 1.3) Atualizacao complementar em 16/03/2026

1. Novo modulo `Licenca` em `Cadastro` (somente administrador).
2. Fluxo de ativacao/renovacao anual por chave.
3. Estrutura de banco adicionada:
   - `migrations/20260316_company_licenses.sql`
   - tabelas `company_licenses` e `company_license_history`
4. Rotas administrativas adicionadas:
   - `GET companies/license`
   - `POST companies/license/activate`
5. Modo de enforcement preparado no `config.php`:
   - `licensing.enabled`
   - `licensing.enforce`
   - `licensing.grace_days`
   - `licensing.fixed_keys`

### 1.4) Atualizacao complementar em 16/03/2026

1. Nova opcao `Degustacao` dentro de `Cursos EAD`.
2. Tela para criar acesso rapido de aluno convidado com:
   - curso especifico
   - data especifica de acesso
   - login/senha gerados automaticamente
3. Migracao criada:
   - `migrations/20260316_courses_trial_access.sql`
4. Rotas adicionadas:
   - `GET courses/trial-access`
   - `POST courses/trial-access/store`
   - `POST courses/trial-access/revoke`
5. Restricoes no portal para degustacao:
   - acesso permitido somente no dia liberado
   - navegacao limitada a `Inicio` e `Aulas ao Vivo`
6. Permissao exigida no admin:
   - `courses.enrollment`

### 1.5) Atualizacao complementar em 17/03/2026

1. Trilha LMS modular implementada em `Cursos EAD`:
   - cadastro de `modulos` e `aulas` por curso
   - ordenacao por modulo e por aula
2. Portal do aluno com player por aula:
   - rota `GET student/course`
   - tracking backend `POST student/course/progress`
3. Regras aplicadas:
   - bloqueio do proximo modulo ate concluir o anterior
   - conclusao de aula por percentual minimo (padrao `70%`)
4. Migracao criada:
   - `migrations/20260317_lms_learning_path.sql`
5. Tabelas novas:
   - `course_modules`
   - `course_lessons`
   - `student_lesson_progress`

### 1.6) Atualizacao complementar em 17/03/2026

1. Chamados passaram a usar identificador unico sequencial:
   - formato: `ANEO001`, `ANEO002`, ...
2. Exibicao do codigo reforcada nas telas:
   - `Solicitacoes` (admin)
   - `Central Tecnica` (suporte)
3. Portal do aluno com abertura de chamados:
   - nova aba `Chamados`
   - rotas `GET student/requests` e `POST student/requests/store`
4. Migracao de padronizacao criada:
   - `migrations/20260317_support_ticket_codes_aneo.sql`

### 1.7) Atualizacao complementar em 17/03/2026

1. Historico Academico movido para aba propria no Portal do Aluno.
2. Nova rota:
   - `GET student/academic-history`
3. Tela `Avaliacoes` permanece focada em provas e resultados.
4. Nova tela `Historico Academico` com:
   - layout formal inspirado em historico escolar
   - periodos semestrais com disciplinas e pontuacoes
   - bloco de descricao e area para carimbo/assinatura ANEO
   - impressao otimizada em A4
5. Nao exige migracao de banco (reaproveita dados de aluno e `exam_results`).

### 1.8) Atualizacao complementar em 17/03/2026

1. Novo perfil `Professor` para acesso administrativo restrito.
2. Permissoes do perfil professor:
   - `Dashboard`
   - `Alunos` (criar/editar)
   - `Cursos EAD` (criar/editar/categorias/matriculas/exames/comentarios)
3. Prova externa por aluno em `Cursos EAD > Exames`:
   - professor vincula URL externa (ex.: Microsoft Forms / Google Forms) por aluno
   - opcional de prazo e instrucoes
   - controle de status e desativacao do vinculo
4. Portal do aluno:
   - exibicao de botao `Abrir prova externa` quando houver vinculo ativo
   - registro de acessos ao link externo para acompanhamento
   - bloqueio de resposta interna quando a avaliacao for externa
5. Migracao criada:
   - `migrations/20260317_professor_external_exam_links.sql`

### 1.9) Atualizacao complementar em 16/04/2026

1. **Sistema de API REST** com tokens e permissoes granulares por recurso.
2. Novo entry point `api.php` — autenticacao Bearer Token, CORS habilitado.
3. Recursos disponibilizados via API:

   | Recurso    | GET list | GET :id | POST | PUT :id | DELETE :id |
   |-----------|:--------:|:-------:|:----:|:-------:|:----------:|
   | students  | sim      | sim     | sim  | sim     | sim        |
   | leads     | sim      | sim     | sim  | sim     | sim        |
   | invoices  | sim      | sim     | sim  | —       | sim        |
   | courses   | sim      | sim     | —    | —       | —          |
   | users     | sim      | sim     | —    | —       | —          |
   | tickets   | sim      | sim     | sim  | —       | —          |

4. Permissoes granulares por token (`get`, `search`, `create`, `update`, `delete`).
5. Gerenciamento de tokens no admin:
   - menu `API > Gerenciamento de API` (apenas admin)
   - menu `API > Manual da API`
6. Token exibido apenas uma vez na criacao (hash SHA-256 armazenado).
7. Suporte a expiracao de token e rastreamento de `last_used_at`.
8. BCC automatico no Financeiro:
   - emails de cobranca enviados ao aluno geram copia para `financeiro@aneobrasil.com.br`
   - configuravel em `config.php` → `automation.finance_bcc_email`
9. `EmailService` atualizado com suporte a BCC via mail() e SMTP manual.
10. Migracao criada:
    - `migrations/20260416_api_tokens.sql`

### 1.10) Como usar a API REST

**Base URL:** `https://erp-hml.aneobrasil.com.br/api.php`

**Autenticacao:**
```
Authorization: Bearer SEU_TOKEN
```

**Exemplos:**
```bash
# Listar alunos
curl -H "Authorization: Bearer TOKEN" "https://erp-hml.aneobrasil.com.br/api.php?r=students"

# Criar lead
curl -X POST \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"full_name":"Joao Silva","email":"joao@email.com","phone":"11999999999"}' \
  "https://erp-hml.aneobrasil.com.br/api.php?r=leads"
```

**Formato de resposta:**
```json
{
  "ok": true,
  "data": [...],
  "meta": { "total": 50, "page": 1, "per_page": 50, "pages": 1 }
}
```

Documentacao completa: `index.php?route=api-management/manual` (admin logado).

### 1.11) Atualizacao complementar em 16/04/2026

1. **Player LMS com suporte a YouTube:**
   - `course_player.php` detecta automaticamente URLs do YouTube (`youtube.com/watch`, `youtu.be`, `youtube.com/shorts`).
   - Links do YouTube renderizam via YouTube IFrame API com player embed responsivo (aspect ratio 16:9).
   - Tracking de progresso funciona em ambos os formatos: YouTube (polling a cada 2 s via IFrame API) e MP4/WebM (evento `timeupdate`).
   - Posicao de retomada e bloqueio de modulo por percentual minimo (70%) funcionam igual para YouTube e MP4.
   - Campo "URL do video" no admin agora aceita qualquer formato.

2. **Deploy sem sobrescrever credenciais de producao:**
   - `bootstrap.php` carrega `config.local.php` (se existir) e aplica `array_replace_recursive` sobre `config.php`.
   - `config.local.php` reside apenas no servidor (nunca versionado, listado no `.gitignore`).
   - Deploys futuros via `pscp` podem sincronizar `config.php` livremente sem risco de sobrescrever as credenciais de producao.

### 1.12) Atualizacao complementar em 16/04/2026

1. **Modo escuro (dark mode) no Portal do Aluno e Central Tecnica:**
   - Botao de alternancia (sol/lua) no cabecalho das duas aplicacoes.
   - Preferencia salva em `localStorage` — persiste entre sessoes.
   - Script anti-flash no `<head>` aplica o tema antes de qualquer renderizacao, evitando pisca branco.
   - Todas as superficies (fundo, cards, inputs, navegacao, bordas, textos) respondem corretamente ao modo escuro via `html.dark` + CSS em `assets/css/app.css`.
   - **Excecao documentada:** a tela de `Historico Academico` mantém fundo branco em modo escuro para preservar a aparencia de documento impresso formal (`#academic-history-paper`).
   - Versao do CSS incrementada para `?v=5` para invalidar cache nos browsers.

### 1.13) Atualizacao complementar em 17/04/2026

1. **Sistema de Cron Jobs interno** implementado.
2. Novo entry point `cron.php` — autenticado por token (`cron.secret_token` em `config.local.php`).
3. Jobs disponíveis:

   | Job                              | Descricao                                                  |
   |----------------------------------|------------------------------------------------------------|
   | `finance_billing_notifications`  | Envia e-mails de cobranca para faturas proximas/vencidas   |
   | `boleto_sync`                    | Sincroniza status de boletos pendentes com o provedor      |
   | `signatures_sync`                | Atualiza status de contratos pendentes no D4Sign           |

4. Execucao via URL (para Cron Hostinger):
   ```
   curl "https://erp-hml.aneobrasil.com.br/cron.php?token=SEU_TOKEN&job=all"
   ```
5. Painel admin em `Cadastro → Cron Jobs`:
   - Visualiza status, ultima execucao, duracao e mensagem de cada job.
   - Botao "Executar" para acionar manualmente via AJAX.
   - Botao "Logs" para ver historico das ultimas 30 execucoes.
   - Ativar/desativar jobs individualmente.
6. Novas tabelas de banco:
   - `cron_jobs` — registro e estado de cada job
   - `cron_job_logs` — historico completo de execucoes
7. Migracao criada:
   - `migrations/20260417_cron_jobs.sql`
8. Configurar na Hostinger (hPanel → Avancado → Cron Jobs):
   ```
   0 * * * *   curl -s "https://erp-hml.aneobrasil.com.br/cron.php?token=SEU_TOKEN&job=all" > /dev/null 2>&1
   ```
   O token correto esta em `config.local.php` no servidor, chave `cron.secret_token`.

### 1.14) Atualizacao complementar em 22/04/2026

1. **Reestilizacao moderna no administrativo (Plano A):**
   - novo visual escuro como padrao no layout administrativo
   - modo claro com alternancia por botao no header
   - persistencia de preferencia em `localStorage` com a chave `aneo_admin_theme`
   - cache-busting de CSS via `filemtime` em `views/layouts/app.php`
2. **Ajustes de layout e navegacao:**
   - correcao de espaco lateral indevido no conteudo principal
   - correcao de faixa clara no topo/fundo com reset de `html, body`
   - menus flutuantes de `Cadastro` e `API` reposicionados e sem clipping
3. **Melhorias de contraste no modo claro (admin):**
   - ajuste de textos `text-slate-*` para evitar baixa legibilidade
   - refinamento visual do rodape da sidebar com cards de Empresa e Usuario
4. **Replicacao dos dois modelos para Portais:**
   - Portal do Aluno e Central Tecnica passaram a usar o mesmo padrao de alternancia claro/escuro
   - persistencia compartilhada em `localStorage` com a chave `aneo_portal_theme`
   - padrao inicial dos portais ajustado para modelo escuro (com opcao de claro)
   - novos wrappers visuais: `portal-modern-shell`, `portal-modern-ambient`, `portal-modern-content`
5. **Arquivos principais impactados:**
   - `public_html/views/layouts/app.php`
   - `public_html/views/layouts/student.php`
   - `public_html/views/layouts/support_desk.php`
   - `public_html/assets/css/app.css`
   - `public_html/assets/js/app.js`
   - `public_html/views/dashboard/index.php`
   - `public_html/views/users/index.php`

## 2) Estrutura Real do Projeto

```txt
aneo/
|-- index.php
|-- support.php
|-- config.php
|-- db.php
|-- database.sql
|-- README.md
|-- DOCUMENTACAO_COMPLETA.md
|-- controllers/
|-- core/
|-- models/
|-- views/
|-- assets/
|-- uploads/
`-- migrations/
```

Importante:

1. No ambiente local (XAMPP), o projeto em execucao esta em `C:\xampp\htdocs\aneo`.
2. Na Hostinger, o conteudo desta pasta deve ser enviado para a raiz web do dominio/subdominio (normalmente `public_html`).

## 3) URLs das 3 Aplicacoes

1. Administrativo:
   - `index.php?route=login`
2. Portal do Aluno:
   - `index.php?route=student/login`
3. Central Tecnica:
   - `support.php?route=support/login`

Exemplo local:

1. `http://localhost/aneo/index.php?route=login`
2. `http://localhost/aneo/index.php?route=student/login`
3. `http://localhost/aneo/support.php?route=support/login`

## 4) Instalacao Rapida (Local)

1. Copie a pasta do projeto para `C:\xampp\htdocs\aneo`.
2. Inicie Apache e MySQL no XAMPP.
3. Crie o banco MySQL.
4. Importe `database.sql`.
5. Se o banco ja existia antes das ultimas versoes, execute tambem (em ordem):
   - `migrations/20260313_arsenal_digital.sql`
   - `migrations/20260315_finance_notification_logs.sql`
   - `migrations/20260315_system_audit_logs.sql`
   - `migrations/20260316_company_licenses.sql`
   - `migrations/20260316_courses_trial_access.sql`
   - `migrations/20260317_lms_learning_path.sql`
   - `migrations/20260317_support_ticket_codes_aneo.sql`
   - `migrations/20260317_professor_external_exam_links.sql`
   - `migrations/20260416_api_tokens.sql`
   - `migrations/20260424_finance_payment_methods.sql`
6. Ajuste credenciais em `config.php` (bloco `db`).
7. Acesse `http://localhost/aneo/index.php?route=login`.

Login admin padrao:

1. Usuario: `admin`
2. Senha: `admin123`

Conta de aluno validada no ambiente local:

1. Login: `enzo`
2. Senha: `123456`

## 5) Validacao Tecnica

### 5.1) Validacao base (11/03/2026)

1. Lint PHP completo:
   - `102` arquivos verificados
   - `0` erros de sintaxe
2. Rotas mapeadas para controllers:
   - `125` rotas validadas
   - `0` metodos ausentes
3. Renderizacao de views:
   - `46` chamadas `render(...)` verificadas
   - `0` views ausentes
4. Banco de dados:
   - conexao OK (`SELECT 1`)
   - `43` tabelas encontradas na data
5. Smoke test HTTP:
   - login admin: OK
   - selecao de empresa: OK
   - dashboard admin: OK
   - portal do aluno: login OK
   - central tecnica: login OK
6. Rotas bloqueadas por regra de negocio:
   - `projects`: `404`
   - `tasks`: `404`

### 5.2) Validacao complementar (13/03/2026)

1. Migracao `20260313_arsenal_digital.sql` executada com sucesso.
2. Tabelas `arsenal_*` criadas:
   - `arsenal_categories`
   - `arsenal_items`
   - `arsenal_item_courses`
   - `arsenal_item_students`
   - `arsenal_access_logs`
3. Total de tabelas no banco local apos migracao: `48`.
4. Smoke HTTP local:
   - `route=arsenal`: `200`
   - `route=student/arsenal`: `200`
   - `route=student/login`: `200`

## 6) Status das Integracoes

Status identificado em `config.php` (ambiente local atual):

1. Chatwoot:
   - `enabled=true`
   - `configured=true`
2. Assistente IA:
   - `enabled=true`
3. D4Sign:
   - `enabled=true` (sandbox)
4. Automacao de entrada (webhook n8n):
   - `enabled=true`
5. Fiscal (NF-e):
   - `enabled=false`
6. Boleto:
   - `enabled=false`
7. Webhook externo de chamados:
   - `enabled=false`

## 7) Publicacao na Hostinger (simples para 3 apps)

### 7.1) Base unica de codigo

Use uma unica instalacao do sistema e publique o conteudo desta pasta no `public_html` (ou raiz do subdominio).

### 7.2) Mapeamento recomendado

1. `app.seudominio.com` -> administrativo
2. `aluno.seudominio.com` -> portal do aluno
3. `suporte.seudominio.com` -> central tecnica

### 7.3) Passos objetivos

1. Criar banco MySQL na Hostinger e importar `database.sql`.
2. Publicar arquivos do projeto no `public_html`.
3. Editar `config.php` com banco de producao.
4. Se producao for incremental, executar migracoes pendentes (incluindo `20260313_arsenal_digital.sql` e `20260316_company_licenses.sql`).
   - incluir `migrations/20260316_courses_trial_access.sql` para liberar degustacao de curso
   - incluir `migrations/20260317_lms_learning_path.sql` para liberar trilha LMS modular no portal
   - incluir `migrations/20260317_support_ticket_codes_aneo.sql` para normalizar codigos de chamados no formato ANEO
   - incluir `migrations/20260317_professor_external_exam_links.sql` para perfil professor e provas externas por aluno
5. Ajustar permissoes de escrita para `uploads/*`.
6. Configurar SSL.
7. Rodar smoke test nas 3 aplicacoes.

## 8) Modulo Arsenal Digital

Funcionalidades entregues:

1. Gestao de itens de acervo (arquivo/link).
2. Categorias para organizacao do acervo.
3. Publicacao por status e janela de datas.
4. Visibilidade por escopo:
   - `global`
   - `course`
   - `student`
5. Vinculos item x curso e item x aluno.
6. Download/abertura de material no admin.
7. Acesso do aluno ao acervo no portal.
8. Log de acesso de aluno (`arsenal_access_logs`).

Arquivos principais:

1. `controllers/ArsenalController.php`
2. `models/ArsenalModel.php`
3. `views/arsenal/index.php`
4. `controllers/StudentPortalController.php` (rotas do aluno)
5. `models/StudentPortalModel.php` (consulta + log)
6. `views/student_portal/arsenal.php`

## 9) Checklist Pre-Demo

1. Login admin e selecao de empresa.
2. Menu lateral com `Solicitacoes`, `Automacoes`, `Chat IA Jully`.
3. Confirmar `projects` e `tasks` retornando 404.
4. Abrir `route=arsenal` e validar abas (Itens/Categorias/Vinculos/Acessos).
5. Criar item e testar abertura no admin.
6. Fazer login do aluno e validar `route=student/arsenal`.
7. Validar abertura de chamado no portal em `route=student/requests`.
8. Confirmar login na central tecnica (`support.php`).
9. Confirmar visualizacao do codigo `ANEO...` em `route=requests` e `support.php?route=support`.
10. Abrir `route=companies/license` e validar status/licenca da empresa.
11. Abrir `route=courses/trial-access` e criar um acesso de degustacao.
12. Validar login no portal do aluno com o usuario de degustacao no dia liberado.
13. Validar aba `Historico Academico` em `route=student/academic-history`.
14. Acionar `Imprimir A4` e conferir bloco de carimbo/assinatura.
15. Criar usuario `Professor`, acessar `Cursos EAD > Exames`, vincular prova externa e validar abertura no portal do aluno.

## 10) Seguranca (obrigatorio antes de producao)

1. Trocar senha do admin.
2. Rotacionar chaves/tokens de API.
3. Nao manter credenciais reais versionadas no repositorio.
4. Validar backup automatico de banco e `uploads`.

## 11) Automacao n8n (Cloud Trial ou Community)

Foi adicionada rota publica para n8n ativar entrada automatica de aluno:

1. Endpoint ANEO:
   - `POST index.php?route=automations/webhook/enrollment`
2. Token de seguranca:
   - `automation.enrollment_webhook_token` em `config.php`
   - enviar no header `X-ANEO-TOKEN` (ou `token` na query/body)
3. Regra de disparo (padrao):
   - `payment_status` aprovado (`confirmed|received|paid`)
   - `contract_status` assinado (`signed|completed|concluded|done`)
   - ou `force_activate=true`

Payload minimo:

```json
{
  "company_id": 1,
  "lead_id": 10,
  "course_id": 3,
  "payment_status": "confirmed",
  "contract_status": "signed",
  "create_portal_account": true,
  "portal_login": "aluno.teste@exemplo.com",
  "portal_password": "123456"
}
```

Resultado esperado:

1. Converte lead em aluno (se ainda nao convertido).
2. Ativa aluno.
3. Cria/atualiza matricula no curso informado.
4. Cria/atualiza acesso ao portal do aluno (quando solicitado).

Arquivos de apoio para n8n:

1. `n8n/README_N8N_CLOUD_TESTE.md`
2. `n8n/payload_teste_enrollment.json`

## 12) Licenciamento anual por empresa

Objetivo: habilitar o uso do sistema por empresa com validade anual de chave.

1. Migracao obrigatoria:
   - `migrations/20260316_company_licenses.sql`
2. Tela administrativa:
   - `Cadastro > Licenca`
   - disponivel apenas para perfil `admin`
3. Configuracao no `config.php`:
   - `licensing.enabled`: liga/desliga o modulo
   - `licensing.enforce`: bloqueia acesso de empresa sem licenca valida
   - `licensing.grace_days`: dias extras apos vencimento
   - `licensing.fixed_keys`: chaves fixas aceitas na fase inicial
4. Comportamento da ativacao:
   - ao ativar com chave valida, a licenca recebe `365` dias
   - renovacao com chave valida estende a data de vencimento
5. Historico e auditoria:
   - eventos salvos em `company_license_history`
   - alteracoes tambem registradas no log de auditoria (`cadastro.licenca`)

Exemplo de chave fixa inicial (ambiente interno):

`ANEO-LICENCA-2026-BASE`

## 13) Degustacao em Cursos EAD

Objetivo: liberar acesso rapido para demonstracao de uma aula ao vivo, sem liberar o portal completo.

1. Tela administrativa:
   - `Cursos EAD > Degustacao`
   - rota: `index.php?route=courses/trial-access`
2. Fluxo de criacao:
   - informar nome do aluno
   - selecionar curso publicado
   - informar data de liberacao
   - sistema gera login e senha automaticamente
3. Persistencia:
   - tabela `course_trial_accesses` (migration `20260316_courses_trial_access.sql`)
4. Regra de acesso no portal:
   - login permitido apenas na data cadastrada
   - se expirar/revogar, bloqueia novo acesso
   - menu restrito a `Inicio` e `Aulas ao Vivo`
5. Operacao:
   - credencial e senha sao exibidas no alerta apos criacao
   - senha nao pode ser lida depois (fica em hash), apenas recriar/revogar acesso

## 14) LMS modular (Portal do Aluno)

Objetivo: permitir cursos sob demanda com controle de progressao.

1. Cadastro no admin:
   - `Cursos EAD > Editar curso > Trilha LMS (Modulos e Aulas)`
   - cadastrar modulos em ordem
   - cadastrar aulas por modulo, com URL de video e percentual minimo (padrao `70`)
2. Regras de progressao:
   - modulo 1 inicia desbloqueado
   - modulo seguinte so libera quando o modulo anterior estiver concluido
   - aula concluida quando `progress_percent >= min_progress_percent`
3. Tracking backend:
   - rota `POST index.php?route=student/course/progress`
   - atualiza `student_lesson_progress`
   - sincroniza `enrollments.progress_percent` automaticamente
4. Observacao tecnica:
   - o tracking automatico depende de video com URL direta (ex.: MP4/WebM)
5. Como informar a URL do video:
   - o campo da aula aceita URL HTTP/HTTPS direta do arquivo
   - exemplo local no XAMPP: `http://localhost/aneo/uploads/videos/aula-01.mp4`
   - exemplo em producao: `https://seu-dominio.com/uploads/videos/aula-01.mp4`
6. O que nao funciona no player atual:
   - caminho local de disco (`C:\...` ou `file:///...`)
   - link de pagina do YouTube (`https://youtube.com/watch?...`)
7. Regra pratica de validacao:
   - se a URL abre o video direto no navegador, no curso tambem funciona

## 15) Chamados com codigo ANEO + portal do aluno

Objetivo: padronizar identificacao dos chamados para conversas de suporte e permitir abertura pelo aluno.

1. Codigo do chamado:
   - novo formato sequencial `ANEO` + numero do chamado (minimo 3 digitos)
   - exemplos: `ANEO001`, `ANEO145`, `ANEO1204`
2. Tela admin/suporte:
   - exibem o codigo em destaque
   - origem do chamado inclui `Portal Aluno`
3. Portal do aluno:
   - nova aba `Chamados`
   - aluno abre chamado com assunto, prioridade e descricao
   - historico mostra codigo, status, prioridade e comentarios
4. Migracao de ajuste:
   - `migrations/20260317_support_ticket_codes_aneo.sql`

## 16) Historico Academico no Portal do Aluno

Objetivo: disponibilizar documento formal de desempenho academico em aba separada, pronto para impressao A4.

1. Navegacao:
   - nova aba `Historico Academico` no menu do aluno
   - rota `index.php?route=student/academic-history`
2. Estrutura do documento:
   - cabecalho institucional
   - dados do aluno (nome, RA, RG, contato)
   - periodos semestrais com disciplinas, C/H, media final, faltas e situacao final
   - resumo consolidado (media geral, total de avaliacoes, carga horaria total)
   - descricao institucional
   - campos para assinatura e carimbo da ANEO
3. Impressao:
   - botao `Imprimir A4` na propria tela
   - CSS `@media print` para gerar formato de papel A4
4. Fonte dos dados:
   - `students` para dados cadastrais
   - `exam_results` + `exams` + `courses` para notas e disciplinas
