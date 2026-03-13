# Plano de Testes QA - Tela a Tela (ANEO)

Data base: 12/03/2026  
Objetivo: validar com rigor de QA o que ja esta pronto no sistema, com foco em fluxo real de uso e confianca para apresentacao ao cliente.

## 1. Escopo e Aplicacoes

Este plano cobre 3 aplicacoes:

1. Administrativo: `index.php?route=login`
2. Portal do Aluno: `index.php?route=student/login`
3. Central Tecnica: `support.php?route=support/login`

Cobertura funcional:

1. Acesso, sessao, permissao e navegacao
2. Modulos administrativos ativos
3. Portal do aluno (fluxo completo)
4. Suporte tecnico (chamados)
5. Regras de negocio conhecidas (ex.: `projects` e `tasks` desativados)

## 2. Regras de Execucao QA

## 2.1 Severidade de defeito

1. `S1 Critico`: bloqueia login, operacao principal ou demo.
2. `S2 Alto`: fluxo principal funciona com erro relevante ou inconsistencias de dados.
3. `S3 Medio`: problema de usabilidade, validacao ou mensagem, sem bloquear fluxo.
4. `S4 Baixo`: cosmetico/texto/ajuste visual.

## 2.2 Evidencias obrigatorias

Para cada caso executado, registrar:

1. Status: `OK`, `NOK`, `NA`
2. Print da tela
3. URL/rota
4. Dados usados no teste
5. ID do defeito (se houver)

## 2.3 Criterio de aprovacao para demo

1. `0` defeitos `S1`
2. `0` defeitos `S2` sem workaround aceito
3. Fluxos E2E obrigatorios com `OK`
4. Evidencias de execucao registradas

## 3. Preparacao do Ambiente

## 3.1 Pre-condicoes tecnicas

1. Apache e MySQL ativos (XAMPP).
2. Banco `aneo_gestao` importado.
3. Projeto acessivel em `http://localhost/aneo`.
4. Permissao de escrita em `uploads/`.

## 3.2 Contas para teste

1. Admin: `admin / admin123`
2. Aluno (exemplo local): `enzo / 123456`
3. Suporte: usar usuario com permissao `support.desk` ou `requests.manage`

## 3.3 Dados de teste sugeridos

1. 1 aluno ativo e 1 inativo
2. 1 lead novo
3. 1 curso publicado
4. 1 fatura aberta e 1 paga
5. 1 ticket de suporte aberto

## 4. Fluxo Macro de Execucao (ordem recomendada)

1. Acesso e autenticacao (admin/aluno/suporte)
2. Navegacao e menu lateral
3. Modulos administrativos por prioridade de negocio
4. Portal do aluno completo
5. Central tecnica completa
6. Regressao final e checklist Go/No-Go

## 5. Casos de Teste - Acesso e Sessao

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| AUTH-01 | `login` | Abrir tela de login admin | Tela abre sem erro 500 | P0 |
| AUTH-02 | `login` | Informar usuario invalido | Mensagem de credencial invalida | P0 |
| AUTH-03 | `login` | Informar senha invalida | Mensagem de credencial invalida | P0 |
| AUTH-04 | `login` | Login com `admin/admin123` | Login concluido e redireciona corretamente | P0 |
| AUTH-05 | `select-company` | Selecionar empresa ativa | Sessao de empresa definida | P0 |
| AUTH-06 | `dashboard` | Acessar rota sem login | Redireciona para login | P0 |
| AUTH-07 | `logout` | Efetuar logout | Sessao encerrada | P0 |
| AUTH-08 | `student/login` | Login aluno com credencial valida | Acesso ao dashboard aluno | P0 |
| AUTH-09 | `support/login` | Login suporte com credencial valida | Acesso a central tecnica | P0 |
| AUTH-10 | geral | Expirar sessao e navegar | Sistema exige novo login | P1 |

