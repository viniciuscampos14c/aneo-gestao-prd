# Checkpoint - Hotfix HML (ERP ANEO)

Data: 2026-05-20

## Resumo executivo
- Corrigido o comportamento recorrente do modal `Alertas administrativos` no dashboard do admin.
- O HML foi atualizado apenas com o arquivo `views/layouts/app.php`, sem misturar outras alteracoes locais.
- O teste E2E de HML foi ajustado para nao mascarar mais esse problema removendo o modal do DOM.

## Problema observado
- No HML atual, o modal de alertas administrativos abria automaticamente no dashboard.
- Mesmo apos o usuario sair da tela e voltar ao painel na mesma sessao, o modal reaparecia.
- Isso atrapalhava cliques e gerava percepcao de overlay recorrente no admin.

## Correcao aplicada
- Abertura automatica mantida apenas uma vez por sessao para o mesmo conjunto de alertas.
- O sino do header continua abrindo o modal manualmente.
- O alerta continua sendo marcado como visto apenas quando o usuario fecha o modal.

## Arquivos alterados
- `public_html/views/layouts/app.php`
- `tests/e2e/hml-validation.spec.ts`

## Publicacao HML
- Destino publicado:
  - `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml/views/layouts/app.php`
- Backup remoto gerado antes da troca:
  - `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/app.php_before_admin_overlay_fix_20260520_160959`
- Hash SHA-256 validado no servidor:
  - `ce9a2aa3758af53fad04607cf48d3336d07b1c15bbcfc70a8150d5614a8d81a7`

## Validacoes executadas
- `php -l public_html/views/layouts/app.php`
- `npx playwright test tests/e2e/hml-validation.spec.ts`
- Validacao dirigida no HML apos deploy:
  - primeiro acesso ao dashboard: modal abre
  - fechar modal
  - navegar para outra tela e voltar ao dashboard
  - resultado esperado confirmado: modal nao reabre sozinho na mesma sessao

## Observacao operacional
- O repositorio local ainda contem um arquivo de teste nao rastreado:
  - `tests/e2e/aneo-e2e.spec.ts`
- Esse arquivo nao foi publicado no HML e nao faz parte do hotfix.
