# Status validacao Itau HML x producao

Data: 2026-06-18

## Objetivo

Preservar e documentar o fluxo de boletos Itau validado no HML em 17/06/2026, confirmar a baixa do pagamento de ponta a ponta e verificar se o codigo e o schema de producao estao alinhados.

## Resultado executivo

- HML: fluxo de emissao e pagamento validado.
- Producao: codigo e schema do recorte Itau iguais ao HML.
- Producao: integracao Itau e forma de pagamento integrada continuam desativadas.
- GitHub: o recorte validado foi preparado para commit e envio neste fechamento.
- Nenhuma configuracao sensivel, credencial ou identificador bancario foi registrada neste documento.

## Evidencia funcional HML

Fatura utilizada:

- Numero: `FATURA-000494-26`
- Valor: `R$ 5,00`
- Boleto: provider `itau`
- Emissao: `17/06/2026 16:33:49`
- Pagamento: `17/06/2026`
- Status final do boleto: `paid`
- Status final da fatura: `paid`
- Valor pago na fatura: `R$ 5,00`

Pagamento registrado:

- Referencia: `PG-20260617173838`
- Metodo: `ITAU - Boleto API`
- Valor: `R$ 5,00`
- Data: `17/06/2026`
- Origem registrada: baixa automatica por sincronizacao da API de boleto
- Vinculo confirmado em `payment_items` com a fatura `494`

Notificacoes:

- `invoice_issued`: enviado ao aluno em `17/06/2026 16:33:50`
- `invoice_paid`: enviado ao aluno em `17/06/2026 17:38:38`

## Validacao do webhook

Em 18/06/2026 o endpoint recebeu/processou uma notificacao Itau referente ao mesmo boleto:

- situacao recebida: `paga`
- data de pagamento: `17/06/2026`
- valor pago: `R$ 5,00`
- mensagem persistida: `Status atualizado por webhook Itau.`

O processamento foi idempotente: a fatura permaneceu paga em `R$ 5,00`, sem duplicacao do valor.

## Leitura da linha do tempo

1. O boleto foi emitido pelo Itau em 17/06/2026.
2. Uma primeira tentativa de consulta retornou HTTP 403 as 17:04.
3. A sincronizacao posterior reconheceu o pagamento as 17:38 e gerou a baixa automatica.
4. O evento `invoice_paid` foi disparado com sucesso.
5. O webhook confirmou posteriormente a situacao paga sem duplicar a baixa.

## Validacoes automatizadas

Executadas em 18/06/2026:

- `php -l` nos arquivos centrais do recorte: aprovado.
- `npm run test:e2e:hml`: aprovado, 1 teste.
- `npm run test:e2e:hml:mobile`: aprovado, 1 teste.
- Tela de faturas HML: acoes de boleto e exibicao dos dados publicadas.

## Comparacao de codigo

Os hashes SHA-256 dos arquivos abaixo sao identicos entre:

- workspace local;
- HML;
- producao.

Arquivos conferidos:

- `public_html/controllers/BanksController.php`
- `public_html/controllers/FinanceController.php`
- `public_html/core/ItauService.php`
- `public_html/models/CompanyIntegrationModel.php`
- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/views/banks/itau.php`
- `public_html/views/finance/invoices.php`
- `public_html/views/email/finance_event_notification.php`
- `public_html/index.php`

## Comparacao de schema e configuracao

HML:

- coluna `bank_slips.nosso_numero`: presente;
- coluna `students.cpf`: presente;
- integracao Itau ativa: sim;
- forma de pagamento integrada Itau ativa: sim.

Producao:

- coluna `bank_slips.nosso_numero`: presente;
- coluna `students.cpf`: presente;
- integracao Itau ativa: nao;
- forma de pagamento integrada Itau ativa: nao;
- boletos existentes: `0`.

Conclusao: producao ja possui codigo e estrutura de banco, mas ainda nao esta operacionalmente habilitada para emitir boletos Itau.

## Arquivos do recorte a versionar

- `public_html/controllers/BanksController.php`
- `public_html/controllers/FinanceController.php`
- `public_html/controllers/StudentController.php`
- `public_html/core/ItauService.php`
- `public_html/index.php`
- `public_html/models/CompanyIntegrationModel.php`
- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/models/StudentModel.php`
- `public_html/models/StudentPortalModel.php`
- `public_html/views/banks/itau.php`
- `public_html/views/email/finance_event_notification.php`
- `public_html/views/finance/invoices.php`
- `public_html/views/student_portal/finances.php`
- `public_html/views/students/form.php`
- `migrations/add_nosso_numero_bank_slips.sql`
- `migrations/20260617_students_cpf.sql`

## Protocolo para ativacao em producao

Antes de ativar:

1. Confirmar `AUTORIZO ALTERAR PROD`.
2. Confirmar `AUTORIZO ALTERAR BANCO PROD` se houver ajuste de dados/configuracao no banco.
3. Criar backup dos arquivos e dump das tabelas envolvidas.
4. Conferir dados oficiais da empresa e do beneficiario.
5. Configurar credenciais/certificados exclusivos de producao.
6. Gerar token de webhook exclusivo de producao.
7. Ativar a integracao Itau para a empresa correta.
8. Ativar a forma `ITAU - Boleto API`.
9. Registrar o webhook com a URL de producao.
10. Emitir boleto controlado de baixo valor.
11. Efetuar pagamento e validar baixa, notificacao e idempotencia.

## Regra para evitar nova divergencia

1. Toda alteracao validada no servidor deve ser refletida no workspace no mesmo dia.
2. Comparar hashes local x HML x producao antes de encerrar a atividade.
3. Commitar o recorte aprovado no `main`.
4. Levar somente o commit aprovado para a branch `production`.
5. Publicar producao a partir da branch `production`.
6. Registrar evidencias e hashes em um arquivo `STATUS_*`.
7. Nunca versionar `config.local.php`, certificados, tokens, payloads bancarios completos ou dumps.
