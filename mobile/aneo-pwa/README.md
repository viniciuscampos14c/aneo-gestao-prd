# ANEO PWA

PWA executivo da ANEO criado em projeto separado, sem alterar o ERP principal.

## Direcao oficial

- daqui para frente, a frente mobile da ANEO passa a ser tratada somente como `PWA`
- o app anterior fica apenas como historico tecnico e nao sera a base de continuidade
- todo novo ajuste funcional ou visual deve acontecer em `mobile/aneo-pwa`

## Objetivo

- disponibilizar instalacao por link
- manter autenticacao e dados do ERP atual
- reaproveitar os modulos do app mobile existente
- evitar custo e complexidade de lojas neste momento

## Escopo inicial entregue

- login via `mobile-auth`
- selecao de empresa
- dashboard executivo
- negociacao financeira
- degustacao de cursos
- base de alunos
- central de chamados
- manifest e service worker de PWA

## Evolucao aplicada

- nomenclatura publica ajustada de `PWA` para `APP` na experiencia do usuario
- barra mobile simplificada para `5` modulos principais
- home executiva reduzida para uma leitura mais curta:
  - `Visao do dia`
  - `O que decidir primeiro`
  - `Panorama geral`
- fluxo de negociacao melhorado com:
  - rolagem automatica ao selecionar aluno
  - recolhimento da lista apos selecao
  - botao `Trocar aluno`
- listas padronizadas com exibicao progressiva em:
  - negociacao
  - alunos
  - degustacao
  - chamados
- ajustes mobile de botoes, espacamento e densidade visual para uso real no celular

## Estado atual da interface

- foco exclusivo no projeto `mobile/aneo-pwa`
- ERP principal permanece intocado
- instalacao continua por link com atualizacao web
- home sem blocos duplicados e sem secoes repetindo a mesma informacao

## Publicacao atual

- URL publicada: `https://mobile.aneobrasil.com.br/`
- instalacao por navegador com atalho na tela inicial
- layout simplificado para evitar duplicidade entre navegacao lateral e cards da dashboard

## Como rodar

```bash
cd mobile/aneo-pwa
npm install
npm run dev
```

## Como gerar build

```bash
cd mobile/aneo-pwa
npm run build
```

O resultado vai para `dist/`.

## Observacao importante

Este projeto fica isolado em `mobile/aneo-pwa`.
Nao altera `public_html`, nem substitui o app Expo existente em `mobile/aneo-mobile-app`.
