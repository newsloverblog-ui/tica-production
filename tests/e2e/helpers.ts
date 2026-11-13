import { expect, Page, test } from '@playwright/test';
import fs from 'node:fs';

export const PASSWORD = process.env.QA_PASSWORD || '';

export type Role = 'guest' | 'owner' | 'manager' | 'finance' | 'gate' | 'admin' | 'rich';

const EMAIL: Record<string, string> = {
  owner: 'qa-owner@tica.test',
  manager: 'qa-manager@tica.test',
  finance: 'qa-finance@tica.test',
  gate: 'qa-gate@tica.test',
  rich: 'qa-rich@tica.test',
};

async function csrf(page: Page): Promise<string> {
  await page.goto('/login');
  return (await page.locator('meta[name="csrf-token"]').getAttribute('content')) || '';
}

/** Signs in through the same endpoints the app uses. */
export async function signIn(page: Page, role: Role): Promise<void> {
  if (role === 'guest') return;
  if (role === 'admin') {
    await page.goto('/admin');
    await page.fill('#taa-user', 'qa_admin');
    await page.fill('#taa-pass', PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')]);
    expect(page.url(), 'admin signed in').toContain('/admin/');
    return;
  }
  const token = await csrf(page);
  const res = await page.request.post('/tica-api/login', {
    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    form: { role: 'org', email: EMAIL[role], password: PASSWORD },
  });
  expect(res.status(), `${role} signed in`).toBeLessThan(400);
}

/** Collects page errors and 5xx responses while a page loads. */
export function watch(page: Page) {
  const problems: string[] = [];
  page.on('pageerror', (e) => problems.push(`JS error: ${e.message}`));
  page.on('response', (r) => { if (r.status() >= 500) problems.push(`${r.status()} ${r.url()}`); });
  return problems;
}

export async function shot(page: Page, name: string): Promise<void> {
  const project = test.info().project.name;
  fs.mkdirSync('tests/screenshots', { recursive: true });
  await page.screenshot({ path: `tests/screenshots/${name}-${project}.png`, fullPage: true });
}

export async function noSidewaysScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow, 'page must not scroll sideways').toBeLessThanOrEqual(1);
}
