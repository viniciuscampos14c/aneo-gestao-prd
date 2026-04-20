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
