# Status da revisão visual pt-BR

Data: 2026-06-19

## Escopo

- Correção somente de textos exibidos ao usuário.
- Views administrativas e Portal do Aluno.
- Mensagens de validação, sucesso, erro e API.
- Assuntos e conteúdos de e-mail.
- Textos do PWA.
- Títulos, botões, rótulos, placeholders e exportações.

## Preservado

- Rotas, nomes de campos, chaves e identificadores.
- Enums, status bancários e parâmetros de integrações.
- Termos normalizados usados por busca e IA.
- Endereços técnicos `nao-responda@...`.
- Mapa de recuperação de encoding antigo em `DataImportController`.

## Validações

- `git diff --check`: aprovado.
- PHP lint: 137 arquivos aprovados.
- PWA build: aprovado.
- UTF-8 estrito: 155 arquivos aprovados.
- Skill `ptbr-format`: 153 arquivos aprovados sem mojibake.
- Views: nenhuma ocorrência candidata restante, exceto o endereço técnico `nao-responda@empresa.com`.
- Atributos técnicos `name`, `id`, `route`, `href` e `action`: sem acentuação acidental.

## Exceção documentada

O auditor identifica oito sequências em `DataImportController.php`. Elas são intencionais:

- o sistema reconhece entradas antigas corrompidas, como `Ã§`, e as normaliza;
- remover ou alterar esse mapa poderia quebrar importações legadas;
- o arquivo está em UTF-8 válido.

## PWA lint

O build foi aprovado. O ESLint encontrou 15 erros preexistentes:

- chamadas síncronas de `setState` dentro de `useEffect`;
- uma atribuição não utilizada em `apiClient.ts`.

Esses erros não foram causados pela revisão de textos e não foram alterados para manter o escopo restrito.

## Ambientes

- HML atualizado em 2026-06-19.
- Produção não foi atualizada.

## Publicação no HML

- Overlay seletivo: 133 arquivos e 1.118 linhas.
- Invariante aplicada: somente diacríticos foram alterados; estrutura e quantidade de linhas foram preservadas.
- Integridade remota: 133 arquivos conferidos byte a byte.
- PHP lint remoto: aprovado em todos os arquivos publicados.
- Complemento da tela de relatórios: 3 arquivos com textos visuais corrigidos.

Backups:

- `/home/u674156040/domains/aneobrasil.com.br/deploy_backups/ptbr_visual_20260619_115140`
- `/home/u674156040/domains/aneobrasil.com.br/deploy_backups/ptbr_visual_followup_20260619_115722`

## End-to-end no HML

- Suíte principal: aprovada.
- Administração: login, dashboard, alunos, leads, financeiro, cursos e API.
- Aluno: login, rematrícula permitida e bloqueada, escala, intercâmbio e chamados.
- Suporte: recebimento do chamado criado pelo aluno.
- API: criação de token, consulta de alunos e criação de lead.
- Dúvidas: envio pelo aluno, fila do professor, resposta e notificação no portal.
- Zoom: lista, formulário de criação e opção de aula global renderizados para professor.
- PWA mobile: negociação, aditivo, degustação, alunos e chamados aprovados.

## Regressão Itaú

- Estrutura e configurações obrigatórias presentes no HML.
- Certificado válido até 2027-01-12.
- OAuth mTLS aprovado para a empresa integrada.
- `boleto_sync`: execução aprovada, sem erros.
- Webhook: HTTP 200 e processamento aprovado.
- Idempotência: um pagamento antes e depois do webhook; nenhuma duplicidade.
- Boleto já pago preservado: `FATURA-000494-26`, valor de R$ 5,00, pagamento em 2026-06-17.

Não foi emitido um novo boleto nesta rodada. A regressão utilizou o boleto pago anteriormente para validar consulta, cron, webhook e proteção contra duplicidade.

## Conclusão

O HML está aprovado para promover o pacote visual pt-BR do ERP para produção. A publicação em produção deve repetir o mesmo recorte seletivo, com backup prévio e smoke test posterior. O PWA foi validado funcionalmente, mas sua publicação visual é um pacote separado do ERP.

## Complemento dos perfis

Após revisão visual, foram identificados textos sem acentuação nos dashboards e no Portal do Aluno que não estavam corrigidos no código local usado pelo primeiro overlay.

- Dashboard administrativo revisado.
- Dashboard do professor revisado integralmente.
- Layout administrativo compartilhado revisado.
- Menu, notificações e páginas principais do Portal do Aluno revisados.
- Backup: `/home/u674156040/domains/aneobrasil.com.br/deploy_backups/ptbr_profiles_complete_20260619_121211`.
- Administrador: aprovado em navegador real com `Visão Geral`.
- Aluno: aprovado em navegador real com `Olá` e `Início`.
- Professor: renderização autenticada aprovada e sem os termos antigos sem acento.
- Fluxo de dúvidas e player do curso: aprovado novamente.
