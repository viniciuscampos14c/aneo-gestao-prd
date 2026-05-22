import { expect, test, type Page } from '@playwright/test';

const runId = `QA${Date.now().toString().slice(-6)}`;
const supportUser = `suporte_${runId.toLowerCase()}`;
const supportEmail = `${supportUser}@aneo.local`;
const supportPassword = 'Qa123456!';
const studentLogin = `aluno_${runId.toLowerCase()}`;
const studentPassword = 'Aluno123!';
const studentName = `Aluno E2E ${runId}`;
const leadName = `Lead E2E ${runId}`;
const courseCategoryName = `Categoria ${runId}`;
const courseName = `Curso ${runId}`;
const paymentMethodName = `PIX ${runId}`;
const invoiceProjectOpen = `Projeto Open ${runId}`;
const invoiceProjectPaid = `Projeto Paid ${runId}`;
const ticketSubject = `Chamado ${runId}`;
const apiTokenName = `Token ${runId}`;
const appBaseUrl = 'http://localhost/aneo-e2e';

let studentId = 0;
let apiToken = '';

function appPath(path: string) {
  return `${appBaseUrl}/${path.replace(/^\//, '')}`;
}

async function loginAdmin(page: Page) {
  await page.goto(appPath('/index.php?route=login'));
  await expect(page.locator('body')).toContainText('Entrar no sistema');
  await page.locator('input[name="login"]').fill('admin');
  await page.locator('input[name="password"]').fill('admin123');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/select-company/);
  await page.getByRole('button', { name: 'Entrar na empresa selecionada' }).click();
  await expect(page).toHaveURL(/dashboard/);
}

async function loginStudent(page: Page) {
  await page.goto(appPath('/index.php?route=student/login'));
  await expect(page.locator('body')).toContainText('Entrar no portal');
  await page.locator('input[name="login"]').fill(studentLogin);
  await page.locator('input[name="password"]').fill(studentPassword);
  await page.getByRole('button', { name: 'Entrar' }).click();
}

async function loginSupport(page: Page) {
  await page.goto(appPath('/support.php?route=support/login'));
  await page.locator('input[name="username"]').fill(supportUser);
  await page.locator('input[name="password"]').fill(supportPassword);
  await page.getByRole('button', { name: /Entrar|Acessar/i }).click();
  await expect(page).toHaveURL(/support\.php\?route=support$/);
}

async function expectRoute(page: Page, url: string, text: string | RegExp) {
  const response = await page.goto(appPath(url));
  expect(response?.ok()).toBeTruthy();
  await expect(page.locator('body')).toContainText(text);
}