## 6. Casos de Teste - Navegacao e Menu

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| NAV-01 | `dashboard` | Validar menu lateral completo | Menus ativos aparecem | P0 |
| NAV-02 | `dashboard` | Verificar itens desativados | `Projetos` e `Tarefas` nao aparecem | P0 |
| NAV-03 | `index.php?route=projects` | Abrir URL direta | Retorno `404` | P0 |
| NAV-04 | `index.php?route=tasks` | Abrir URL direta | Retorno `404` | P0 |
| NAV-05 | geral | Navegar entre modulos | Sem quebra de layout ou erro JS | P1 |

## 7. Casos de Teste - Dashboard e Busca Global

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| DASH-01 | `dashboard` | Abrir dashboard apos login | Cards carregam | P0 |
| DASH-02 | `dashboard` | Conferir numeros com banco | Valores coerentes | P1 |
| DASH-03 | `search` | Buscar por aluno existente | Resultado localizado | P1 |
| DASH-04 | `search` | Buscar termo inexistente | Estado vazio amigavel | P2 |
| DASH-05 | `dashboard` | Alterar empresa e reabrir dashboard | Dados isolados por empresa | P0 |

## 8. Casos de Teste - Usuarios e Permissoes

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| USER-01 | `users` | Abrir listagem | Lista carrega | P0 |
| USER-02 | `users/create` | Criar usuario suporte com permissoes minimas | Usuario criado | P0 |
| USER-03 | `users/edit` | Editar permissoes | Alteracao persistida | P0 |
| USER-04 | `users/toggle` | Inativar usuario | Usuario inativo e sem acesso | P1 |
| USER-05 | `users/delete` | Excluir usuario nao critico | Exclusao concluida | P1 |
| USER-06 | `users` | Login com suporte sem permissao de modulo | Acesso bloqueado no modulo | P0 |
| USER-07 | `users` | Vincular usuario a empresa especifica | Usuario ve apenas empresas vinculadas | P0 |

## 9. Casos de Teste - Empresas e Integracoes

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| CMP-01 | `companies` | Abrir tela de empresas | Tela carrega sem erro | P0 |
| CMP-02 | `companies/store` | Criar nova empresa | Empresa criada | P1 |
| CMP-03 | `companies/update` | Editar empresa existente | Dados atualizados | P1 |
| CMP-04 | `companies/toggle` | Inativar e reativar empresa | Status alterado | P1 |
| CMP-05 | `companies/integrations/update` | Salvar integracao Chatwoot | Configuracao persistida | P1 |

## 10. Casos de Teste - Alunos

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| STD-01 | `students` | Abrir listagem | Lista carrega com filtros | P0 |
| STD-02 | `students/create` | Criar aluno completo | Aluno criado | P0 |
| STD-03 | `students/edit` | Editar dados do aluno | Atualizacao salva | P0 |
| STD-04 | `students/show` | Abrir detalhe do aluno | Dados exibidos corretos | P0 |
| STD-05 | `students/upload-document` | Enviar documento | Upload concluido e visivel | P1 |
| STD-06 | `students/import` | Importar CSV valido | Importacao com sucesso | P1 |
| STD-07 | `students/import` | Importar CSV invalido | Mensagem de erro clara | P1 |
| STD-08 | `students/export` | Exportar CSV | Arquivo baixado | P1 |
| STD-09 | `students/toggle` | Inativar aluno | Aluno inativo | P1 |
| STD-10 | `students/bulk` | Acao em massa | Quantidade afetada correta | P1 |

## 11. Casos de Teste - Kanban Cliente

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| KAN-01 | `kanban` | Abrir quadro | Colunas e cards aparecem | P0 |
| KAN-02 | `kanban/move` | Mover card entre colunas | Mudanca persistida | P0 |
| KAN-03 | `kanban/settings` | Abrir configuracao | Tela carrega | P1 |
| KAN-04 | `kanban/status/store` | Criar novo status | Status criado | P1 |
| KAN-05 | `kanban/status/delete` | Excluir status permitido | Exclusao com validacoes corretas | P2 |

