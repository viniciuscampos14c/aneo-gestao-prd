# Status - Auditoria dos relatorios financeiros do aluno Daniel Cavalcanti

Data: 2026-06-01
Ambiente verificado: HML (`https://erp-hml.aneobrasil.com.br`)

## Resultado da auditoria

Os cards de `Faturado` e `Pendente` estao batendo com as faturas existentes no banco para o aluno Daniel Cavalcanti quando o filtro usa o ano inteiro de 2026.

- Aluno: `Daniel Cavalcanti` (`students.id = 36`)
- Valor da parcela: `R$ 1.800,00`
- Plano atual salvo no cadastro: `24` parcelas
- Primeiro vencimento salvo atualmente no cadastro: `2025-10-30`
- Faturas geradas para o aluno: `24`
- Total do plano gerado: `R$ 43.200,00`

## Numeros do filtro ano 2026

Filtro: `2026-01-01` ate `2026-12-31`, aluno Daniel Cavalcanti.

- Faturas dentro do periodo: `8`
- Faturado: `R$ 14.400,00`
- Recebido: `R$ 3.600,00`
- Pendente: `R$ 10.800,00`

Composicao:

- `2` faturas pagas de `R$ 1.800,00`
- `6` faturas em aberto de `R$ 1.800,00`
- `8 x R$ 1.800,00 = R$ 14.400,00`
- `6 x R$ 1.800,00 = R$ 10.800,00`

## Ponto de atencao encontrado

A inconsistencia nao esta no somatorio do card. Ela esta na composicao do plano/faturas do aluno.

A trilha encontrada no HML mostra:

- O aluno foi criado em `2026-05-30 09:45:47`.
- As faturas do plano foram criadas em `2026-05-30 09:48:24`.
- A parcela `01/24` ficou com vencimento em `2026-05-30`.
- A parcela `02/24` foi criada originalmente com vencimento em `2026-06-15`.
- A parcela `02/24` foi editada manualmente em `2026-05-30 10:32:49`, mudando o vencimento de `2026-06-15` para `2026-04-15`.
- A parcela `02/24` foi baixada em `2026-05-30 10:34:27`.
- O cadastro do aluno hoje esta com `financial_plan_first_due_date = 2025-10-30`, valor que nao corresponde a sequencia das faturas ja geradas.

## Correcao local aplicada

Foi adicionada uma protecao local para evitar nova divergencia:

- Se um aluno ja possui faturas geradas pelo plano financeiro, o sistema bloqueia alteracoes em campos que impactam os relatorios.
- Campos protegidos: valor da parcela, dia de vencimento, quantidade de parcelas, primeiro vencimento e forma de pagamento padrao.
- A mensagem orienta ajustar diretamente as faturas existentes ou refazer o plano em procedimento controlado.

Arquivos alterados localmente:

- `public_html/controllers/StudentController.php`
- `public_html/models/FinanceModel.php`

## Validacao executada

- `php -l public_html/controllers/StudentController.php`
- `php -l public_html/models/FinanceModel.php`

Resultado: sem erros de sintaxe.

## Recomendacao antes de go-live

Nao corrigir esses dados diretamente sem decisao de negocio. O sistema esta calculando corretamente sobre as faturas existentes, mas o plano do Daniel precisa ser saneado com um procedimento controlado se a sequencia esperada for diferente.

Opcoes seguras:

- Manter como esta, se as 8 faturas de 2026 e 2 baixas forem intencionais.
- Ajustar manualmente as faturas do Daniel para refletir o contrato real.
- Cancelar/remover faturas do plano do Daniel e regerar o plano com datas corretas, somente com backup e aprovacao.
