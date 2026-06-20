# Relatório pré-go-live do HML

Data da execução: 20 de junho de 2026
Ambiente avaliado: `https://erp-hml.aneobrasil.com.br`
Escopo: processos críticos, carga até 100 alunos e segurança controlada em homologação.

## Parecer executivo

O HML concluiu os fluxos funcionais e suportou 100 alunos simultâneos sem erros funcionais. Uma segunda rodada separou a autenticação da navegação e confirmou que 100 alunos já autenticados conseguem acessar simultaneamente o painel, os cursos, o player e as provas com tempos adequados.

As correções de aplicação foram publicadas e retestadas no HML: proteção contra força bruta, cabeçalhos HTTP, CORS restrito, validação de uploads, retirada dos segredos do arquivo padrão e atualização das dependências do PWA. Zoom, YouTube, portal do aluno, PWA e fluxos administrativos continuaram funcionando após a publicação.

O bloqueio crítico remanescente é externo ao código: MariaDB na porta 3306 e FTP na porta 21 continuam acessíveis pela internet. Também é obrigatória a rotação dos segredos que já estiveram versionados.

Status atual: **aplicação aprovada no HML; liberação para go-live condicionada ao fechamento dos riscos de infraestrutura e à rotação das credenciais**.

## Processos funcionais

Resultados aprovados:

- Login administrativo e seleção de empresa.
- Painel administrativo e rotas principais.
- Cadastro de lead em HML.
- Consulta de alunos QA.
- Rematrícula liberada e rematrícula bloqueada por pendência.
- Portal do aluno, escala, intercâmbio e chamados.
- Recebimento do chamado pelo portal de suporte.
- Criação de token de API, consulta de alunos e criação de lead por API.
- Dúvidas do aluno enviadas ao professor.
- Jully com rodada real de perguntas.
- PWA com login, painel, negociação, aditivo, degustação, consulta financeira e fila.
- Player externo do YouTube carregado em 10 navegadores reais simultâneos.
- Reunião Zoom criada pela API, publicada no portal e validada externamente.
- Link da reunião Zoom exibido corretamente para 100 de 100 alunos simultâneos.
- Reunião de teste cancelada no ERP e no fluxo integrado após a validação.

Observação: a primeira execução do PWA falhou apenas na gravação do trace do Playwright dentro da pasta sincronizada pelo OneDrive. Todos os passos funcionais haviam passado. A repetição com trace desligado foi aprovada.

## Massa e teste de carga

Foram criadas 100 contas com o prefixo `QA Carga`, portal ativo e matrícula no curso QA já validado. As credenciais estão armazenadas somente em `tests/load/credentials.local.json`, arquivo ignorado pelo Git.

| Usuários simultâneos | Sucessos | Falhas | Login p95 | Painel p95 | Duração total |
|---:|---:|---:|---:|---:|---:|
| 10 | 10 | 0 | 354 ms | 86 ms | 1.075 ms |
| 25 | 25 | 0 | 948 ms | 399 ms | 1.806 ms |
| 50 | 50 | 0 | 1.934 ms | 1.112 ms | 3.456 ms |
| 100 | 100 | 0 | 3.928 ms | 3.026 ms | 7.163 ms |

Conclusão:

- O sistema processou os 100 fluxos completos sem perda de resposta ou erro funcional.
- A rodada inicial, com 100 logins disparados no mesmo instante, ultrapassou a meta de 2,5 segundos no login.
- Os índices de usuário, login do portal e matrícula foram conferidos e já existem.
- O servidor usa PHP 8.2.30, OPcache ativo com 196 MB e limite de memória de 1,5 GB.
- Não foi reduzido o custo seguro do hash de senha para mascarar o resultado.

### Navegação simultânea após autenticação

Foi executado um cenário mais próximo de uma aula real: os 100 alunos autenticaram em lotes de 10 e, em seguida, navegaram simultaneamente.

