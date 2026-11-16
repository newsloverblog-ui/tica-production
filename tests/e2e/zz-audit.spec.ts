import { test } from '@playwright/test';

const PAGES = ['/', '/discover', '/how', '/pricing', '/about', '/contact', '/login', '/signup', '/forgot', '/join', '/policies'];

for (const path of PAGES) {
  test(`audit ${path}`, async ({ page }, info) => {
    await page.goto(path, { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: '.ck-banner,[data-consent],#cookie-consent,.cookie-banner{display:none!important}' }).catch(() => {});
    await page.waitForTimeout(700);
    const name = (path === '/' ? 'home' : path.slice(1).replace(/\//g, '-')) + '-' + info.project.name;
    await page.screenshot({ path: `tests/screenshots/pub-${name}.png`, fullPage: true });
  });
}

test('audit event page', async ({ page }, info) => {
  await page.goto('/discover', { waitUntil: 'networkidle' });
  const link = page.locator('a[href*="/event/"]').first();
  if (await link.count()) {
    await page.goto(await link.getAttribute('href') as string, { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);
    await page.screenshot({ path: `tests/screenshots/pub-event-${info.project.name}.png`, fullPage: true });
  }
});
