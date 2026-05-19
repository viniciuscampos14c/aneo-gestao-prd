import { expect, test, type Page } from '@playwright/test';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';

const adminUser = 'qa_admin_hml';
const adminPassword = 'Qa123456!';
const supportUser = 'qa_suporte_hml';
const supportPassword = 'Qa123456!';
const studentUser = 'qa.aluno.portal';
const studentPassword = 'Aluno123!';
const reenrollOkUser = 'qa.aluno.reok';
const reenrollBlockedUser = 'qa.aluno.rebloq';

const runId = `HML-${Date.now()}`;
const leadName = `QA Lead ${runId}`;
const ticketSubject = `QA Ticket ${runId}`;
const apiTokenName = `QA Token ${runId}`;

async function clearAdminOverlay(page: Page) {
  await page.evaluate(() => {
    document.querySelectorAll('#mobile-negotiation-modal, .admin-alert-overlay').forEach((node) => node.remove());
    document.body.style.overflow = 'auto';
  }).catch(() => {});
}

async function loginAdmin(page: Page) {
  await page.goto(`${baseUrl}/index.php?route=login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(adminUser);
  await page.locator('input[name="password"]').fill(adminPassword);
  await page.getByRole('button', { name: /entrar/i }).click();
  await expect(page).toHaveURL(/select-company|dashboard/);

  if (page.url().includes('select-company')) {
    await clearAdminOverlay(page);
    const button = page.getByRole('button', { name: /empresa selecionada|entrar/i }).first();
    await button.click();
  }

  await expect(page).toHaveURL(/dashboard/);
  await clearAdminOverlay(page);
}

async function loginSupport(page: Page) {
  await page.goto(`${baseUrl}/support.php?route=support/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="username"]').fill(supportUser);
  await page.locator('input[name="password"]').fill(supportPassword);
  await page.locator('button').first().click();
  await expect(page).toHaveURL(/support\.php\?route=support$/);
}

