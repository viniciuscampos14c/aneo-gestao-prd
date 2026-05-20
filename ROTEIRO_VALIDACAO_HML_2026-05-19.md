# Roteiro de Validacao HML ANEO

Data da validacao: 2026-05-19
Ambiente: `https://erp-hml.aneobrasil.com.br`

## Acessos QA

Administrador
- URL: `https://erp-hml.aneobrasil.com.br/index.php?route=login`
- Usuario: `qa_admin_hml`
- Senha: `Qa123456!`

Portal do aluno
- URL: `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`
- Usuario principal: `qa.aluno.portal`
- Senha: `Aluno123!`

Portal do aluno para rematricula liberada
- URL: `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`
- Usuario: `qa.aluno.reok`
- Senha: `Aluno123!`

Portal do aluno para rematricula bloqueada
- URL: `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`
- Usuario: `qa.aluno.rebloq`
- Senha: `Aluno123!`

Suporte
- URL: `https://erp-hml.aneobrasil.com.br/support.php?route=support/login`
- Usuario: `qa_suporte_hml`
- Senha: `Qa123456!`

## Resultado resumido

Passou
- Login administrativo, selecao de empresa e dashboard.
- Rotas principais do admin: alunos, leads, financeiro, cursos, API e intercambio.
- Cadastro de lead pela interface administrativa.
- Portal do aluno com acesso a escala, intercambio e chamados.
- Rematricula automatica:
  - usuario `qa.aluno.reok` liberado.
  - usuario `qa.aluno.rebloq` bloqueado por fatura em aberto.
- Portal de suporte recebendo chamado criado pelo aluno.
- API com token novo e `GET /api.php?r=students`.
- API com `POST /api.php?r=leads` criando lead com sucesso.

Observacoes
- Os "graficos" do dashboard e do financeiro sao componentes HTML/CSS e cards KPI, nao canvas.
- A regra de escala por data de entrada foi validada no HML com bloqueio antes de 40 dias.

Atualizacao em 2026-05-20
- Hotfix publicado no HML para o modal `Alertas administrativos` do dashboard.
- Comportamento validado apos publicacao:
  - o modal pode abrir automaticamente ao entrar no dashboard quando houver alerta novo;
  - apos fechar, navegar e voltar ao dashboard na mesma sessao, ele nao reabre sozinho.

## Passo a passo manual

### 1. Admin

1. Acesse `https://erp-hml.aneobrasil.com.br/index.php?route=login`.
2. Entre com `qa_admin_hml / Qa123456!`.
3. Se abrir a tela de empresa, confirme a empresa selecionada.
4. Valide as telas:
   - `Dashboard`
   - `Alunos`
   - `Leads`
   - `Financeiro > Formas de Pagamento`
   - `Financeiro > Relatorios`
   - `Cursos EAD`
   - `Gerenciamento de API`
   - `Intercambio Aluno`
5. Em `Leads > + Novo Lead`, crie um lead com nome iniciado por `QA Lead`.
6. Confirme que o lead aparece na lista.

### 2. Escala por data de entrada

1. No admin, abra `Alunos`.
2. Procure por `QA Aluno Escala Bloq`.
3. Abra o cadastro e confira a `data de entrada` recente.
4. Em `Escala Aluno`, tente usar esse aluno em uma escala da unidade correspondente.
5. Resultado esperado:
   - o sistema deve bloquear a alocacao antes de completar 40 dias de entrada.
6. Para o aluno `QA Aluno Portal`, abra `Minha Escala` no portal e confirme que existe escala publicada.

### 3. Rematricula automatica

Usuario liberado
1. Acesse `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`.
2. Entre com `qa.aluno.reok / Aluno123!`.
3. Resultado esperado:
   - o aluno entra no fluxo de rematricula ou no dashboard ja com rematricula validada.
   - nao deve existir bloqueio financeiro.

Usuario bloqueado
1. Acesse a mesma URL.
2. Entre com `qa.aluno.rebloq / Aluno123!`.
3. Resultado esperado:
   - o sistema redireciona para rematricula.
   - deve exibir bloqueio por `faturas em aberto`.

### 4. Portal do aluno

1. Acesse `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`.
2. Entre com `qa.aluno.portal / Aluno123!`.
3. Valide:
   - `Minha Escala`
   - `Intercambio Aneo`
   - `Meus Chamados Tecnicos`
4. Em `Intercambio Aneo`, confira o historico da solicitacao existente.
   - estado atual esperado no HML: `Visualizado` ou `Aprovado`, conforme a ultima analise no admin.
5. Em `Meus Chamados Tecnicos`, abra um novo chamado com assunto iniciado por `QA Ticket`.
6. Confirme que o chamado recebe codigo `ANEO...`.

### 5. Portal de suporte

1. Acesse `https://erp-hml.aneobrasil.com.br/support.php?route=support/login`.
2. Entre com `qa_suporte_hml / Qa123456!`.
3. Procure o chamado criado no portal do aluno.
4. Confirme que o chamado aparece na fila/listagem.
5. Se quiser completar a prova manual, adicione comentario e atualize status para `Resolvido`.

### 6. API

1. No admin, abra `Gerenciamento de API`.
2. Clique em criar token.
3. Vincule o token a um usuario administrativo.
4. Marque permissoes para `students` e `leads`.
5. Salve e copie o token exibido.
6. Teste:

```bash
curl -H "Authorization: Bearer SEU_TOKEN" "https://erp-hml.aneobrasil.com.br/api.php?r=students"
```

Resultado esperado
- retorno `200` com `ok: true`.

7. Teste:

```bash
curl -X POST "https://erp-hml.aneobrasil.com.br/api.php?r=leads" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"full_name\":\"Lead API Manual\",\"email\":\"lead.manual@aneo.test\",\"phone\":\"11999999999\"}"
```

Resultado esperado
- retorno `201` com `ok: true`.

## Evidencia automatizada

Teste executado com Playwright:
- `tests/e2e/hml-validation.spec.ts`

Resultado:
- `1 passed` em `2026-05-19`
