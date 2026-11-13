import { defineConfig } from '@playwright/test';

// Browser QA for the organiser and admin dashboards. Runs against a local
// server seeded by `php artisan tica:qa-seed` (see .github/workflows/ui-qa.yml).
export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 60_000,
  retries: 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'storage/playwright-report' }]],
  use: {
    baseURL: process.env.QA_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
});
