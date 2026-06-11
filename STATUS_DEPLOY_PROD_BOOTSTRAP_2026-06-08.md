# Status bootstrap producao ANEO - 2026-06-08

## Objetivo

Criar a nova instancia de producao em paralelo usando o subdominio:

```text
https://aneo.aneobrasil.com.br
```

HML preservado:

```text
https://erp-hml.aneobrasil.com.br
```

## Acoes executadas

- Validado DNS do novo subdominio apontando para `149.62.37.84`.
- Validado HTTPS ativo no novo subdominio.
- Confirmada pasta criada pela Hostinger:
  - `/home/u674156040/domains/aneo.aneobrasil.com.br/public_html`
- Feito backup da pagina padrao da Hostinger:
  - `/home/u674156040/domains/aneo.aneobrasil.com.br/deploy_backups/bootstrap_before_app_20260608_174912`
- Copiado o nucleo da aplicacao HML validada para a nova pasta.
- Nao copiados:
  - `config.local.php` do HML
  - dumps SQL
  - backups antigos
  - arquivos de debug
- Criado `config.local.php` novo para producao.
- Importado apenas o schema do banco HML para o banco novo, sem dados.

## Banco de producao

- Banco: `u674156040_aneo_prd`
- Schema importado: 92 tabelas.
- Dados operacionais: zerados.

Contagens verificadas:

- `users`: 0
- `companies`: 0
- `students`: 0
- `invoices`: 0
- `api_tokens`: 0
- `support_tickets`: 0

## Configuracao ativa

- `app.public_url`: `https://aneo.aneobrasil.com.br`
- `app.base_url`: `https://aneo.aneobrasil.com.br`
- Banco apontando para producao nova.
- `chatwoot.enabled`: 0
- `d4sign.enabled`: 0
- `cron.enabled`: 1, endpoint protegido por token e sem jobs ativos
- `smtp.enabled`: 0
- Itau nao ativado.

## Smoke HTTP

- `https://aneo.aneobrasil.com.br/` -> 200
- `https://aneo.aneobrasil.com.br/index.php?route=login` -> 200
- `https://aneo.aneobrasil.com.br/index.php?route=student/login` -> 200
- `https://aneo.aneobrasil.com.br/support.php?route=support/login` -> 200
- `https://aneo.aneobrasil.com.br/api.php` -> 400 esperado sem recurso/token
- HML login continuou respondendo 200.

## URLs amigaveis

Criado `.htaccess` somente na nova producao, mantendo as URLs antigas funcionando.

- `https://aneo.aneobrasil.com.br/admin` -> 200
- `https://aneo.aneobrasil.com.br/aluno` -> 200
- `https://aneo.aneobrasil.com.br/portaldoaluno` -> 200
- `https://aneo.aneobrasil.com.br/suporte` -> 200

## PWA diretoria

Novo subdominio criado e publicado:

```text
https://diretoria.aneobrasil.com.br
```

Build publicado apontando para:

```text
https://aneo.aneobrasil.com.br/api.php
```

Validacoes:

- `https://diretoria.aneobrasil.com.br/` -> 200
- `https://diretoria.aneobrasil.com.br/manifest.webmanifest` -> 200
- `https://diretoria.aneobrasil.com.br/sw.js` -> 200
- Asset principal sem referencia a `erp-hml.aneobrasil.com.br`.
- `https://aneo.aneobrasil.com.br/api.php` -> 400 esperado sem recurso/token.
- Login tecnico do PWA via `mobile-auth` validado com usuario admin.
- Consulta autenticada `students` validada retornando lista vazia, esperado para base limpa.
- Token gerado no teste tecnico foi removido apos validacao.

Backup da pagina padrao Hostinger:

- `/home/u674156040/domains/diretoria.aneobrasil.com.br/deploy_backups/pwa_before_20260608_203054`

## Cron

Cron preparado tecnicamente, mas sem agendamento automatico ativo no Hostinger.

Estado:

- `cron.enabled`: 1
- `cron.secret_token`: configurado
- `automation.enabled`: 0
- `smtp.enabled`: 0
- `d4sign.enabled`: 0
- `bank_slip.enabled`: 0

Jobs cadastrados em `cron_jobs`, todos desativados:

- `finance_billing_notifications`
- `boleto_issue_due`
- `boleto_sync`
- `signatures_sync`

Validacoes:

- `https://aneo.aneobrasil.com.br/cron.php?job=all` sem token -> 401
- chamada com token real -> 200
- nenhum job ficou habilitado
- nenhum agendamento automatico foi criado

Proximo passo antes de ativar execucao real:

1. Configurar SMTP e testar envio controlado.
2. Validar dados reais de empresa/alunos/faturas.
3. Ativar um job por vez.
4. Criar agendamento no Hostinger somente depois do aceite.

## Validacoes tecnicas

- `php -l` OK:
  - `index.php`
  - `api.php`
  - `support.php`
  - `cron.php`
  - `config.local.php`

## Pendencia imediata

Empresa e usuario administrador inicial criados.

Dados criados:

- Empresa: `ANEO Brasil`
- CNPJ provisório: `00.000.000/0001-00`
- Usuario: `admin`
- E-mail: `admin@aneobrasil.com.br`
- Perfil: `admin`

Proximo passo seguro:

1. Logar na nova URL.
2. Trocar a senha do usuario administrador inicial.
3. Ajustar o CNPJ/dados oficiais da empresa antes de qualquer uso fiscal, contrato, boleto ou cobranca.
4. Validar telas principais sem cadastrar dados reais ainda.
5. Trocar/rotacionar a senha do banco antes do go-live final, pois ela apareceu em print durante a criacao.

## Observacao

HML nao foi alterado. A nova producao esta isolada em pasta e banco proprios.
