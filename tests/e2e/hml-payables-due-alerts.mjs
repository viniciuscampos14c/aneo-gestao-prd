import fs from 'node:fs';
import path from 'node:path';
import { chromium, expect } from '@playwright/test';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';
const adminUser = 'qa_admin_hml';
const adminPassword = 'Qa123456!';

async function loginAdmin(page) {
  await page.goto(`${baseUrl}/index.php?route=login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(adminUser);
  await page.locator('input[name="password"]').fill(adminPassword);
  await page.getByRole('button', { name: /entrar/i }).click();
  await expect(page).toHaveURL(/select-company|dashboard/);

  if (page.url().includes('select-company')) {
    await page.getByRole('button', { name: /empresa selecionada|entrar/i }).first().click();
  }

  await expect(page).toHaveURL(/dashboard/);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

const outputJsonPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-due-alerts.json');
const outputPngPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-due-alerts.png');

try {
  await loginAdmin(page);

  const trigger = page.locator('[data-mobile-neg-trigger]').first();
  await expect(trigger).toBeVisible();

  const modal = page.locator('#mobile-negotiation-modal');
  if (!(await modal.isVisible().catch(() => false))) {
    await trigger.click();
  }

  await expect(modal).toBeVisible();
  await expect(modal).toContainText(/Contas a pagar/i);
  await expect(modal).toContainText(/Hostinger Brasil/i);
  await expect(modal).toContainText(/Zoom Video Communications/i);
  await expect(modal).toContainText(/Vence em/i);
  await expect(modal).toContainText(/Abrir contas a pagar/i);

  await modal.screenshot({ path: outputPngPath });

  await modal.getByRole('link', { name: /Abrir contas a pagar/i }).click();
  await expect(page).toHaveURL(/route=finance%2Fpayables|route=finance\/payables|route=finance%2fpayables/);
  await expect(page.locator('body')).toContainText(/Contas a Pagar/i);
  await expect(page.locator('body')).toContainText(/Hostinger Brasil/i);

  fs.writeFileSync(outputJsonPath, JSON.stringify({
    visible: true,
    section: 'Contas a pagar',
    expectedSuppliers: ['Hostinger Brasil', 'Zoom Video Communications'],
    linkAvailable: true,
  }, null, 2));
} finally {
  await context.close();
  await browser.close();
}
