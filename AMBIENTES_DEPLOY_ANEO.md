# Ambientes e protocolo de deploy - ANEO

Data: 2026-06-09

## Objetivo

Evitar confusao entre HML/treinamento e producao. Nenhum ajuste deve ser feito sem declarar antes o ambiente-alvo, o tipo de mudanca e o plano de rollback.

## Ambientes

### HML / treinamento

Uso:

- Homologacao historica.
- Treinamento da equipe.
- Demonstracoes controladas.

URLs:

- Admin: `https://erp-hml.aneobrasil.com.br/index.php?route=login`
- Portal do aluno: `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`
- Suporte: `https://erp-hml.aneobrasil.com.br/support.php?route=support/login`
- PWA treino: `https://mobile.aneobrasil.com.br`

Servidor:

- Pasta ERP: `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
- Pasta PWA treino: `/home/u674156040/domains/aneobrasil.com.br/public_html/mobile`

Regra:

- Nao tocar no HML sem pedido explicito contendo a frase: `AUTORIZO ALTERAR HML`.
- Nao publicar nada no HML por engano.
- Nao limpar base HML enquanto ela estiver sendo usada para treinamento.
- Nao ativar/desativar cron, SMTP, webhooks ou integracoes no HML sem autorizacao especifica.

### Producao

Uso:

- Ambiente novo, limpo e oficial.
- Cadastros reais.
- Operacao futura.

URLs:

- Admin: `https://aneo.aneobrasil.com.br/admin`
- Portal do aluno: `https://aneo.aneobrasil.com.br/aluno`
- Portal do aluno alternativo: `https://aneo.aneobrasil.com.br/portaldoaluno`
- Suporte: `https://aneo.aneobrasil.com.br/suporte`
- API: `https://aneo.aneobrasil.com.br/api.php`
- PWA diretoria: `https://diretoria.aneobrasil.com.br`

Servidor:

- Pasta ERP: `/home/u674156040/domains/aneo.aneobrasil.com.br/public_html`
- Pasta PWA: `/home/u674156040/domains/diretoria.aneobrasil.com.br/public_html`

Banco:

- `u674156040_aneo_prd`

Regra:

- Toda mudanca em producao precisa de backup antes.
- Toda mudanca em producao precisa de validacao depois.
- Integracoes devem ser ativadas uma por vez.

## Branches Git recomendadas

- `main`: desenvolvimento e preparacao para HML.
- `production`: codigo aprovado para producao.

Regra:

- Mudancas nascem em `main`.
- Validacao acontece fora de producao primeiro, exceto ajustes documentais/operacionais explicitamente autorizados.
- Producao recebe somente recortes aprovados via `production`.
- `config.local.php` nunca entra no Git.
- Senhas, tokens, dumps SQL, backups, prints e arquivos temporarios nunca entram no Git.

## Perguntas obrigatorias antes de qualquer ajuste

Antes de executar qualquer alteracao, responder:

1. Qual ambiente?
   - `PROD`
   - `HML`
   - `LOCAL`
   - `PWA PROD`
   - `PWA HML`

2. Qual tipo de mudanca?
   - codigo
   - banco
   - configuracao
   - cron
   - integracao
   - PWA/build
   - documentacao

3. Pode alterar servidor?
   - sim, com backup
   - nao, somente local/Git

4. Pode alterar banco?
   - nao
   - sim, schema
   - sim, dados

5. Pode gerar efeitos externos?
   - nao
   - sim, envio de e-mail
   - sim, webhook
   - sim, boleto/Itau
   - sim, D4Sign

6. Como validar?
   - URL/tela esperada
   - comando de lint/teste
   - consulta de banco
   - smoke HTTP

7. Como voltar?
   - arquivo de backup
   - dump de banco
   - commit/tag
   - desativar cron/webhook

## Frases de autorizacao

Para reduzir risco, usar estas frases quando quiser mexer em ambiente sensivel:

- `AUTORIZO ALTERAR PROD`
- `AUTORIZO ALTERAR HML`
- `AUTORIZO ALTERAR BANCO PROD`
- `AUTORIZO ATIVAR CRON PROD`
- `AUTORIZO ATIVAR SMTP PROD`
- `AUTORIZO ATIVAR INTEGRACAO PROD`

Sem uma frase clara, o padrao e:

- nao alterar HML;
- nao alterar banco;
- nao ativar cron;
- nao disparar e-mail;
- nao acionar integracoes externas.

## Checklists rapidos

### Ajuste de codigo em producao

1. Confirmar que o alvo e `PROD`.
2. Conferir diff local.
3. Rodar lint/teste aplicavel.
4. Fazer backup do arquivo remoto.
5. Publicar somente arquivos do recorte.
6. Rodar smoke da URL/tela afetada.
7. Registrar no status.

### Ajuste de banco em producao

1. Confirmar `AUTORIZO ALTERAR BANCO PROD`.
2. Fazer dump antes.
3. Aplicar SQL pequeno e revisado.
4. Validar contagens/estrutura.
5. Registrar rollback.

### Ajuste de PWA producao

1. Buildar com `VITE_ANEO_API_BASE_URL=https://aneo.aneobrasil.com.br/api.php`.
2. Conferir que o bundle nao aponta para HML.
3. Fazer backup da pasta atual do PWA.
4. Publicar em `diretoria.aneobrasil.com.br`.
5. Validar `index.html`, `manifest.webmanifest`, `sw.js` e asset principal.

### Cron producao

1. Confirmar `AUTORIZO ATIVAR CRON PROD`.
2. Ativar somente um job por vez.
3. Executar manualmente uma vez.
4. Conferir logs.
5. So depois criar agendamento no Hostinger.

## Estado atual importante

- HML esta reservado para treinamento.
- Producao nova esta em `aneo.aneobrasil.com.br`.
- PWA producao esta em `diretoria.aneobrasil.com.br`.
- Cron producao esta tecnicamente pronto, mas jobs estao desativados e sem agendamento automatico.
- Itau nao esta ativo.
- SMTP ainda nao esta ativo.
