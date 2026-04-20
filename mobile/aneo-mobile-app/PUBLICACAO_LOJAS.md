# Publicacao iOS e Android (ANEO Diretoria)

## Status atual

- Projeto preparado com `eas.json`.
- IDs de app definidos:
  - Android package: `br.com.aneobrasil.diretoria`
  - iOS bundle identifier: `br.com.aneobrasil.diretoria`
- Scripts de build e submit adicionados no `package.json`.

## 1) Login Expo

```bash
npx eas login
npx eas whoami
```

## 2) Build de producao

```bash
npm run build:android
npm run build:ios
```

Ou via comando direto:

```bash
npx eas build --platform android --profile production
npx eas build --platform ios --profile production
```

## 3) Publicacao Android (Google Play)

1. Criar app no Google Play Console com package `br.com.aneobrasil.diretoria`.
2. Gerar Service Account JSON no Google Cloud e vincular ao Play Console.
3. Rodar submit:

```bash
npm run submit:android
```

Se preferir apenas upload manual, baixar o `.aab` gerado no EAS e enviar no Play Console.

## 4) Publicacao iOS (App Store Connect)

1. Criar app no App Store Connect com bundle `br.com.aneobrasil.diretoria`.
2. Ter assinatura Apple Developer ativa.
3. Configurar certificados (o EAS pode gerenciar automatico).
4. Rodar submit:

```bash
npm run submit:ios
```

Se preferir manual, baixar o `.ipa` e enviar pelo Transporter.

## 5) Checklist rapido antes do envio

- Atualizar `expo.version` (ex: `1.0.1`).
- Revisar changelog da versao.
- Validar login, indicadores e negociacao em dispositivo real.
- Confirmar logo/icone no app.
