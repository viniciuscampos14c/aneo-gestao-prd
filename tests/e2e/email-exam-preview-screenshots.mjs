import path from 'node:path';
import { chromium, expect } from '@playwright/test';

const root = process.cwd();
const previews = [
  {
    html: path.join(root, 'test-results', 'email-exam-published-preview.html'),
    png: path.join(root, 'test-results', 'email-exam-published-preview.png'),
    text: /Nova avaliacao disponivel/i,
  },
  {
    html: path.join(root, 'test-results', 'email-exam-result-preview.html'),
    png: path.join(root, 'test-results', 'email-exam-result-preview.png'),
    text: /Resultado aprovado/i,
  },
];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 760, height: 900 } });

try {
  for (const preview of previews) {
    const page = await context.newPage();
    await page.goto(`file:///${preview.html.replaceAll('\\', '/')}`);
    await expect(page.locator('body')).toContainText(preview.text);
    await expect(page.locator('body')).toContainText(/Abrir portal do aluno/i);
    await page.screenshot({ path: preview.png, fullPage: true });
    await page.close();
  }
} finally {
  await context.close();
  await browser.close();
}
