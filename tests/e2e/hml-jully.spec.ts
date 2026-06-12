import { expect, test, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';

const baseUrl = 'https://erp-hml.aneobrasil.com.br';
const adminUser = 'qa_admin_hml';
const adminPassword = process.env.ANEO_HML_ADMIN_PASSWORD ?? '';

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
    const button = page.getByRole('button', { name: /empresa selecionada|entrar/i }).first();
    await button.click();
  }

  await expect(page).toHaveURL(/dashboard/);
  await clearAdminOverlay(page);
}

test('valida Jully no HML com rodada real de perguntas', async ({ page }) => {
  const runStamp = new Date().toISOString().replace(/[:.]/g, '-');
  const transcript: string[] = [
    '# Rodada E2E Jully HML',
    '',
    `Data: ${new Date().toISOString()}`,
    `URL: ${baseUrl}/index.php?route=ai-chat`,
    '',
  ];

  const prompts = [
    {
      question: `Rodada QA Jully ${runStamp}: qual e o saldo vencido hoje e quantos alunos estao em atraso?`,
      expected: /R\$|saldo|atraso|aluno/i,
    },
    {
      question: 'Temos negociacoes ou aditivos mobile pendentes agora?',
      expected: /negoci|aditiv|pendente|mobile/i,
    },
    {
      question: 'Quais leads o comercial precisa priorizar hoje?',
      expected: /lead|comercial|prior|R\$|contato/i,
    },
    {
      question: 'Quais sao as maiores faturas em aberto hoje?',
      expected: /fatura|aberto|saldo|R\$/i,
    },
    {
      question: 'O que eu devo priorizar agora na operacao?',
      expected: /prioridade|financeiro|comercial|negoci|lead|fatura/i,
    },
  ];

  await loginAdmin(page);
  await page.goto(`${baseUrl}/index.php?route=ai-chat`, { waitUntil: 'domcontentloaded' });
  await clearAdminOverlay(page);
  await expect(page.locator('body')).toContainText(/Chat IA Jully|Assistente operacional/i);

  const textarea = page.locator('#ai-message-input');
  const submit = page.locator('#ai-submit-btn');

  for (const prompt of prompts) {
    const beforeCount = await page.locator('#ai-chat-messages article').count();
    await textarea.fill(prompt.question);
    await submit.click();

    await expect
      .poll(async () => page.locator('#ai-chat-messages article').count(), { timeout: 30000 })
      .toBeGreaterThan(beforeCount + 1);

    const lastAssistantBubble = page.locator('#ai-chat-messages article').last().locator('div').first();
    await expect(lastAssistantBubble).toBeVisible();

    const answer = ((await lastAssistantBubble.innerText()) || '').trim();
    expect(answer.length).toBeGreaterThan(20);
    expect(answer).toMatch(prompt.expected);

    transcript.push(`## Pergunta`);
    transcript.push(prompt.question);
    transcript.push('');
    transcript.push('## Resposta');
    transcript.push(answer);
    transcript.push('');
  }

  mkdirSync('test-results', { recursive: true });
  const transcriptFile = `test-results/jully-hml-transcript-${runStamp}.md`;
  writeFileSync(transcriptFile, transcript.join('\n'), 'utf8');

  await expect(page.locator('#ai-chat-messages')).toContainText(/Rodada QA Jully|priorizar|negoci|lead/i);
});
