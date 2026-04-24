# Projeto Mobile ANEO (iOS + Android + Web)

## Objetivo

Aplicativo executivo para diretoria com foco em:

- Visao estrategica do negocio (alunos, financeiro, inadimplencia, recuperacao).
- Acesso rapido para negociacao de alunos.
- Geracao e envio de aditivo para o sistema central.

## Status atual

Primeira versao inicial entregue (MVP de base):

- Estrutura React Native + Expo criada.
- Tela `Dashboard Executivo` com indicadores.
- Tela `Negociacao` com busca de aluno e simulacao de acordo.
- Publicacao web inicial realizada em `https://mobile.aneobrasil.com.br`.

Etapa 2 entregue:

- Tela `Conexao API` para configurar URL + Bearer Token.
- Integracao real com API `api.php` para leitura de:
  - `students` (alunos)
  - `invoices` (faturas)
- Dashboard passa a calcular indicadores reais da empresa do token.
- Negociacao passa a listar alunos e dividas reais.

Etapa 3 entregue:

- Atualizacao automatica no mobile a cada 5 minutos.
- Botao `Atualizar agora` nas telas de Indicadores e Negociacao.
- Envio real para CRM:
  - `Gerar aditivo` e `Enviar negociacao` criam registro via `POST api.php?r=tickets`.

Etapa 4 entregue:

- Novo endpoint backend `POST api.php?r=mobile-auth`.
- Login no app por usuario/senha da diretoria (sem inserir token manual).
- Token gerado automaticamente no ERP, com permissoes de `students`, `invoices` e `tickets`.
- Token salvo localmente no app para manter conexao entre acessos.

Etapa 5 entregue:

- Selecao de empresa no login mobile para usuarios com multiplos CNPJs.
- Fluxo em duas etapas no endpoint `mobile-auth`:
  - retorna lista de empresas quando necessario (`auth_status=company_required`);
  - finaliza autenticacao ao receber `company_id`.

Etapa 6 entregue:

- Aplicacao da logo oficial ANEO no app:
  - cabecalho principal;
  - icones de app (`icon`, `adaptive`, `splash`, `favicon`).
- Preparacao de publicacao nativa com EAS:
  - `eas.json` criado;
  - scripts de build/submit adicionados;
  - guia `PUBLICACAO_LOJAS.md` criado.

Etapa 7 entregue:

- Login obrigatorio na entrada do app (usuario/senha) antes de abrir as abas internas.
- Remocao da URL da API da tela inicial de login para simplificar o uso final.
- Nova logo aplicada no cabecalho do app com destaque maior e composicao ajustada.
- Texto da marca (`ANEO DIRETORIA`) posicionado abaixo da logo na tela inicial.
- Novo arquivo de tela de acesso: `src/screens/AppLoginScreen.tsx`.

## Estrutura principal

```txt
mobile/aneo-mobile-app/
|-- App.tsx
|-- src/
|   |-- components/MetricCard.tsx
|   |-- services/
|   |-- screens/DashboardScreen.tsx
|   |-- screens/NegotiationScreen.tsx
|   `-- types/index.ts
|-- assets/
|-- package.json
`-- README.md
```

## Regra de separacao (mobile x ERP)

- Implementacoes mobile devem ser realizadas apenas em `mobile/aneo-mobile-app`.
- Nao incluir arquivos de `public_html/` em commits de app mobile, salvo quando a tarefa exigir mudanca de API/backend.

## Como executar localmente

```bash
cd mobile/aneo-mobile-app
npm install
npx expo start --lan
```

No celular, abrir o app `Expo Go` e escanear o QR code.

## Deploy web (Hostinger)

```bash
cd mobile/aneo-mobile-app
npx expo export -p web
```

Publicar o conteudo de `dist/` em `public_html/mobile/` no servidor.

## Proximas etapas

1. Criar endpoint dedicado de negociacao/aditivo no backend.
2. Fluxo real de geracao de aditivo PDF + assinatura.
3. Finalizar cadastro nas lojas e concluir submissao de producao.
