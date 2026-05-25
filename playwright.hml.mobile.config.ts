import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: 'hml-mobile-pwa.spec.ts',
  timeout: 180000,
  expect: {
    timeout: 20000,
  },
  use: {
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  reporter: [
    ['list'],
    ['json', { outputFile: 'test-results/aneo-e2e-hml-mobile-results.json' }],
  ],
});
