import { test, expect } from '@playwright/test';

async function login(page) {
    await page.goto('/login');
    await page.locator('[name=username]').fill('toan_lead');
    await page.locator('[name=password]').fill('Password@123');
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function invalidSubmit(page, form, values) {
    const action = await form.getAttribute('action');
    const token = await form.locator('[name=_token]').inputValue();
    const response = await page.request.post(action, {
        form: { _token: token, ...values }, maxRedirects: 0,
        headers: { Referer: page.url(), Accept: 'text/html' },
    });
    expect(response.status()).toBe(302);
    await page.goto(response.headers().location);
    await expect(page.getByRole('alert')).toContainText('Đã xảy ra lỗi kiểm tra dữ liệu');
}

for (const scenario of [
    { name: 'đường bay', path: '/admin/routes', summary: 'Thêm đường bay', field: '#route_code', values: { route_code: 'RV-INVALID', distance_km: '-1' } },
    { name: 'lịch bay', path: '/admin/schedules', summary: 'Thêm lịch bay', field: '#flight_number', values: { flight_number: 'RVINVALID' } },
    { name: 'dữ liệu chủ', path: '/admin/master-data', summary: 'Tạo dữ liệu chủ', field: '#code', values: { category: 'CABIN', code: 'RV-INVALID', payload_json: 'invalid JSON' } },
]) {
    test(`UI-VALIDATION: mở lại form ${scenario.name}, giữ dữ liệu và cho sửa trên mobile`, async ({ page }) => {
        await login(page);
        await page.goto(scenario.path);
        const details = page.locator('details').filter({ has: page.locator('summary', { hasText: scenario.summary }) });
        const summary = details.locator('summary');
        await expect(summary).toBeVisible();
        await summary.click();
        await expect(details.locator(scenario.field)).toBeVisible();
        await invalidSubmit(page, details.locator('form'), scenario.values);
        await expect(details).toHaveAttribute('open', '');
        const input = details.locator(scenario.field);
        await expect(input).toBeVisible();
        await expect(input).toHaveValue(scenario.values[scenario.field.slice(1)]);
        await page.setViewportSize({ width: 390, height: 844 });
        await input.fill('CORRECTED');
        await expect(input).toHaveValue('CORRECTED');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `${process.env.TEST_EVIDENCE_DIR || 'storage/app/browser-results'}/validation-${scenario.field.slice(1)}.png`, fullPage: true });
    });
}

test('UI-VALIDATION: mở toàn bộ lớp thu gọn chứa tài nguyên lỗi dù đã lưu tùy chọn đóng', async ({ page }) => {
    await login(page);
    await page.goto('/admin/airports/HAN');
    await page.evaluate(() => localStorage.setItem('smartAirline.infrastructure', 'closed'));
    await page.reload();
    const toggle = page.locator('[data-infrastructure-toggle]');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    const row = page.locator('tr[data-resource]').first();
    await row.locator('summary').click();
    await page.evaluate(() => localStorage.setItem('smartAirline.infrastructure', 'closed'));
    await invalidSubmit(page, row.locator('form').first(), { _method: 'PUT', resource_code: '', status: 'AVAILABLE' });
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(row.locator('details')).toHaveAttribute('open', '');
    await expect(row.locator('input[name=resource_code]')).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('smartAirline.infrastructure'))).toBe('closed');
});