test('valida fluxos E2E dos paineis admin, aluno, suporte e API', async ({ browser, request }) => {
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();

  await test.step('Login admin e smoke das principais rotas', async () => {
    await loginAdmin(adminPage);

    const adminRoutes: Array<[string, string | RegExp]> = [
      ['/index.php?route=dashboard', 'Visao Geral'],
      ['/index.php?route=users', 'Usuarios'],
      ['/index.php?route=companies', 'Empresas'],
      ['/index.php?route=students', 'Alunos'],
      ['/index.php?route=leads', 'Leads'],
      ['/index.php?route=finance/invoices', 'Faturas'],
      ['/index.php?route=finance/reports', 'Relatorios'],
      ['/index.php?route=chatwoot', 'Chatwoot'],
      ['/index.php?route=signatures', 'Assinaturas'],
      ['/index.php?route=courses', 'Cursos'],
      ['/index.php?route=courses/calendar', 'Agenda Academica'],
      ['/index.php?route=courses/comments', 'Gerenciar Comentarios'],
      ['/index.php?route=courses/live-sessions', 'Aulas ao Vivo'],
      ['/index.php?route=requests', 'Solicitacoes'],
      ['/index.php?route=ai-chat', 'Assistente IA'],
      ['/index.php?route=api-management', 'Gerenciamento de API'],
      ['/index.php?route=system/logs', 'Logs'],
      ['/index.php?route=cron', 'Cron Jobs'],
      ['/index.php?route=data-imports', 'Importacao de Dados'],
      ['/index.php?route=system-modules', 'Modulos do Sistema'],
      ['/index.php?route=gda', /Gestao do Aluno|Pipeline/],
    ];

    for (const [url, text] of adminRoutes) {
      await expectRoute(adminPage, url, text);
    }
  });

  await test.step('Cadastro de forma de pagamento e usuario de suporte', async () => {
    await adminPage.goto(appPath('/index.php?route=finance/payment-methods'));
    await adminPage.getByLabel('Nome *').fill(paymentMethodName);
    await adminPage.getByLabel('Canal').selectOption('pix');
    await adminPage.getByRole('button', { name: 'Adicionar forma manual' }).click();
    await expect(adminPage.locator('body')).toContainText(paymentMethodName);

    await adminPage.goto(appPath('/index.php?route=users/create'));
    await adminPage.getByLabel('Nome *').fill(`Suporte ${runId}`);
    await adminPage.getByLabel('Usuario *').fill(supportUser);
    await adminPage.getByLabel('Email *').fill(supportEmail);
    await adminPage.getByLabel('Senha *').fill(supportPassword);
    await adminPage.getByLabel('Perfil *').selectOption('suporte');
    await adminPage.getByText('ANEO Brasil').click();
    await adminPage.getByRole('button', { name: 'Marcar tudo' }).click();
    await adminPage.getByRole('button', { name: 'Criar usuario' }).click();
    await expect(adminPage).toHaveURL(/users$/);
    await expect(adminPage.locator('body')).toContainText(supportUser);
  });

  await test.step('Cadastro de lead e aluno com acesso ao portal', async () => {
    await adminPage.goto(appPath('/index.php?route=leads/create'));
    await adminPage.getByLabel('Nome completo *').fill(leadName);
    await adminPage.getByLabel('Email').fill(`lead.${runId.toLowerCase()}@mail.com`);
    await adminPage.getByLabel('Telefone').fill('11999999999');
    await adminPage.getByLabel('Valor do lead').fill('3500,00');
    await adminPage.getByLabel('Fonte').fill('Google Ads');
    await adminPage.getByLabel('Unidade').fill('Sao Paulo');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage).toHaveURL(/leads$/);
    await expect(adminPage.locator('body')).toContainText(leadName);

    await adminPage.goto(appPath('/index.php?route=students/create'));
    await adminPage.getByLabel('Nome completo *').fill(studentName);
    await adminPage.getByLabel('Contato principal').fill('Contato QA');
    await adminPage.getByLabel('Email principal').fill(`aluno.${runId.toLowerCase()}@mail.com`);
    await adminPage.getByLabel('Telefone').fill('11988887777');
    await adminPage.getByLabel('Cidade').fill('Barueri');
    await adminPage.getByLabel('RA').fill(`RA-${runId}`);
    await adminPage.getByLabel('RG').fill(`RG-${runId}`);
    await adminPage.getByLabel('CRO').fill(`CRO-${runId}`);
    await adminPage.getByLabel('Status Kanban').selectOption({ index: 1 });
    await adminPage.locator('input[name="portal_login"]').fill(studentLogin);
    await adminPage.locator('input[name="portal_password"]').fill(studentPassword);
    await adminPage.locator('select[name="portal_is_active"]').selectOption('1');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage).toHaveURL(/students\/show&id=\d+/);
    await expect(adminPage.locator('body')).toContainText(studentName);

    const currentUrl = new URL(adminPage.url());
    studentId = Number(currentUrl.searchParams.get('id') || '0');
    expect(studentId).toBeGreaterThan(0);
  });

  await test.step('Escala do aluno: validar regra dos 40 dias e publicacao', async () => {
    const unitName = `Hospital ${runId}`;
    const scheduleTitle = `Escala ${runId}`;

    await adminPage.goto(appPath('/index.php?route=escala-aluno'));
    await adminPage.locator('input[name="name"]').fill(unitName);
    await adminPage.locator('input[name="city"]').fill('Barueri');
    await adminPage.locator('input[name="state"]').fill('SP');
    await adminPage.getByRole('button', { name: 'Salvar unidade' }).click();
    await expect(adminPage.locator('body')).toContainText(unitName);

    await adminPage.goto(appPath(`/index.php?route=students/edit&id=${studentId}`));
    await adminPage.locator('input[name="enrolled_at"]').fill('2026-05-10');
    await adminPage.locator('select[name="practice_unit_id"]').selectOption({ label: unitName });
    await adminPage.locator('select[name="residency_level"]').selectOption('R1');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage.locator('body')).toContainText('Aluno atualizado com sucesso');
    await expect(adminPage.locator('body')).toContainText('19/06/2026');

    await adminPage.goto(appPath('/index.php?route=escala-aluno/create'));
    await adminPage.locator('select[name="unit_id"]').selectOption({ label: unitName });
    await adminPage.locator('input[name="title"]').fill(scheduleTitle);
    await adminPage.locator('input[name="start_date"]').fill('2026-05-26');
    await adminPage.locator('input[name="end_date"]').fill('2026-06-30');
    await adminPage.locator('textarea[name="notes"]').fill('Escala criada para validar elegibilidade por data de entrada.');
    await adminPage.getByRole('button', { name: 'Salvar escala' }).click();
    await expect(adminPage).toHaveURL(/escala-aluno\/show&id=\d+/);
    await expect(adminPage.locator('body')).toContainText(scheduleTitle);

    await adminPage.locator('input[name="r3_slots"]').fill('0');
    await adminPage.locator('input[name="r2_slots"]').fill('0');
    await adminPage.locator('input[name="r1_slots"]').fill('1');
    await adminPage.getByRole('button', { name: 'Gerar grade' }).click();
    await expect(adminPage.locator('body')).toContainText('ainda aguardam os 40 dias');

    await adminPage.goto(appPath(`/index.php?route=students/edit&id=${studentId}`));
    await adminPage.locator('input[name="enrolled_at"]').fill('2026-04-01');
    await adminPage.locator('select[name="practice_unit_id"]').selectOption({ label: unitName });
    await adminPage.locator('select[name="residency_level"]').selectOption('R1');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage.locator('body')).toContainText('11/05/2026');

    await adminPage.goBack();
    await adminPage.reload();
    await adminPage.locator('select[name="student_id"]').selectOption({ label: studentName });
    await adminPage.getByRole('button', { name: 'Alocar' }).click();
    await expect(adminPage.locator('body')).toContainText(studentName);
    await adminPage.getByRole('button', { name: 'Publicar' }).click();
    await expect(adminPage.locator('body')).toContainText('Escala publicada');
  });

  await test.step('Categoria, curso, matricula, exame e faturas', async () => {
    await adminPage.goto(appPath('/index.php?route=courses/categories'));
    await adminPage.getByPlaceholder('Nome da categoria').fill(courseCategoryName);
    await adminPage.getByRole('button', { name: 'Adicionar' }).click();
    await expect(adminPage.locator('body')).toContainText(courseCategoryName);

    await adminPage.goto(appPath('/index.php?route=courses/create'));
    await adminPage.getByLabel('Nome do curso *').fill(courseName);
    await adminPage.getByLabel('Categoria').selectOption({ label: courseCategoryName });
    await adminPage.getByLabel('Descricao').fill('Curso criado automaticamente na validacao E2E.');
    await adminPage.getByLabel('Situacao').selectOption('published');
    await adminPage.getByLabel('Carga horaria').fill('24');
    await adminPage.getByLabel('Grade (modulos/aulas)').fill('Modulo 1');
    await adminPage.getByLabel('Materiais (PDF, links, downloads)').fill('Material inicial');
    await adminPage.getByLabel('Link da aula ao vivo (manual)').fill('https://example.com/live');
    await adminPage.getByLabel('Senha da sala').fill('123456');
    await adminPage.getByLabel('ID da reuniao').fill('MEETING-E2E');
    await adminPage.getByLabel('Data e horario da aula').fill('2026-05-25T19:00');
    await adminPage.getByRole('button', { name: 'Salvar Curso' }).click();
    await expect(adminPage.locator('body')).toContainText(courseName);

    await adminPage.goto(appPath('/index.php?route=courses/enrollments'));
    await adminPage.locator('select[name="student_id"]').selectOption({ label: studentName });
    await adminPage.locator('select[name="course_id"]').selectOption({ label: courseName });
    await adminPage.locator('select[name="status"]').selectOption('active');
    await adminPage.locator('input[name="started_at"]').fill('2026-05-19');
    await adminPage.getByRole('button', { name: 'Matricular' }).click();
    await expect(adminPage.locator('body')).toContainText(studentName);
    await expect(adminPage.locator('body')).toContainText(courseName);

    await adminPage.goto(appPath('/index.php?route=courses/exams'));
    await adminPage.locator('form#internal-exam-form select[name="course_id"]').selectOption({ label: courseName });
    await adminPage.locator('form#internal-exam-form input[name="title"]').fill(`Prova ${runId}`);
    await adminPage.locator('form#internal-exam-form input[name="passing_score"]').fill('7,0');
    await adminPage.locator('form#internal-exam-form input[name="scheduled_at"]').fill('2026-05-19T09:00');
    await adminPage.locator('form#internal-exam-form input[name="description"]').fill('Prova criada no teste E2E.');
    await adminPage.locator('select[name="delivery_scope_internal"]').selectOption('student');
    await adminPage.locator('select[name="target_student_id"]').selectOption({ label: studentName });
    await adminPage.locator('input[name="question_text[]"]').fill('Qual e a capital da Franca?');
    await adminPage.locator('textarea[name="options_text[]"]').fill('Paris\nRoma\nMadri');
    await adminPage.locator('input[name="correct_answer[]"]').fill('Paris');
    await adminPage.getByRole('button', { name: 'Criar prova interna' }).click();
    await expect(adminPage.locator('body')).toContainText(`Prova ${runId}`);

    await adminPage.goto(appPath('/index.php?route=finance/invoices/create'));
    await adminPage.locator('select[name="student_id"]').selectOption({ label: studentName });
    await adminPage.locator('select[name="payment_method_id"]').selectOption({ label: paymentMethodName });
    await adminPage.locator('input[name="due_date"]').fill('2026-05-28');
    await adminPage.locator('input[name="amount"]').fill('850,00');
    await adminPage.locator('input[name="tax_amount"]').fill('0,00');
    await adminPage.locator('input[name="project_name"]').fill(invoiceProjectOpen);
    await adminPage.locator('input[name="tags"]').fill('mensalidade');
    await adminPage.getByRole('button', { name: 'Salvar Fatura' }).click();
    await expect(adminPage.locator('body')).toContainText(invoiceProjectOpen);

    await adminPage.goto(appPath('/index.php?route=finance/invoices/create'));
    await adminPage.locator('select[name="student_id"]').selectOption({ label: studentName });
    await adminPage.locator('select[name="payment_method_id"]').selectOption({ label: paymentMethodName });
    await adminPage.locator('input[name="due_date"]').fill('2026-05-10');
    await adminPage.locator('input[name="amount"]').fill('920,00');
    await adminPage.locator('input[name="tax_amount"]').fill('0,00');
    await adminPage.locator('input[name="project_name"]').fill(invoiceProjectPaid);
    await adminPage.locator('input[name="tags"]').fill('ajuste');
    await adminPage.getByRole('button', { name: 'Salvar Fatura' }).click();
    await expect(adminPage.locator('body')).toContainText(invoiceProjectPaid);

    adminPage.once('dialog', (dialog) => dialog.accept());
    const paidRow = adminPage.locator('tr', { hasText: invoiceProjectPaid });
    await paidRow.getByRole('button', { name: 'Efetuar baixa' }).click();
    await expect(paidRow).toContainText('Conta baixada');

    await expect(adminPage.locator('body')).toContainText('Forma manual (sem automacao de boleto).');
  });

  await test.step('Graficos, portal do aluno e abertura de chamado', async () => {
    await adminPage.goto(appPath('/index.php?route=dashboard'));
    await expect(adminPage.locator('body')).toContainText('BI Gerencial');
    await expect(adminPage.locator('body')).toContainText('Pipeline de Leads');
    await expect(adminPage.locator('body')).toContainText('Desempenho por Curso');
    await expect(adminPage.locator('body')).toContainText(studentName);

    const studentContext = await browser.newContext();
    const studentPage = await studentContext.newPage();
    await loginStudent(studentPage);

    const studentRoutes: Array<[string, string | RegExp]> = [
      ['/index.php?route=student/dashboard', studentName],
      ['/index.php?route=student/courses', courseName],
      ['/index.php?route=student/calendar', /Calendario|Agenda/],
      ['/index.php?route=student/live', /Aulas ao Vivo|Aula ao Vivo/],
      ['/index.php?route=student/materials', /Materiais|Downloads/],
      ['/index.php?route=student/progress', /Progresso|Percentual/],
      ['/index.php?route=student/schedule', /Minha Escala|Voce esta escalado/],
      ['/index.php?route=student/exams', `Prova ${runId}`],
      ['/index.php?route=student/finances', invoiceProjectOpen],
      ['/index.php?route=student/requests', 'Meus Chamados Tecnicos'],
    ];

    for (const [url, text] of studentRoutes) {
      await expectRoute(studentPage, url, text);
    }

    await studentPage.goto(appPath('/index.php?route=student/exams'));
    await studentPage.getByRole('link', { name: 'Responder agora' }).click();
    await expect(studentPage.locator('body')).toContainText('Responder Prova');
    await studentPage.getByLabel('Paris').check();
    await studentPage.getByRole('button', { name: 'Enviar prova' }).click();
    await expect(studentPage.locator('body')).toContainText(/Historico de Avaliacoes|Aprovado/);

    await studentPage.goto(appPath('/index.php?route=student/requests'));
    await studentPage.getByLabel('Assunto *').fill(ticketSubject);
    await studentPage.getByLabel('Prioridade').selectOption('high');
    await studentPage.getByLabel('Descricao do problema *').fill('Erro validado na automacao E2E para fluxo de suporte.');
    await studentPage.getByRole('button', { name: 'Abrir chamado' }).click();
    await expect(studentPage.locator('body')).toContainText(ticketSubject);
    await expect(studentPage.locator('body')).toContainText(/ANEO\d+/);

    await studentContext.close();
  });

  await test.step('Portal de suporte recebe, comenta e atualiza chamado', async () => {
    const supportContext = await browser.newContext();
    const supportPage = await supportContext.newPage();
    await loginSupport(supportPage);

    await expect(supportPage.locator('body')).toContainText(ticketSubject);
    const ticketCard = supportPage.locator('article', { hasText: ticketSubject });
    await ticketCard.locator('input[name="comment"]').fill('Comentario automatico do suporte QA.');
    await ticketCard.getByRole('button', { name: 'Comentar' }).click();
    await expect(ticketCard).toContainText('Comentario automatico do suporte QA.');
    await ticketCard.locator('select[name="status"]').selectOption('resolved');
    await ticketCard.locator('input[name="status_note"]').fill('Resolvido na rodada E2E.');
    await ticketCard.getByRole('button', { name: 'Atualizar' }).click();
    await expect(ticketCard).toContainText('Resolvido');

    await supportContext.close();
  });

  await test.step('Rematricula automatica: bloqueio com fatura aberta e liberacao apos baixa', async () => {
    await adminPage.goto(appPath(`/index.php?route=students/edit&id=${studentId}`));
    await adminPage.locator('input[name="enrolled_at"]').fill('2025-11-19');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage.locator('body')).toContainText('19/05/2026');

    const blockedContext = await browser.newContext();
    const blockedStudentPage = await blockedContext.newPage();
    await loginStudent(blockedStudentPage);
    await expect(blockedStudentPage).toHaveURL(/student\/reenrollment/);
    await expect(blockedStudentPage.locator('body')).toContainText('Chegou a hora da sua rematricula');
    await expect(blockedStudentPage.locator('body')).toContainText('faturas em aberto');
    await blockedContext.close();

    await adminPage.goto(appPath('/index.php?route=finance/invoices'));
    const openInvoiceRow = adminPage.locator('tr', { hasText: invoiceProjectOpen });
    adminPage.once('dialog', (dialog) => dialog.accept());
    await openInvoiceRow.getByRole('button', { name: 'Efetuar baixa' }).click();
    await expect(openInvoiceRow).toContainText('Conta baixada');

    const confirmContext = await browser.newContext();
    const confirmStudentPage = await confirmContext.newPage();
    await loginStudent(confirmStudentPage);
    await expect(confirmStudentPage).toHaveURL(/student\/reenrollment/);
    await expect(confirmStudentPage.locator('body')).toContainText('Pode confirmar sua rematricula');
    await confirmStudentPage.getByRole('button', { name: 'Confirmar Rematricula' }).click();
    await expect(confirmStudentPage).toHaveURL(/student\/dashboard/);
    await expect(confirmStudentPage.locator('body')).toContainText(/Rematricula confirmada|Portal do Aluno/);
    await confirmContext.close();
  });

  await test.step('Intercambio do aluno e aprovacao no administrativo', async () => {
    const exchangeContext = await browser.newContext();
    const exchangeStudentPage = await exchangeContext.newPage();
    await loginStudent(exchangeStudentPage);
    await exchangeStudentPage.goto(appPath('/index.php?route=student/exchange'));
    await expect(exchangeStudentPage.locator('body')).toContainText('Intercambio Aneo');
    await exchangeStudentPage.locator('select[name="target_unit"]').selectOption({ label: 'ANEO Unidade Apoio' });
    await exchangeStudentPage.locator('input[name="desired_month"]').fill('2026-06');
    await exchangeStudentPage.locator('input[name="months_enrolled"]').fill('7');
    await exchangeStudentPage.getByRole('button', { name: 'Enviar Solicitacao de Intercambio' }).click();
    await expect(exchangeStudentPage.locator('body')).toContainText('Solicitacao de intercambio enviada com sucesso');
    await expect(exchangeStudentPage.locator('body')).toContainText('Aguardando');
    await exchangeContext.close();

    await adminPage.goto(appPath('/index.php?route=exchange'));
    await expect(adminPage.locator('body')).toContainText(studentName);
    await adminPage.getByRole('link', { name: /Abrir|Visualizar/ }).first().click();
    await expect(adminPage.locator('body')).toContainText('Intercambio Solicitado');
    await adminPage.locator('select[name="status"]').selectOption('approved');
    await adminPage.locator('textarea[name="admin_notes"]').fill('Solicitacao aprovada na validacao E2E.');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage.locator('body')).toContainText('Status atualizado com sucesso');

    const approvedContext = await browser.newContext();
    const approvedStudentPage = await approvedContext.newPage();
    await loginStudent(approvedStudentPage);
    await approvedStudentPage.goto(appPath('/index.php?route=student/exchange'));
    await expect(approvedStudentPage.locator('body')).toContainText('Aprovado');
    await expect(approvedStudentPage.locator('body')).toContainText('Solicitacao aprovada na validacao E2E');
    await approvedContext.close();
  });

  await test.step('Gerenciamento de API e validacao dos endpoints', async () => {
    await adminPage.goto(appPath('/index.php?route=api-management/create'));
    await adminPage.locator('select[name="user_id"]').selectOption({ label: 'Administrador ANEO (admin)' });
    await adminPage.locator('input[name="name"]').fill(apiTokenName);
    const permissions = [
      'permissions[students][]',
      'permissions[leads][]',
      'permissions[invoices][]',
      'permissions[courses][]',
      'permissions[users][]',
      'permissions[tickets][]',
    ];
    for (const name of permissions) {
      const checks = adminPage.locator(`input[name="${name}"]`);
      const count = await checks.count();
      for (let i = 0; i < count; i += 1) {
        await checks.nth(i).check();
      }
    }
    await adminPage.getByRole('button', { name: 'Salvar Token' }).click();
    const tokenBlock = adminPage.locator('pre code');
    await expect(tokenBlock).toBeVisible();
    const tokenText = (await tokenBlock.textContent()) || '';
    const tokenMatch = tokenText.match(/[a-f0-9]{64}/i);
    expect(tokenMatch).not.toBeNull();
    apiToken = tokenMatch![0];

    const apiContext = await request.newContext({
      extraHTTPHeaders: {
        Authorization: `Bearer ${apiToken}`,
        'Content-Type': 'application/json',
      },
    });

    const studentsResponse = await apiContext.get('http://localhost/aneo-e2e/api.php?r=students');
    expect(studentsResponse.ok()).toBeTruthy();
    const studentsJson = await studentsResponse.json();
    expect(studentsJson.ok).toBeTruthy();
    expect(JSON.stringify(studentsJson.data)).toContain(studentName);

    const coursesResponse = await apiContext.get('http://localhost/aneo-e2e/api.php?r=courses');
    expect(coursesResponse.ok()).toBeTruthy();
    expect(JSON.stringify(await coursesResponse.json())).toContain(courseName);

    const leadsResponse = await apiContext.post('http://localhost/aneo-e2e/api.php?r=leads', {
      data: {
        full_name: `Lead API ${runId}`,
        email: `lead.api.${runId.toLowerCase()}@mail.com`,
        phone: '11977776666',
      },
    });
    expect(leadsResponse.ok()).toBeTruthy();
    expect(JSON.stringify(await leadsResponse.json())).toContain(`Lead API ${runId}`);

    const ticketsResponse = await apiContext.post('http://localhost/aneo-e2e/api.php?r=tickets', {
      data: {
        subject: `Ticket API ${runId}`,
        description: 'Ticket criado via API durante a validacao E2E.',
        requester_name: studentName,
        requester_email: `aluno.${runId.toLowerCase()}@mail.com`,
      },
    });
    expect(ticketsResponse.ok()).toBeTruthy();
    expect(JSON.stringify(await ticketsResponse.json())).toContain(`Ticket API ${runId}`);

    const usersResponse = await apiContext.get('http://localhost/aneo-e2e/api.php?r=users');
    expect(usersResponse.ok()).toBeTruthy();
    expect(JSON.stringify(await usersResponse.json())).toContain('Administrador ANEO');

    await apiContext.dispose();
  });

  await adminContext.close();
});
