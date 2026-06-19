# Status HML - Duvidas dos Alunos e Aulas Zoom

Data: 2026-06-18

## Ambiente

- Alterado somente HML.
- Producao nao recebeu codigo nem migracao.
- Raiz HML: `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
- Banco HML: `u674156040_erpaneo`

## Implementado

- Aluno envia uma duvida a partir do player do curso.
- Duvida fica vinculada a empresa, aluno, curso e aula selecionada.
- Aluno acompanha o historico em `Minhas Duvidas`.
- Professor recebe menu exclusivo `Duvidas dos Alunos`.
- Professor visualiza a fila, responde e marca como resolvida.
- Resposta cria notificacao interna no Portal do Aluno.
- Inicio do professor exibe contador real de duvidas aguardando resposta.
- Outros perfis administrativos nao recebem o novo menu.

## Correcao Zoom

O botao do Inicio ja apontava para a rota correta. A pagina falhava porque o controlador e a view usavam o helper inexistente `get()`.

Foram substituidas as leituras por `request()` em:

- `controllers/CourseLiveSessionController.php`
- `views/courses/live_sessions/index.php`

Nenhuma chamada da API Zoom, credencial, reuniao ou notificacao foi alterada.

### Ajustes visuais complementares

- O formulario geral de `Aulas Zoom`, aberto pelo Inicio do Professor, agora tambem oferece `Aula global para todas as unidades deste curso`.
- A opcao usa a mesma regra do formulario interno do curso: o curso selecionado funciona como base e o mesmo link e vinculado aos cursos equivalentes das empresas ativas.
- As mensagens respondidas pelo professor receberam cores proprias do tema ANEO, eliminando o cartao branco com texto sem contraste.
- Tema claro e tema original possuem regras de contraste separadas.

Backup desta rodada:

- `/home/u674156040/domains/aneobrasil.com.br/deploy_backups/professor_zoom_questions_visual_20260619_013346`

## Banco HML

Migracao aplicada:

- `migrations/20260618_course_questions.sql`

Tabelas:

- `course_questions`
- `course_question_messages`

## Backup

- `/home/u674156040/domains/aneobrasil.com.br/deploy_backups/course_questions_20260619_011009`

## Validacoes

- Lint PHP local e remoto: aprovado.
- Fluxo controlado pergunta, resposta e historico: aprovado.
- Notificacao interna do aluno: aprovada.
- Tela de duvidas do professor: aprovada.
- Tela de Aulas Zoom como professor: aprovada.
- Playwright do novo fluxo do aluno: `1 passed`.
- Suite geral HML: `1 passed`.
- Dados temporarios de duvida QA removidos ao final.

## Producao

Confirmado ao final:

- `CourseQuestionController.php` ausente em producao.
- `CourseQuestionModel.php` ausente em producao.
- Nenhuma migracao de duvidas aplicada em producao.
- Correcao de Aulas Zoom ainda nao publicada em producao.

Publicacao em producao deve ocorrer somente apos autorizacao especifica e novo backup.
