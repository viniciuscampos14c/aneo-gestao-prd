import { expect, test } from '@playwright/test';

const baseUrl = process.env.ANEO_PRD_BASE_URL || 'https://aneo.aneobrasil.com.br';
const requireSecurity = process.env.ANEO_PRD_REQUIRE_SECURITY === '1';

test('valida produção em modo somente leitura', async ({ request }) => {
  const publicPages = [
    ['/index.php?route=login', /Entrar no sistema|Gestão Educacional/i],
    ['/index.php?route=student/login', /Portal do Aluno|Entrar/i],
    ['/support.php?route=support/login', /Suporte|Entrar/i],
  ] as const;

  for (const [path, marker] of publicPages) {
    const response = await request.get(`${baseUrl}${path}`);
    expect(response.status()).toBe(200);
    expect(await response.text()).toMatch(marker);

    if (requireSecurity) {
      expect(response.headers()['strict-transport-security']).toContain('max-age=');
      expect(response.headers()['x-content-type-options']).toBe('nosniff');
      expect(response.headers()['x-frame-options']).toBe('SAMEORIGIN');
      expect(response.headers()['referrer-policy']).toBe('strict-origin-when-cross-origin');
      expect(response.headers()['permissions-policy']).toContain('camera=(self)');
      expect(response.headers()['content-security-policy']).toContain("object-src 'none'");
      expect(response.headers()['x-powered-by']).toBeUndefined();
    }
  }

  const protectedPage = await request.get(`${baseUrl}/index.php?route=students`, {
    maxRedirects: 0,
  });
  expect([302, 303]).toContain(protectedPage.status());

  const unauthorizedApi = await request.get(`${baseUrl}/api.php?r=students`);
  expect(unauthorizedApi.status()).toBe(401);

  const allowedCors = await request.fetch(`${baseUrl}/api.php?r=mobile-auth`, {
    method: 'OPTIONS',
    headers: { Origin: 'https://mobile.aneobrasil.com.br' },
  });
  expect(allowedCors.status()).toBe(204);

  const blockedCors = await request.fetch(`${baseUrl}/api.php?r=mobile-auth`, {
    method: 'OPTIONS',
    headers: { Origin: 'https://example.invalid' },
  });
  expect(blockedCors.status()).toBe(204);

  if (requireSecurity) {
    expect(allowedCors.headers()['access-control-allow-origin']).toBe('https://mobile.aneobrasil.com.br');
    expect(blockedCors.headers()['access-control-allow-origin']).toBeUndefined();
  }
});
