# Status validacao Itau HML x producao

Data: 2026-06-18

## Objetivo

Preservar e documentar o fluxo de boletos Itau validado no HML em 17/06/2026, confirmar a baixa do pagamento de ponta a ponta e verificar se o codigo e o schema de producao estao alinhados.

## Resultado executivo

- HML: fluxo de emissao e pagamento validado.
- Producao: codigo e schema do recorte Itau iguais ao HML.
- Producao: integracao Itau e forma de pagamento integrada continuam desativadas.
- GitHub: o recorte validado foi preparado para commit e envio neste fechamento.
- Nenhuma configuracao sensivel, credencial ou identificador bancario foi registrada neste documento.

## Evidencia funcional HML

Fatura utilizada:

- Numero: `FATURA-000494-26`
- Valor: `R$ 5,00`
- Boleto: provider `itau`
- Emissao: `17/06/2026 16:33:49`
- Pagamento: `17/06/2026`
- Status final do boleto: `paid`
- Status final da fatura: `paid`
- Valor pago na fatura: `R$ 5,00`

Pagamento registrado:

- Referencia: `PG-20260617173838`
- Metodo: `ITAU - Boleto API`
- Valor: `R$ 5,00`
- Data: `17/06/2026`
- Origem registrada: baixa automatica por sincronizacao da API de boleto
- Vinculo confirmado em `payment_items` com a fatura `494`

Notificacoes:

- `invoice_issued`: enviado ao aluno em `17/06/2026 16:33:50`
- `invoice_paid`: enviado ao aluno em `17/06/2026 17:38:38`

## Validacao do webhook

Em 18/06/2026 o endpoint recebeu/processou uma notificacao Itau referente ao mesmo boleto:

- situacao recebida: `paga`
- data de pagamento: `17/06/2026`
- valor pago: `R$ 5,00`
- mensagem persistida: `Status atualizado por webhook Itau.`

O processamento foi idempotente: a fatura permaneceu paga em `R$ 5,00`, sem duplicacao do valor.

## Leitura da linha do tempo

1. O boleto foi emitido pelo Itau em 17/06/2026.
2. Uma primeira tentativa de consulta retornou HTTP 403 as 17:04.
3. A sincronizacao posterior reconheceu o pagamento as 17:38 e gerou a baixa automatica.
4. O evento `invoice_paid` foi disparado com sucesso.
5. O webhook confirmou posteriormente a situacao paga sem duplicar a baixa.

## Validacoes automatizadas

Executadas em 18/06/2026:

- `php -l` nos arquivos centrais do recorte: aprovado.
- `npm run test:e2e:hml`: aprovado, 1 teste.
- `npm run test:e2e:hml:mobile`: aprovado, 1 teste.
- Tela de faturas HML: acoes de boleto e exibicao dos dados publicadas.

## Comparacao de codigo

Os hashes SHA-256 dos arquivos abaixo sao identicos entre:

- workspace local;
- HML;
- producao.

Arquivos conferidos:

