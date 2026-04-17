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

1. Autenticacao real com token da API ANEO.
2. Integracao dos indicadores com dados reais do ERP.
3. Fluxo real de negociacao com persistencia no backend.
4. Geracao de aditivo PDF e envio para assinatura.
5. Publicacao nativa Android (Play Store) e iOS (App Store).
