import fs from 'node:fs';
import path from 'node:path';
import { chromium, expect } from '@playwright/test';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';
const adminUser = 'qa_admin_hml';
const adminPassword = 'Qa123456!';
const payableNumber = 'CPAG-20260524-516604-01';

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
const context = await browser.newContext({ acceptDownloads: true });
const page = await context.newPage();

const outputJsonPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-attachments.json');
const outputPngPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-attachments.png');
const downloadedPath = path.join(process.cwd(), 'test-results', 'hml-payable-attachment-download.txt');
const attachmentName = `comprovante-hml-qa-${Date.now()}.txt`;
const attachmentContent = `Comprovante QA anexos contas a pagar ${new Date().toISOString()}`;

try {
  await loginAdmin(page);
  await page.goto(`${baseUrl}/index.php?route=finance/payables`, { waitUntil: 'domcontentloaded' });
  await clearAdminOverlay(page);
  await expect(page.locator('body')).toContainText(/Contas a Pagar/);

  let row = page.locator('tr', { hasText: payableNumber }).first();
  await expect(row).toContainText('Hostinger Brasil');

  await row.locator('summary', { hasText: /Anexos/i }).click();
  const attachmentDetails = row.locator('details').nth(1);
  await expect(attachmentDetails.getByRole('button', { name: /Anexar arquivo/i })).toBeVisible();
  const attachmentForm = attachmentDetails.locator('form').first();
  await attachmentForm.locator('select[name="attachment_type"]').selectOption('comprovante');
  await attachmentForm.locator('input[name="attachment_notes"]').fill('Validacao HML de anexos do contas a pagar.');
  await attachmentForm.locator('input[name="attachment_file"]').setInputFiles({
    name: attachmentName,
    mimeType: 'text/plain',
    buffer: Buffer.from(attachmentContent, 'utf8'),
  });
  await attachmentForm.getByRole('button', { name: /Anexar arquivo/i }).click();

  await expect(page.locator('body')).toContainText(/Anexo enviado com sucesso/i);
  row = page.locator('tr', { hasText: payableNumber }).first();
  await row.locator('summary', { hasText: /Anexos/i }).click();
  const attachmentDetailsAfterUpload = row.locator('details').nth(1);
  await expect(row).toContainText(`Comprovante: ${attachmentName}`);

  const downloadPromise = page.waitForEvent('download');
  await attachmentDetailsAfterUpload.getByRole('link', { name: /Baixar/i }).first().click();
  const download = await downloadPromise;
  await download.saveAs(downloadedPath);
  const downloadedContent = fs.readFileSync(downloadedPath, 'utf8');
  if (downloadedContent !== attachmentContent) {
    throw new Error('Downloaded attachment content does not match uploaded content.');
  }

  await page.screenshot({ path: outputPngPath, fullPage: true });

  page.once('dialog', async (dialog) => {
    await dialog.accept();
  });
  await attachmentDetailsAfterUpload.getByRole('button', { name: /Remover/i }).first().click();
  await expect(page.locator('body')).toContainText(/Anexo removido com sucesso/i);
  row = page.locator('tr', { hasText: payableNumber }).first();
  await row.locator('summary', { hasText: /Anexos/i }).click();
  await expect(row).not.toContainText(attachmentName);

  fs.writeFileSync(outputJsonPath, JSON.stringify({
    payableNumber,
    supplier: 'Hostinger Brasil',
    attachmentType: 'comprovante',
    uploaded: true,
    downloaded: true,
    removed: true,
    fileName: attachmentName,
  }, null, 2));
} finally {
  await context.close();
  await browser.close();
}