- `public_html/controllers/BanksController.php`
- `public_html/controllers/FinanceController.php`
- `public_html/core/ItauService.php`
- `public_html/models/CompanyIntegrationModel.php`
- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/views/banks/itau.php`
- `public_html/views/finance/invoices.php`
- `public_html/views/email/finance_event_notification.php`
- `public_html/index.php`

## Comparacao de schema e configuracao

HML:

- coluna `bank_slips.nosso_numero`: presente;
- coluna `students.cpf`: presente;
- integracao Itau ativa: sim;
- forma de pagamento integrada Itau ativa: sim.

Producao:

- coluna `bank_slips.nosso_numero`: presente;
- coluna `students.cpf`: presente;
- integracao Itau ativa: nao;
- forma de pagamento integrada Itau ativa: nao;
- boletos existentes: `0`.

Conclusao: producao ja possui codigo e estrutura de banco, mas ainda nao esta operacionalmente habilitada para emitir boletos Itau.

## Checkpoint de producao apos autorizacoes

Autorizacoes recebidas:

- `AUTORIZO ALTERAR PROD`
- `AUTORIZO ALTERAR BANCO PROD`

Backups criados antes da ativacao:

- codigo: `/home/u674156040/domains/aneo.aneobrasil.com.br/deploy_backups/itau_code_pre_activation_20260618_144309.tar.gz`
- banco/configuracoes: `/home/u674156040/domains/aneo.aneobrasil.com.br/deploy_backups/itau_db_pre_activation_20260618_114252`

O backup de banco contem:

- 6 registros de `company_integrations` do Itau;
- 6 formas de pagamento integradas;
- estrutura das tabelas `bank_slips` e `students`.

Validacao das seis unidades de producao:

- configuracao em ambiente `production`;
- etapa `efetivacao`;
- instrumento `boleto_pix`;
- Client ID e Client Secret preenchidos;
- ID do beneficiario preenchido;
- token de webhook preenchido;
- certificado e chave privada encontrados em pasta segura fora do document root;
- integracao e forma de pagamento mantidas desativadas.

Validacoes tecnicas:

- lint PHP remoto dos 15 arquivos do recorte: aprovado;
- `/admin`: HTTP 200;
- `/aluno`: HTTP 200;
- `/suporte`: HTTP 200;
- `/api.php`: HTTP 400 esperado sem recurso/token;
- raiz: HTTP 302 esperado para redirecionamento.

Nenhuma alteracao de dados foi necessaria nesta etapa, pois schema, registros e parametros ja estavam preparados. A ativacao continua aguardando autorizacao especifica.

## Validacao preventiva de go-live

Validacoes adicionais executadas sem ativar cobrancas:

- OAuth2/mTLS HML: HTTP 200 e token obtido.
- OAuth2/mTLS producao: HTTP 200 e token obtido.
- certificado de producao valido de `12/01/2026` ate `12/01/2027`;
- certificado e chave privada com permissao `600`;
- diretorios dos certificados com acesso restrito;
- webhook HML com token real: endpoint autenticado e processamento alcancado;
- webhook producao enquanto desativado: retorna token invalido por desenho, pois apenas integracoes ativas aceitam webhook;
- cron sem token em HML e producao: HTTP 401;
- cron producao com token real e `boleto_sync` desativado: token aceito, runner carregado e execucao bloqueada;
- nenhuma fatura de producao vinculada atualmente a forma Itau;
- nenhuma fatura entraria imediatamente na janela automatica de emissao.

Estado do cron:

- HML: jobs financeiros habilitados, agendamento executado em `18/06/2026 11:00:05`, todos com status `ok`.
- Producao: `boleto_issue_due`, `boleto_sync` e notificacoes financeiras desativados, sem execucao anterior.
- Producao: nao existe evidencia de agendamento automatico financeiro ativo.

Estado do webhook:

- a confirmacao real do pagamento foi entregue ao HML;
- o cadastro externo atual deve ser considerado apontado para HML;
- a URL deve ser alterada para producao somente na janela de go-live;
- a API do Itau retornou HTTP 403 para tentativa de consulta GET do webhook, portanto a confirmacao final deve ser feita pelo registro PUT e por uma entrega controlada.

Bloqueios operacionais encontrados:

- todos os alunos atualmente cadastrados em producao estao sem CPF:
  - Brasilia: 24;
  - Bahia: 19;
  - Rio de Janeiro: 14;
  - Palmas: 5;
  - Goiania: 4;
  - Sao Paulo: 1 registro, inativo.
- SMTP permanece desativado em HML e producao;
- integracoes e formas Itau permanecem desativadas em producao;
- cron financeiro de producao permanece desativado;
- ainda nao houve emissao e pagamento controlados usando a URL e o banco de producao.

Conclusao de prontidao:

- codigo, schema, credenciais, certificado, OAuth e fluxo HML: aprovados;
- operacao bancaria em producao: ainda nao liberada;
- os bloqueios obrigatorios sao carga/validacao de CPF, virada do webhook, ativacao controlada dos jobs e teste real de baixo valor em producao.

## Sequencia recomendada para o dia do go-live

1. Concluir e validar os CPFs dos alunos que receberao boleto.
2. Manter `boleto_issue_due` desativado.
3. Ativar a integracao e a forma Itau apenas para a unidade piloto.
4. Registrar no Itau o webhook de producao.
5. Testar o webhook de producao com identificador inexistente.
6. Criar uma fatura piloto de baixo valor com CPF validado.
7. Emitir manualmente o boleto piloto.
8. Pagar e aguardar a baixa por webhook.
9. Se necessario, testar `boleto_sync` manualmente.
10. Conferir `bank_slips`, `payments`, `payment_items`, fatura e notificacoes.
11. Somente depois ativar `boleto_sync`.
12. Ativar `boleto_issue_due` por ultimo.
13. Criar/confirmar o agendamento automatico de producao.
14. Acompanhar o primeiro ciclo completo antes de ampliar para as demais unidades.

## Carga de CPF a partir do Perfex

Fonte:

- Perfex de producao: `sistema.aneobrasil.com.br`;
- tabela de clientes/contatos;
- campo personalizado `customers_cpf`, ID `39`;
- campo fiscal `vat` usado somente como alternativa quando o campo personalizado continha CPF invalido.

Procedimento:

1. Comparacao dos alunos ANEO com clientes/contatos Perfex por:
   - e-mail;
   - telefone normalizado;
   - nome normalizado.
2. Exigencia de correspondencia unica.
3. Validacao matematica dos digitos verificadores do CPF.
4. Backup integral dos registros de alunos antes da escrita.
5. Atualizacao transacional apenas da coluna `students.cpf`.
6. Revalidacao completa contra a fonte Perfex.

Resultado:

- alunos em producao: `66`;
- correspondencias unicas: `66`;
- ambiguidades: `0`;
- alunos sem correspondencia: `0`;
- CPFs validos gravados: `66`;
- CPFs duplicados: `0`;
- divergencias apos revalidacao: `0`.

Distribuicao:

- Brasilia: `24/24`;
- Bahia: `19/19`;
- Rio de Janeiro: `14/14`;
- Palmas: `5/5`;
- Goiania: `4/4`;
- Sao Paulo: sem alunos cadastrados.

Backup:

- `/home/u674156040/domains/aneo.aneobrasil.com.br/deploy_backups/student_cpf_perfex_20260618_120021`

Seguranca:

- backup e mapeamento armazenados fora da pasta publica;
- arquivos com permissao `600`;
- nenhum CPF foi incluido no Git, documentacao ou logs de fechamento;
- integracao Itau e formas de pagamento permaneceram desativadas durante toda a carga.

## Arquivos do recorte a versionar

- `public_html/controllers/BanksController.php`
- `public_html/controllers/FinanceController.php`
- `public_html/controllers/StudentController.php`
- `public_html/core/ItauService.php`
- `public_html/index.php`
- `public_html/models/CompanyIntegrationModel.php`
- `public_html/models/FinanceModel.php`
- `public_html/models/FinanceNotificationModel.php`
- `public_html/models/StudentModel.php`
- `public_html/models/StudentPortalModel.php`
- `public_html/views/banks/itau.php`
- `public_html/views/email/finance_event_notification.php`
- `public_html/views/finance/invoices.php`
- `public_html/views/student_portal/finances.php`
- `public_html/views/students/form.php`
- `migrations/add_nosso_numero_bank_slips.sql`
- `migrations/20260617_students_cpf.sql`

## Protocolo para ativacao em producao

Antes de ativar:

1. Confirmar `AUTORIZO ALTERAR PROD`.
2. Confirmar `AUTORIZO ALTERAR BANCO PROD` se houver ajuste de dados/configuracao no banco.
3. Criar backup dos arquivos e dump das tabelas envolvidas.
4. Conferir dados oficiais da empresa e do beneficiario.
5. Configurar credenciais/certificados exclusivos de producao.
6. Gerar token de webhook exclusivo de producao.
7. Ativar a integracao Itau para a empresa correta.
8. Ativar a forma `ITAU - Boleto API`.
9. Registrar o webhook com a URL de producao.
10. Emitir boleto controlado de baixo valor.
11. Efetuar pagamento e validar baixa, notificacao e idempotencia.

## Regra para evitar nova divergencia

1. Toda alteracao validada no servidor deve ser refletida no workspace no mesmo dia.
2. Comparar hashes local x HML x producao antes de encerrar a atividade.
3. Commitar o recorte aprovado no `main`.
4. Levar somente o commit aprovado para a branch `production`.
5. Publicar producao a partir da branch `production`.
6. Registrar evidencias e hashes em um arquivo `STATUS_*`.
7. Nunca versionar `config.local.php`, certificados, tokens, payloads bancarios completos ou dumps.
