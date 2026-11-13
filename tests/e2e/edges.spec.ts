import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { signIn, watch } from './helpers';

test.describe('edge cases (owner)', () => {
  test.beforeEach(async ({ page }) => { await signIn(page, 'owner'); });

  test('a setting saves once on double-click and survives a reload', async ({ page }) => {
    await page.goto('/dashboard/settings');
    await page.click('[data-stab="notifications"]');
    const sw = page.locator('[data-pref="notify_sale"]');
    const before = await sw.isChecked();
    const saves: number[] = [];
    page.on('request', (r) => { if (r.url().includes('/tica-api/organizer/prefs')) saves.push(1); });
    await sw.dblclick();
    await page.waitForTimeout(1500);
    expect(saves.length, 'each click saves at most once').toBeLessThanOrEqual(2);
    await page.reload();
    await page.click('[data-stab="notifications"]');
    expect(await page.locator('[data-pref="notify_sale"]').isChecked()).toBe(before);
  });

  test('a photo over 3 MB is refused with an inline message', async ({ page }) => {
    await page.goto('/dashboard/settings');
    const big = path.join(os.tmpdir(), 'qa-big.jpg');
    fs.writeFileSync(big, Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(3.5 * 1024 * 1024)]));
    await page.setInputFiles('#od-photo', big);
    await page.click('#od-prof [type="submit"]');
    await expect(page.locator('#od-prof')).toContainText(/3 MB|too large|smaller/i);
  });

  test('the wrong file type is refused before upload', async ({ page }) => {
    await page.goto('/dashboard/settings');
    await page.setInputFiles('#od-photo', { name: 'qa.txt', mimeType: 'text/plain', buffer: Buffer.from('nope') });
    await expect(page.locator('#od-prof')).toContainText(/JPG, PNG, WebP or GIF/);
  });

  test('an expired session sends you to log in instead of failing', async ({ page, context }) => {
    await page.goto('/dashboard/settings');
    await context.clearCookies();
    // Signed-out visitors to the dashboard land on log in or sign up (which links to log in).
    const problems = watch(page);
    const res = await page.goto('/dashboard/settings');
    expect(res?.status() ?? 0).toBeLessThan(500);
    expect(page.url()).toMatch(/login|signup/);
    expect(problems).toEqual([]);
  });

  test('pages stay usable on a slow network', async ({ page, context }) => {
    await context.route('**/tica-api/organizer/**', async (route) => { await new Promise((r) => setTimeout(r, 1500)); await route.continue(); });
    const problems = watch(page);
    await page.goto('/dashboard/orders');
    await expect(page.locator('#od-att')).toBeVisible();
    await page.waitForTimeout(2500);
    expect(problems).toEqual([]);
  });
});

test('a ₦100,000,000 balance and a very long name fit', async ({ page }) => {
  await signIn(page, 'rich');
  await page.goto('/dashboard/payouts');
  await expect(page.locator('#od-avail')).toContainText('100,000,000');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(1);
});

test('"Continue with Google" stays hidden while Google sign-in is off', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('[data-page="login"] [data-demo]')).toBeHidden();
});
