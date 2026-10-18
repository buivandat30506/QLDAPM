import { test, expect } from '@playwright/test';

async function login(page, username, password) {
    await page.goto('/login');
    await page.locator('input[name="username"]').fill(username);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
}

test('UAT-UC45-G1: tạo tài khoản, gán CUSTOMER/self, đăng nhập và bị từ chối audit', async ({ page }) => {
    const username = 'uat_customer_' + Date.now();
    await login(page, 'toan_lead', 'Password@123');
    await expect(page).toHaveURL(/administration\/users$/);
    await page.getByLabel('Tên đăng nhập', { exact: true }).last().fill(username);
    await page.getByLabel('Họ tên', { exact: true }).fill('Hành khách mô phỏng UAT');
    await page.getByLabel('Email', { exact: true }).fill(username + '@example.test');
    await page.getByLabel('Mật khẩu ban đầu (ít nhất 12 ký tự)').fill('SyntheticUat12345');
    await page.getByRole('button', { name: 'Tạo tài khoản', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Đã lưu tài khoản');
    await page.getByRole('button', { name: 'Thêm vai trò và phạm vi' }).click();
    await page.getByLabel('Vai trò', { exact: true }).selectOption({ label: 'Hành khách' });
    await page.getByLabel('Loại phạm vi', { exact: true }).first().selectOption('self');
    await page.getByRole('button', { name: 'Xác nhận lưu quyền' }).click();
    await expect(page.getByRole('status')).toContainText('Đã lưu tài khoản');
    await expect(page.getByLabel('Vai trò', { exact: true })).toHaveValue(/\d+/);
    await expect(page.getByLabel('Loại phạm vi', { exact: true }).first()).toHaveValue('self');
    await page.screenshot({ path: (process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18') + '/uc45-granted.png', fullPage: true });
    await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
    await login(page, username, 'SyntheticUat12345');
    await expect(page).toHaveURL(/\/account$/);
    await expect(page.getByRole('link', { name: 'Nhật ký', exact: true })).toHaveCount(0);
    const denied = await page.goto('/administration/audit');
    expect(denied.status()).toBe(403);
});

test('UAT-UC46-G1: lọc nhật ký, xuất CSV; CSRF chặn POST thiếu token', async ({ page }) => {
    await login(page, 'toan_lead', 'Password@123');
    await page.getByRole('link', { name: 'Nhật ký', exact: true }).click();
    await page.getByLabel('Hành động', { exact: true }).fill('LOGIN_SUCCEEDED');
    await page.getByRole('button', { name: 'Lọc nhật ký' }).click();
    await expect(page.locator('tbody')).toContainText('LOGIN_SUCCEEDED');
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Xuất CSV đã lọc' }).click();
    expect((await download).suggestedFilename()).toBe('audit.csv');
    const response = await page.request.post('/administration/users', { form: { full_name: 'Rejected' } });
    expect(response.status()).toBe(419);
    await page.screenshot({ path: (process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18') + '/uc46-audit.png', fullPage: true });
});