| Etapa | Sucessos | Falhas | p95 |
|---|---:|---:|---:|
| Login controlado | 100 | 0 | 455 ms |
| Painel do aluno simultâneo | 100 | 0 | 793 ms |
| Lista de cursos simultânea | 100 | 0 | 372 ms |
| Player da aula simultâneo | 100 | 0 | 310 ms |
| Provas simultâneas | 100 | 0 | 189 ms |

Conclusão: o uso simultâneo por 100 alunos foi aprovado. A degradação observada anteriormente está concentrada no cenário artificial de 100 verificações de senha iniciadas no mesmo instante, e não na aula, no player ou na navegação normal do portal.

## YouTube e Zoom

### YouTube

O vídeo do curso QA foi aberto em 10 navegadores reais simultâneos. O teste confirmou:

- Carregamento da YouTube IFrame API.
- Respostas externas do YouTube sem erros HTTP.
- Criação do iframe do vídeo.
- ID válido do vídeo: `jSq2qaQ4WAc`.
- Duração retornada pelo player: 3.105 segundos.
- Integração do player com o controle de progresso do portal.

O teste de carga anterior já havia validado 100 alunos acessando a página do curso, abrindo a aula e enviando progresso ao servidor. A combinação das duas rodadas cobre a carga no sistema ANEO e a disponibilidade externa do player.

### Zoom

Foram executadas duas criações controladas durante o ajuste da automação. A rodada final criou a sessão de teste `22` e confirmou:

- Credenciais Zoom válidas no HML.
- Criação real da reunião pela API do Zoom.
- Retorno de Meeting ID, senha e `join_url`.
- Gravação da aula no ERP.
- Exibição da aula e do mesmo link no portal do aluno.
- Consulta simultânea de 100 contas QA: 100 sucessos e nenhuma falha.
- Abertura do link externo no domínio oficial `zoom.us`.
- Rota externa da reunião confirmada com retorno de sucesso.
- Cancelamento da aula após o teste.

O navegador automatizado não abre o aplicativo Zoom instalado, pois esse passo depende de um diálogo nativo do computador. A validação confirmou tudo que o sistema ANEO controla: criação, armazenamento, publicação, entrega do link e acesso à página oficial da reunião.

## Segurança aprovada

- HTTPS redireciona corretamente a partir do HTTP.
- TLS 1.2 foi negociado com certificado válido.
- Cookies de sessão usam `Secure`, `HttpOnly` e `SameSite=Lax`.
- Requisições sem CSRF foram rejeitadas com HTTP 419.
- Rotas administrativas, financeiras, do aluno e do suporte redirecionam usuários sem sessão.
- APIs de negócio sem token retornam HTTP 401.
- `.git` e o diretório de uploads não permitem listagem pública.
- Payloads controlados de SQL injection, XSS, travessia de diretório e entrada longa não autenticaram, não foram refletidos e não expuseram erros internos.
- Acesso FTP anônimo foi rejeitado.

## Correções de segurança aplicadas no HML

