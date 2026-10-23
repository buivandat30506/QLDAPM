import { expect } from '@playwright/test';

export async function openSidebar(page) {
    const toggle = page.locator('[data-sidebar-toggle]');
    if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
    await expect(page.locator('#sidebarMenu')).toHaveAttribute('aria-hidden', 'false');
}

export async function openDetails(page, title) {
    const summary = page.locator('summary').filter({ hasText: title });
    const details = summary.locator('..');
    if (await details.getAttribute('open') === null) await summary.click();
    await expect(details).toHaveAttribute('open', '');
}
