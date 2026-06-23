import { expect, test, type Page } from '@playwright/test';

const baseUrl = 'https://aneo.aneobrasil.com.br';
const password = process.env.ANEO_PRD_QA_PASSWORD || '';

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

test('valida produção autenticada sem criar dados', async ({ browser }) => {
  expect(password).not.toBe('');

  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await loginUser(adminPage, 'qa_security_admin');
  await expect(adminPage.locator('body')).toContainText(/Visão Geral|Gestão Integrada/);
  await adminPage.goto(`${baseUrl}/index.php?route=students`, { waitUntil: 'domcontentloaded' });
  await expect(adminPage.locator('body')).toContainText(/Alunos|Total de alunos/);
  await adminContext.close();

  const professorContext = await browser.newContext();
  const professorPage = await professorContext.newPage();
  await loginUser(professorPage, 'qa_security_professor');
  await expect(professorPage.locator('body')).toContainText(/Portal do Professor|Visão pedagógica/);
  await professorPage.goto(`${baseUrl}/index.php?route=courses`, { waitUntil: 'domcontentloaded' });
  await expect(professorPage.locator('body')).toContainText(/Cursos EAD|Catálogo de cursos/);
  await professorContext.close();

  const studentContext = await browser.newContext();
  const studentPage = await studentContext.newPage();
  await studentPage.goto(`${baseUrl}/index.php?route=student/login`, { waitUntil: 'domcontentloaded' });
  await studentPage.locator('input[name="login"]').fill('qa_security_student_001');
  await studentPage.locator('input[name="password"]').fill(password);
  await studentPage.getByRole('button', { name: /entrar/i }).click();
  await expect(studentPage).toHaveURL(/student(?:\/|%2F)dashboard/);
  await expect(studentPage.locator('body')).toContainText(/Portal do Aluno|Início/);

  await studentPage.goto(`${baseUrl}/index.php?route=student/courses`, { waitUntil: 'domcontentloaded' });
  await expect(studentPage.getByRole('link', { name: /continuar curso/i }).first()).toBeVisible();
  await studentPage.getByRole('link', { name: /continuar curso/i }).first().click();
  await expect(studentPage.locator('body')).toContainText(/Enviar dúvida ao professor|Progresso/);

  await studentPage.goto(`${baseUrl}/index.php?route=student/exams`, { waitUntil: 'domcontentloaded' });
  await expect(studentPage.locator('body')).toContainText(/Avaliações|Histórico Acadêmico|Provas disponíveis/i);
  await studentContext.close();
});
