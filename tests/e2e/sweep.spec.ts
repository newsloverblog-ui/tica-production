import { expect, test } from '@playwright/test';
import { noSidewaysScroll, Role, shot, signIn, watch } from './helpers';

/**
 * Route × role sweep: every page opens for every role with no 5xx and no JS
 * error. Pages a role may not open must redirect or refuse (never 500).
 */
const ORGANISER = [
  '/dashboard', '/dashboard/events', '/dashboard/tickets', '/dashboard/orders', '/dashboard/payouts', '/dashboard/promote',
  '/dashboard/team', '/dashboard/settings', '/create', '/checkin',
  '/organizer/transaction', '/organizer/finance', '/organizer/kyc',
  '/organizer/support-tikcet/tickets', '/organizer/support-tikcet/create',
  // The rest of "More tools"
  '/organizer/event-booking/report', '/organizer/event/guest-list', '/organizer/event/registrations', '/organizer/forms',
  '/organizer/withdraw/exports', '/organizer/event-booking/exports', '/organizer/check-in-ledger/exports', '/organizer/pwa',
];
const ADMIN = [
  '/admin/dashboard', '/admin/event-builder', '/admin/transcation', '/admin/website?tab=home',
  '/admin/integrations', '/admin/integrations/paystack', '/admin/integrations/email',
  '/admin/integrations/sms', '/admin/integrations/google_login', '/admin/integrations/storage',
  '/admin/coupons',
  '/admin/organizer-management/registered-organizers', '/admin/organizer-management/add-organzer',
  '/admin/customer-management/registered-customer', '/admin/directory-exports',
  '/admin/organizer-management/kyc', '/admin/payout-accounts',
  '/admin/withdraw/payment-methods', '/admin/withdraw/withdraw-request',
  '/admin/payment-gateways/online-gateways',
  '/admin/basic-settings/mail-templates',
  '/admin/advertise/tica-promotions', '/admin/support/tickets',
];
const PUBLIC = ['/', '/discover', '/login', '/pricing'];

const plan: Record<Role, string[]> = {
  guest: [...PUBLIC, '/dashboard', '/organizer/finance', '/admin/dashboard'],
  owner: ORGANISER,
  rich: ['/dashboard', '/dashboard/payouts', '/organizer/finance', '/dashboard/settings'],
  manager: ORGANISER,
  finance: ORGANISER,
  gate: ['/checkin', '/scanner/workspace', '/dashboard', '/dashboard/payouts'],
  admin: ADMIN,
  customer: ['/account', '/account#orders', '/account#saved', '/account#profile', '/account#help', '/tickets'],
};

