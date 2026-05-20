# Status de Sincronizacao - GitHub x Local x Hostinger HML

Data: 2026-05-20

## Resumo

- Repositorio local atual: `acd7eb9e5583329da03596b41271fa8576220c85`
- Branch local: `main`
- O HML continua fora de Git e segue com historico de deploy manual.
- O hotfix do modal administrativo foi publicado diretamente no HML em `2026-05-20`.
- O relatorio antigo de `2026-05-05` serviu como alerta, mas parte dele esta desatualizada no estado atual do ambiente.

## Confirmacoes do dia

1. HML acessivel via SSH em:
   - `u674156040@149.62.37.84:65002`
2. Raiz da aplicacao HML:
   - `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
3. Hotfix publicado hoje:
   - `views/layouts/app.php`
4. Backup do hotfix antes da troca:
   - `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/app.php_before_admin_overlay_fix_20260520_160959`

## Auditoria realizada

Foi criado um snapshot local do HML em:
- `audit/hml-snapshot-2026-05-20/`

Comparacao textual executada entre:
1. snapshot do HML
2. arquivos locais atuais
3. conteudo versionado no `HEAD`

## Resultado do recorte auditado

Nos arquivos abaixo, `HML`, `Local` e `Git` estao equivalentes em conteudo textual:

- `public_html/api.php`
- `public_html/controllers/ApiEndpointController.php`
- `public_html/controllers/MobileAuthApiController.php`
- `public_html/assets/css/gestao_aluno.css`
- `public_html/assets/js/gestao_aluno.js`
- `public_html/models/ApiTokenModel.php`
- `public_html/models/StudentModel.php`
- `public_html/views/gestao_aluno/partials/modal_card.php`
- `public_html/views/students/form.php`
- `public_html/views/students/show.php`

Tambem foi confirmado no dia:
- `public_html/views/layouts/app.php`
  - `HML = Local != Git` no momento, por causa do hotfix publicado hoje que ainda nao foi commitado.

## Leitura pratica

1. O alerta estrutural do relatorio de `2026-05-05` foi valido naquele momento, mas nao pode mais ser usado como verdade atual para esses arquivos auditados.
2. Hoje, no recorte tecnico mais sensivel que foi conferido diretamente do servidor, o ambiente esta bem mais alinhado do que parecia.
3. O principal ponto de divergencia confirmado agora e o hotfix do modal administrativo, que ja esta no HML e no local, mas ainda nao foi consolidado em commit.

## Riscos que continuam existindo

- O HML ainda nao e um clone Git.
- O processo de deploy ainda depende de publicacao manual/dirigida.
- Nem todo o projeto foi auditado linha a linha em `2026-05-20`.
- `database.sql`, migrations soltas, arquivos de imagem e outros pontos historicamente sensiveis ainda merecem conferencia dedicada antes de um deploy amplo.

## Proximo passo recomendado

1. Consolidar em commit os arquivos do hotfix de `2026-05-20`:
   - `public_html/views/layouts/app.php`
   - `tests/e2e/hml-validation.spec.ts`
   - documentacao associada
2. Manter o snapshot `audit/hml-snapshot-2026-05-20/` como evidencia tecnica desta auditoria.
3. Se houver necessidade de saneamento completo, auditar na proxima rodada:
   - `database.sql`
   - `migrations/`
   - assets de imagem/logo
   - qualquer modulo que voce tenha validado hoje e suspeite de divergencia funcional.
