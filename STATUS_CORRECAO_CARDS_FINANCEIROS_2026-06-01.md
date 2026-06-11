# Status correcao cards financeiros

Data: 2026-06-01

## Problema

Os cards/KPIs financeiros nao acompanhavam todos os filtros aplicados na listagem. O caso observado foi filtro por periodo anual e aluno especifico.

## Causa

Na tela de faturas, a tabela usava filtros em `listInvoices($filters, ...)`, mas os cards chamavam `invoiceStats()` sem receber os mesmos filtros.

Na tela de relatorios financeiros, parte dos KPIs da Visao Geral ja respeitava periodo/aluno/status, mas o filtro de metodo nao era aplicado nos totais de contas a receber. A aba `Contas a Receber` tambem nao aplicava metodo no resultado.

## Ajuste feito

- `FinanceController::invoices()` agora envia os filtros para `FinanceModel::invoiceStats($filters)`.
- `FinanceModel::invoiceStats()` passou a respeitar:
  - periodo/data inicial e final;
  - aluno;
  - status;
  - busca textual.
- `FinanceModel::reportOverview()` passou a aplicar metodo de pagamento nos KPIs de faturas quando a estrutura de formas de pagamento existe.
- `FinanceModel::reportReceivables()` passou a aplicar metodo de pagamento na aba Contas a Receber quando a estrutura existe.

## O que nao foi feito

- Nenhum deploy.
- Nenhuma alteracao no banco.
- Nenhuma limpeza de dados.
- Nenhuma execucao de teste HML funcional que gere massa nova.

## Validacoes

- `php -l` em todos os arquivos PHP de `public_html`: `195` arquivos sem erro.
- `npm run test:e2e:hml:list`: suite HML reconhecida.
- `git diff --check`: sem erro real; apenas avisos LF/CRLF do Windows.

## Proximo passo seguro

Validar visualmente em HML ou ambiente publicado somente depois de decidirmos publicar este recorte. O teste recomendado e repetir o caso:

- Relatorios Financeiros;
- periodo anual;
- aluno especifico;
- aba Visao Geral e Contas a Receber;
- conferir se cards e listagem respondem ao mesmo filtro.

