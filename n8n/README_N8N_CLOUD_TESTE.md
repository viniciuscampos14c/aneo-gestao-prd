# Guia Rapido n8n Cloud -> ANEO (Entrada de Aluno)

Este guia conecta seu workflow n8n ao endpoint do ANEO:

`POST /index.php?route=automations/webhook/enrollment`

## 1) Prerequisitos

1. ANEO local rodando no XAMPP.
2. ngrok expondo local (exemplo `ngrok http 80`).
3. Token configurado em `public_html/config.php`:
   - `automation.enrollment_webhook_token`
4. Workflow n8n com Webhook Trigger criado.

## 2) URL do ANEO para o node HTTP Request

Use:

`https://SEU-NGROK.ngrok-free.app/aneo/index.php?route=automations/webhook/enrollment`

## 3) Sequencia de nodes no n8n

1. `Webhook` (POST, path `aneo-enrollment`)
2. `IF` (regra de ativacao)
3. `HTTP Request` (chama endpoint ANEO)
4. `Respond to Webhook` (opcional se usar resposta customizada)

## 4) Regra IF (exemplo simples)

Regra para seguir no fluxo:

1. `payment_status` em `confirmed|received|paid`
2. `contract_status` em `signed|completed|concluded|done`

Alternativa:

1. no payload, enviar `force_activate=true` e pular IF.

## 5) Config do node HTTP Request

1. Method: `POST`
2. URL: `https://SEU-NGROK.ngrok-free.app/aneo/index.php?route=automations/webhook/enrollment`
3. Send Headers: `true`
4. Header:
   - `X-ANEO-TOKEN: <seu_token_do_config>`
5. Send Body: `JSON`
6. Body (mapeado do webhook de entrada):

```json
{
  "company_id": 1,
  "lead_id": 10,
  "course_id": 3,
  "payment_status": "confirmed",
  "contract_status": "signed",
  "create_portal_account": true,
  "portal_login": "aluno.teste@exemplo.com",
  "portal_password": "123456",
  "activate_student": true
}
```

## 6) Teste rapido

1. No node `Webhook`, clique `Listen for test event`.
2. Envie POST para `n8n_webhook_test_url` com payload de teste.
3. Valide resposta `ok=true` no n8n.
4. No ANEO, confira:
   - lead convertido
   - aluno ativo
   - matricula criada
   - portal criado (se habilitado)

## 7) Payload pronto

Use o arquivo:

`n8n/payload_teste_enrollment.json`
