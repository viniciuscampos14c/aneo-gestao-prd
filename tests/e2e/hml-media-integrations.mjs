import { chromium, request as playwrightRequest } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = process.env.ANEO_HML_BASE_URL || 'https://erp-hml.aneobrasil.com.br';
const adminUser = process.env.ANEO_HML_ADMIN_USER || 'qa_admin_hml';
const adminPassword = process.env.ANEO_HML_ADMIN_PASSWORD || '';
const studentUser = process.env.ANEO_HML_STUDENT_USER || 'qa.aluno.portal';
const studentPassword = process.env.ANEO_HML_STUDENT_PASSWORD || '';
const youtubeUsers = Math.max(1, Number(process.env.ANEO_HML_YOUTUBE_USERS || 10));
const mediaMode = String(process.env.ANEO_HML_MEDIA_MODE || 'all').toLowerCase();
const loadCredentialsPath = path.resolve(
  process.env.ANEO_LOAD_CREDENTIALS || path.join('tests', 'load', 'credentials.local.json')
);

if (!adminPassword || !studentPassword) {
  throw new Error('Defina ANEO_HML_ADMIN_PASSWORD e ANEO_HML_STUDENT_PASSWORD.');
}

const resultsDir = path.resolve('test-results', 'hml-media-integrations');

function ensureResultsDir() {
  fs.mkdirSync(resultsDir, { recursive: true });
}

