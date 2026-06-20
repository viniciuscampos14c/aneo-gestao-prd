# Testes de Carga - Portal do Aluno

Esta pasta concentra os cenarios de carga e concorrencia para o projeto ANEO.

## Objetivo

Simular:

- varios alunos autenticando ao mesmo tempo
- varios alunos navegando no portal
- varios alunos abrindo um curso
- varios alunos enviando progresso de aula

## Arquivos

- `k6-student-portal.js`
  Cenário principal para rodar com `k6`.
- `smoke-student-portal.mjs`
  Runner de smoke concorrente em `Node`, util quando `k6` nao estiver instalado localmente.
- `authenticated-student-portal.mjs`
  Separa o login em lotes da navegação simultânea, permitindo identificar se o gargalo está na autenticação ou no uso do portal.
- `credentials.example.json`
  Exemplo de pool de credenciais para testes.

## Recomendacao de uso

1. Crie um arquivo `credentials.local.json` a partir do exemplo.
2. Preencha com contas reais de QA/HML.
3. Comece com baixa carga:
   - `5` usuarios
   - `10` usuarios
   - `25` usuarios
4. So depois suba para `50+`.

## Runner Node

Exemplo:

```bash
node tests/load/smoke-student-portal.mjs --baseUrl=https://erp-hml.aneobrasil.com.br --vus=5 --iterations=2 --credentials=tests/load/credentials.local.json
```

## Carga com sessões autenticadas

Exemplo:

```bash
node tests/load/authenticated-student-portal.mjs --baseUrl=https://erp-hml.aneobrasil.com.br --loginConcurrency=10 --credentials=tests/load/credentials.local.json
```

## k6

Exemplo:

```bash
k6 run tests/load/k6-student-portal.js -e BASE_URL=https://erp-hml.aneobrasil.com.br -e CREDENTIALS_FILE=tests/load/credentials.local.json
```

## Observacoes

- Evite rodar carga pesada no HML em horario de uso.
- Se o mesmo login for reutilizado por muitos usuarios, o resultado pode ficar distorcido por disputa de sessao.
- Para teste serio de concorrencia, use um pool com varios alunos reais de QA.
