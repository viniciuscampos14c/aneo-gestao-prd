import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: 'hml-jully.spec.ts',
  timeout: 120000,
  expect: {
    timeout: 15000,
  },
  use: {
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  reporter: [
    ['list'],
    ['json', { outputFile: 'test-results/aneo-e2e-hml-jully-results.json' }],
  ],
});