## 12. Casos de Teste - Leads

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| LEAD-01 | `leads` | Abrir listagem | Lista e filtros funcionando | P0 |
| LEAD-02 | `leads/create` | Criar novo lead | Lead criado | P0 |
| LEAD-03 | `leads/edit` | Editar lead | Dados persistidos | P0 |
| LEAD-04 | `leads/set-status` | Alterar status do lead | Status alterado e historico criado | P0 |
| LEAD-05 | `leads/history/store` | Inserir interacao | Interacao exibida no historico | P1 |
| LEAD-06 | `leads/convert` | Converter lead em aluno | Aluno criado e lead vinculado | P0 |
| LEAD-07 | `leads/bulk` | Atualizacao em massa | Registros alterados corretamente | P1 |
| LEAD-08 | `leads/export` | Exportar CSV | Arquivo gerado | P1 |
| LEAD-09 | `leads/settings` | Configurar pipeline | Statuses editados com sucesso | P1 |
| LEAD-10 | `leads/delete` | Excluir lead | Lead removido | P1 |

## 13. Casos de Teste - Financeiro

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| FIN-01 | `finance/invoices` | Abrir contas a receber | Lista carrega | P0 |
| FIN-02 | `finance/invoices/create` | Criar fatura | Fatura criada | P0 |
| FIN-03 | `finance/invoices/settle` | Dar baixa manual | Status e valores atualizados | P0 |
| FIN-04 | `finance/invoices/delete` | Excluir fatura de teste | Registro removido | P1 |
| FIN-05 | `finance/invoices/recurring` | Gerar recorrencia | Faturas recorrentes criadas | P1 |
| FIN-06 | `finance/invoices/export` | Exportar faturas | CSV baixado | P1 |
| FIN-07 | `finance/payments` | Abrir tela de pagamentos | Tela carrega e lista pagamentos | P0 |
| FIN-08 | `finance/payments/store` | Registrar pagamento | Pagamento salvo e refletido | P0 |
| FIN-09 | `finance/reports` | Abrir relatorios | Indicadores carregam | P1 |
| FIN-10 | `finance/reports/export` | Exportar relatorio | Arquivo baixado | P1 |
| FIN-11 | `finance/invoices/boleto-generate` | Testar sem provider real | Sistema retorna mensagem controlada | P1 |
| FIN-12 | `finance/invoices/fiscal-generate` | Testar sem provider real | Sistema retorna mensagem controlada | P1 |

## 14. Casos de Teste - Atendimento Chatwoot

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| CHT-01 | `chatwoot` | Abrir modulo de atendimento | Tela abre e status integracao exibido | P0 |
| CHT-02 | `chatwoot/open-lead` | Abrir conversa para lead | Conversa criada/reaproveitada | P1 |
| CHT-03 | `chatwoot/open-student` | Abrir conversa para aluno | Conversa criada/reaproveitada | P1 |
| CHT-04 | `chatwoot/open-phone` | Abrir conversa por telefone | Conversa aberta | P1 |
| CHT-05 | `chatwoot/webhook` | Chamada sem token valido | Retorno de seguranca adequado | P0 |
| CHT-06 | `chatwoot` | Validar vinculos `chatwoot_links` na tela | Entidade e conversa coerentes | P1 |

## 15. Casos de Teste - Assinaturas (D4Sign)

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| SIG-01 | `signatures` | Abrir modulo | Tela carrega | P1 |
| SIG-02 | `signatures/store` | Criar solicitacao | Solicitacao salva | P1 |
| SIG-03 | `signatures/send` | Enviar solicitacao | Status atualizado/mensagem controlada | P1 |
| SIG-04 | `signatures/sync` | Sincronizar status | Retorno coerente | P1 |
| SIG-05 | `signatures/webhook` | Chamar webhook sem token/hmac valido | Bloqueio de seguranca | P0 |

