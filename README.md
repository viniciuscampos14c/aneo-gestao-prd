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
5. Se o banco ja existia antes das ultimas versoes, execute tambem:
   - `migrations/20260313_arsenal_digital.sql`
   - `migrations/20260316_company_licenses.sql`
   - `migrations/20260316_courses_trial_access.sql`
   - `migrations/20260317_lms_learning_path.sql`
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
7. Confirmar login na central tecnica (`support.php`).
8. Abrir `route=companies/license` e validar status/licenca da empresa.
9. Abrir `route=courses/trial-access` e criar um acesso de degustacao.
10. Validar login no portal do aluno com o usuario de degustacao no dia liberado.

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
