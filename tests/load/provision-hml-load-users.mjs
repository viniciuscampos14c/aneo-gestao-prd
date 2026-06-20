import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = process.env.ANEO_HML_BASE_URL || 'https://erp-hml.aneobrasil.com.br';
const adminUser = process.env.ANEO_HML_ADMIN_USER || 'qa_admin_hml';
const adminPassword = process.env.ANEO_HML_ADMIN_PASSWORD || '';
const studentUser = process.env.ANEO_HML_STUDENT_USER || 'qa.aluno.portal';
const studentPassword = process.env.ANEO_HML_STUDENT_PASSWORD || '';
const accountPassword = process.env.ANEO_HML_LOAD_PASSWORD || '';
const total = Number(process.env.ANEO_HML_LOAD_USERS || 100);

const outputDir = path.resolve('test-results', 'hml-load-provisioning');
const csvPath = path.join(outputDir, 'qa-load-students.csv');
const credentialsPath = path.resolve('tests', 'load', 'credentials.local.json');

if (!adminPassword || !studentPassword || !accountPassword) {
  throw new Error(
    'Defina ANEO_HML_ADMIN_PASSWORD, ANEO_HML_STUDENT_PASSWORD e ANEO_HML_LOAD_PASSWORD antes de executar.'
  );
}

fs.mkdirSync(outputDir, { recursive: true });

function csvValue(value) {
  const text = String(value ?? '');
  return `"${text.replaceAll('"', '""')}"`;
}

const accounts = Array.from({ length: total }, (_, index) => {
  const sequence = String(index + 1).padStart(3, '0');
  return {
    name: `QA Carga ${sequence}`,
    email: `qa.carga.${sequence}@aneo.test`,
    username: `qa.carga.${sequence}`,
    password: accountPassword,
  };
});

const header = [
  'Nome',
  'Email',
  'Telefone',
  'Status',
  'Informacoes_adm',
  'Login_portal',
  'Senha_inicial',
  'Portal_ativo',
];
const csvRows = [
  header.map(csvValue).join(';'),
  ...accounts.map((account) =>
    [
      account.name,
      account.email,
      '11900000000',
      'Ativo',
      'QA-CARGA-PRE-GO-LIVE',
      account.username,
      account.password,
      'Sim',
    ].map(csvValue).join(';')
  ),
];
fs.writeFileSync(csvPath, `\uFEFF${csvRows.join('\r\n')}\r\n`, 'utf8');

async function loginAdmin(page) {
  await page.goto(`${baseUrl}/index.php?route=login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(adminUser);
  await page.locator('input[name="password"]').fill(adminPassword);
  await page.getByRole('button', { name: /entrar/i }).click();
  if (page.url().includes('select-company')) {
    await page.getByRole('button', { name: /empresa selecionada|entrar/i }).first().click();
  }
  await page.waitForURL(/route=dashboard/);
}

async function resolveValidatedCourseId(browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(`${baseUrl}/index.php?route=student/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(studentUser);
  await page.locator('input[name="password"]').fill(studentPassword);
  await page.getByRole('button', { name: /entrar/i }).click();
  await page.waitForURL(/student(?:\/|%2F)dashboard/);
  await page.goto(`${baseUrl}/index.php?route=student/courses`, { waitUntil: 'domcontentloaded' });
  const href = await page.getByRole('link', { name: /continuar curso/i }).first().getAttribute('href');
  await context.close();
  const match = String(href || '').match(/course_id=(\d+)/);
  if (!match) {
    throw new Error('Não foi possível identificar o curso validado da conta QA.');
  }
  return Number(match[1]);
}

async function importAccounts(page) {
  await page.goto(`${baseUrl}/index.php?route=data-imports`, { waitUntil: 'domcontentloaded' });
  await page.locator('select[name="import_type"]').selectOption('students');
  await page.locator('input[name="csv_file"]').setInputFiles(csvPath);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.getByRole('button', { name: /validar arquivo/i }).click(),
  ]);

  const bodyText = await page.locator('body').innerText();
  const validMatch = bodyText.match(/V[aá]lidas\s+(\d+)/i);
  const errorMatch = bodyText.match(/Com erro\s+(\d+)/i);
  const validRows = Number(validMatch?.[1] || 0);
  const errorRows = Number(errorMatch?.[1] || 0);
  if (validRows !== total || errorRows !== 0) {
    throw new Error(`Importação não confirmada: válidas=${validRows}, erros=${errorRows}, esperado=${total}.`);
  }

  const confirmButton = page.getByRole('button', { name: /confirmar carga/i });
  if (await confirmButton.isVisible().catch(() => false)) {
    page.once('dialog', (dialog) => dialog.accept());
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      confirmButton.click(),
    ]);
  }

  const completedText = await page.locator('body').innerText();
  if (!/conclu|importad|criad|atualizad/i.test(completedText)) {
    throw new Error('O sistema não confirmou a conclusão da importação.');
  }
}

async function enrollAccounts(page, courseId) {
  await page.goto(`${baseUrl}/index.php?route=courses/enrollments`, { waitUntil: 'domcontentloaded' });
  const csrf = await page.locator('input[name="_csrf"]').first().getAttribute('value');
  if (!csrf) {
    throw new Error('CSRF ausente na tela de matrículas.');
  }

  const studentOptions = await page.locator('select[name="student_id"] option').evaluateAll((options) =>
    options.map((option) => ({
      id: Number(option.getAttribute('value') || 0),
      name: (option.textContent || '').trim(),
    }))
  );
  const idsByName = new Map(studentOptions.map((option) => [option.name, option.id]));
  const missing = accounts.filter((account) => !idsByName.get(account.name));
  if (missing.length > 0) {
    throw new Error(`Alunos ausentes na matrícula: ${missing.slice(0, 5).map((item) => item.name).join(', ')}`);
  }

  const startedAt = new Date().toISOString().slice(0, 10);
  let enrolled = 0;
  for (const account of accounts) {
    const response = await page.request.post(`${baseUrl}/index.php?route=courses/enrollments/store`, {
      form: {
        _csrf: csrf,
        student_id: String(idsByName.get(account.name)),
        course_id: String(courseId),
        status: 'active',
        started_at: startedAt,
      },
      maxRedirects: 0,
    });
    if (![302, 303].includes(response.status())) {
      throw new Error(`Falha ao matricular ${account.name}: HTTP ${response.status()}.`);
    }
    enrolled += 1;
    if (enrolled % 10 === 0) {
      console.log(`MATRICULADOS=${enrolled}/${total}`);
    }
  }
}

const browser = await chromium.launch({ headless: true });
try {
  const courseId = await resolveValidatedCourseId(browser);
  const context = await browser.newContext();
  const page = await context.newPage();
  await loginAdmin(page);
  await importAccounts(page);
  await enrollAccounts(page, courseId);
  await context.close();

  fs.writeFileSync(
    credentialsPath,
    `${JSON.stringify(accounts.map(({ username, password }) => ({ username, password })), null, 2)}\n`,
    'utf8'
  );
  console.log(`COURSE_ID=${courseId}`);
  console.log(`ACCOUNTS_READY=${accounts.length}`);
  console.log(`CREDENTIALS_FILE=${credentialsPath}`);
} finally {
  await browser.close();
}
