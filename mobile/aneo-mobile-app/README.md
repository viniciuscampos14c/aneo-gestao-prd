# ANEO Mobile App (Diretoria)

Aplicativo mobile executivo para iOS e Android, desenvolvido em React Native + Expo.

## Etapas entregues

- Estrutura inicial do app separada do sistema principal.
- Tela **Dashboard Executivo** com metricas principais do negocio (mock).
- Tela **Negociacao** com busca de aluno, simulador de acordo e acao de envio (mock).
- Tela **Conexao API** para informar URL + Bearer Token e validar acesso.
- Dashboard e Negociacao com **dados reais** quando conectado (resources `students` e `invoices`).
- Fallback automatico para mock quando desconectado.

## Como rodar

```bash
npm install
npm run web
# ou
npm run android
```

## Proximas entregas

1. Persistencia segura do token (storage criptografado).
2. Endpoint real para registrar negociacao e gerar aditivo no backend.
3. Fluxo de assinatura do aditivo e retorno de status.
4. Publicacao nativa Android/iOS nas lojas.
