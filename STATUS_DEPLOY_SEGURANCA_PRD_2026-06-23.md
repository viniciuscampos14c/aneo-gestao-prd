# Deploy de segurança em produção

Data: 23 de junho de 2026

## Escopo publicado

Foram publicados somente os 19 arquivos definidos em `deploy/prd_security_manifest_20260623.txt`.

Principais entregas:

- rate limiting nos logins administrativo, aluno, suporte e API móvel;
- CORS restrito aos domínios oficiais;
- cabeçalhos HTTP, HSTS e CSP;
- câmera autorizável somente para a própria origem;
- validação reforçada de uploads;
- bloqueio de acesso direto à pasta de uploads;
- remoção de segredos do arquivo padrão versionado.

Não houve migration ou alteração de schema.

## Preservações

Não foram alterados:

- `config.local.php`;
- banco de dados, certificados, chaves, SMTP, cron ou Itaú;
- módulo `provas_assistidas`;
- `StudentPortalController.php`;
- `SystemModuleRuntime.php`;
- `views/layouts/student.php`;
- tabelas iniciadas por `assisted_`.

## Backup e rollback

Backup técnico protegido:

`/home/u674156040/secure/aneo-prd/deploy_backups/security_predeploy_20260623_105154`

Resultado:

- 19 caminhos registrados;
- 16 arquivos copiados;
- três arquivos novos documentados como ausentes;
- zero divergências de hash;
- arquivo compactado de rollback criado e protegido.

## Validações

- Smoke público e rotas protegidas: aprovado.
- Cabeçalhos e CSP: aprovados.
- CORS oficial permitido e origem externa bloqueada.
- Rate limiting: cinco respostas `401`, sexta tentativa `429`.
- Upload CSV válido: aprovado.
- PHP disfarçado de imagem: bloqueado.
- Pasta pública de uploads: HTTP `403`.
- OWASP ZAP Baseline: zero falhas, 53 regras aprovadas e 14 avisos residuais.
- API móvel: autenticação, token, alunos e faturas aprovados.
- YouTube: 10 de 10 navegadores reais aprovados.
- PWA: HTTP `200` com build publicado.
- Cron Itaú: `boleto_issue_due` e `boleto_sync` permaneceram ativos e com status `ok`.

## Carga

| Alunos | Sucessos | Falhas | Dashboard p95 | Cursos p95 | Player p95 | Provas p95 |
|---:|---:|---:|---:|---:|---:|---:|
| 10 | 10 | 0 | 135 ms | 118 ms | 163 ms | 100 ms |
| 25 | 25 | 0 | 935 ms | 136 ms | 370 ms | 361 ms |
| 50 | 50 | 0 | 1.101 ms | 299 ms | 193 ms | 236 ms |
| 100, primeira rodada | 93 | 7 | 2.702 ms | 1.031 ms | 855 ms | 965 ms |
| 100, repetição após 30 segundos | 100 | 0 | 2.076 ms | 1.448 ms | 879 ms | 1.328 ms |

A primeira rodada de 100 apresentou sete encerramentos transitórios de conexão. A aplicação continuou saudável e a repetição, com a mesma metodologia, foi aprovada em 100 de 100 acessos.

## Massa temporária

Foram criados, mediante autorização:

- 100 alunos sintéticos;
- 100 contas de portal;
- 100 matrículas;
- três usuários internos QA.

Todos possuíam mensalidade zero, geração financeira desativada e endereços `.test`.

Após os testes, a massa foi removida. Contagens finais:

- alunos: 67;
- usuários: 7;
- faturas: 2;
- boletos: 2;
- matrículas: 1;
- alunos, usuários, portais e tokens QA: zero.

## Conclusão

O pacote de segurança está publicado e validado em produção. O módulo de Provas Assistidas permanece fora desta entrega e poderá ser publicado separadamente pelo responsável, sobre a base atual.
