# Plano de migracao para dominio de producao - ANEO

Data: 2026-05-29

Objetivo: preparar a troca do ambiente com dominio HML para um subdominio de producao, sem quebrar o que ja foi validado e sem misturar homologacao com uso real.

## 1. Decisao principal

O caminho seguro e criar um novo ambiente de producao em paralelo, mantendo o HML funcionando.

- HML atual: `https://erp-hml.aneobrasil.com.br`
- Producao nova: `https://aneo.aneobrasil.com.br`
- PWA diretoria producao: `https://diretoria.aneobrasil.com.br`
- Codigo base: conteudo de `public_html/`
- Configuracao sensivel: `public_html/config.local.php`, criada diretamente no servidor e nunca versionada
- Banco recomendado: banco novo de producao, separado do HML
- Rollback: manter HML intacto e, se necessario, pausar o subdominio de producao sem mexer no ambiente validado

Nao recomendamos apontar o novo dominio de producao para o mesmo banco do HML. Isso pode misturar testes, cadastros reais, cobrancas, notificacoes e execucoes de cron.

## 2. Inventario de pontos impactados

### Servidor e dominio

- Criar subdominio no Hostinger.
- Confirmar pasta raiz criada pelo Hostinger para o subdominio.
- Ativar SSL/HTTPS antes de qualquer validacao funcional.
- Publicar somente os arquivos necessarios do sistema, principalmente o conteudo de `public_html/`.
- Preservar ou recriar permissao de escrita para `uploads/`.
- Nao publicar `node_modules/`, `tests/`, `test-results/`, arquivos temporarios, relatorios locais e previews HTML.

### Configuracao PHP

Arquivo critico no servidor:

```text
public_html/config.local.php
```

Campos que precisam ser ajustados para producao:

- `app.base_url`
- `app.public_url`
- `db.host`
- `db.name`
- `db.user`
- `db.pass`
- `cron.secret_token`
- `admin_ai.http_referer`
- `smtp.*`
- `support.from_email`
- `support.notification_email`
- `support.external_webhook_url`, se usado
- `chatwoot.webhook_token`
- `d4sign.webhook_token`
- `automation.enrollment_webhook_token`
- `automation.finance_webhook_token`
- Configuracoes de Itaú, fiscal e boletos por empresa, se forem ativadas em producao

### URLs internas do sistema

As URLs abaixo devem responder no novo subdominio de producao:

- Admin: `https://aneo.aneobrasil.com.br/admin`
- Portal do aluno: `https://aneo.aneobrasil.com.br/aluno`
- Portal do aluno alternativo: `https://aneo.aneobrasil.com.br/portaldoaluno`
- Suporte: `https://aneo.aneobrasil.com.br/suporte`
- API: `https://aneo.aneobrasil.com.br/api.php`
- Cron: `https://aneo.aneobrasil.com.br/cron.php?token=<TOKEN_PROD>&job=all`

### Webhooks externos

Estes enderecos precisam ser revisados nos provedores externos quando o dominio de producao existir:

- Chatwoot: `https://<SUBDOMINIO-PROD>.aneobrasil.com.br/index.php?route=chatwoot/webhook&token=<TOKEN_CHATWOOT_PROD>`
- D4Sign: `https://<SUBDOMINIO-PROD>.aneobrasil.com.br/index.php?route=signatures/webhook&token=<TOKEN_D4SIGN_PROD>`
- Automacao de matricula: `https://<SUBDOMINIO-PROD>.aneobrasil.com.br/index.php?route=automations/webhook/enrollment&token=<TOKEN_AUTOMACAO_PROD>`
- Automacao financeira: `https://<SUBDOMINIO-PROD>.aneobrasil.com.br/index.php?route=automations/webhook/finance-notifications&token=<TOKEN_FINANCEIRO_PROD>`
- Chamados: `https://<SUBDOMINIO-PROD>.aneobrasil.com.br/index.php?route=requests/webhook`
- Itaú: `https://<SUBDOMINIO-PROD>.aneobrasil.com.br/index.php?route=finance/webhook/itau`

Pendencia tecnica identificada: a rota `finance/webhook/itau` existe em `public_html/index.php`, mas depende de `FinanceController::itauWebhook()`. Nao encontrei esse metodo no controller atual. Antes de habilitar webhook do Itaú em producao, precisamos corrigir ou confirmar essa implementacao.

### Mobile e PWA

Foram encontrados defaults apontando para HML:

- `mobile/aneo-pwa/src/config/constants.ts`
- `mobile/aneo-mobile-app/src/config/constants.ts`

O PWA de producao deve ser buildado apontando para:

```text
https://aneo.aneobrasil.com.br/api.php
```

O caminho seguro e manter a URL configuravel por ambiente via `VITE_ANEO_API_BASE_URL`, evitando troca manual entre HML e producao.

### Testes e documentacao

Os testes HML devem continuar apontando para `https://erp-hml.aneobrasil.com.br`.

Para producao, criar somente smoke tests nao destrutivos:

- Login admin.
- Login aluno.
- Login suporte.
- API health/read simples.
- Abertura de telas principais.
- Cron em job controlado, sem disparo duplicado de cobranca.

## 3. Sequencia segura de execucao

### Fase 0 - Preparacao

- Definir subdominio final de producao.
- Criar banco de dados exclusivo de producao.
- Definir se os dados iniciais serao importados do HML, de dump limpo ou de carga manual.
- Revisar se existem registros QA/teste que nao podem ir para producao.
- Gerar tokens novos para cron e webhooks.
- Preparar `config.local.php` de producao sem reaproveitar tokens fracos ou marcados como HML.

### Fase 1 - Infraestrutura

