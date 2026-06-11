# Status organizacao Git/producao - ANEO

Data: 2026-06-11

## Objetivo

Deixar claro onde cada ambiente vive, o que pode ser versionado e o que ainda nao deve ir para producao sem validacao. O HML esta reservado para treinamento e nao deve ser alterado por engano.

## Ambientes oficiais

### HML / treinamento

- Admin: `https://erp-hml.aneobrasil.com.br/index.php?route=login`
- Portal do aluno: `https://erp-hml.aneobrasil.com.br/index.php?route=student/login`
- Suporte: `https://erp-hml.aneobrasil.com.br/support.php?route=support/login`
- PWA treino: `https://mobile.aneobrasil.com.br`
- Pasta ERP: `/home/u674156040/domains/aneobrasil.com.br/public_html/erphml`
- Pasta PWA treino: `/home/u674156040/domains/aneobrasil.com.br/public_html/mobile`

Regra: nao alterar HML sem autorizacao explicita contendo `AUTORIZO ALTERAR HML`.

### Producao nova

- Admin: `https://aneo.aneobrasil.com.br/admin`
- Portal do aluno: `https://aneo.aneobrasil.com.br/aluno`
- Portal do aluno alternativo: `https://aneo.aneobrasil.com.br/portaldoaluno`
- Suporte: `https://aneo.aneobrasil.com.br/suporte`
- API: `https://aneo.aneobrasil.com.br/api.php`
- PWA diretoria: `https://diretoria.aneobrasil.com.br`
- Pasta ERP: `/home/u674156040/domains/aneo.aneobrasil.com.br/public_html`
- Pasta PWA: `/home/u674156040/domains/diretoria.aneobrasil.com.br/public_html`
- Banco: `u674156040_aneo_prd`

Regra: toda mudanca em producao precisa de backup, recorte claro e validacao.

## Branches

- `main`: desenvolvimento, preparacao e ajustes ainda em validacao.
- `production`: referencia do codigo aprovado para producao.

Fluxo recomendado:

1. Ajuste nasce em `main`.
2. Validacao e feita localmente ou em ambiente autorizado.
3. Recorte aprovado vai para `production`.
4. Deploy em producao publica somente arquivos do recorte.
5. HML nao participa desse fluxo enquanto estiver reservado para treinamento.

## Estado Git local antes da organizacao

- `main` alinhado com `origin/main` no commit `f46c5acd0428599730156e78572ab7b897feb0e0`.
- Existem alteracoes locais misturadas entre documentacao, PWA/QA, Gestao do Aluno, provas e financeiro.
- Essas alteracoes nao devem ser publicadas em bloco.

## Recortes locais identificados

### Baixo risco / operacional

- Documentos de ambiente, deploy, migracao e continuidade.
- `.htaccess` com URLs amigaveis da nova producao.
- PWA configuravel por `VITE_ANEO_API_BASE_URL`.
- Scripts Playwright adicionais para listagem/validacao controlada.

### Ja tratado ou de baixo risco funcional

- Ajuste visual dos cards da Gestao do Aluno.
- Ocultar menu Atendimento/Chatwoot sem excluir funcionalidade.
- Ajuste de exibicao das ultimas parcelas no card do aluno.

### Medio risco

- Logica de provas objetivas versus dissertativas.
- Layout/resultado de provas.
- Precisa validacao funcional antes de qualquer go-live academico.

### Alto risco

- Financeiro, relatorios, notificacoes, eventos de fatura e webhook Itau.
- Nao ativar Itau nesta fase.
- Nao ativar SMTP/cron financeiro sem autorizacao especifica.

## Pendencias antes de consolidar producao final

- Criar/atualizar branch `production` como referencia separada do `main`.
- Commitar documentacao operacional sem credenciais.
- Separar commits funcionais por recorte.
- Rotacionar credenciais sensiveis antes do go-live final.
- Trocar dados provisiorios da empresa na producao.
- Criar usuarios reais e reduzir dependencia do usuario admin inicial.
- Ativar SMTP, cron e integracoes somente em etapas separadas.

## Regra pratica para proximas manutencoes

Antes de qualquer ajuste, declarar:

- Ambiente alvo: `LOCAL`, `PROD`, `PWA PROD` ou `HML`.
- Tipo: codigo, banco, configuracao, cron, integracao, PWA/build ou documentacao.
- Se pode alterar servidor.
- Se pode alterar banco.
- Se pode gerar e-mail, boleto, webhook ou assinatura.
- Como validar.
- Como voltar.

Sem isso, o padrao e trabalhar apenas localmente e nao tocar HML/producao.