- Proteção contra força bruta ativa nos logins administrativo, aluno, suporte e API móvel.
- Bloqueio confirmado após cinco tentativas inválidas, com HTTP 429 na API móvel.
- Cabeçalhos HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy` ativos.
- CORS limitado aos domínios oficiais; origem externa não autorizada deixou de receber permissão.
- Uploads protegidos por extensão, MIME real, tamanho, assinatura de PHP e marcador EICAR.
- Arquivo público de uploads protegido contra execução e acesso indevido.
- Segredos removidos dos valores padrão versionados; configuração real do HML transferida para `config.local.php` com permissão `0600`.
- Vite, React e Babel atualizados no PWA; `npm audit` passou sem vulnerabilidades.
- PWA recompilado, publicado e aprovado nos testes funcionais.
- Testes funcionais completos, Zoom, YouTube e carga repetidos após as correções.
- Acesso remoto irrestrito (`%`) removido do banco antigo de HML no painel Hostinger.
- Chave SSH exclusiva cadastrada e validada antes da rotação da senha FTP/SSH.
- CSP reforçada com origens explícitas, `object-src 'none'`, `base-uri`, `form-action` e `frame-ancestors`.
- Cabeçalhos de segurança aplicados também aos arquivos estáticos.
- Identificação `X-Powered-By` removida das respostas.

## Varredura dinâmica

O OWASP ZAP Baseline foi executado contra a tela de login do HML antes e depois do endurecimento.

| Rodada | Falhas | Avisos | Regras aprovadas |
|---|---:|---:|---:|
| Inicial | 0 | 16 | 51 |
| Após endurecimento | 0 | 14 | 53 |

Foram eliminados os alertas de exposição da versão do PHP e ausência geral de CSP. Os cabeçalhos passaram a ser aplicados aos arquivos estáticos do sistema.

Os avisos residuais se concentram em:

- Tailwind carregado pelo CDN oficial, sem SRI.
- Uso de scripts e estilos embutidos, necessário na arquitetura atual.
- Páginas de erro `404` geradas pela infraestrutura da Hostinger, fora da aplicação.
- Recomendações adicionais de isolamento entre origens que podem afetar YouTube e Zoom.

Não foram encontrados alertas de severidade alta. O endurecimento foi mantido porque os testes administrativo, portal do aluno, dúvidas, PWA e YouTube passaram após a publicação.

## Riscos residuais

### Crítico: banco de dados exposto

A porta 3306 está acessível pela internet e respondeu com banner do MariaDB 11.8.6. O serviço anuncia `mysql_native_password`.

Ação obrigatória:

- Fechar a porta 3306 para a internet.
- Se acesso remoto for indispensável, liberar somente IPs administrativos conhecidos.
- Usar túnel SSH ou recurso seguro equivalente.
- Rotacionar a senha do banco após o fechamento.
- Confirmar que o usuário da aplicação possui somente os privilégios necessários.

### Crítico: rotação dos segredos

Os valores foram retirados do arquivo padrão atual, mas credenciais antigas permaneceram no histórico do Git e devem ser consideradas expostas.

Ação obrigatória:

- Revogar e rotacionar todos os valores expostos.
- Atualizar o `config.local.php` protegido após cada rotação.
- Limpar o histórico do Git antes de publicar a versão saneada, quando aplicável.
- Verificar logs de uso dos tokens antigos.

### Médio: serviços de rede expostos

Portas identificadas como abertas:

- 21: FTP, com acesso anônimo bloqueado.
- 80: HTTP com redirecionamento.
- 443: HTTPS.
- 3306: MariaDB.
- 65002: SSH.

Ação recomendada:

- Desativar FTP se não for necessário.
- Preferir SFTP.
- Restringir SSH por IP quando o plano permitir.
- Manter 3306 fechado ou limitado por IP.

## Pendências da rodada

- Os logs HTTP e a quantidade de workers PHP não estão acessíveis pela conta SSH do plano Hostinger.
- Resta executar teste de restauração de backup.
- Resta concluir a rotação da senha FTP/SSH no painel Hostinger.
- Resta rotacionar as credenciais e tokens que já estiveram no histórico do Git.

## Critérios para nova aprovação

1. Fechar ou restringir a porta 3306.
2. Desativar o FTP ou restringi-lo, mantendo SFTP como opção preferencial.
3. Rotacionar todos os segredos que já estiveram versionados.
4. Executar varredura dinâmica complementar e analisar os logs disponíveis no painel.
5. Executar teste de restauração de backup.
6. Repetir uma validação curta após as alterações de infraestrutura.

## Evidências locais

- `test-results/aneo-e2e-hml-results.json`
- `test-results/aneo-e2e-hml-mobile-results.json`
- `test-results/jully-hml-transcript-*.md`
- `test-results/student-load-smoke-*.json`
- `test-results/student-authenticated-load-*.json`
- `test-results/hml-media-integrations/youtube-*.json`
- `test-results/hml-media-integrations/zoom-*.json`
- `test-results/hml-media-integrations/zoom-portal-load-*.json`
- `tests/load/provision-hml-load-users.mjs`
- `tests/e2e/hml-media-integrations.mjs`
- `tests/load/credentials.local.json` (local e ignorado pelo Git)