- Criar subdominio no Hostinger.
- Ativar SSL.
- Confirmar document root exato.
- Criar backup do HML antes de copiar qualquer coisa.
- Publicar arquivos no novo document root.
- Criar `config.local.php` diretamente no servidor de producao.
- Validar permissao de escrita de `uploads/`.

### Fase 2 - Banco

- Criar banco de producao.
- Importar schema e dados aprovados.
- Aplicar migrations pendentes, incluindo a migration financeira de 2026-05-29.
- Validar charset `utf8mb4`.
- Validar tabelas criticas: usuarios, empresas, alunos, financeiro, logs, tokens de API, integracoes e cron.

### Fase 3 - Smoke test fechado

Antes de alterar webhook externo ou ativar cron:

- Abrir tela de login admin.
- Logar e selecionar empresa.
- Abrir dashboard.
- Abrir alunos, cursos, leads e financeiro.
- Abrir portal do aluno.
- Abrir suporte.
- Consultar API com token valido.
- Testar envio de e-mail controlado.
- Verificar logs PHP e logs da aplicacao.

### Fase 4 - Integracoes

Ativar uma integracao por vez:

- SMTP/e-mail.
- Chatwoot.
- D4Sign.
- Automacoes.
- Financeiro/boletos.
- Itaú somente apos resolver a pendencia do endpoint `itauWebhook`.

Depois de cada ativacao, registrar evidencia: hora, tela/endpoint, retorno e log.

### Fase 5 - Cron

Nao ligar cron antes dos smoke tests e integracoes basicas.

Jobs atuais:

- `finance_billing_notifications`
- `boleto_sync`
- `signatures_sync`
- `all`

Recomendacao:

- Primeiro executar manualmente job isolado.
- Depois cadastrar cron com token de producao.
- Evitar rodar HML e producao contra o mesmo banco.

Exemplo de comando Hostinger:

```bash
curl -s "https://<SUBDOMINIO-PROD>.aneobrasil.com.br/cron.php?token=<TOKEN_PROD>&job=finance_billing_notifications" > /dev/null 2>&1
```

## 4. Checklist de go-live

- [ ] Subdominio final definido.
- [ ] SSL ativo e forcando HTTPS.
- [ ] Document root confirmado.
- [ ] Backup HML feito.
- [ ] Banco de producao criado.
- [ ] `config.local.php` de producao criado.
- [ ] `app.base_url` e `app.public_url` apontando para producao.
- [ ] Tokens de producao gerados.
- [ ] Arquivos publicados sem sobrescrever configuracao local indevida.
- [ ] Permissoes de `uploads/` validadas.
- [ ] Migrations aplicadas.
- [ ] Admin login validado.
- [ ] Portal do aluno validado.
- [ ] Suporte validado.
- [ ] API validada.
- [ ] SMTP validado.
- [ ] Webhooks atualizados nos provedores externos.
- [ ] Cron cadastrado com token de producao.
- [ ] Mobile/PWA apontando para API de producao.
- [ ] Monitoramento/logs acompanhados apos go-live.

## 5. Checklist de rollback

- [ ] Manter HML sem alteracao.
- [ ] Guardar backup do document root de producao antes de cada publicacao.
- [ ] Guardar dump do banco de producao antes de migrations.
- [ ] Se houver falha critica, desativar cron de producao primeiro.
- [ ] Desativar webhooks externos de producao ou apontar temporariamente para HML somente se o banco/fluxo for compativel.
- [ ] Restaurar backup de arquivos ou banco de producao se necessario.
- [ ] Registrar causa e proxima acao antes de tentar novo go-live.

## 6. Pendencias antes de publicar

- [x] Informar o nome exato do subdominio de producao: `aneo.aneobrasil.com.br`.
- [x] Confirmar banco de producao separado e limpo: `u674156040_aneo_prd`.
- [ ] Resolver/validar endpoint de webhook do Itaú.
- [ ] Decidir se mobile/PWA sera configurado por ambiente ou se o default sera trocado no build de producao.
- [ ] Revisar tokens/secrets hoje presentes em configuracoes versionadas e garantir sobrescrita segura via `config.local.php`.
- [ ] Definir janela de go-live e responsavel por validar cada area.

## 7. Atualizacao de estrategia - 2026-06-08

Decisao operacional recomendada: criar uma nova instancia de producao em paralelo, limpa e com nova URL, mantendo o HML atual vivo durante toda a validacao.

Fluxo seguro:

1. Manter `https://erp-hml.aneobrasil.com.br` intacto como referencia e rollback.
2. Criar novo subdominio de producao no Hostinger.
3. Publicar o sistema em um novo document root, sem reaproveitar automaticamente arquivos sensiveis do HML.
4. Criar banco separado para producao, preferencialmente vazio ou com carga minima aprovada.
5. Configurar `config.local.php` de producao com nova URL, novo banco, novos tokens e parametros reais.
6. Rodar smoke tests fechados na nova URL.
7. Fazer carga/cadastro real somente depois da validacao do ambiente.
8. Manter HML por um periodo de seguranca apos o go-live.

Nao recomendamos excluir HML imediatamente apos subir a producao. O ideal e manter por alguns dias ou ate o primeiro ciclo operacional validado. Depois disso, podemos bloquear acesso, remover crons/webhooks e arquivar backup antes de excluir.

Sobre Itau:

- A integracao Itau ainda nao sera ativada nesta fase.
- O endpoint local existe, mas o HML ainda nao possui esse recorte publicado.
- A ativacao Itau deve ocorrer somente depois da nova URL estar estavel, com banco definitivo, tokens finais e webhook configurado no provedor.
- Antes de ativar Itau, validar boleto em modo controlado e registrar evidencia de request, response e baixa.
