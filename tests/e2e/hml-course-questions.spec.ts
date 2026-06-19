import { expect, test } from '@playwright/test';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';
const marker = `QA-DUVIDA-UI-${Date.now()}`;

test('aluno envia e acompanha dúvida no HML', async ({ page }) => {
  await page.goto(`${baseUrl}/index.php?route=student/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill('qa.aluno.portal');
  await page.locator('input[name="password"]').fill('Aluno123!');
  await page.getByRole('button', { name: /entrar/i }).click();
  await expect(page).toHaveURL(/student(?:\/|%2F)dashboard/);

  await page.goto(`${baseUrl}/index.php?route=student/questions`, { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'Minhas Dúvidas' })).toBeVisible();

  await page.goto(`${baseUrl}/index.php?route=student/courses`, { waitUntil: 'domcontentloaded' });
  await page.getByRole('link', { name: 'Continuar curso' }).first().click();
  await expect(page.getByRole('heading', { name: 'Enviar dúvida ao professor' })).toBeVisible();

  await page.locator('input[name="subject"]').fill(marker);
  await page.locator('textarea[name="message"]').fill('Pergunta criada pela validação de interface no HML.');
  await page.getByRole('button', { name: 'Enviar ao professor' }).click();
  await expect(page.locator('body')).toContainText('Dúvida enviada ao professor');

  await page.goto(`${baseUrl}/index.php?route=student/questions`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText(marker);
  await expect(page.locator('body')).toContainText('Aguardando professor');
});