for (const [role, paths] of Object.entries(plan) as [Role, string[]][]) {
  test.describe(`${role}`, () => {
    test.beforeEach(async ({ page }) => { await signIn(page, role); });

    for (const path of paths) {
      test(`${path}`, async ({ page }) => {
        const problems = watch(page);
        const res = await page.goto(path, { waitUntil: 'networkidle' });
        expect(res?.status() ?? 0, `${role} ${path}`).toBeLessThan(500);
        if (role === 'guest' && path === '/') {
          await expect(page.locator('.h-photo.on[fetchpriority="high"]')).toHaveCount(1);
          await expect(page.locator('.h-photo[data-slide-src]')).not.toHaveCount(0);
          const hero = page.locator('.h-in h1');
          await expect(hero).toHaveCSS('font-family', /Montserrat/);
          await expect(hero).toHaveCSS('font-weight', '700');
          await expect(hero).toHaveCSS('font-size', test.info().project.name === 'desktop' ? '48px' : '32px');
          await expect(page.locator('.h-in > p').first()).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('.spot-body h2')).toHaveCSS('font-family', /Montserrat/);
          await expect(page.locator('.ft-nav h2').first()).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('#h-q')).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('#h-q')).toHaveCSS('font-size', '16px');
          await expect(page.locator('#h-q')).toHaveCSS('font-weight', '400');
          if (test.info().project.name === 'desktop') {
            const lines = await page.locator('.org-title--split > span').evaluateAll(spans => spans.map(span => {
              const range = document.createRange();
              range.selectNodeContents(span);
              return range.getClientRects().length;
            }));
            expect(lines).toEqual([1, 1]);
          }
          await expect(page.getByRole('button', { name: 'Search events', exact: true })).toBeVisible();
          const motion = await page.locator('#home-motion').boundingBox();
          const featured = await page.locator('.spot').boundingBox();
          if (motion && featured) expect(motion.y + motion.height).toBeLessThanOrEqual(featured.y);
          await page.keyboard.press('Tab');
          await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
          await page.keyboard.press('Enter');
          await expect(page.locator('main')).toBeFocused();
          const notice = page.getByRole('region', { name: 'Cookie choices' });
          await expect(notice).toBeVisible();
          const noticeBox = await notice.boundingBox();
          const heroBox = await page.locator('.h-hero').boundingBox();
          expect(noticeBox!.y + noticeBox!.height).toBeLessThanOrEqual(heroBox!.y + 1);
          expect(noticeBox!.height).toBeLessThan(150);
          await notice.getByRole('button', { name: 'Essential only' }).click();
          await expect(notice).toBeHidden();
          await page.reload();
          await expect(notice).toBeHidden();
        }
        if (role === 'admin' && path === '/admin/dashboard') {
          await expect(page.locator('.cd-top h1')).toHaveCSS('font-weight', '600');
          await expect(page.locator('.cd-card h2').first()).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('.cd-kpi-value').first()).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('.cd-kpi-value').first()).toHaveCSS('font-variant-numeric', 'tabular-nums');
          await expect(page.locator('.cd-kpi-value').first()).toHaveCSS('font-weight', '600');
        }
        if (role === 'admin' && path === '/admin/transcation') {
          const filter = page.locator('.transaction-filters input').first();
          await expect(filter).toHaveCSS('font-family', /Inter/);
          await expect(filter).toHaveCSS('font-size', '16px');
        }
        if (role === 'owner' && path === '/dashboard') {
          await expect(page.locator('#db-hi')).toHaveCSS('font-family', /Montserrat/);
          await expect(page.locator('.ov-summary__value').first()).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('#db-hi')).toHaveCSS('font-weight', '600');
          await expect(page.locator('.ov-card__title').first()).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('.ov-summary__value').first()).toHaveCSS('font-variant-numeric', 'tabular-nums');
        }
        if (role === 'owner' && path === '/organizer/finance') {
          await expect(page.locator('#fin-balance-h')).toHaveCSS('font-family', /Inter/);
          await expect(page.locator('#fin-balance-h')).toHaveCSS('font-variant-numeric', 'tabular-nums');
          await expect(page.locator('#fin-balance-h')).toHaveCSS('font-weight', '600');
        }
        if (role === 'guest' && path === '/discover' && test.info().project.name === 'mobile') {
          const filters = page.getByRole('button', { name: /^Filters/ });
          await filters.focus();
          await page.keyboard.press('Enter');
          const dialog = page.getByRole('dialog', { name: 'Filter events' });
          const close = dialog.getByRole('button', { name: 'Close filters' });
          await expect(close).toBeFocused();
          await page.keyboard.press('Shift+Tab');
          await expect(dialog.locator('#f-apply')).toBeFocused();
          await page.keyboard.press('Tab');
          await expect(close).toBeFocused();
          const country = dialog.getByRole('button', { name: /^Country/ });
          await country.click();
          await expect(dialog.getByRole('listbox', { name: 'Country' })).toBeVisible();
          await page.keyboard.press('Escape');
          await expect(country).toBeFocused();
          await page.keyboard.press('Escape');
          await expect(dialog).toBeHidden();
          await expect(filters).toBeFocused();
        }
        if (role === 'guest' && path === '/pricing') {
          await expect(page.getByRole('heading', { name: 'Ticketing fees for your event' })).toBeVisible();
          await expect(page.locator('#c1')).toHaveAttribute('aria-valuetext', /5,000/);
          await expect(page.locator('#c2')).toHaveAttribute('aria-valuetext', '200 tickets');
        }
        if (role === 'guest' && path === '/discover' && await page.locator('#cf-stage .cfs').count() > 1) {
          const featured = page.locator('#cf-stage .cfs[data-pos="c"]');
          await featured.focus();
          const previous = await featured.getAttribute('href');
          await page.keyboard.press('ArrowRight');
          await expect(featured).toBeFocused();
          await expect(featured).not.toHaveAttribute('href', previous!);
        }
        if (role === 'admin' && path === '/admin/event-builder') {
          await expect(page.locator('.tica-admin-event-builder')).toBeVisible();
          await expect(page.locator('#ce-admin')).toBeVisible();
          await expect(page.locator('#home-search')).toBeHidden();
          await page.locator('#c-name').fill('QA admin event');
          await page.locator('#ce-next').click();
          await expect(page.locator('#ce-progress')).toContainText('Step 2 of 5');
          const date = new Date();
          date.setUTCDate(date.getUTCDate() + 14);
          await page.locator('#c-date').fill(date.toISOString().slice(0, 10));
          await page.locator('#c-time').fill('18:00');
          await page.locator('#c-venue').fill('QA Test Hall');
          await page.locator('#c-city').fill('Lagos');
          await page.locator('#ce-next').click();
          await expect(page.locator('#ce-progress')).toContainText('Step 3 of 5');
          await page.locator('#c-price').fill('5000');
          const quoted = await page.evaluate(() => {
            const { TICA_DATA, TICA_MONEY } = window as any;
            const { feePct, feeFlat } = TICA_DATA.currency;
            return TICA_MONEY(5000 + 5000 * feePct / 100 + feeFlat);
          });
          await expect(page.locator('#ce-ex-b')).toContainText(`Buyer pays ${quoted}.`);
        }
        if (role === 'admin' && path === '/admin/website?tab=home') {
          const photos = page.locator('[data-rows="photos"] [data-row]');
          const nextPhoto = await photos.nth(1).locator('input[type="hidden"]').inputValue();
          await photos.nth(1).getByRole('button', { name: 'Use as background' }).click();
          await expect(photos.first().locator('input[type="hidden"]')).toHaveValue(nextPhoto);
          await expect(photos.first().getByText('Selected background')).toBeFocused();
        }
        if (role === 'admin' && path === '/admin/payment-gateways/online-gateways') {
          expect(res?.status()).toBe(200);
          await expect(page.locator('[name="flutterwave_public_key"]')).toBeVisible();
        }
        if (role === 'manager' && path === '/dashboard/orders') {
          await expect(page.getByRole('link', { name: 'Report', exact: true })).toHaveCount(0);
          await expect(page.getByRole('link', { name: 'Export history', exact: true })).toHaveCount(0);
        }
        if (role === 'manager' && path === '/organizer/pwa') {
          await expect(page.locator('#tica-main-content').getByRole('heading', { name: 'Gate devices', exact: true })).toBeVisible();
          await expect(page.locator('#scanner-staff')).toHaveCount(0);
        }
        if (role === 'finance' && path === '/organizer/event-booking/exports') {
          await expect(page.getByRole('link', { name: 'Registration export' })).toHaveCount(0);
          await expect(page.getByRole('button', { name: 'Create schedule' })).toHaveCount(0);
        }
        if (role === 'finance' && path === '/organizer/finance') {
          expect(res?.status()).toBe(200);
          const csvUrl = await page.locator('a[href*="file_type=csv"]').getAttribute('href');
          const csv = await page.request.get(csvUrl!);
          expect(csv.status()).toBe(200);
          expect(csv.headers()['content-type']).toContain('text/csv');
          expect(await csv.text()).toContain('transaction_id');
          const excelUrl = await page.locator('a[href*="file_type=xlsx"]').getAttribute('href');
          const excel = await page.request.get(excelUrl!);
          expect(excel.status()).toBe(200);
          expect((await excel.body()).subarray(0, 2).toString()).toBe('PK');
        }
        if (role === 'gate' && path === '/checkin') {
          await expect(page.getByRole('link', { name: 'Back to dashboard' })).toHaveCount(0);
          await expect(page.getByRole('button', { name: 'Log out', exact: true })).toBeVisible();
        }
        if (role === 'customer' && path === '/tickets') {
          await expect(page).toHaveURL(/\/account#tickets$/);
          await expect(page.locator('.acd-p[data-p="tickets"]')).toBeVisible();
        }
        await page.waitForTimeout(400);
        expect(problems, problems.join('\n')).toEqual([]);
        if (test.info().project.name === 'mobile') await noSidewaysScroll(page);
        await shot(page, `${role}${path.replace(/[^a-z0-9]+/gi, '-')}`);
      });
    }
  });
}

