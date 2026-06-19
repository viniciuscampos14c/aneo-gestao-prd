import { expect, test, type Page } from '@playwright/test';

const baseUrl = 'https://aneo.aneobrasil.com.br';
const password = process.env.ANEO_PRD_QA_PASSWORD ?? '';
const studentName = process.env.ANEO_PRD_QA_STUDENT_NAME ?? '';
const marker = `QA-PRD-RELEASE-${Date.now()}`;

async function loginUser(page: Page, username: string) {
  await page.goto(`${baseUrl}/index.php?route=login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(username);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /entrar/i }).click();
  if (page.url().includes('select-company')) {
    await page.getByRole('button', { name: /empresa selecionada|entrar/i }).first().click();
  }
  await expect(page).toHaveURL(/dashboard/);
}

test('valida release completa em produção sem efeitos financeiros', async ({ browser }) => {
  expect(password).not.toBe('');
  expect(studentName).not.toBe('');

  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await loginUser(adminPage, 'qa_release_admin');
  await expect(adminPage.locator('body')).toContainText('Visão Geral');
  await expect(adminPage.locator('body')).toContainText('Gestão Integrada');
  await adminPage.goto(`${baseUrl}/index.php?route=api-management/manual`, { waitUntil: 'domcontentloaded' });
  await expect(adminPage.locator('body')).toContainText('RD Station / Cadastro de Alunos');
  await adminContext.close();

  const studentContext = await browser.newContext();
  const studentPage = await studentContext.newPage();
  await studentPage.goto(`${baseUrl}/index.php?route=student/login`, { waitUntil: 'domcontentloaded' });
  await studentPage.locator('input[name="login"]').fill('qa_release_student');
  await studentPage.locator('input[name="password"]').fill(password);
  await studentPage.getByRole('button', { name: /entrar/i }).click();
  await expect(studentPage).toHaveURL(/student(?:\/|%2F)dashboard/);
  await expect(studentPage.locator('body')).toContainText(`Olá, ${studentName}`);
  await expect(studentPage.locator('body')).toContainText('Início');

  await studentPage.goto(`${baseUrl}/index.php?route=student/courses`, { waitUntil: 'domcontentloaded' });
  await studentPage.getByRole('link', { name: /continuar curso/i }).first().click();
  await expect(studentPage.getByRole('heading', { name: 'Enviar dúvida ao professor' })).toBeVisible();
  await studentPage.locator('input[name="subject"]').fill(marker);
  await studentPage.locator('textarea[name="message"]').fill('Pergunta controlada da validação de produção.');
  await studentPage.getByRole('button', { name: 'Enviar ao professor' }).click();
  await expect(studentPage.locator('body')).toContainText('Dúvida enviada ao professor');

  const professorContext = await browser.newContext();
  const professorPage = await professorContext.newPage();
  await loginUser(professorPage, 'qa_release_professor');
  await expect(professorPage.locator('body')).toContainText('Olá, Prof. QA');
  await expect(professorPage.locator('body')).toContainText('Visão pedagógica');
  await professorPage.goto(`${baseUrl}/index.php?route=courses/questions`, { waitUntil: 'domcontentloaded' });
  await expect(professorPage.locator('body')).toContainText(marker);
  const card = professorPage.locator('article').filter({ hasText: marker }).first();
  await card.locator('textarea[name="message"]').fill('Resposta controlada da validação de produção.');
  await card.getByRole('button', { name: /responder e notificar aluno/i }).click();
  await expect(professorPage.locator('body')).toContainText('Resposta enviada');

  await professorPage.goto(`${baseUrl}/index.php?route=courses/live-sessions/create`, { waitUntil: 'domcontentloaded' });
  await expect(professorPage.locator('body')).toContainText('Aula global para todas as unidades deste curso');
  await professorContext.close();

  await studentPage.goto(`${baseUrl}/index.php?route=student/questions`, { waitUntil: 'domcontentloaded' });
  await expect(studentPage.locator('body')).toContainText(marker);
  await expect(studentPage.locator('body')).toContainText('Resposta controlada da validação de produção.');
  await studentContext.close();
});
