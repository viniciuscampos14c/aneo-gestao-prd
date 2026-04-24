# ANEO Mobile App (Diretoria)

Aplicativo mobile executivo para iOS e Android, desenvolvido em React Native + Expo.

## Etapas entregues

- Estrutura inicial do app separada do sistema principal.
- Tela **Dashboard Executivo** com metricas principais do negocio.
- Tela **Negociacao** com busca de aluno e simulador de acordo.
- Tela **Conexao API** com login por usuario/senha da diretoria (token gerado automaticamente).
- Selecao de empresa no login quando o usuario possui mais de um CNPJ vinculado.
- Logo oficial ANEO aplicada no app (header + icones Android/iOS/Web).
- Dashboard e Negociacao com **dados reais** quando conectado (resources `students` e `invoices`).
- Atualizacao automatica a cada 5 minutos + botao **Atualizar agora**.
- Envio real de negociacao/aditivo para o CRM via `POST api.php?r=tickets`.
- Base de publicacao iOS/Android preparada com `eas.json`.
- Tela de login obrigatoria na abertura do app (usuario + senha) antes de liberar as abas.
- Remocao do campo de URL da API na tela inicial de login (uso de URL padrao do app).
- Atualizacao da identidade visual no topo com nova logo ANEO e destaque maior da marca.

## Isolamento do projeto mobile

- Todo o app esta isolado em `mobile/aneo-mobile-app`.
- Alteracoes do app mobile devem ficar somente neste caminho para nao impactar o ERP (`public_html/...`).

## Como rodar

```bash
npm install
npm run web
# ou
npm run android
```

## Proximas entregas

1. Fluxo de assinatura do aditivo e retorno de status.
2. Endpoint dedicado para negociacao financeira no backend (sem usar tickets).
3. Publicacao nativa Android/iOS nas lojas (processo iniciado).