async function loginStudent(page, username = studentUser, password = studentPassword) {
  await page.goto(`${baseUrl}/index.php?route=student/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="login"]').fill(username);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /entrar/i }).click();
  await page.waitForURL(/student(?:\/|%2F)dashboard/);
}

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

async function validateYouTube(browser) {
  const credentials = JSON.parse(
    fs.readFileSync(loadCredentialsPath, 'utf8').replace(/^\uFEFF+/, '')
  ).slice(0, youtubeUsers);

  const startedAt = Date.now();
  const runs = await Promise.all(
    credentials.map(async (credential) => {
      const context = await browser.newContext();
      const page = await context.newPage();
      const youtubeResponses = [];
      page.on('response', (response) => {
        const url = response.url();
        if (/youtube\.com|googlevideo\.com|ytimg\.com|googleapis\.com/i.test(url)) {
          youtubeResponses.push({ url, status: response.status() });
        }
      });

      try {
        await loginStudent(page, credential.username, credential.password);
        await page.goto(`${baseUrl}/index.php?route=student/courses`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('link', { name: /continuar curso/i }).first().click();
        await page.locator('#yt-player-wrap').waitFor({ state: 'visible', timeout: 30000 });

        await page.waitForFunction(
          () =>
            typeof window.YT !== 'undefined' &&
            window._ytPlayerInstance &&
            typeof window._ytPlayerInstance.getDuration === 'function' &&
            window._ytPlayerInstance.getDuration() > 0,
          null,
          { timeout: 45000 }
        );

        const player = await page.evaluate(() => {
          const wrap = document.getElementById('yt-player-wrap');
          const instance = window._ytPlayerInstance;
          return {
            videoId: wrap?.dataset.youtubeId || '',
            duration: Number(instance?.getDuration?.() || 0),
            iframeSrc:
              document.querySelector('iframe#yt-player')?.getAttribute('src') ||
              document.querySelector('#yt-player iframe')?.getAttribute('src') ||
              '',
            playerState: Number(instance?.getPlayerState?.() ?? -1),
          };
        });

        if (!/^[A-Za-z0-9_-]{11}$/.test(player.videoId)) {
          throw new Error(`ID do YouTube inválido para ${credential.username}.`);
        }
        if (player.duration <= 0 || !/youtube\.com\/embed\//i.test(player.iframeSrc)) {
          throw new Error(`Player externo não ficou pronto para ${credential.username}.`);
        }
        if (!youtubeResponses.some((item) => item.status >= 200 && item.status < 400)) {
          throw new Error(`Nenhuma resposta válida do YouTube para ${credential.username}.`);
        }

        return {
          user: credential.username,
          ok: true,
          player,
          youtubeRequests: youtubeResponses.length,
          youtubeErrors: youtubeResponses.filter((item) => item.status >= 400).slice(0, 10),
        };
      } catch (error) {
        return {
          user: credential.username,
          ok: false,
          error: String(error?.message || error),
          youtubeRequests: youtubeResponses.length,
          youtubeErrors: youtubeResponses.filter((item) => item.status >= 400).slice(0, 10),
        };
      } finally {
        await context.close();
      }
    })
  );

  const summary = {
    concurrentBrowsers: credentials.length,
    successes: runs.filter((run) => run.ok).length,
    failures: runs.filter((run) => !run.ok).length,
    durationMs: Date.now() - startedAt,
    runs,
  };
  ensureResultsDir();
  fs.writeFileSync(
    path.join(resultsDir, `youtube-${Date.now()}.json`),
    `${JSON.stringify(summary, null, 2)}\n`,
    'utf8'
  );
  if (summary.failures > 0) {
    throw new Error(`YouTube apresentou ${summary.failures} falha(s) em ${summary.concurrentBrowsers} navegadores.`);
  }
  return summary;
}

async function validateZoomPortalConcurrency(joinUrl, marker) {
  const credentials = JSON.parse(
    fs.readFileSync(loadCredentialsPath, 'utf8').replace(/^\uFEFF+/, '')
  );
  const startedAt = Date.now();
  const results = await Promise.all(
    credentials.map(async (credential) => {
      const api = await playwrightRequest.newContext({ baseURL: baseUrl });
      try {
        const loginPage = await api.get('/index.php?route=student/login');
        const loginHtml = await loginPage.text();
        const csrf = loginHtml.match(/name="_csrf"\s+value="([^"]+)"/i)?.[1] || '';
        if (!csrf) {
          throw new Error('CSRF ausente.');
        }

        const login = await api.post('/index.php?route=student/login', {
          form: {
            _csrf: csrf,
            login: credential.username,
            password: credential.password,
          },
          maxRedirects: 0,
        });
        if (![302, 303].includes(login.status())) {
          throw new Error(`Login HTTP ${login.status()}.`);
        }

        const live = await api.get('/index.php?route=student/live');
        const html = await live.text();
        const ok = live.ok() && html.includes(marker) && html.includes(joinUrl.replaceAll('&', '&amp;'));
        return {
          user: credential.username,
          ok,
          status: live.status(),
          error: ok ? '' : 'Aula ou link não encontrado no portal.',
        };
      } catch (error) {
        return {
          user: credential.username,
          ok: false,
          status: 0,
          error: String(error?.message || error),
        };
      } finally {
        await api.dispose();
      }
    })
  );

  const summary = {
    users: credentials.length,
    successes: results.filter((result) => result.ok).length,
    failures: results.filter((result) => !result.ok).length,
    durationMs: Date.now() - startedAt,
    errors: results.filter((result) => !result.ok).slice(0, 20),
  };
  ensureResultsDir();
  fs.writeFileSync(
    path.join(resultsDir, `zoom-portal-load-${Date.now()}.json`),
    `${JSON.stringify(summary, null, 2)}\n`,
    'utf8'
  );
  if (summary.failures > 0) {
    throw new Error(`Portal Zoom falhou para ${summary.failures} de ${summary.users} alunos.`);
  }
  return summary;
}

async function validateZoom(browser) {
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  const marker = `QA Zoom Pré-Go-Live ${new Date().toISOString().replace(/[:.]/g, '-')}`;
  const scheduled = new Date(Date.now() + 24 * 60 * 60 * 1000);
  const localScheduled = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'America/Sao_Paulo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(scheduled).replace(' ', 'T');

  let sessionId = 0;
  let meetingId = '';
  let joinUrl = '';
  let cancelled = false;

  try {
    await loginAdmin(adminPage);
    await adminPage.goto(`${baseUrl}/index.php?route=courses/live-sessions/create`, {
      waitUntil: 'domcontentloaded',
    });

    const courseOption = adminPage.locator('select[name="course_id"] option[value="1"]');
    if ((await courseOption.count()) === 0) {
      throw new Error('Curso QA 1 não está disponível para criação da aula Zoom.');
    }

    await adminPage.locator('select[name="course_id"]').selectOption('1');
    await adminPage.locator('input[name="title"]').fill(marker);
    await adminPage.locator('input[name="scheduled_at"]').fill(localScheduled);
    await adminPage.locator('input[name="duration_minutes"]').fill('30');
    await adminPage.locator('textarea[name="notes"]').fill(
      'Reunião controlada para validação pré-go-live. Cancelar após o teste.'
    );

    await Promise.all([
      adminPage.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
      adminPage.getByRole('button', { name: /criar reuni/i }).click(),
    ]);

    const newIdMatch = adminPage.url().match(/new_id=(\d+)/);
    sessionId = Number(newIdMatch?.[1] || 0);
    if (sessionId <= 0) {
      const body = await adminPage.locator('body').innerText();
      throw new Error(`Reunião não foi salva no ERP. Retorno: ${body.slice(0, 500)}`);
    }

    joinUrl = await adminPage.locator('#join_url_copy').inputValue();
    meetingId = (
      (await adminPage.locator('text=/Meeting ID/i').locator('..').innerText().catch(() => '')) || ''
    ).replace(/\D/g, '');
    if (!meetingId) {
      meetingId = joinUrl.match(/\/j\/(\d+)/)?.[1] || '';
    }
    if (!/^https:\/\/[^/]*zoom\.us\//i.test(joinUrl)) {
      throw new Error('O Zoom não retornou um join_url válido.');
    }

    const studentContext = await browser.newContext();
    const studentPage = await studentContext.newPage();
    await loginStudent(studentPage);
    await studentPage.goto(`${baseUrl}/index.php?route=student/live`, {
      waitUntil: 'domcontentloaded',
    });
    const sessionCard = studentPage.locator('article').filter({ hasText: marker }).first();
    await sessionCard.waitFor({ state: 'visible', timeout: 30000 });
    const studentJoinUrl = await sessionCard.getByRole('link', { name: /entrar na aula/i }).getAttribute('href');
    if (studentJoinUrl !== joinUrl) {
      throw new Error('O link exibido ao aluno difere do link criado pelo Zoom.');
    }

    const portalLoad = await validateZoomPortalConcurrency(joinUrl, marker);

    const zoomPage = await studentContext.newPage();
    const zoomResponse = await zoomPage.goto(joinUrl, {
      waitUntil: 'domcontentloaded',
      timeout: 60000,
    });
    await zoomPage.waitForTimeout(3000);
    const zoomText = await zoomPage.locator('body').innerText().catch(() => '');
    const zoomTitle = await zoomPage.title().catch(() => '');
    const zoomUrl = zoomPage.url();
    const externalOk =
      !!zoomResponse &&
      zoomResponse.status() >= 200 &&
      zoomResponse.status() < 400 &&
      /zoom\.us/i.test(zoomUrl) &&
      new RegExp(`/j/${meetingId || '\\d+'}`, 'i').test(zoomUrl) &&
      (
        /#success/i.test(zoomUrl) ||
        /launch meeting|open zoom|join from your browser|iniciar reuni|abrir zoom|entrar/i.test(
          `${zoomTitle} ${zoomText}`
        )
      );
    if (!externalOk) {
      throw new Error(
        `Página externa do Zoom não confirmou a reunião. HTTP ${zoomResponse?.status() ?? 0}, URL: ${zoomUrl}`
      );
    }
    await studentContext.close();

    const cancelForm = adminPage.locator(`form[action*="courses/live-sessions/cancel"]`).filter({
      has: adminPage.locator(`input[name="id"][value="${sessionId}"]`),
    });
    const csrf = await cancelForm.locator('input[name="_csrf"]').inputValue();
    const cancelResponse = await adminPage.request.post(
      `${baseUrl}/index.php?route=courses/live-sessions/cancel`,
      {
        form: { _csrf: csrf, id: String(sessionId) },
        maxRedirects: 0,
      }
    );
    if (![302, 303].includes(cancelResponse.status())) {
      throw new Error(`Cancelamento retornou HTTP ${cancelResponse.status()}.`);
    }
    cancelled = true;

    await adminPage.goto(`${baseUrl}/index.php?route=courses/live-sessions&status=cancelled`, {
      waitUntil: 'domcontentloaded',
    });
    const cancelledRow = adminPage.locator('tr').filter({ hasText: marker }).first();
    await cancelledRow.waitFor({ state: 'visible', timeout: 30000 });
    if (!/cancelada/i.test(await cancelledRow.innerText())) {
      throw new Error('A aula não ficou marcada como cancelada no ERP.');
    }

    const result = {
      ok: true,
      title: marker,
      sessionId,
      meetingId,
      joinHost: new URL(joinUrl).host,
      studentPortalValidated: true,
      portalConcurrentUsers: portalLoad.users,
      portalConcurrentSuccesses: portalLoad.successes,
      portalConcurrentDurationMs: portalLoad.durationMs,
      externalZoomPageValidated: true,
      cancelled,
    };
    ensureResultsDir();
    fs.writeFileSync(
      path.join(resultsDir, `zoom-${Date.now()}.json`),
      `${JSON.stringify(result, null, 2)}\n`,
      'utf8'
    );
    return result;
  } finally {
    if (sessionId > 0 && !cancelled) {
      try {
        await adminPage.goto(`${baseUrl}/index.php?route=courses/live-sessions`, {
          waitUntil: 'domcontentloaded',
        });
        const row = adminPage.locator('tr').filter({ hasText: marker }).first();
        const form = row.locator('form[action*="courses/live-sessions/cancel"]');
        if (await form.isVisible().catch(() => false)) {
          const csrf = await form.locator('input[name="_csrf"]').inputValue();
          await adminPage.request.post(`${baseUrl}/index.php?route=courses/live-sessions/cancel`, {
            form: { _csrf: csrf, id: String(sessionId) },
            maxRedirects: 0,
          });
        }
      } catch {}
    }
    await adminContext.close();
  }
}

const browser = await chromium.launch({ headless: true });
try {
  if (mediaMode === 'all' || mediaMode === 'youtube') {
    const youtube = await validateYouTube(browser);
    console.log(
      `YOUTUBE_OK=${youtube.successes}/${youtube.concurrentBrowsers} DURATION_MS=${youtube.durationMs}`
    );
  }
  if (mediaMode === 'all' || mediaMode === 'zoom') {
    const zoom = await validateZoom(browser);
    console.log(
      `ZOOM_OK=1 SESSION_ID=${zoom.sessionId} PORTAL=${zoom.portalConcurrentSuccesses}/${zoom.portalConcurrentUsers} EXTERNAL=OK CANCELLED=${zoom.cancelled}`
    );
  }
} finally {
  await browser.close();
}
