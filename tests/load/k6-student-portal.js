import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';
import exec from 'k6/execution';

const baseUrl = __ENV.BASE_URL || 'https://erp-hml.aneobrasil.com.br';
const credentialsFile = __ENV.CREDENTIALS_FILE || 'tests/load/credentials.example.json';
const credentials = JSON.parse(open(credentialsFile));

export const options = {
  scenarios: {
    portal_load: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '30s', target: 5 },
        { duration: '1m', target: 10 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<2500'],
    student_login_post: ['p(95)<2500'],
    student_course_progress: ['p(95)<3000'],
  },
};

const loginPostTrend = new Trend('student_login_post');
const dashboardTrend = new Trend('student_dashboard');
const coursesTrend = new Trend('student_courses');
const playerTrend = new Trend('student_course_player');
const progressTrend = new Trend('student_course_progress');
const flowErrors = new Counter('student_flow_errors');
const flowSuccess = new Rate('student_flow_success');

function parseJsonSafe(raw) {
  return JSON.parse(String(raw || '').replace(/^\uFEFF+/, '').trim());
}

function credentialForVu() {
  const index = (exec.vu.idInTest - 1) % credentials.length;
  return credentials[index];
}

function extractCsrf(html) {
  const match =
    html.match(/name="_csrf"\s+value="([^"]+)"/i) ||
    html.match(/const\s+csrfToken\s*=\s*'([^']+)'/i);
  return match ? match[1] : '';
}

function extractCourseLink(html) {
  const match = html.match(/href="([^"]*route=student(?:\/|%2F)course[^"]*course_id=\d+[^"]*)"/i);
  return match ? match[1].replace(/&amp;/g, '&') : '';
}

function extractLessonPayload(html) {
  if (!/id="lesson-video"|id="yt-player-wrap"/i.test(html)) {
    return null;
  }

  const courseId = html.match(/data-course-id="(\d+)"/i);
  const lessonId = html.match(/data-lesson-id="(\d+)"/i);
  if (!courseId || !lessonId) {
    return null;
  }

  return {
    courseId: Number(courseId[1]),
    lessonId: Number(lessonId[1]),
  };
}

export default function () {
  const credential = credentialForVu();
  const loginPage = http.get(`${baseUrl}/index.php?route=student/login`);
  check(loginPage, { 'login page 200': (r) => r.status === 200 });
  const csrf = extractCsrf(loginPage.body || '');
  if (!csrf) {
    flowErrors.add(1);
    flowSuccess.add(false);
    return;
  }

  const loginPost = http.post(
    `${baseUrl}/index.php?route=student/login`,
    {
      _csrf: csrf,
      login: credential.username,
      password: credential.password,
    },
    {
      redirects: 0,
      tags: { name: 'student_login_post' },
    },
  );
  loginPostTrend.add(loginPost.timings.duration);

  const location = loginPost.headers.Location || loginPost.headers.location || '';
  const loginOk = [302, 303].includes(loginPost.status) && /student(?:\/|%2F)dashboard/i.test(location);
  check(loginPost, { 'student login redirect ok': () => loginOk });
  if (!loginOk) {
    flowErrors.add(1);
    flowSuccess.add(false);
    return;
  }

  const dashboard = http.get(`${baseUrl}/index.php?route=student/dashboard`, {
    tags: { name: 'student_dashboard' },
  });
  dashboardTrend.add(dashboard.timings.duration);
  const dashboardOk = check(dashboard, {
    'dashboard 200': (r) => r.status === 200,
    'dashboard content': (r) => /Portal do Aluno|Meus Cursos|Minha Escala/i.test(r.body || ''),
  });
  if (!dashboardOk) {
    flowErrors.add(1);
    flowSuccess.add(false);
    return;
  }

  const courses = http.get(`${baseUrl}/index.php?route=student/courses`, {
    tags: { name: 'student_courses' },
  });
  coursesTrend.add(courses.timings.duration);
  const courseLink = extractCourseLink(courses.body || '');
  if (!courseLink) {
    flowErrors.add(1);
    flowSuccess.add(false);
    return;
  }

  const player = http.get(`${baseUrl}/${courseLink.replace(/^\//, '')}`, {
    tags: { name: 'student_course_player' },
  });
  playerTrend.add(player.timings.duration);
  const playerCsrf = extractCsrf(player.body || '');
  const lesson = extractLessonPayload(player.body || '');
  if (!playerCsrf || !lesson) {
    flowErrors.add(1);
    flowSuccess.add(false);
    return;
  }

  const progress = http.post(
    `${baseUrl}/index.php?route=student/course/progress`,
    {
      _csrf: playerCsrf,
      course_id: String(lesson.courseId),
      lesson_id: String(lesson.lessonId),
      watched_seconds: '45',
      duration_seconds: '120',
      position_seconds: '45',
    },
    {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      tags: { name: 'student_course_progress' },
    },
  );
  progressTrend.add(progress.timings.duration);
  const progressOk = check(progress, {
    'progress 200': (r) => r.status === 200,
    'progress ok body': (r) => {
      try {
        return parseJsonSafe(r.body || '{}').ok === true;
      } catch (error) {
        return false;
      }
    },
  });

  flowSuccess.add(progressOk);
  if (!progressOk) {
    flowErrors.add(1);
  }

  sleep(1);
}
