# Checkpoint - Implementacoes do Dia (ERP ANEO)

Data: 2026-05-05

## Resumo executivo
- Implementado o novo modulo `Escala Aluno` no administrativo.
- Integrado o cadastro do aluno com unidade/hospital, nivel de residencia e elegibilidade de 40 dias.
- Adicionada a visao `Minha Escala` no Portal do Aluno.
- Implementado fluxo de notificacao da escala no portal do aluno e por e-mail.
- Ajustado o comportamento das notificacoes para so dispararem quando a escala estiver publicada.
- Adicionada deteccao e bloqueio de conflito de escala entre semanas coincidentes na mesma unidade.
- Refinado o visual do Portal do Aluno em claro/escuro: menu do avatar, modal de notificacoes, botoes e tipografia.
- Ajustado o status operacional da escala com `Encerrar escala` e `Desarquivar`.

## Entregas funcionais

### 1. Modulo Escala Aluno (admin)
- Novo menu `Escala Aluno` no painel administrativo.
- Cadastro de unidade/hospital para escala pratica.
- Cadastro de escalas por periodo e unidade.
- Geracao de semanas da escala.
- Configuracao de vagas por coluna `R3`, `R2` e `R1`.
- Alocacao manual de alunos por semana e nivel.
- Exportacao/impressao da escala em layout proprio.
- Fluxo de status:
  - `Rascunho`
  - `Publicada`
  - `Arquivada`
- Alteracao de nomenclatura operacional:
  - `Arquivar` -> `Encerrar escala`
  - suporte a `Desarquivar`

### 2. Regras da escala implementadas
- Todo aluno precisa estar ativo.
- O aluno precisa pertencer a mesma unidade da escala.
- O aluno precisa respeitar a regra de 40 dias desde `enrolled_at`.
- O aluno precisa estar no mesmo nivel da coluna (`R1`, `R2`, `R3`).
- O aluno nao pode ser duplicado na mesma semana.
- O aluno nao pode ser alocado em outra escala da mesma unidade quando houver sobreposicao da semana.
- Em caso de conflito, a tela do admin agora mostra a escala conflitante antes mesmo da tentativa de salvar.

### 3. Portal do Aluno - Minha Escala
- Nova rota `student/schedule`.
- Novo item `Minha Escala` na navegacao principal do portal.
- Exibicao apenas de escalas publicadas.
- Exibicao da unidade, periodo, semanas e coluna do aluno (`R1`, `R2`, `R3`).
- Ajustes visuais para modo claro e escuro.

### 4. Notificacoes de escala
- Criada tabela de notificacoes do portal do aluno.
- Quando a escala esta publicada e o aluno e alocado:
  - gera notificacao no portal
  - tenta enviar e-mail
- O sino do portal foi ajustado para:
  - contar notificacoes nao lidas
  - marcar como lidas ao abrir o modal
  - atualizar o badge em tempo real
- Evitada duplicidade de notificacoes iguais.
- Escala em `Rascunho` nao notifica o aluno.

### 5. Refinos visuais do Portal do Aluno
- Limpeza da navegacao principal removendo:
  - `Chamados`
  - `Financeiro`
  - `Sair`
- Essas acoes foram movidas para o menu do avatar.
- Dropdown do avatar redesenhado com:
  - cabecalho do aluno
  - secoes `Acessos rapidos` e `Sessao`
  - suporte a claro/escuro
- Modal de notificacoes do portal refinado com:
  - nova hierarquia tipografica
  - botoes com classes proprias
  - melhor contraste em claro e escuro
- Modal administrativo de notificacoes tambem recebeu acabamento visual para ficar consistente com o restante do sistema.

## Banco de dados e migrations

### Migration 1
`migrations/20260505_student_duty_schedule.sql`

Cria:
- `student_practice_units`
- `student_duty_schedules`
- `student_duty_schedule_weeks`
- `student_duty_assignments`

Adiciona na tabela `students`:
- `practice_unit_id`
- `residency_level`

### Migration 2
`migrations/20260505_student_portal_notifications.sql`

Cria:
- `student_portal_notifications`

## Arquivos principais alterados
- `public_html/config.php`
- `public_html/index.php`
- `public_html/core/BaseController.php`
- `public_html/core/BaseModel.php`
- `public_html/controllers/StudentController.php`
- `public_html/controllers/StudentPortalController.php`
- `public_html/controllers/StudentScheduleController.php`
- `public_html/models/StudentModel.php`
- `public_html/models/StudentPortalModel.php`
- `public_html/models/StudentScheduleModel.php`
- `public_html/views/layouts/app.php`
- `public_html/views/layouts/print.php`
- `public_html/views/layouts/student.php`
- `public_html/views/student_portal/schedule.php`
- `public_html/views/student_schedule/index.php`
- `public_html/views/student_schedule/form.php`
- `public_html/views/student_schedule/show.php`
- `public_html/views/student_schedule/export.php`
- `public_html/views/students/form.php`
- `public_html/views/students/show.php`
- `public_html/assets/css/app.css`
- `migrations/20260505_student_duty_schedule.sql`
- `migrations/20260505_student_portal_notifications.sql`

## Validacoes executadas
- Validacao de sintaxe PHP (`php -l`) nos arquivos alterados.
- Validacao local do XAMPP apos sincronizacao dos arquivos.
- Aplicacao local da migration de notificacoes do portal.
- Testes funcionais do modulo `Escala Aluno`:
  - criacao de escala
  - geracao de semanas
  - alocacao de aluno
  - bloqueio por inelegibilidade
  - bloqueio por conflito
  - notificacao no portal
- Ajustes iterativos de UI/UX no Portal do Aluno com base em testes visuais reais.

## Observacoes operacionais
- O envio de e-mail depende de SMTP valido por empresa/ambiente.
- O ambiente local possui arquivos temporarios de checagem HML e debug que nao fazem parte da entrega e nao devem ser versionados.
- Para ver alteracoes de CSS imediatamente no navegador, usar `Ctrl + F5`.

## Estado esperado apos publicacao
- GitHub atualizado com as implementacoes do dia.
- Hostinger HML atualizada com:
  - novo modulo `Escala Aluno`
  - portal do aluno com `Minha Escala`
  - notificacoes de escala
  - refinamentos visuais do portal e do modal administrativo.
