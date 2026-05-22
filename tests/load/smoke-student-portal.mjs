import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { performance } from 'node:perf_hooks';

const defaults = {
  baseUrl: 'https://erp-hml.aneobrasil.com.br',
  vus: 5,
  iterations: 2,
  credentials: path.resolve('tests/load/credentials.example.json'),
  timeoutMs: 30000,
};

function parseArgs(argv) {
  const args = { ...defaults };
  for (const rawArg of argv.slice(2)) {
    const [key, value] = rawArg.split('=');
    if (!value) {
      continue;
    }
    if (key === '--baseUrl') args.baseUrl = value;
    if (key === '--vus') args.vus = Number(value);
    if (key === '--iterations') args.iterations = Number(value);
    if (key === '--credentials') args.credentials = path.resolve(value);
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

function parseJsonSafe(raw) {
  const normalized = String(raw ?? '').replace(/^\uFEFF+/, '').trim();
  return JSON.parse(normalized);
}

function extractCsrf(html) {
  const match =
    html.match(/name="_csrf"\s+value="([^"]+)"/i) ||
    html.match(/const\s+csrfToken\s*=\s*'([^']+)'/i);
  return match ? match[1] : '';
}

function extractCourseLink(html) {
  const match = html.match(/href="([^"]*route=student(?:\/|%2F)course[^"]*course_id=\d+[^"]*)"/i);
  return match ? decodeHtml(match[1]) : '';
}

function decodeHtml(value) {
  return value
    .replace(/&amp;/g, '&')
    .replace(/&#0*39;/g, "'")
    .replace(/&quot;/g, '"');
}

function extractLessonPayload(html) {
  if (!/id="lesson-video"|id="yt-player-wrap"/i.test(html)) {
    return null;
  }

  const getAttr = (name, fallback = '0') => {
    const match = html.match(new RegExp(`${name}="([^"]*)"`, 'i'));
    return match ? match[1] : fallback;
  };

  return {
    courseId: Number(getAttr('data-course-id')),
    lessonId: Number(getAttr('data-lesson-id')),
    requiredPercent: Number(getAttr('data-required-percent', '70')),
    watchedSeconds: Number(getAttr('data-initial-watched')),
    positionSeconds: Number(getAttr('data-initial-position')),
    progressPercent: Number(getAttr('data-initial-progress')),
  };
}

function makeCookieHeader(cookieJar) {
  return Array.from(cookieJar.entries())
    .map(([name, value]) => `${name}=${value}`)
    .join('; ');
}

function updateCookies(cookieJar, response) {
  const raw = response.headers.get('set-cookie');
  if (!raw) {
    return;
  }
  const cookieChunks = raw.split(/,(?=[^;]+=[^;]+)/g);
  for (const chunk of cookieChunks) {
    const firstPart = chunk.split(';')[0];
    const eq = firstPart.indexOf('=');
    if (eq <= 0) continue;
    const name = firstPart.slice(0, eq).trim();
    const value = firstPart.slice(eq + 1).trim();
    cookieJar.set(name, value);
  }
}

async function fetchWithTiming(url, options, metricBag, stepName, timeoutMs) {
  const started = performance.now();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(url, { ...options, redirect: 'manual', signal: controller.signal });
    const elapsed = performance.now() - started;
    metricBag[stepName] ??= [];
    metricBag[stepName].push(elapsed);
    return { response, elapsed };
  } finally {
    clearTimeout(timer);
  }
}