test('homepage cities rotate and keep the focused destination stable', async ({ page }) => {
  await page.clock.install();
  await page.goto('/');
  const city = page.locator('#home-cities .city').first();
  await city.scrollIntoViewIfNeeded();
  const initialDestination = await city.getAttribute('href');
  await expect(city).not.toHaveAttribute('href', initialDestination!, { timeout: 9000 });

  const destination = await city.getAttribute('href');
  const name = await city.locator('b').textContent();
  expect(new URL(destination!, page.url()).searchParams.get('city')).toBe(name);
  await city.focus();
  await page.clock.runFor(4200);
  await expect(city).toHaveAttribute('href', destination!);
});

test('homepage organiser gallery cycles through six photos and honours motion preferences', async ({ page }) => {
  await page.clock.install();
  await page.goto('/');
  const gallery = page.locator('.event-reel');
  const active = gallery.locator('.event-reel-slide.is-active');
  await expect(gallery.locator('img[src]')).toHaveCount(1);
  await gallery.scrollIntoViewIfNeeded();
  await expect(active).toHaveJSProperty('naturalWidth', 1200);
  const photos = ['culture', 'dance', 'seminar', 'games', 'party', 'rave', 'culture'];

  for (const [index, photo] of photos.entries()) {
    if (index > 0) await page.clock.fastForward(5100);
    await expect(active).toHaveAttribute('src', `/assets/tica-ui/img/organiser-gallery/${photo}.webp`);
    await expect(active).toHaveJSProperty('naturalWidth', 1200);
  }

  const pause = page.getByRole('button', { name: 'Pause animations' });
  await pause.click();
  await expect(pause).toHaveAttribute('aria-pressed', 'true');
  await gallery.scrollIntoViewIfNeeded();
  await page.clock.fastForward(10000);
  await expect(active).toHaveAttribute('src', /culture\.webp$/);

  await pause.click();
  await expect(pause).toHaveAttribute('aria-pressed', 'false');
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.reload();
  await expect(pause).toBeHidden();
  await gallery.scrollIntoViewIfNeeded();
  await page.clock.fastForward(10000);
  await expect(active).toHaveAttribute('src', /culture\.webp$/);
});

