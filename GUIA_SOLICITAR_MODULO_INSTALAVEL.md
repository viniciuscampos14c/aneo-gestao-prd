# Guia para Solicitar Modulos Instalaveis ANEO

Use este guia sempre que for pedir para uma IA ou desenvolvedor criar uma nova funcionalidade depois da entrega principal do sistema.

O objetivo e garantir que novas demandas sejam entregues como modulos instalaveis pelo painel administrativo, sem alterar diretamente o core do sistema.

## Pedido Padrao Curto

```text
Desenvolva este recurso como modulo instalavel do ANEO, sem alterar o core.

Gere a estrutura completa com:
- module.json
- routes.php
- controllers/
- models/
- views/
- migrations/ quando necessario
- README.md

A chave tecnica sera: [chave_do_modulo].
Todas as rotas devem iniciar com modules/[chave_do_modulo].
O modulo deve instalar inativo.
```

## Pedido Completo Recomendado

```text
Crie um modulo instalavel para o sistema ANEO, seguindo o padrao do Gerenciador de Modulos.

Nome do modulo: [nome do modulo]
Chave tecnica: [chave_em_minusculo_sem_acentos]
Objetivo: [o que o modulo deve fazer]
Menu: [principal ou Cadastro]
Permissoes: [permissoes necessarias]
Telas necessarias:
- [tela 1]
- [tela 2]
- [tela 3]

Regras de negocio:
- [regra 1]
- [regra 2]
- [regra 3]

Banco de dados:
- [tabelas/campos, se souber]
- Se nao precisar de banco, nao gerar migration.

Observacoes:
- Nao alterar arquivos do core.
- Gerar pacote ZIP instalavel.
- O ZIP deve conter module.json na raiz.
- Todas as rotas devem iniciar com modules/[chave_tecnica].
- O modulo deve ser instalado primeiro como inativo.
- Se houver migration, nao usar DROP, TRUNCATE ou RENAME.
```

## Exemplo

```text
Crie um modulo instalavel para o sistema ANEO, seguindo o padrao do Gerenciador de Modulos.

Nome do modulo: Relatorio Comercial Avancado
Chave tecnica: relatorio_comercial
Objetivo: permitir visualizar leads por origem, status, unidade e periodo.
Menu: principal
Permissoes:
- relatorio_comercial.view

Telas necessarias:
- Dashboard do relatorio
- Filtros por periodo, unidade, origem e status
- Exportacao CSV

Regras de negocio:
- Apenas usuarios com permissao podem acessar.
- O modulo nao deve alterar arquivos do core.
- Todas as rotas devem comecar com modules/relatorio_comercial.
- O pacote deve instalar inativo.

Banco de dados:
- Criar tabela somente se necessario.
- Se nao precisar, nao gerar migration.

Gerar um ZIP instalavel com:
- module.json
- routes.php
- controllers/
- models/
- views/
- migrations/ se necessario
- README.md
```

## Estrutura Esperada do ZIP

```text
module.json
routes.php
controllers/
models/
views/
assets/
migrations/
README.md
```

## Regras Obrigatorias

- Nao alterar arquivos do core.
- Nao sobrescrever `controllers`, `models`, `views`, `assets`, `index.php`, `config.php` ou qualquer arquivo principal do sistema.
- O modulo deve ficar isolado em `public_html/modules/[chave_do_modulo]`.
- O `module.json` deve estar na raiz do ZIP.
- A chave tecnica deve usar apenas letras minusculas, numeros, hifen ou underline.
- Todas as rotas devem iniciar com `modules/[chave_do_modulo]`.
- O modulo deve instalar como `inativo`.
- O administrador ativa manualmente depois de revisar.
- Migrations nao podem conter `DROP`, `TRUNCATE` ou `RENAME`.
- Se o modulo nao precisar de banco, nao criar migration.

## Campos Importantes do module.json

```json
{
  "key": "relatorio_comercial",
  "title": "Relatorio Comercial Avancado",
  "version": "1.0.0",
  "min_core_version": "1.0.0",
  "author": "ANEO",
  "description": "Modulo oficial instalado pelo painel administrativo.",
  "permissions": [
    {"key": "relatorio_comercial.view", "label": "Relatorio Comercial: acessar"}
  ],
  "menu": [
    {"label": "Relatorio Comercial", "route": "modules/relatorio_comercial", "icon": "chart-bar", "area": "main"}
  ],
  "migrations": []
}
```

## Exemplo de routes.php

```php
<?php

return [
    [
        'method' => 'GET',
        'route' => 'modules/relatorio_comercial',
        'title' => 'Relatorio Comercial',
        'permission' => 'relatorio_comercial.view',
        'view' => 'index',
    ],
];
```

## Checklist Antes de Instalar

- O ZIP abre normalmente.
- Existe `module.json` na raiz.
- Existe `routes.php` se o modulo tiver tela.
- As rotas iniciam com `modules/[chave_do_modulo]`.
- As permissoes estao declaradas no `module.json`.
- O menu aponta para uma rota valida.
- A migration, se existir, nao contem comandos destrutivos.
- O modulo nao altera arquivos do core.
- O pacote foi gerado para instalar inativo.

## Como Pedir Ajustes Depois

Quando quiser alterar um modulo ja criado, peca assim:

```text
Atualize o modulo instalavel [chave_do_modulo] para incluir [descricao da alteracao].

Mantenha o padrao de modulo instalavel ANEO.
Nao alterar o core.
Gerar novo ZIP com versao incrementada.
Se houver mudanca de banco, criar nova migration incremental.
```

