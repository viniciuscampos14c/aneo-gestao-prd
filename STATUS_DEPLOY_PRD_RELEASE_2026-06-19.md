# Deploy PRD - Professor, Itaú e pt-BR

Data: 2026-06-19

## Escopo

- Dashboard exclusivo do professor.
- Dúvidas entre aluno e professor.
- Lista e criação de aulas Zoom, incluindo opção global.
- Correções finais do fluxo Itaú já validado.
- Revisão visual de textos pt-BR no ERP e Portal do Aluno.
- Preservação da API RD Station e das correções já existentes em produção.

## Publicação

- Arquivos publicados: 146.
- Arquivos PHP validados: 145.
- Integridade remota: pacote e destino conferidos byte a byte.
- Migration aplicada: `migrations/20260618_course_questions.sql`.
- Tabelas criadas:
  - `course_questions`
  - `course_question_messages`

## Backup

- Diretório: `/home/u674156040/domains/aneo.aneobrasil.com.br/deploy_backups/release_professor_itau_ptbr_20260619_122536`
- Código anterior: `code_before.tar.gz`
- Dump integral do banco: `database/database.sql`
- SHA-256 do banco: `a5d32ec7259c87f8f354db986de42c524c89bf966036af6b65b670966fb08d92`

## End-to-end de produção

Teste realizado com contas técnicas temporárias, removidas ao final:

- administrador: login, dashboard `Visão Geral` e manual da API RD Station;
- aluno: login, dashboard, cursos, player e envio de dúvida;
- professor: dashboard, fila de dúvidas, resposta e notificação;
- Zoom: formulário aberto e opção de aula global confirmada;
- aluno: resposta do professor exibida no Portal do Aluno.

Nenhuma fatura, boleto, reunião Zoom ou envio em massa foi criado.

## Itaú

- `boleto_sync`: aprovado, um boleto verificado e atualizado, zero erros;
- webhook: HTTP 200;
- fatura usada: `FATURA-000380-26`;
- pagamento aplicado novamente: não;
- vínculos antes/depois: `1/1`;
- duplicidade financeira: não.

As ativações das integrações, formas de pagamento e jobs foram preservadas. A publicação não alterou credenciais, certificados, beneficiário ou tokens.

## Resultado

Release aprovada em produção. Código, banco, perfis, Portal do Aluno, dúvidas, Zoom, cron e webhook permaneceram operacionais após a publicação.
