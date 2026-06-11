# Status Validacao Funcional HML - Finance Events

Data: 2026-05-29

## Objetivo

Validar no HML os fluxos publicados da frente financeira:

1. emissao de boleto;
2. baixa manual;
3. gravacao de logs financeiros por evento.

## Massa de teste usada

Faturas QA criadas no HML:

- `FATURA-000278-26`
  - projeto: `QA_Finance_Boleto_1780070361618`
  - invoice_id: `278`
- `FATURA-000279-26`
  - projeto: `QA_Finance_Paid_1780070361618`
  - invoice_id: `279`

Aluno QA:

- `QA Aluno Portal`

## Resultado validado

### 1. Emissao de boleto

Resultado:

- fluxo executado sem erro no HML
- `bank_slips` criado para a fatura `278`
- status registrado: `pending`
- mensagem visivel na UI:
  - `Integracao de boleto nao configurada. Registro pendente criado.`

Leitura pratica:

- o deploy nao quebrou o fluxo;
- o HML atual nao emite boleto como `issued`, porque a integracao de boleto neste contexto ainda nao esta configurada para isso.

### 2. Baixa manual

Resultado:

- fluxo executado com sucesso no HML
- a fatura `279` passou para `paid`
- `paid_amount = 209.90`
- `paid_at = 2026-05-29`
- evidencia visivel na UI:
  - `Conta baixada`
  - `Pago em: 2026-05-29`

### 3. Logs financeiros por evento

Resultado confirmado no banco:

- fatura `279`
  - `notification_type = invoice_paid`
  - `recipient_type = student`
  - `status = sent`
- fatura `278`
  - nao houve `invoice_issued`

Motivo do `invoice_issued` nao aparecer:

- o HML registrou o boleto da fatura `278` como `pending`
- o codigo dispara `invoice_issued` apenas quando o retorno do fluxo fica em `issued` ou `registered`

## Evidencias objetivas

### Estado da UI

Fatura boleto:

- `Pendente`
- `Regerar boleto`
- `Sincronizar status`
- `Integracao de boleto nao configurada. Registro pendente criado.`

Fatura paga:

- `Pago`
- `Conta baixada`
- `Pago em: 2026-05-29`

### Estado do banco

Fatura `278`:

- segue `open`
- possui registro em `bank_slips` com `status = pending`

Fatura `279`:

- ficou `paid`
- possui log em `finance_notification_logs` com `invoice_paid`

## Conclusao

A validacao funcional do HML confirmou:

1. o deploy nao quebrou a emissao de boleto;
2. o deploy nao quebrou a baixa manual;
3. o novo log `invoice_paid` esta funcionando no HML;
4. o schema novo aceita os tipos de evento financeiros publicados.

O item `invoice_issued` ficou tecnicamente preparado e publicado, mas depende de um ambiente que realmente retorne `issued` ou `registered` no fluxo de boleto para aparecer em log.
