# Modulos do Sistema ANEO

## Objetivo

Permitir que novas funcionalidades sejam entregues como pacotes oficiais instalaveis pelo painel administrativo, sem alterar diretamente os arquivos do core depois da entrega principal.

## Fase 1 implementada

- Cadastro > Modulos do Sistema.
- Upload de pacote `.zip`.
- Validacao obrigatoria de `module.json` na raiz do ZIP.
- Bloqueio de caminhos inseguros no pacote, como `../`, caminhos absolutos e pastas fora da estrutura permitida.
- Extracao isolada em `public_html/modules/<chave_do_modulo>`.
- Backup logico do pacote enviado em `public_html/uploads/module_packages`.
- Registro de inventario na tabela `system_modules`.
- Registro de permissoes declaradas em `system_module_permissions`.
- Registro de migrations em `system_module_migrations`.
- Auditoria em `system_module_logs`.
- Instalacao sempre inicia como `inativa`.
- Ativacao e desativacao manual pelo administrador.
- Carregamento de menus dinamicos apenas para modulos ativos.
- Carregamento de rotas dinamicas apenas para modulos ativos.

## Estrutura esperada do ZIP

```text
module.json
controllers/
models/
views/
assets/
migrations/
README.md
```

Arquivos opcionais aceitos nesta fase:

```text
routes.php
menu.php
permissions.php
install.php
```

Na fase 2, `routes.php` pode declarar rotas do modulo. Elas so sao carregadas quando o modulo esta ativo.

## Exemplo de module.json

```json
{
  "key": "relatorio_avancado",
  "title": "Relatorio Avancado",
  "version": "1.0.0",
  "min_core_version": "1.0.0",
  "author": "ANEO",
  "description": "Modulo oficial instalado pelo painel administrativo.",
  "permissions": [
    {"key": "relatorio_avancado.view", "label": "Relatorio Avancado: acessar"}
  ],
  "menu": [
    {"label": "Relatorio Avancado", "route": "modules/relatorio_avancado", "icon": "chart-bar", "area": "main"}
  ],
  "migrations": [
    "migrations/001_create_tables.sql"
  ]
}
```

## Exemplo de routes.php

```php
<?php

return [
    [
        'method' => 'GET',
        'route' => 'modules/relatorio_avancado',
        'title' => 'Relatorio Avancado',
        'permission' => 'relatorio_avancado.view',
        'view' => 'index',
    ],
    [
        'method' => 'POST',
        'route' => 'modules/relatorio_avancado/processar',
        'permission' => 'relatorio_avancado.view',
        'controller' => 'RelatorioAvancadoController',
        'action' => 'processar',
    ],
];
```

Regras da fase 2:

- Toda rota precisa iniciar com `modules/<chave_do_modulo>`.
- Views sao carregadas de `views/<nome>.php` dentro da pasta do modulo.
- Controllers sao carregados de `controllers/*.php` dentro da pasta do modulo.
- Models sao carregados de `models/*.php` dentro da pasta do modulo.
- Rotas so abrem para usuarios autenticados.
- Se a rota declarar `permission`, o sistema aplica `require_permission`.

## Regras de seguranca

- O instalador nao sobrescreve `controllers`, `models`, `views`, `assets` ou outros arquivos do core.
- O pacote nao pode conter caminhos fora da pasta do modulo.
- A pasta `public_html/modules` possui `.htaccess` para bloquear acesso direto via navegador.
- A pasta de pacotes enviados recebe `.htaccess` automaticamente para bloquear acesso direto.
- Migrations com `DROP`, `TRUNCATE` ou `RENAME` sao bloqueadas nesta fase.
- Um modulo instalado nao fica ativo automaticamente.

## Proximas fases sugeridas

1. Atualizacao de modulo por versao com backup antes de substituir arquivos.
2. Desinstalacao segura com manifestos de rollback.
3. Assinatura digital do pacote para aceitar somente instaladores gerados oficialmente.
4. Empacotador oficial para gerar ZIPs padronizados.