async function runStudentFlow(baseUrl, credential, metricBag, timeoutMs) {
  const cookieJar = new Map();
  const failurePrefix = `[${credential.username}]`;

  const loginPage = await fetchWithTiming(
    `${baseUrl}/index.php?route=student/login`,
    { method: 'GET' },
    metricBag,
    'student_login_page',
    timeoutMs,
  );
  updateCookies(cookieJar, loginPage.response);
  const loginHtml = await loginPage.response.text();
  const csrf = extractCsrf(loginHtml);
  if (!csrf) {
    throw new Error(`${failurePrefix} csrf ausente na tela de login`);
  }

  const loginBody = new URLSearchParams({
    _csrf: csrf,
    login: credential.username,
    password: credential.password,
  });

  const loginPost = await fetchWithTiming(
    `${baseUrl}/index.php?route=student/login`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Cookie: makeCookieHeader(cookieJar),
      },
      body: loginBody.toString(),
    },
    metricBag,
    'student_login_post',
    timeoutMs,
  );
  updateCookies(cookieJar, loginPost.response);
  const location = loginPost.response.headers.get('location') || '';
  if (![302, 303].includes(loginPost.response.status) || !/student(?:\/|%2F)dashboard|student(?:\/|%2F)reenrollment/i.test(location)) {
    throw new Error(`${failurePrefix} login nao redirecionou para dashboard/reenrollment`);
  }
  if (/student(?:\/|%2F)reenrollment/i.test(location)) {
    throw new Error(`${failurePrefix} conta bloqueada em rematricula, nao serve para carga do portal`);
  }

  const dashboard = await fetchWithTiming(
    `${baseUrl}/index.php?route=student/dashboard`,
    {
      method: 'GET',
      headers: { Cookie: makeCookieHeader(cookieJar) },
    },
    metricBag,
    'student_dashboard',
    timeoutMs,
  );
  const dashboardHtml = await dashboard.response.text();
  if (!/Portal do Aluno|Meus Cursos|Minha Escala/i.test(dashboardHtml)) {
    throw new Error(`${failurePrefix} dashboard nao carregou como esperado`);
  }

  const courses = await fetchWithTiming(
    `${baseUrl}/index.php?route=student/courses`,
    {
      method: 'GET',
      headers: { Cookie: makeCookieHeader(cookieJar) },
    },
    metricBag,
    'student_courses',
    timeoutMs,
  );
  const coursesHtml = await courses.response.text();
  const courseLink = extractCourseLink(coursesHtml);
  if (!courseLink) {
    throw new Error(`${failurePrefix} nenhum link de curso encontrado`);
  }

  const coursePage = await fetchWithTiming(
    `${baseUrl}/${courseLink.replace(/^\//, '')}`,
    {
      method: 'GET',
      headers: { Cookie: makeCookieHeader(cookieJar) },
    },
    metricBag,
    'student_course_player',
    timeoutMs,
  );
  const courseHtml = await coursePage.response.text();
  const playerCsrf = extractCsrf(courseHtml);
  const lessonPayload = extractLessonPayload(courseHtml);
  if (!playerCsrf || !lessonPayload) {
    throw new Error(`${failurePrefix} payload da aula nao encontrado`);
  }

  const progressBody = new URLSearchParams({
    _csrf: playerCsrf,
    course_id: String(lessonPayload.courseId),
    lesson_id: String(lessonPayload.lessonId),
    watched_seconds: String(Math.max(lessonPayload.watchedSeconds + 45, 45)),
    duration_seconds: '120',
    position_seconds: '45',
  });

  const progress = await fetchWithTiming(
    `${baseUrl}/index.php?route=student/course/progress`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
        Cookie: makeCookieHeader(cookieJar),
      },
      body: progressBody.toString(),
    },
    metricBag,
    'student_course_progress',
    timeoutMs,
  );
  const progressJson = parseJsonSafe(await progress.response.text());
  if (!progress.response.ok || !progressJson?.ok) {
    throw new Error(`${failurePrefix} envio de progresso falhou`);
  }

  const exams = await fetchWithTiming(
    `${baseUrl}/index.php?route=student/exams`,
    {
      method: 'GET',
      headers: { Cookie: makeCookieHeader(cookieJar) },
    },
    metricBag,
    'student_exams',
    timeoutMs,
  );
  const examsHtml = await exams.response.text();
  if (!/Avaliacoes|Historico Academico|Provas disponiveis/i.test(examsHtml)) {
    throw new Error(`${failurePrefix} tela de avaliacoes nao carregou`);
  }

  return {
    user: credential.username,
    courseId: lessonPayload.courseId,
    lessonId: lessonPayload.lessonId,
    progressPercent: progressJson.progress_percent ?? 0,
  };
}

async function main() {
  const args = parseArgs(process.argv);
  const credentials = JSON.parse(fs.readFileSync(args.credentials, 'utf8'));
  if (!Array.isArray(credentials) || credentials.length === 0) {
    throw new Error('Arquivo de credenciais vazio ou invalido.');
  }

  const metricBag = {};
  const errors = [];
  const results = [];
  const totalRuns = args.vus * args.iterations;
  const tasks = Array.from({ length: totalRuns }, (_, index) => async () => {
    const credential = credentials[index % credentials.length];
    try {
      const result = await runStudentFlow(args.baseUrl, credential, metricBag, args.timeoutMs);
      results.push(result);
    } catch (error) {
      errors.push(String(error?.message || error));
    }
  });

  let cursor = 0;
  async function worker() {
    while (cursor < tasks.length) {
      const current = tasks[cursor++];
      await current();
    }
  }

  const started = performance.now();
  await Promise.all(Array.from({ length: args.vus }, () => worker()));
  const totalElapsed = performance.now() - started;

  const summary = {
    baseUrl: args.baseUrl,
    vus: args.vus,
    iterations: args.iterations,
    credentialPool: credentials.length,
    totalRuns,
    successes: results.length,
    failures: errors.length,
    totalDurationMs: Math.round(totalElapsed),
    metrics: Object.fromEntries(
      Object.entries(metricBag).map(([name, values]) => [
        name,
        {
          count: values.length,
          avgMs: Math.round(values.reduce((sum, current) => sum + current, 0) / Math.max(1, values.length)),
          p50Ms: Math.round(percentile(values, 50)),
          p95Ms: Math.round(percentile(values, 95)),
          maxMs: Math.round(Math.max(...values, 0)),
        },
      ]),
    ),
    errors,
  };

  const reportDir = path.resolve('test-results');
  fs.mkdirSync(reportDir, { recursive: true });
  const reportPath = path.join(reportDir, `student-load-smoke-${Date.now()}.json`);
  fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));

  console.log(JSON.stringify(summary, null, 2));
  console.log(`REPORT_FILE=${reportPath}`);

  if (errors.length > 0) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
