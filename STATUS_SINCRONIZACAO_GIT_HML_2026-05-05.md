# Status de Sincronizacao - GitHub x Local x Hostinger HML

Data: 2026-05-05

## Resumo

- Repositorio remoto GitHub: `origin/main`
- Commit atual local: `c1e1563a75b1de3cb0dd53f8c36a13eb8760feda`
- Pasta local: possui arquivos modificados e novos nao commitados
- Hostinger HML: publicado em `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
- Hostinger HML nao e clone Git: nao existe `.git` no diretorio publicado

## Estrutura observada

- No repositorio local, a aplicacao esta dentro de `public_html/`
- Na Hostinger HML, o conteudo de `public_html/` foi publicado diretamente na raiz de `erphml/`
- Existe uma subpasta residual `erphml/public_html/` apenas com `views/`, indicando historico de deploy manual/parcial

## Matriz de comparacao

Legenda:

- `Git=Hostinger != Local`: servidor igual ao commitado, local diferente
- `Local=Hostinger != Git`: servidor igual ao local atual, mas diferente do GitHub
- `Git != Local != Hostinger`: os 3 estados diferem
- `Local only`: existe apenas localmente

| Arquivo | Estado |
|---|---|
| `database.sql` | `Git != Local != Hostinger` |
| `public_html/api.php` | `Local=Hostinger != Git` |
| `public_html/assets/css/gestao_aluno.css` | `Local=Hostinger != Git` |
| `public_html/assets/js/gestao_aluno.js` | `Local=Hostinger != Git` |
| `public_html/controllers/ApiEndpointController.php` | `Git != Local != Hostinger` |
| `public_html/controllers/MobileAuthApiController.php` | `Local=Hostinger != Git` |
| `public_html/controllers/StudentController.php` | `Git=Hostinger != Local` |
| `public_html/models/ApiTokenModel.php` | `Local=Hostinger != Git` |
| `public_html/models/StudentModel.php` | `Git=Hostinger != Local` |
| `public_html/views/gestao_aluno/partials/modal_card.php` | `Local=Hostinger != Git` |
| `public_html/views/students/form.php` | `Git=Hostinger != Local` |
| `public_html/views/students/show.php` | `Git=Hostinger != Local` |
| `migrations/20260427_students_extended_fields.sql` | `Local only` |
| `public_html/assets/img/logo_aneo_claro.png` | `Local=Hostinger != Git` |
| `public_html/assets/img/logo_aneo_escuro.png` | `Local=Hostinger != Git` |

## Indicios do fluxo de deploy

Datas dos arquivos publicados no HML:

- `api.php`: `2026-04-27 18:05 UTC`
- `controllers/ApiEndpointController.php`: `2026-04-27 18:05 UTC`
- `assets/css/gestao_aluno.css`: `2026-04-27 12:48 UTC`
- `assets/js/gestao_aluno.js`: `2026-04-27 12:48 UTC`
- `controllers/StudentController.php`: `2026-04-23 12:49 UTC`
- `models/StudentModel.php`: `2026-04-23 12:49 UTC`
- `views/students/form.php`: `2026-04-23 12:49 UTC`
- `views/students/show.php`: `2026-04-23 12:49 UTC`
- `database.sql`: `2026-04-24 17:36 UTC`

Tambem existe `deploy_backups/` no servidor com varios snapshots manuais, incluindo:

- `codex_recovery_20260423_124800.tar.gz`
- `finance_payment_methods_20260424_173526`
- `20260427_150452`
- `backup_exam_internal_flow_20260427_214857`

## Conclusao tecnica

O HML esta funcional, mas nao existe uma unica fonte de verdade hoje.

Temos 3 estados simultaneos:

1. GitHub (`origin/main`)
2. Pasta local com alteracoes nao commitadas
3. Hostinger HML com publicacoes manuais/parciais

Isso explica a percepcao de que "foi commitado e depois subido", mas o resultado final ficou misturado: em parte o HML reflete o Git, em parte reflete a pasta local, e em alguns arquivos nenhum dos dois bate exatamente com o servidor.

## Proximo passo mais seguro

1. Definir a fonte de verdade para cada grupo de arquivos:
   - `Hostinger`
   - `GitHub`
   - `Local`
2. Consolidar essas decisoes na pasta local
3. Criar commits limpos
4. Publicar novamente o HML a partir de um pacote/versionamento unico
