# Plano de segurança para produção

Data da auditoria: 23 de junho de 2026

## Objetivo

Replicar em produção somente as proteções já aprovadas no HML, preservando dados, configurações, cron, Itaú e o módulo de Provas Assistidas.

## Estado encontrado

- Produção possui seis empresas, 67 alunos e cron financeiro ativo.
- `boleto_issue_due` e `boleto_sync` executaram com status `ok` em 23 de junho de 2026.
- Produção ainda usa CORS irrestrito, expõe a versão do PHP e não carrega rate limiting ou validação reforçada de uploads.
- O `config.local.php` de produção possui permissão `0600` e cobre explicitamente os blocos sensíveis.
- O módulo `provas_assistidas` versão `4.7.0` existe somente no HML.
- Não há migration de banco nesta entrega.

## Exclusões obrigatórias

Não publicar nem alterar:

- `public_html/modules/provas_assistidas/**`
- `public_html/controllers/StudentPortalController.php`
- `public_html/core/SystemModuleRuntime.php`
- `public_html/views/layouts/student.php`
- tabelas iniciadas por `assisted_`
- `public_html/config.local.php`
- certificados, chaves e scripts em `secure/aneo-prd`
- dados de alunos, faturas, boletos ou cron

## Pacote

O arquivo `deploy/prd_security_manifest_20260623.txt` contém os 19 arquivos autorizáveis.

O pacote inclui:

- rate limiting para os quatro fluxos de login;
- CORS restrito aos domínios oficiais;
- cabeçalhos HTTP e CSP;
- câmera permitida apenas para a própria origem, sujeita à autorização do navegador;
- validação reforçada de uploads;
- bloqueio de acesso e execução na pasta pública de uploads;
- remoção de segredos do arquivo padrão versionado.

## Procedimento

1. Executar o smoke somente leitura antes do deploy.
2. Baixar backup atual de arquivos e do banco `u674156040_aneo_prd` pela Hostinger.
3. Criar backup adicional, no servidor, somente dos 19 arquivos.
4. Validar sintaxe PHP do pacote.
5. Publicar os arquivos em uma janela curta.
6. Validar sintaxe PHP no servidor.
7. Executar o smoke com segurança obrigatória.
8. Validar login com contas QA, sem criação de dados.
9. Consultar estado do cron e Itaú sem executar jobs manualmente.
10. Executar carga progressiva somente com contas QA e sem operações financeiras.
11. Executar ZAP Baseline passivo.
12. Atualizar a branch `production` somente depois da aprovação do ambiente.

## Rollback

Em caso de falha:

1. Restaurar somente os arquivos do backup criado imediatamente antes do deploy.
2. Não restaurar banco, pois esta entrega não altera schema nem dados.
3. Revalidar login, portal, API, cron e Itaú.
4. Registrar o arquivo responsável antes de uma nova tentativa.

## Autorizações necessárias

- Autorização para criar o backup técnico no servidor.
- Confirmação do download do backup atual no painel Hostinger.
- Autorização separada para publicar os 19 arquivos em PRD.
- Autorização separada para carga e testes que gerem registros ou notificações.