## 16. Casos de Teste - Cursos EAD

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| CRS-01 | `courses` | Abrir listagem de cursos | Lista carrega | P0 |
| CRS-02 | `courses/create` | Criar curso `draft` | Curso criado | P0 |
| CRS-03 | `courses/store` | Criar curso `published` | Curso criado sem erro FK | P0 |
| CRS-04 | `courses/edit` | Editar curso | Alteracoes salvas | P0 |
| CRS-05 | `courses/materials/upload` | Upload de material | Arquivo associado ao curso | P1 |
| CRS-06 | `courses/materials/delete` | Remover material | Material excluido | P1 |
| CRS-07 | `courses/categories` | Abrir categorias | Tela carrega | P1 |
| CRS-08 | `courses/categories/store` | Criar categoria | Categoria criada | P1 |
| CRS-09 | `courses/enrollments` | Abrir matriculas | Tela carrega | P0 |
| CRS-10 | `courses/enrollments/store` | Matricular aluno no curso | Matricula criada | P0 |
| CRS-11 | `courses/calendar` | Abrir calendario academico | Eventos carregam | P1 |
| CRS-12 | `courses/activities/store` | Criar atividade | Atividade salva | P1 |
| CRS-13 | `courses/exams` | Criar prova e resultado | Registro salvo | P1 |
| CRS-14 | `courses/comments` | Inserir comentario | Comentario exibido | P2 |

## 17. Casos de Teste - Solicitacoes, Automacoes e Chat IA Jully

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| GEN-01 | `requests` | Criar solicitacao | Item criado | P0 |
| GEN-02 | `requests/comment` | Comentar solicitacao | Comentario salvo | P1 |
| GEN-03 | `requests/status` | Alterar status | Status atualizado | P1 |
| GEN-04 | `automations` | CRUD basico | Criar/editar/excluir funcionando | P1 |
| GEN-05 | `help` | CRUD basico do modulo | Criar/editar/excluir funcionando | P1 |
| GEN-06 | `ai-chat` | Pergunta objetiva (alunos ativos) | Resposta coerente com banco | P0 |
| GEN-07 | `ai-chat` | Pergunta de seguimento (contexto) | IA preserva contexto da conversa | P1 |

## 18. Casos de Teste - Portal do Aluno

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| STP-01 | `student/login` | Login invalido | Mensagem de erro | P0 |
| STP-02 | `student/login` | Login valido | Acesso ao dashboard | P0 |
| STP-03 | `student/dashboard` | Validar cards/resumo | Dados carregam | P1 |
| STP-04 | `student/courses` | Abrir lista de cursos | Cursos matriculados exibidos | P0 |
| STP-05 | `student/calendar` | Abrir calendario | Aulas/provas/atividades visiveis | P1 |
| STP-06 | `student/live` | Abrir area de aula ao vivo | Link e dados exibidos quando houver | P1 |
| STP-07 | `student/materials` | Abrir materiais | Downloads funcionam | P1 |
| STP-08 | `student/progress` | Abrir progresso | Percentuais coerentes | P1 |
| STP-09 | `student/exams` | Listar provas | Lista carrega | P0 |
| STP-10 | `student/exams/take` | Iniciar prova | Tela de prova abre | P0 |
| STP-11 | `student/exams/submit` | Enviar prova | Submissao registrada | P0 |
| STP-12 | `student/logout` | Logout aluno | Sessao encerrada | P0 |

