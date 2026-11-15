import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { noSidewaysScroll, signIn, watch } from './helpers';

test.describe('edge cases (owner)', () => {
  test.beforeEach(async ({ page }) => { await signIn(page, 'owner'); });

  test('publishing an event returns a real event and shows a durable success confirmation', async ({ page }) => {
    const problems = watch(page);
    const name = `QA publish ${Date.now()}`;
    const future = new Date();
    future.setDate(future.getDate() + 14);
    const date = future.toISOString().slice(0, 10);

    await page.addInitScript(() => {
      localStorage.setItem('tica_consent_v1', JSON.stringify({ v: 1, at: new Date().toISOString(), p: false, a: false, m: false }));
    });
    await page.goto('/create', { waitUntil: 'networkidle' });
    await page.fill('#c-name', name);
    await page.fill('#c-date', date);
    await page.fill('#c-time', '18:00');
    await page.click('#ce-next');

    await page.fill('#c-venue', 'QA Test Hall');
    await page.fill('#c-city', 'Lagos');
    await page.click('#ce-next');

    await page.fill('#c-price', '0');
    await page.fill('#c-qty', '20');
    await page.click('#ce-next');

    await page.setInputFiles('#c-flyer', {
      name: 'qa-event.png',
      mimeType: 'image/png',
      buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z7V8AAAAASUVORK5CYII=', 'base64'),
    });
    await page.click('#ce-next');

    const responsePromise = page.waitForResponse((response) => response.url().endsWith('/tica-api/events') && response.request().method() === 'POST');
    await page.click('#ce-publish');
    const response = await responsePromise;
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.event_id).toBeTruthy();
    expect(body.name).toBe(name);
    await expect(page.locator('#ce-success')).toBeVisible();
    await expect(page.locator('#ce-success-title')).toContainText(name);
    await expect(page.locator('#ce-success-manage')).toHaveAttribute('href', /\/dashboard\/events/);
    expect(problems).toEqual([]);
  });

  test('event action menu escapes the scrolling event card and stays on screen', async ({ page }) => {
    await page.goto('/dashboard/events');
    await page.evaluate(() => {
      const dashboard = (window as any).TICA_DATA.dashboard;
      dashboard.events = [{
        id: 999,
        name: 'QA action menu event',
        state: 'up',
        status: 'On sale',
        ts: Date.now() + 86_400_000,
        day: '16',
        mon: 'Nov',
        time: '6:00 PM',
        city: 'Lagos',
        venue: 'QA venue',
        mode: 'In person',
        payer: 'buyer',
        regMode: 'ticket',
        sold: 2,
        cap: 50,
        revenue: 5000,
        cur: { code: 'NGN', symbol: '₦', position: 'left' },
        slug: 'qa-action-menu-event/999',
        editUrl: '/create?event=999',
        registrationUrl: '',
        art: '',
      }];
    });
    await page.locator('#ev-q').fill('QA action menu event');
    const trigger = page.locator('#od-evlist .t-menu summary').first();
    await expect(trigger).toBeVisible();
    await trigger.click();

    const menu = page.locator('body > .t-menu__list.is-floating');
    await expect(menu).toBeVisible();
    await expect(menu.getByText('Manage sales and settings')).toBeVisible();
    const box = await menu.boundingBox();
    const viewport = page.viewportSize();
    expect(box).not.toBeNull();
    expect(viewport).not.toBeNull();
    expect(box!.x).toBeGreaterThanOrEqual(8);
    expect(box!.y).toBeGreaterThanOrEqual(8);
    expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width - 8);
    expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height - 8);

    await page.keyboard.press('Escape');
    await expect(menu).toHaveCount(0);
    await expect(trigger).toBeFocused();
  });

  test('a setting saves once on double-click and survives a reload', async ({ page }) => {
    await page.goto('/dashboard/settings');
    const sw = page.locator('[data-pref="notify_sale"]');
    const before = await sw.isChecked();
    const saves: number[] = [];
    page.on('request', (r) => { if (r.url().includes('/tica-api/organizer/prefs')) saves.push(1); });
    await sw.dblclick();
    await page.waitForTimeout(1500);
    expect(saves.length, 'each click saves at most once').toBeLessThanOrEqual(2);
    await page.reload();
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

  test('door sales is one responsive workflow with direct navigation', async ({ page }) => {
    const problems = watch(page);
    await page.goto('/organizer/operations/door-sales', { waitUntil: 'networkidle' });

    await expect(page.getByRole('heading', { name: 'Sell at the door' })).toBeVisible();
    await expect(page.locator('.door-step')).toHaveCount(3);
    await expect(page.locator('.door-receipt')).toBeVisible();
    await expect(page.locator('.tsh-link[href="/organizer/operations/door-sales"]')).toHaveCount(0);
    await expect(page.locator('.tsh-more')).not.toHaveAttribute('open', '');
    await noSidewaysScroll(page);
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
