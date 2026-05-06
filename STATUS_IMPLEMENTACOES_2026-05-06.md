# Checkpoint - Implementacoes do Dia (ERP ANEO)

Data: 2026-05-06

## Resumo executivo
- Implementado o modulo `Importacao de Dados` dentro de `Cadastro`.
- Criado fluxo de importacao por CSV com validacao previa, revisao de lote e confirmacao controlada.
- Adicionadas cargas para alunos completos, cursos EAD com modulos/aulas, professores, usuarios administrativos, unidades/hospitais e Arsenal Digital.
- Ajustada a importacao de alunos para permitir destino por filial/empresa existente.
- Adicionado campo `cidade` no cadastro do aluno e na planilha de alunos.
- Corrigida compatibilidade do cadastro de aulas ao vivo quando as colunas de credenciais Zoom ainda nao existem no banco.
- Corrigida compatibilidade da migration de importacao com MariaDB/Hostinger, escapando a coluna `row_number`.
- Removido BOM de `config.php` e `core/bootstrap.php` para evitar avisos de header/session em execucoes CLI e reduzir risco operacional.

## Entregas funcionais

### 1. Modulo Importacao de Dados
- Novo item em `Cadastro > Importacao de Dados`.
- Download de modelos CSV por tipo de carga.
- Upload de CSV separado por ponto e virgula.
- Criacao de lote de importacao.
- Pre-validacao linha a linha antes de gravar dados mestres.
- Tela de previa com:
  - total de linhas
  - linhas validas
  - linhas com erro
  - mensagens de erro e aviso por linha
- Confirmacao manual da carga apos revisao.
- Historico de lotes importados.

### 2. Alunos completos
- Cria ou atualiza alunos por e-mail ou RA dentro da filial correta.
- Permite informar filial por:
  - `filial_id`
  - `codigo_filial`
  - `empresa_id`
  - `company_id`
  - `filial`
  - `empresa`
  - `nome_filial`
  - `cnpj_filial`
- Permite informar `cidade` do aluno.
- Mantem campos existentes:
  - nome
  - e-mail
  - telefone
  - contato
  - RA
  - data de nascimento
  - RG
  - CRO
  - data de entrada
  - unidade pratica
  - nivel de residencia
  - mensalidade
  - dia de vencimento
  - status
  - informacoes administrativas
  - observacoes
  - login/senha/status do portal do aluno
- Bloqueia e-mail ou RA duplicado dentro da mesma filial na propria planilha.

### 3. Cursos EAD com modulos e aulas
- Cria ou atualiza curso.
- Cria ou atualiza categoria do curso.
- Cria ou atualiza modulo.
- Cria ou atualiza aula em video.
- Bloqueia aula duplicada no mesmo curso/modulo dentro da planilha.

### 4. Professores
- Cria ou atualiza usuarios com perfil `professor`.
- Vincula automaticamente o professor a empresa atual.
- Bloqueia tentativa de converter usuario `admin` ou `suporte` em professor por importacao.
- Bloqueia e-mail/login duplicados na propria planilha.

### 5. Usuarios administrativos
- Cria ou atualiza usuarios com perfil `admin` ou `suporte`.
- Vincula usuarios a filiais existentes pela coluna `filiais_ids`.
- Permite informar permissoes para usuarios de suporte.
- Usuarios admin usam permissoes do proprio perfil.
- Bloqueia tentativa de converter usuario professor em usuario administrativo por importacao.
- Bloqueia e-mail/login duplicados na propria planilha.

### 6. Unidades / Hospitais
- Cria ou atualiza unidades de pratica usadas no cadastro de alunos e na Escala Aluno.
- Campos do modelo:
  - nome
  - cidade
  - UF
  - status
- Bloqueia unidade duplicada dentro da propria planilha.

### 7. Arsenal Digital
- Cria ou atualiza categorias.
- Cria ou atualiza materiais do tipo link/URL.
- Campos do modelo:
  - codigo_material
  - categoria
  - descricao_categoria
  - titulo
  - descricao
  - tipo
  - URL
  - escopo
  - status
  - ordem
  - janela de publicacao
- Nesta fase, upload fisico de arquivos por planilha ficou fora do escopo por seguranca operacional.
- Bloqueia material duplicado na mesma categoria dentro da planilha.

## Banco de dados e migrations

### Migration 1
`migrations/20260506_data_imports.sql`

Cria:
- `data_import_batches`
- `data_import_rows`
- `data_import_entity_map`

### Migration 2
`migrations/20260506_students_city.sql`

Adiciona na tabela `students`:
- `city`

## Arquivos principais alterados
- `public_html/config.php`
- `public_html/core/bootstrap.php`
- `public_html/index.php`
- `public_html/controllers/DataImportController.php`
- `public_html/controllers/StudentController.php`
- `public_html/models/DataImportModel.php`
- `public_html/models/StudentModel.php`
- `public_html/models/CourseLiveSessionModel.php`
- `public_html/views/data_imports/index.php`
- `public_html/views/layouts/app.php`
- `public_html/views/students/form.php`
- `migrations/20260506_data_imports.sql`
- `migrations/20260506_students_city.sql`

## Validacoes executadas
- `php -l` nos arquivos PHP alterados.
- Testes simulados por reflection para:
  - validacao de professor
  - validacao de unidade/hospital
  - validacao de Arsenal Digital
  - validacao de usuario administrativo
  - validacao de aluno com filial e cidade
- Sincronizacao local com XAMPP.
- Aplicacao local da coluna `students.city`.
- Validacao de sintaxe PHP na Hostinger para arquivos publicados.
- Aplicacao das migrations na Hostinger com conferencia das tabelas:
  - `data_import_batches`
  - `data_import_rows`
  - `data_import_entity_map`
  - coluna `students.city`
- Smoke test em `https://erp-hml.aneobrasil.com.br/index.php?route=login` com retorno HTTP 200.

## Publicacao

### GitHub
- `ac2d414` - implementacao dos fluxos de importacao.
- `a406679` - ajuste de compatibilidade MariaDB para `row_number`.
- `d668a1d` - remocao de BOM dos arquivos de bootstrap/config.

### Hostinger HML
- Ambiente atualizado em:
  - `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
- Backup antes da publicacao principal:
  - `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/hml_before_imports_20260506_194531.tar.gz`
- Backup antes do ajuste de BOM:
  - `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/hml_before_bomfix_20260506_195233.tar.gz`

## Observacoes operacionais
- Filiais/empresas nao sao criadas por importacao nesta entrega.
- As filiais devem ser criadas manualmente em `Cadastro > Empresas`.
- Na importacao de alunos, a planilha deve apontar para uma filial ja existente.
- O usuario logado precisa ter acesso a filial informada para importar alunos nela.
- A importacao do Arsenal Digital aceita apenas links/URLs nesta fase.
