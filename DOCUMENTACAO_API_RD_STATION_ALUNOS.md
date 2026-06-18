# API ANEO - Integracao RD Station para cadastro de alunos

## Endpoint

```text
POST https://aneo.aneobrasil.com.br/api.php?r=rdstation_students
```

## Autenticacao

Enviar o token fornecido pela ANEO exclusivamente no header:

```http
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
```

O token precisa ter apenas a permissao `rdstation_students.create`.

## Empresas de producao

O campo tecnico `company_id` e obrigatorio para direcionar o aluno a filial correta.

| company_id | Empresa |
|---:|---|
| 4 | ANEO BRASILIA |
| 5 | ANEO BAHIA |
| 6 | ANEO RIO DE JANEIRO |
| 7 | ANEO PALMAS |
| 8 | ANEO GOIANIA |
| 10 | ANEO SAO PAULO |

## Campos

| Campo JSON | Tipo | Obrigatorio | Formato e regra |
|---|---|---:|---|
| `company_id` | inteiro | Sim | ID da empresa conforme tabela acima |
| `full_name` | texto | Sim | Nome completo do aluno |
| `email` | texto | Sim | E-mail valido |
| `phone` | texto | Sim | Telefone brasileiro com DDD, 10 ou 11 digitos |
| `city` | texto | Sim | Cidade do aluno |
| `birth_date` | data | Sim | `YYYY-MM-DD`; deve ser anterior a data atual |
| `rg` | texto | Sim | RG do aluno |
| `cpf` | texto | Sim | CPF valido, com ou sem pontuacao |
| `cro` | texto | Nao | Registro CRO, quando houver |
| `enrolled_at` | data | Sim | Data de entrada no formato `YYYY-MM-DD` |
| `invoice_due_day` | inteiro | Sim | Dia mensal de vencimento das faturas, entre 1 e 31 |

`invoice_due_day` configura o dia de vencimento no cadastro do aluno. Esse endpoint nao cria faturas nem boletos.

## Exemplo JSON

```json
{
  "company_id": 5,
  "full_name": "Maria da Silva",
  "email": "maria.silva@example.com",
  "phone": "71999998888",
  "city": "Salvador",
  "birth_date": "1995-08-21",
  "rg": "123456789",
  "cpf": "529.982.247-25",
  "cro": "CRO-BA 12345",
  "enrolled_at": "2026-06-18",
  "invoice_due_day": 10
}
```

Exemplo sem CRO:

```json
{
  "company_id": 4,
  "full_name": "Joao Pereira",
  "email": "joao.pereira@example.com",
  "phone": "61988887777",
  "city": "Brasilia",
  "birth_date": "1992-03-14",
  "rg": "987654321",
  "cpf": "111.444.777-35",
  "enrolled_at": "2026-06-18",
  "invoice_due_day": 15
}
```

## Exemplo cURL

```bash
curl --request POST \
  --url "https://aneo.aneobrasil.com.br/api.php?r=rdstation_students" \
  --header "Authorization: Bearer SEU_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "company_id": 5,
    "full_name": "Maria da Silva",
    "email": "maria.silva@example.com",
    "phone": "71999998888",
    "city": "Salvador",
    "birth_date": "1995-08-21",
    "rg": "123456789",
    "cpf": "52998224725",
    "cro": "CRO-BA 12345",
    "enrolled_at": "2026-06-18",
    "invoice_due_day": 10
  }'
```

## Respostas

### Aluno criado

HTTP `201 Created`

```json
{
  "ok": true,
  "data": {
    "action": "created",
    "student": {
      "id": 123,
      "company_id": 5,
      "full_name": "Maria da Silva",
      "email_primary": "maria.silva@example.com",
      "billing_day": 10
    }
  }
}
```

### Aluno atualizado por reenvio

HTTP `200 OK`

```json
{
  "ok": true,
  "data": {
    "action": "updated",
    "student": {
      "id": 123,
      "company_id": 5,
      "full_name": "Maria da Silva"
    }
  }
}
```

### Dados invalidos

HTTP `422 Unprocessable Entity`

```json
{
  "ok": false,
  "message": "cpf invalido.",
  "code": 422
}
```

### CPF em outra empresa

HTTP `409 Conflict`

```json
{
  "ok": false,
  "message": "CPF ja cadastrado em outra empresa. Contate a ANEO para transferir o aluno.",
  "code": 409
}
```

## Idempotencia

- O CPF e a chave principal de identificacao.
- Reenviar o mesmo CPF para a mesma empresa atualiza o aluno existente.
- Se nao houver CPF correspondente, o sistema procura o e-mail dentro da empresa.
- O mesmo CPF nao pode criar alunos em empresas diferentes.
- A RD Station pode repetir uma requisicao apos timeout sem gerar duplicidade.

## Seguranca

- Nunca enviar o token na URL ou no corpo JSON.
- Nao registrar o token em planilhas, prints ou tickets.
- Utilizar somente HTTPS.
- Solicitar a revogacao imediata do token em caso de vazamento.
