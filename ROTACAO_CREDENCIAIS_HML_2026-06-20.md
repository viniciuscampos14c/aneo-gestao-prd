# Rotação de credenciais do HML

Data da auditoria: 20 de junho de 2026
Ambiente: homologação
Produção: nenhuma credencial alterada.

## Resultado da comparação

As credenciais internas de HML e produção possuem valores diferentes. Nenhum segredo ativo localizado no `config.local.php` de produção coincide com os valores antigos encontrados no histórico do Git.

No HML, as credenciais abaixo ainda coincidem com valores que já foram versionados:

| Integração | Credencial | Situação | Ação necessária |
|---|---|---|---|
| OpenRouter | Chave da API administrativa | Ativa e exposta no histórico | Revogar no OpenRouter, criar outra e atualizar somente o HML |
| Chatwoot | Token de acesso da API | Ativo e exposto no histórico | Regenerar no Chatwoot e atualizar somente o HML |
| Chatwoot | Token do webhook | Ativo e exposto no histórico | Gerar outro valor e atualizar o emissor e o HML |
| D4Sign | Token da API | Ativo e exposto no histórico | Revogar/regenerar na D4Sign e atualizar somente o HML |
| D4Sign | Chave criptográfica | Ativa e exposta no histórico | Regenerar na D4Sign e atualizar somente o HML |
| D4Sign | Token do webhook | Ativo e exposto no histórico | Gerar outro valor e atualizar o webhook e o HML |
| Automação | Token de matrícula | Ativo e exposto no histórico | Gerar outro valor e atualizar os emissores do HML |
| Automação | Token financeiro | Ativo e exposto no histórico | Gerar outro valor e atualizar os emissores do HML |
| Suporte | Token do webhook externo | Integração externa desativada | Girar o token antes de qualquer reativação |

O token real do cron do HML não foi localizado no histórico. As credenciais ativas de produção também não foram localizadas no histórico auditado.

## Ordem segura

1. Criar a nova credencial no fornecedor.
2. Atualizar apenas o `config.local.php` do HML.
3. Atualizar o webhook ou sistema emissor correspondente.
4. Executar um teste funcional controlado.
5. Revogar a credencial antiga.
6. Registrar data, responsável e evidência, sem armazenar o segredo neste documento.

## Git

O arquivo versionado `public_html/config.php` passou a conter somente valores vazios e integrações desativadas por padrão. O `config.local.php` permanece ignorado pelo Git.

Depois da rotação, o histórico remoto deverá ser saneado. Essa etapa exige reescrita e envio forçado coordenado das branches, pois altera os identificadores dos commits antigos.
