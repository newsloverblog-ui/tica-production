import { expect, test } from '@playwright/test';
import { noSidewaysScroll, Role, shot, signIn, watch } from './helpers';

/**
 * Route × role sweep: every page opens for every role with no 5xx and no JS
 * error. Pages a role may not open must redirect or refuse (never 500).
 */
const ORGANISER = [
  '/dashboard', '/dashboard/events', '/dashboard/orders', '/dashboard/payouts', '/dashboard/promote',
  '/dashboard/team', '/dashboard/settings', '/create', '/checkin',
  '/organizer/transaction', '/organizer/finance', '/organizer/kyc',
  '/organizer/support-tikcet/tickets', '/organizer/support-tikcet/create',
];
const ADMIN = ['/admin/dashboard', '/admin/organizer-management/kyc', '/admin/payout-accounts', '/admin/withdraw/withdraw-request', '/admin/advertise/tica-promotions', '/admin/support/tickets'];
const PUBLIC = ['/', '/discover', '/login', '/pricing'];

const plan: Record<Role, string[]> = {
  guest: [...PUBLIC, '/dashboard', '/organizer/finance', '/admin/dashboard'],
  owner: ORGANISER,
  rich: ['/dashboard', '/dashboard/payouts', '/organizer/finance', '/dashboard/settings'],
  manager: ORGANISER,
  finance: ORGANISER,
  gate: ['/checkin', '/dashboard', '/dashboard/payouts'],
  admin: ADMIN,
};

for (const [role, paths] of Object.entries(plan) as [Role, string[]][]) {
  test.describe(`${role}`, () => {
    test.beforeEach(async ({ page }) => { await signIn(page, role); });

    for (const path of paths) {
      test(`${path}`, async ({ page }) => {
        const problems = watch(page);
        const res = await page.goto(path, { waitUntil: 'networkidle' });
        expect(res?.status() ?? 0, `${role} ${path}`).toBeLessThan(500);
        await page.waitForTimeout(400);
        expect(problems, problems.join('\n')).toEqual([]);
        if (test.info().project.name === 'mobile') await noSidewaysScroll(page);
        await shot(page, `${role}${path.replace(/[^a-z0-9]+/gi, '-')}`);
      });
    }
  });
}
