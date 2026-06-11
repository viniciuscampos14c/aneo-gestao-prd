# Status Deploy HML - Cards Financeiros

Data: 2026-06-01

## Objetivo

Publicar no HML a correcao para cards/KPIs financeiros respeitarem os filtros aplicados na tela.

## Escopo publicado

Publicacao minima, baseada nos arquivos atuais do HML baixados antes do deploy.

- `controllers/FinanceController.php`
- `models/FinanceModel.php`

Nao foi publicado o arquivo local completo com outros ajustes em andamento. O pacote enviado ao HML recebeu somente o patch dos cards/filtros.

## Backup remoto

Backup criado antes da troca:

- `/home/u674156040/domains/aneobrasil.com.br/public_html/deploy_backups/cards_filters_20260601_152611`

## Validacoes tecnicas

- Lint local do pacote HML:
  - `FinanceController.php`: sem erro
  - `FinanceModel.php`: sem erro
- Lint remoto apos upload:
  - `controllers/FinanceController.php`: sem erro
  - `models/FinanceModel.php`: sem erro
- Hashes conferidos entre pacote local e servidor apos upload.

## Validacao funcional de leitura

Sem alterar dados, sem criar registros, sem executar fluxos invasivos.

### Relatorios Financeiros

Filtro validado:

- tela: `Relatorios Financeiros`
- aba: `Visao Geral`
- periodo: `01/05/2026` a `30/12/2026`
- aluno: `Daniel Cavalcanti`

Resultado observado:

- `Faturado`: `R$ 12.600,00`
- `Recebido`: `R$ 3.600,00`
- `Pendente`: `R$ 10.800,00`
- `Vencido`: `R$ 0,00`
- `Baixas no periodo`: `2`
- `Inadimplencia`: `0,00%`

### Faturas

Filtro validado:

- tela: `Faturas`
- periodo: `01/05/2026` a `30/12/2026`
- aluno: `Daniel Cavalcanti`

Resultado observado:

- `Em aberto`: `6`
- `Pago`: `1`
- `Parcial`: `0`
- `Vencido`: `0`
- `Rascunho`: `0`
- `Faturas pagas`: `R$ 1.800,00`
- `Faturas vencidas`: `R$ 0,00`
- `Faturas pendentes`: `R$ 10.800,00`
- `Baixadas hoje`: `0`
- `NF-e emitidas / pendentes`: `0 / 0`

## Observacao

Esta publicacao nao executou limpeza de base, nao alterou schema e nao publicou o restante das mudancas locais em andamento.

