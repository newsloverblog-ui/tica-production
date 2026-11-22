import { expect, test } from '@playwright/test';
import { shot, signIn, watch } from './helpers';

/**
 * Promote: each tool card opens its side panel, the featured placement walks
 * through its steps without paying, and the admin review page renders.
 */
test.describe('promote panels', () => {
  test('organiser opens every promotion panel', async ({ page }) => {
    await signIn(page, 'owner');
    const problems = watch(page);
    await page.goto('/dashboard/promote', { waitUntil: 'networkidle' });
    await expect(page.locator('.pv-head h1')).toHaveText('Promote your event');
    await page.waitForTimeout(1000);
    await shot(page, 'promote-page');

    await page.click('[data-prnew="choose"].pv-create');
    await expect(page.locator('#pr-choose-dlg')).toBeVisible();
    await page.waitForTimeout(450);
    await shot(page, 'promote-choose');
    await page.keyboard.press('Escape');

    await page.click('.pv-tool--code');
    await expect(page.locator('#pr-code-dlg')).toBeVisible();
    await page.fill('#od-cn', 'EARLY10');
    await page.waitForTimeout(450);
    await shot(page, 'promote-code');
    await page.keyboard.press('Escape');

    await page.click('.pv-tool--link');
    await expect(page.locator('#pr-link-dlg')).toBeVisible();
    await page.click('[data-chan="Instagram"]');
    await page.waitForTimeout(450);
    await shot(page, 'promote-link');
    await page.keyboard.press('Escape');

    await page.click('.pv-tool--feat');
    await expect(page.locator('#pr-feat-dlg')).toBeVisible();
    await page.waitForTimeout(450);
    await shot(page, 'promote-featured-1');
    if (await page.locator('[data-plan]').count()) {
      await page.click('#pr-f-next');
      await page.waitForTimeout(400);
      await shot(page, 'promote-featured-2');
      if (await page.locator('#od-promo-event option[value=""]').count() === 0) {
        await page.click('#pr-f-next');
        await page.waitForTimeout(400);
        await shot(page, 'promote-featured-3');
      }
    }
    await page.keyboard.press('Escape');
    expect(problems, problems.join('\n')).toEqual([]);
  });

  test('admin review page shows the queue, placements and offers', async ({ page }) => {
    await signIn(page, 'admin');
    const problems = watch(page);
    for (const section of ['queue', 'placements', 'plans']) {
      const res = await page.goto(`/admin/advertise/tica-promotions?section=${section}`, { waitUntil: 'networkidle' });
      expect(res?.status() ?? 0).toBeLessThan(500);
      if (section === 'plans' && await page.locator('.plan-card').count()) {
        const firstPlan = page.locator('.plan-card').first();
        await expect(firstPlan).not.toHaveAttribute('open', '');
        await firstPlan.locator('.plan-card__summary').click();
        await expect(firstPlan.locator('.plan-card__editor')).toBeVisible();
        await firstPlan.locator('.plan-card__summary').click();
        await expect(firstPlan.locator('.plan-card__editor')).not.toBeVisible();
      }
      await page.waitForTimeout(500);
      await shot(page, `admin-promotions-${section}`);
    }
    expect(problems, problems.join('\n')).toEqual([]);
  });
});
