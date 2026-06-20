import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { performance } from 'node:perf_hooks';

const defaults = {
  baseUrl: 'https://erp-hml.aneobrasil.com.br',
  credentials: path.resolve('tests/load/credentials.local.json'),
  loginConcurrency: 10,
  timeoutMs: 30000,
};

function parseArgs(argv) {
  const args = { ...defaults };
  for (const rawArg of argv.slice(2)) {
    const [key, value] = rawArg.split('=');
    if (!value) continue;
    if (key === '--baseUrl') args.baseUrl = value;
    if (key === '--credentials') args.credentials = path.resolve(value);
    if (key === '--loginConcurrency') args.loginConcurrency = Number(value);
    if (key === '--timeoutMs') args.timeoutMs = Number(value);
  }
  return args;
}

function percentile(values, p) {
  if (values.length === 0) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const index = Math.min(sorted.length - 1, Math.max(0, Math.ceil((p / 100) * sorted.length) - 1));
  return sorted[index];
}

function extractCsrf(html) {
  const match =
    html.match(/name="_csrf"\s+value="([^"]+)"/i) ||
    html.match(/const\s+csrfToken\s*=\s*'([^']+)'/i);
  return match ? match[1] : '';
}

function decodeHtml(value) {
  return value
    .replace(/&amp;/g, '&')
    .replace(/&#0*39;/g, "'")
    .replace(/&quot;/g, '"');
}

function extractCourseLink(html) {
  const match = html.match(/href="([^"]*route=student(?:\/|%2F)course[^"]*course_id=\d+[^"]*)"/i);
  return match ? decodeHtml(match[1]) : '';
}

function makeCookieHeader(cookieJar) {
  return Array.from(cookieJar.entries())
    .map(([name, value]) => `${name}=${value}`)
    .join('; ');
}

function updateCookies(cookieJar, response) {
  const raw = response.headers.get('set-cookie');
  if (!raw) return;
  for (const chunk of raw.split(/,(?=[^;]+=[^;]+)/g)) {
    const firstPart = chunk.split(';')[0];
    const eq = firstPart.indexOf('=');
    if (eq <= 0) continue;
    cookieJar.set(firstPart.slice(0, eq).trim(), firstPart.slice(eq + 1).trim());
  }
}

async function timedFetch(url, options, metrics, name, timeoutMs) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  const started = performance.now();
  try {
    const response = await fetch(url, { ...options, redirect: 'manual', signal: controller.signal });
    metrics[name] ??= [];
    metrics[name].push(performance.now() - started);
    return response;
  } finally {
    clearTimeout(timer);
  }
}

async function authenticate(baseUrl, credential, metrics, timeoutMs) {
  const cookieJar = new Map();
  const loginPage = await timedFetch(
    `${baseUrl}/index.php?route=student/login`,
    { method: 'GET' },
    metrics,
    'login_page',
    timeoutMs,
  );
  updateCookies(cookieJar, loginPage);
  const csrf = extractCsrf(await loginPage.text());
  if (!csrf) throw new Error(`[${credential.username}] CSRF ausente no login`);

  const response = await timedFetch(
    `${baseUrl}/index.php?route=student/login`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Cookie: makeCookieHeader(cookieJar),
      },
      body: new URLSearchParams({
        _csrf: csrf,
        login: credential.username,
        password: credential.password,
      }).toString(),
    },
    metrics,
    'login_post',
    timeoutMs,
  );
  updateCookies(cookieJar, response);
  const location = response.headers.get('location') || '';
  if (![302, 303].includes(response.status) || !/student(?:\/|%2F)dashboard/i.test(location)) {
    throw new Error(`[${credential.username}] login não redirecionou ao dashboard`);
  }
  return { credential, cookieJar };
}

