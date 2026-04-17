# Projeto Mobile ANEO (iOS + Android + Web)

## Objetivo

Aplicativo executivo para diretoria com foco em:

- Visao estrategica do negocio (alunos, financeiro, inadimplencia, recuperacao).
- Acesso rapido para negociacao de alunos.
- Geracao e envio de aditivo para o sistema central.

## Status atual

Primeira versao inicial entregue (MVP de base):

- Estrutura React Native + Expo criada.
- Tela `Dashboard Executivo` com indicadores mock.
- Tela `Negociacao` com busca de aluno, simulacao e acao mock de envio.
- Publicacao web inicial realizada em `https://mobile.aneobrasil.com.br`.

Etapa 2 entregue:

- Tela `Conexao API` para configurar URL + Bearer Token.
- Integracao real com API `api.php` para leitura de:
  - `students` (alunos)
  - `invoices` (faturas)
- Dashboard passa a calcular indicadores reais da empresa do token.
- Negociacao passa a listar alunos e dividas reais (com fallback mock em falha).

## Estrutura principal

```txt
mobile/aneo-mobile-app/
|-- App.tsx
|-- src/
|   |-- components/MetricCard.tsx
|   |-- data/mock.ts
|   |-- screens/DashboardScreen.tsx
|   |-- screens/NegotiationScreen.tsx
|   `-- types/index.ts
|-- assets/
|-- package.json
`-- README.md
```

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

1. Persistir token com seguranca no app.
2. Criar endpoint de negociacao/aditivo no backend.
3. Fluxo real de geracao de aditivo PDF + assinatura.
4. Envio e sincronizacao definitiva para o sistema central.
5. Publicacao nativa Android (Play Store) e iOS (App Store).
