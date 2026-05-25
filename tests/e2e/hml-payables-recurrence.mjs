import fs from 'node:fs';
import path from 'node:path';
import { chromium, expect } from '@playwright/test';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';
const adminUser = 'qa_admin_hml';
const adminPassword = 'Qa123456!';

async function clearAdminOverlay(page) {
  const closeButton = page.locator('#mobile-negotiation-modal [data-mobile-neg-close]').first();
  if (await closeButton.isVisible().catch(() => false)) {
    await closeButton.click().catch(() => {});
  }

  await page.keyboard.press('Escape').catch(() => {});
  await page.waitForTimeout(150).catch(() => {});
}

async function loginAdmin(page) {
  await page.goto(`${baseUrl}/index.php?route=login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(adminUser);
  await page.locator('input[name="password"]').fill(adminPassword);
  await page.getByRole('button', { name: /entrar/i }).click();
  await expect(page).toHaveURL(/select-company|dashboard/);

  if (page.url().includes('select-company')) {
    await clearAdminOverlay(page);
    await page.getByRole('button', { name: /empresa selecionada|entrar/i }).first().click();
  }

  await expect(page).toHaveURL(/dashboard/);
  await clearAdminOverlay(page);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

const runId = `QA-${Date.now()}`;
const payableNumber = `CPAG-REC-E2E-${runId}`;
const outputJsonPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-recurrence.json');
const outputPngPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-recurrence.png');

try {
  await loginAdmin(page);
  await page.goto(`${baseUrl}/index.php?route=finance/payables`, { waitUntil: 'domcontentloaded' });
  await clearAdminOverlay(page);
  await expect(page.locator('body')).toContainText(/Despesas fixas/);

  const createForm = page.locator('form').filter({ has: page.getByRole('button', { name: /Salvar conta a pagar/i }) }).first();
  await createForm.locator('input[name="payable_number"]').fill(payableNumber);
  await createForm.locator('select[name="supplier_id"]').selectOption({ label: 'Hostinger Brasil' });
  await createForm.locator('input[name="description"]').fill(`Despesa fixa QA ${runId}`);
  await createForm.locator('input[name="category"]').fill('QA Recorrencia');
  await createForm.locator('input[name="competence_date"]').fill('2026-05-01');
  await createForm.locator('input[name="due_date"]').fill('2026-05-28');
  await createForm.locator('input[name="amount"]').fill('123,45');
  await createForm.locator('select[name="status"]').selectOption('open');
  await createForm.locator('input[name="notes"]').fill('Validacao HML de recorrencia.');
  await createForm.locator('input[name="is_recurring"]').check();
  await createForm.locator('select[name="recurrence_interval"]').selectOption('monthly');
  await createForm.locator('input[name="recurrence_until"]').fill('2026-07-31');
  await createForm.getByRole('button', { name: /Salvar conta a pagar/i }).click();

  await expect(page.locator('body')).toContainText(/Conta a pagar cadastrada com sucesso/i);
  await expect(page.locator('tr', { hasText: payableNumber })).toContainText(/Despesa fixa/);

  const recurringForm = page.locator('form').filter({ has: page.getByRole('button', { name: /Gerar recorrentes/i }) }).first();
  await recurringForm.locator('input[name="reference_month"]').fill('2026-07');
  await recurringForm.getByRole('button', { name: /Gerar recorrentes/i }).click();
  await expect(page.locator('body')).toContainText(/2 conta\(s\) recorrente\(s\) gerada\(s\)/i);

  await page.goto(`${baseUrl}/index.php?route=finance/payables&period=custom&start_date=2026-05-01&end_date=2026-07-31&q=${encodeURIComponent(payableNumber)}&per_page=50`, { waitUntil: 'domcontentloaded' });
  await clearAdminOverlay(page);
  await expect(page.locator('tr', { hasText: payableNumber }).first()).toContainText(/Despesa fixa/);
  await expect(page.locator('body')).toContainText(`${payableNumber}-202606`);
  await expect(page.locator('body')).toContainText(`${payableNumber}-202607`);
  await expect(page.locator('body')).toContainText(/Gerada/);

  await page.screenshot({ path: outputPngPath, fullPage: true });

  await page.goto(`${baseUrl}/index.php?route=finance/payables`, { waitUntil: 'domcontentloaded' });
  await clearAdminOverlay(page);
  await recurringForm.locator('input[name="reference_month"]').fill('2026-07');
  await recurringForm.getByRole('button', { name: /Gerar recorrentes/i }).click();
  await expect(page.locator('body')).toContainText(/0 conta\(s\) recorrente\(s\) gerada\(s\).*2 ja existia\(m\)/i);

  fs.writeFileSync(outputJsonPath, JSON.stringify({
    payableNumber,
    generatedNumbers: [`${payableNumber}-202606`, `${payableNumber}-202607`],
    firstGenerationCreated: 2,
    secondGenerationCreated: 0,
    duplicateProtection: true,
  }, null, 2));
} finally {
  await context.close();
  await browser.close();
}
