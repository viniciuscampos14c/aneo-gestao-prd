import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';
const adminUser = 'qa_admin_hml';
const adminPassword = 'Qa123456!';

async function clearAdminOverlay(page: Page) {
  const closeButton = page.locator('#mobile-negotiation-modal [data-mobile-neg-close]').first();
  if (await closeButton.isVisible().catch(() => false)) {
    await closeButton.click().catch(() => {});
  }

  await page.keyboard.press('Escape').catch(() => {});
  await page.waitForTimeout(150).catch(() => {});
}

async function loginAdmin(page: Page) {
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

test('edita e cancela conta a pagar no HML', async ({ page }) => {
  const runId = `QA-${Date.now()}`;
  const payableNumber = `CPAG-E2E-${runId}`;
  const updatedDescription = `Despesa QA editada ${runId}`;
  const updatedCategory = 'QA Operacional';
  const updatedAmount = '455,55';
  const updatedDueDate = '2026-05-31';
  const updatedNotes = `Fluxo editado e cancelado ${runId}`;
  const outputJsonPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-edit-cancel.json');
  const outputPngPath = path.join(process.cwd(), 'test-results', 'hml-finance-payables-edit-cancel.png');

  await loginAdmin(page);
  await page.goto(`${baseUrl}/index.php?route=finance/payables`, { waitUntil: 'domcontentloaded' });
  await clearAdminOverlay(page);
  await expect(page.locator('body')).toContainText(/Contas a Pagar/);

  const createForm = page.locator('form').filter({ has: page.getByRole('button', { name: /Salvar conta a pagar/i }) }).first();
  await createForm.locator('input[name="payable_number"]').fill(payableNumber);
  await createForm.locator('select[name="supplier_id"]').selectOption({ label: 'Hostinger Brasil' });
  await createForm.locator('input[name="description"]').fill(`Despesa QA inicial ${runId}`);
  await createForm.locator('input[name="category"]').fill('QA');
  await createForm.locator('input[name="competence_date"]').fill('2026-05-24');
  await createForm.locator('input[name="due_date"]').fill('2026-05-30');
  await createForm.locator('input[name="amount"]').fill('410,40');
  await createForm.locator('select[name="status"]').selectOption('open');
  await createForm.locator('input[name="notes"]').fill(`Criado para validacao ${runId}`);
  await createForm.getByRole('button', { name: /Salvar conta a pagar/i }).click();

  await expect(page.locator('body')).toContainText(/Conta a pagar cadastrada com sucesso/i);
  let row = page.locator('tr', { hasText: payableNumber }).first();
  await expect(row).toContainText('Hostinger Brasil');

  await row.locator('summary', { hasText: /Editar conta/i }).click();
  await expect(row.getByRole('button', { name: /Salvar alteracoes/i })).toBeVisible();
  const editForm = row.locator('details form').first();
  await editForm.locator('input[name="description"]').fill(updatedDescription);
  await editForm.locator('input[name="category"]').fill(updatedCategory);
  await editForm.locator('input[name="due_date"]').fill(updatedDueDate);
  await editForm.locator('input[name="amount"]').fill(updatedAmount);
  await editForm.locator('input[name="notes"]').fill(updatedNotes);
  await editForm.getByRole('button', { name: /Salvar alteracoes/i }).click();

  await expect(page.locator('body')).toContainText(/Conta a pagar atualizada com sucesso/i);
  row = page.locator('tr', { hasText: payableNumber }).first();
  await expect(row).toContainText(updatedDescription);
  await expect(row).toContainText('R$ 455,55');

  await row.locator('summary', { hasText: /Editar conta/i }).click();
  await expect(row.getByRole('button', { name: /Cancelar conta/i })).toBeVisible();
  page.once('dialog', async (dialog) => {
    await dialog.accept();
  });
  await row.getByRole('button', { name: /Cancelar conta/i }).click();

  await expect(page.locator('body')).toContainText(/Conta a pagar cancelada com sucesso/i);
  row = page.locator('tr', { hasText: payableNumber }).first();
  await expect(row).toContainText('Cancelado');
  await expect(row).toContainText(updatedDescription);
  await expect(row).toContainText(/Sem baixa disponivel/);

  const partialRow = page.locator('tr', { hasText: 'CPAG-20260524-516604-02' }).first();
  await expect(partialRow).toContainText('Parcial');
  await partialRow.locator('summary', { hasText: /Editar conta/i }).click();
  await expect(partialRow.locator('text=Cancelamento disponivel apenas para contas sem pagamento registrado.')).toBeVisible();

  await page.screenshot({ path: outputPngPath, fullPage: true });
  fs.writeFileSync(outputJsonPath, JSON.stringify({
    payableNumber,
    updatedDescription,
    updatedCategory,
    updatedAmount: 455.55,
    updatedDueDate,
    finalStatus: 'cancelled',
    partialCancelBlocked: true,
  }, null, 2));
});