async function loginStudent(page: Page, username: string, password: string) {
  await page.goto(`${baseUrl}/index.php?route=student/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(username);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /entrar/i }).click();
}

test('valida HML real da ANEO', async ({ browser, request }) => {
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();

  await test.step('admin login e rotas principais', async () => {
    await loginAdmin(adminPage);

    const routes: Array<[string, string | RegExp]> = [
      ['/index.php?route=dashboard', /BI Gerencial|Visao Geral|Dashboard/],
      ['/index.php?route=students', /Alunos|Total de alunos/],
      ['/index.php?route=leads', /Leads|Funil comercial/],
      ['/index.php?route=finance/payment-methods', /Formas de Pagamento/],
      ['/index.php?route=finance/reports', /Relatorios Financeiros|Entradas por forma/],
      ['/index.php?route=courses', /Cursos EAD|Catalogo de cursos/],
      ['/index.php?route=api-management', /Gerenciamento de API|Tokens de API/],
      ['/index.php?route=exchange', /Intercâmbio Aluno|Solicitações de intercâmbio/],
    ];

    for (const [path, text] of routes) {
      await adminPage.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded' });
      await clearAdminOverlay(adminPage);
      await expect(adminPage.locator('body')).toContainText(text);
    }
  });

  await test.step('cadastro de lead pela interface', async () => {
    await adminPage.goto(`${baseUrl}/index.php?route=leads/create`, { waitUntil: 'domcontentloaded' });
    await clearAdminOverlay(adminPage);
    await adminPage.locator('input[name="full_name"]').fill(leadName);
    await adminPage.locator('input[name="email"]').fill(`qa.${Date.now()}@aneo.test`);
    await adminPage.locator('input[name="phone"]').fill('11999999999');
    await adminPage.locator('input[name="lead_value"]').fill('1500,00');
    await adminPage.locator('input[name="source"]').fill('QA HML');
    await adminPage.locator('input[name="unit_name"]').fill('ANEO HML');
    await adminPage.getByRole('button', { name: 'Salvar' }).click();
    await expect(adminPage).toHaveURL(/route=leads$/);
    await expect(adminPage.locator('body')).toContainText(leadName);
  });

  await test.step('alunos QA e fluxo de rematricula', async () => {
    await adminPage.goto(`${baseUrl}/index.php?route=students&q=QA`, { waitUntil: 'domcontentloaded' });
    await clearAdminOverlay(adminPage);
    await expect(adminPage.locator('body')).toContainText('QA Aluno Portal');
    await expect(adminPage.locator('body')).toContainText('QA Aluno Reenroll OK');
    await expect(adminPage.locator('body')).toContainText('QA Aluno Reenroll Bloq');

    const okContext = await browser.newContext();
    const okPage = await okContext.newPage();
    await loginStudent(okPage, reenrollOkUser, studentPassword);
    await expect(okPage).toHaveURL(/student(?:\/|%2F)(dashboard|reenrollment)/);
    await expect(okPage.locator('body')).toContainText(/Rematricula confirmada|Portal do Aluno|Pode confirmar sua rematricula/);
    await okContext.close();

    const blockedContext = await browser.newContext();
    const blockedPage = await blockedContext.newPage();
    await loginStudent(blockedPage, reenrollBlockedUser, studentPassword);
    await expect(blockedPage).toHaveURL(/student(?:\/|%2F)reenrollment/);
    await expect(blockedPage.locator('body')).toContainText(/faturas em aberto|pendencias financeiras/);
    await blockedContext.close();
  });

  await test.step('portal do aluno, escala, intercambio e chamado', async () => {
    const studentContext = await browser.newContext();
    const studentPage = await studentContext.newPage();
    await loginStudent(studentPage, studentUser, studentPassword);
    await expect(studentPage).toHaveURL(/student(?:\/|%2F)dashboard/);

    await studentPage.goto(`${baseUrl}/index.php?route=student/schedule`, { waitUntil: 'domcontentloaded' });
    await expect(studentPage.locator('body')).toContainText(/Minha Escala|Voce esta escalado|Escala/);

    await studentPage.goto(`${baseUrl}/index.php?route=student/exchange`, { waitUntil: 'domcontentloaded' });
    await expect(studentPage.locator('body')).toContainText(/Intercambio Aneo|Minhas Solicitacoes/);
    await expect(studentPage.locator('body')).toContainText(/Aprovado|Visualizado|Solicitação|solicitação em andamento/);

    await studentPage.goto(`${baseUrl}/index.php?route=student/requests`, { waitUntil: 'domcontentloaded' });
    await studentPage.locator('input[name="subject"]').fill(ticketSubject);
    await studentPage.locator('select[name="priority"]').selectOption('high');
    await studentPage.locator('textarea[name="description"]').fill('Chamado criado na rodada final de validacao HML.');
    await studentPage.getByRole('button', { name: /Abrir chamado/i }).click();
    await expect(studentPage.locator('body')).toContainText(ticketSubject);
    await expect(studentPage.locator('body')).toContainText(/ANEO\d+/);

    await studentContext.close();
  });

  await test.step('portal de suporte recebe chamado', async () => {
    const supportContext = await browser.newContext();
    const supportPage = await supportContext.newPage();
    await loginSupport(supportPage);
    await expect(supportPage.locator('body')).toContainText(ticketSubject);
    await supportContext.close();
  });

  await test.step('api token, GET students ok e POST leads ok', async () => {
    await adminPage.goto(`${baseUrl}/index.php?route=api-management/create`, { waitUntil: 'domcontentloaded' });
    await clearAdminOverlay(adminPage);
    const userId = await adminPage.locator('select[name="user_id"] option:not([value=""])').first().getAttribute('value');
    expect(userId).toBeTruthy();
    await adminPage.locator('select[name="user_id"]').selectOption(userId!);
    await adminPage.locator('input[name="name"]').fill(apiTokenName);

    const checkboxes = adminPage.locator('input[type="checkbox"]');
    const count = await checkboxes.count();
    for (let i = 0; i < count; i += 1) {
      await checkboxes.nth(i).check();
    }

    await adminPage.getByRole('button', { name: /Salvar Token/i }).click();
    await expect(adminPage.locator('#raw-token')).toBeVisible();
    const rawToken = ((await adminPage.locator('#raw-token').textContent()) || '').trim();
    expect(rawToken).toMatch(/[a-f0-9]{64}/i);

    const headers = {
      Authorization: `Bearer ${rawToken}`,
      'Content-Type': 'application/json',
    };

    const studentsResponse = await request.get(`${baseUrl}/api.php?r=students`, {
      headers,
    });
    expect(studentsResponse.ok()).toBeTruthy();
    const studentsJson = await studentsResponse.json();
    expect(studentsJson.ok).toBeTruthy();

    const leadApiName = `Lead API ${runId}`;
    const leadsResponse = await request.post(`${baseUrl}/api.php?r=leads`, {
      headers,
      data: {
        full_name: leadApiName,
        email: `lead.api.${Date.now()}@aneo.test`,
        phone: '11977776666',
      },
    });
    expect(leadsResponse.status()).toBe(201);
    const leadsJson = await leadsResponse.json();
    expect(leadsJson.ok).toBeTruthy();
    expect(JSON.stringify(leadsJson.data)).toContain(leadApiName);
  });

  await adminContext.close();
});