## 19. Casos de Teste - Central Tecnica

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| SUP-01 | `support/login` | Login invalido | Mensagem de erro | P0 |
| SUP-02 | `support/login` | Login valido | Redireciona para lista de chamados | P0 |
| SUP-03 | `support` | Filtrar chamados por status/prioridade | Filtro funciona | P1 |
| SUP-04 | `support/comment` | Inserir comentario em chamado | Comentario registrado | P0 |
| SUP-05 | `support/status` | Alterar status para `in_progress` | Status atualizado | P0 |
| SUP-06 | `support/status` | Alterar status para `resolved` | Status atualizado | P0 |
| SUP-07 | `support` | Validar isolamento por empresa permitida | Suporte nao ve empresa sem permissao | P0 |
| SUP-08 | `support/logout` | Logout suporte | Sessao encerrada | P0 |

## 20. Casos de Teste - Seguranca e Regras Negativas

| ID | Tela/Rota | Passos na tela | Resultado esperado | Prioridade |
|---|---|---|---|---|
| SEC-01 | qualquer POST | Enviar sem CSRF | Retorno `419` | P0 |
| SEC-02 | rota protegida | Abrir sem sessao | Redireciona para login | P0 |
| SEC-03 | modulo sem permissao | Acessar via URL direta | Bloqueio com mensagem | P0 |
| SEC-04 | `chatwoot/webhook` | Token invalido | Nao processa evento | P0 |
| SEC-05 | `signatures/webhook` | Token/HMAC invalido | Nao processa evento | P0 |
| SEC-06 | upload aluno/curso | Enviar arquivo nao permitido | Erro tratado | P1 |
| SEC-07 | validacoes obrigatorias | Salvar formulario vazio | Mensagem clara sem quebrar tela | P0 |
| SEC-08 | empresa | Trocar empresa ativa e repetir consulta | Dados mudam conforme empresa | P0 |

## 21. Fluxos E2E Obrigatorios para Demo

## 21.1 E2E-Admin-Lead-to-Student

1. Login admin.
2. Selecionar empresa.
3. Criar lead.
4. Alterar status.
5. Converter lead em aluno.
6. Abrir cadastro do aluno convertido.

Resultado esperado: conversao concluida, historico consistente.

## 21.2 E2E-Course-Published-Enrollment-StudentPortal

1. Criar curso publicado.
2. Matricular aluno.
3. Login no portal do aluno.
4. Validar curso na area do aluno.
5. Abrir materiais/progresso.

Resultado esperado: aluno enxerga curso e dados relacionados.

## 21.3 E2E-Finance-Invoice-Payment-Report

1. Criar fatura para aluno.
2. Dar baixa parcial/total.
3. Validar reflexo em relatorios.

Resultado esperado: status e valores consistentes em todas as telas.

## 21.4 E2E-Support-Ticket-Lifecycle

1. Criar solicitacao/chamado.
2. Login suporte.
3. Comentar chamado.
4. Mudar status para `in_progress`.
5. Mudar status para `resolved`.

Resultado esperado: trilha de atendimento coerente ponta a ponta.

## 22. Modelo de Registro de Defeito

Usar este padrao para cada bug:

1. ID: `BUG-YYYYMMDD-XX`
2. Titulo: resumo objetivo
3. Severidade: `S1/S2/S3/S4`
4. Ambiente: local/homolog/prod
5. Modulo/tela/rota
6. Passos para reproduzir
7. Resultado atual
8. Resultado esperado
9. Evidencias (prints/video/log)
10. Responsavel e prazo

## 23. Checklist de Encerramento QA

1. Todos os casos `P0` executados.
2. Todos os fluxos E2E obrigatorios com `OK`.
3. Nenhum `S1` aberto.
4. Nenhum `S2` sem workaround aprovado.
5. Evidencias anexadas.
6. Decisao final registrada: `GO` ou `NO-GO`.

## 24. Observacoes do Ciclo Atual

1. `projects` e `tasks` estao desativados por regra de negocio e devem continuar fora da validacao funcional ativa.
2. Integracoes externas podem estar desativadas por configuracao; nesses casos, validar comportamento controlado (mensagem clara e sem erro fatal).
3. Para IA (`ai-chat`), validar respostas com base no banco real e perguntas de seguimento no mesmo contexto.