test('checkout fits an intact naira total at 320px', async ({ page }) => {
  await page.goto('/');
  const eventPath = await page.evaluate(() => {
    type Event = { slug: string; sold: boolean; closed: boolean; tiers: [string, number, number][] };
    const events = (window as Window & { TICA_EVENTS?: Event[] }).TICA_EVENTS;
    return events?.find(event => !event.sold && !event.closed && event.tiers.some(tier => tier[2] !== 0))?.slug;
  });
  expect(eventPath, 'QA must provide a bookable event').toBeTruthy();
  await page.setViewportSize({ width: 320, height: 844 });
  await page.goto('/checkout/' + eventPath);

  const checkout = page.locator('#checkout-view');
  await expect(checkout.locator('h1')).toHaveCSS('font-family', /Montserrat/);
  const total = checkout.locator('.sumtotal > :last-child');
  await expect(total).toHaveCSS('font-family', /Inter/);
  await expect(total).toHaveCSS('font-weight', '600');
  await expect(total).toHaveCSS('font-variant-numeric', 'tabular-nums');
  const lines = await total.evaluate(async amount => {
    amount.textContent = '₦1,234,567,890,123.45';
    await document.fonts.ready;
    return [amount.previousElementSibling!, amount].map(element => {
      const range = document.createRange();
      range.selectNodeContents(element);
      return range.getClientRects().length;
    });
  });
  expect(lines).toEqual([1, 1]);
  await noSidewaysScroll(page);
});
