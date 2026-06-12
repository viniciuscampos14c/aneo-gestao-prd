# Cursos comuns ANEO

## Contexto

Em producao, o curso `Especializacao Modalidade Residencia em Bucomaxilo` deve existir em todas as unidades ANEO, mantendo o mesmo conteudo academico para alunos de empresas diferentes.

O aluno continua pertencendo a sua respectiva empresa/unidade. O curso, modulos, aulas, provas, comunicados e materiais podem ser comuns, mas matriculas, progresso, respostas de prova, financeiro e historico academico devem permanecer separados por empresa e por aluno.

## Estado validado em PRD em 2026-06-12

Curso origem usado como referencia:

- Empresa: `ANEO BAHIA`
- `company_id`: `5`
- `course_id`: `6`
- Nome: `Especializacao Modalidade Residencia em Bucomaxilo`
- Status: `published`
- Modulos: `46`
- Aulas: `119`

Cursos existentes por unidade:

- `ANEO BRASILIA` (`company_id=4`, `course_id=7`): 46 modulos, 119 aulas
- `ANEO BAHIA` (`company_id=5`, `course_id=6`): 46 modulos, 119 aulas
- `ANEO RIO DE JANEIRO` (`company_id=6`, `course_id=8`): 46 modulos, 119 aulas
- `ANEO PALMAS` (`company_id=7`, `course_id=9`): 46 modulos, 119 aulas
- `ANEO GOIANIA` (`company_id=8`, `course_id=10`): 46 modulos, 119 aulas
- `ANEO SAO PAULO` (`company_id=10`, `course_id=11`): 46 modulos, 119 aulas

Backup da validacao/operacao em PRD:

- `/home/u674156040/domains/aneo.aneobrasil.com.br/public_html/deploy_backups/common_course_bucomaxilo_clone_20260612_160856/backup.json`
- `/home/u674156040/domains/aneo.aneobrasil.com.br/public_html/deploy_backups/common_course_bucomaxilo_clone_20260612_160856/result.json`

## Regra operacional atual

Enquanto nao houver sincronizacao automatica, qualquer ajuste estrutural neste curso comum deve seguir este fluxo:

1. Ajustar primeiro no ambiente HML.
2. Validar no portal administrativo e no portal do aluno.
3. Aplicar no PRD somente com autorizacao explicita.
4. Se o ajuste for de conteudo comum, replicar para todos os `course_id` mapeados acima.
5. Nunca replicar dados de aluno, financeiro, progresso, respostas, notas ou rematriculas.

## O que deve sincronizar no futuro

Quando implementarmos a automacao de cursos comuns, estes dados podem ser refletidos automaticamente para as unidades participantes:

- Curso: nome, descricao, capa, status, carga horaria, categoria e informacoes academicas.
- Modulos: titulo, descricao, ordem e status ativo/inativo.
- Aulas: titulo, descricao, tipo, URL de video, duracao, ordem, obrigatoriedade, percentual minimo e status ativo/inativo.
- Provas: avaliacao, questoes, alternativas e parametros de correcao.
- Comunicados/atividades academicas vinculados ao curso.
- Materiais/links de apoio vinculados ao curso.

## Aulas ao vivo globais

Aula ao vivo/Zoom tem uma regra propria. Para curso comum, o fluxo correto e criar uma unica reuniao Zoom e vincular o mesmo `meeting_id`, senha e `join_url` aos cursos equivalentes de todas as empresas participantes.

Fluxo esperado:

1. Administrador cria a aula ao vivo dentro de um curso.
2. Marca a opcao `Aula global para todas as unidades deste curso`.
3. Sistema cria uma unica reuniao no Zoom.
4. Sistema grava uma sessao em cada unidade/curso equivalente usando o mesmo link Zoom.
5. Cada aluno recebe o convite conforme sua matricula na propria empresa.
6. O portal do aluno continua filtrando pela matricula do aluno, sem misturar empresas.
7. Ao cancelar uma aula global, todas as sessoes vinculadas pelo mesmo `global_session_uuid` devem ser canceladas em conjunto.

Regra importante: nao criar uma reuniao Zoom diferente por empresa quando a aula for global. O objetivo e todos os alunos entrarem no mesmo invite.

## O que nao deve sincronizar automaticamente

Estes dados devem permanecer isolados por empresa/aluno:

- Matriculas de alunos.
- Progresso de aulas.
- Respostas e tentativas de prova.
- Notas e resultados publicados.
- Dados financeiros.
- Rematriculas.
- Usuarios, perfis, APIs e empresas.
- Sessoes ao vivo/Zoom ja criadas.

Observacao: sessoes Zoom devem ser tratadas com cuidado porque possuem `company_id`, meeting ID, join/start URL e contexto operacional da unidade. Para aulas globais criadas pelo sistema, o compartilhamento do mesmo link e intencional e deve ficar marcado por `is_global` e `global_session_uuid`. Para aulas antigas ou criadas individualmente, nao clonar reunioes reais de uma empresa para outra sem revisao.

## Proposta tecnica para automatizar depois

Criar uma camada de "curso comum" sem alterar a separacao atual por empresa:

- Tabela `shared_course_groups`: identifica o grupo comum, por exemplo `bucomaxilo_residencia`, com curso mestre e empresa origem.
- Tabela `shared_course_members`: relaciona cada empresa e seu respectivo `course_id` participante do grupo.
- Tabela de mapeamento estrutural: relaciona IDs do mestre com IDs equivalentes nas copias, para modulos, aulas, provas e questoes.
- Servico de sincronizacao: ao salvar alteracoes no curso mestre, propaga somente campos permitidos para os membros.
- Modo dry-run: antes de aplicar, mostra o que sera criado, atualizado, inativado ou ignorado.
- Backup obrigatorio: toda execucao em PRD deve salvar snapshot antes e resultado depois.

## Cuidados de manutencao

- Nao usar nome do curso como unica chave de sincronizacao no longo prazo. Nome pode mudar.
- Preferir `shared_group_key` estavel para identificar o curso comum.
- Exclusoes devem virar inativacao/soft delete quando possivel, para preservar historico.
- Alteracoes de provas devem respeitar respostas ja recebidas. Se uma prova ja tiver submissao, avaliar versionamento em vez de sobrescrever.
- Toda sincronizacao em PRD deve registrar empresa, curso, usuario/processo, data e resumo da alteracao.