async function navigate(baseUrl, session, metrics, timeoutMs) {
  const headers = { Cookie: makeCookieHeader(session.cookieJar) };
  const prefix = `[${session.credential.username}]`;

  const dashboard = await timedFetch(
    `${baseUrl}/index.php?route=student/dashboard`,
    { method: 'GET', headers },
    metrics,
    'authenticated_dashboard',
    timeoutMs,
  );
  const dashboardHtml = await dashboard.text();
  if (!dashboard.ok || !/Portal do Aluno|Meus Cursos|Minha Escala/i.test(dashboardHtml)) {
    throw new Error(`${prefix} dashboard autenticado inválido`);
  }

  const courses = await timedFetch(
    `${baseUrl}/index.php?route=student/courses`,
    { method: 'GET', headers },
    metrics,
    'authenticated_courses',
    timeoutMs,
  );
  const coursesHtml = await courses.text();
  const courseLink = extractCourseLink(coursesHtml);
  if (!courses.ok || !courseLink) throw new Error(`${prefix} curso não encontrado`);

  const player = await timedFetch(
    `${baseUrl}/${courseLink.replace(/^\//, '')}`,
    { method: 'GET', headers },
    metrics,
    'authenticated_course_player',
    timeoutMs,
  );
  const playerHtml = await player.text();
  if (!player.ok || !/id="lesson-video"|id="yt-player-wrap"/i.test(playerHtml)) {
    throw new Error(`${prefix} player da aula não carregou`);
  }

  const exams = await timedFetch(
    `${baseUrl}/index.php?route=student/exams`,
    { method: 'GET', headers },
    metrics,
    'authenticated_exams',
    timeoutMs,
  );
  const examsHtml = await exams.text();
  if (!exams.ok || !/Avaliações|Histórico Acadêmico|Provas disponíveis/i.test(examsHtml)) {
    throw new Error(`${prefix} avaliações não carregaram`);
  }
}

async function runPool(items, concurrency, task) {
  let cursor = 0;
  const workers = Array.from({ length: Math.min(concurrency, items.length) }, async () => {
    while (cursor < items.length) {
      const item = items[cursor++];
      await task(item);
    }
  });
  await Promise.all(workers);
}

function summarize(metrics) {
  return Object.fromEntries(
    Object.entries(metrics).map(([name, values]) => [
      name,
      {
        count: values.length,
        avgMs: Math.round(values.reduce((sum, value) => sum + value, 0) / values.length),
        p50Ms: Math.round(percentile(values, 50)),
        p95Ms: Math.round(percentile(values, 95)),
        maxMs: Math.round(Math.max(...values)),
      },
    ]),
  );
}

async function main() {
  const args = parseArgs(process.argv);
  const credentials = JSON.parse(fs.readFileSync(args.credentials, 'utf8').replace(/^\uFEFF+/, ''));
  if (!Array.isArray(credentials) || credentials.length === 0) {
    throw new Error('Arquivo de credenciais vazio ou inválido.');
  }

  const metrics = {};
  const loginErrors = [];
  const navigationErrors = [];
  const sessions = [];

  const loginStarted = performance.now();
  await runPool(credentials, args.loginConcurrency, async (credential) => {
    try {
      sessions.push(await authenticate(args.baseUrl, credential, metrics, args.timeoutMs));
    } catch (error) {
      loginErrors.push(String(error?.message || error));
    }
  });
  const loginDurationMs = Math.round(performance.now() - loginStarted);

  const navigationStarted = performance.now();
  await Promise.all(
    sessions.map(async (session) => {
      try {
        await navigate(args.baseUrl, session, metrics, args.timeoutMs);
      } catch (error) {
        navigationErrors.push(String(error?.message || error));
      }
    }),
  );
  const navigationDurationMs = Math.round(performance.now() - navigationStarted);

  const summary = {
    baseUrl: args.baseUrl,
    users: credentials.length,
    loginConcurrency: args.loginConcurrency,
    authenticatedSessions: sessions.length,
    loginFailures: loginErrors.length,
    navigationSuccesses: sessions.length - navigationErrors.length,
    navigationFailures: navigationErrors.length,
    loginDurationMs,
    concurrentNavigationDurationMs: navigationDurationMs,
    metrics: summarize(metrics),
    errors: [...loginErrors, ...navigationErrors],
  };

  const reportDir = path.resolve('test-results');
  fs.mkdirSync(reportDir, { recursive: true });
  const reportPath = path.join(reportDir, `student-authenticated-load-${Date.now()}.json`);
  fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));

  console.log(JSON.stringify(summary, null, 2));
  console.log(`REPORT_FILE=${reportPath}`);
  if (loginErrors.length || navigationErrors.length) process.exitCode = 1;
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
