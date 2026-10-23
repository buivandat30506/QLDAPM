import { test, expect } from '@playwright/test';
import { openSidebar, openDetails } from './ui.js';

const evidence = process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18-fleet';
async function login(page, username = 'fleet_demo') {
    await page.goto('/login');
    await page.locator('[name="username"]').fill(username);
    await page.locator('[name="password"]').fill('Demo@12345');
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}
async function headers(page) {
    const response = await page.request.post('/api/v1/auth/login', { data: { username: 'fleet_demo', password: 'Demo@12345' } });
    expect(response.status()).toBe(200);
    return { Authorization: 'Bearer ' + (await response.json()).data.token, Accept: 'application/json' };
}
test('UAT-UC10: hồ sơ, phiên bản cấu hình ghế, trạng thái và giao diện mobile lưu DB thật', async ({ page }) => {
    await login(page);
    await openSidebar(page); await page.getByRole('link', { name: 'Quản lý máy bay', exact: true }).click();
    await page.getByLabel('Số đăng ký', { exact: true }).last().fill('VN-BF01');
    await page.getByLabel('Năm sản xuất', { exact: true }).fill('2021');
    await page.getByLabel('Sân bay cơ sở', { exact: true }).selectOption('HAN');
    await page.getByRole('button', { name: 'Lưu máy bay', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Máy bay VN-BF01', exact: true })).toBeVisible();
    const detail = page.url();
    await openDetails(page, 'Công bố cấu hình ghế mới');
    const form = page.getByRole('form', { name: 'Cấu hình ghế', exact: true });
    await form.getByLabel('Tên cấu hình', { exact: true }).fill('Cấu hình thử nghiệm 1');
    await form.locator('#section_0_from').fill('1'); await form.locator('#section_0_to').fill('2');
    await form.locator('#section_0_columns').fill('AB');
    await form.getByLabel('Ghế khóa (cách bằng dấu phẩy)', { exact: true }).fill('2B');
    await form.getByLabel('Lý do / hồ sơ cấu hình', { exact: true }).fill('Cấu hình tổng hợp dùng cho UAT');
    await form.getByRole('button', { name: 'Công bố cấu hình', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Đã công bố phiên bản');
    await expect(page.getByLabel('Sơ đồ ghế', { exact: true })).toContainText('1A');
    await expect(page.getByLabel('Sơ đồ ghế', { exact: true }).getByText('2B', { exact: true })).toHaveClass(/bg-secondary/);
    await page.reload();
    await expect(page.locator('main')).toContainText('4 ghế vật lý / 3 ghế sử dụng');
    await openDetails(page, 'Công bố cấu hình ghế mới');
    await form.getByLabel('Tên cấu hình', { exact: true }).fill('Cấu hình thử nghiệm 2');
    await form.locator('#section_0_from').fill('1'); await form.locator('#section_0_to').fill('2');
    await form.locator('#section_0_columns').fill('AB');
    await form.getByLabel('Lý do / hồ sơ cấu hình', { exact: true }).fill('Mở lại ghế cho cấu hình mới');
    await form.getByRole('button', { name: 'Công bố cấu hình', exact: true }).click();
    await expect(page.locator('main')).toContainText('4 ghế vật lý / 4 ghế sử dụng');
    await openDetails(page, 'Lịch sử cấu hình được lưu nguyên trạng');
    await expect(page.getByRole('table', { name: 'Lịch sử cấu hình', exact: true })).toContainText('Cấu hình thử nghiệm 1');
    await expect(page.getByRole('table', { name: 'Lịch sử cấu hình', exact: true })).toContainText('Cấu hình thử nghiệm 2');
    await openDetails(page, 'Cập nhật hồ sơ');
    await page.getByLabel('Trạng thái khai thác', { exact: true }).selectOption('GROUNDED');
    await page.getByLabel('Lý do cập nhật (bắt buộc khi đổi trạng thái)', { exact: true }).fill('Ngừng khai thác trong UAT');
    await page.getByRole('button', { name: 'Lưu hồ sơ', exact: true }).click();
    await page.reload(); await openDetails(page, 'Cập nhật hồ sơ'); await expect(page.getByLabel('Trạng thái khai thác', { exact: true })).toHaveValue('GROUNDED');
    const auth = await headers(page);
    const response = await page.request.get('/api/v1/core/aircrafts/VN-BF01/configurations', { headers: auth });
    expect(response.status()).toBe(200); const configs = (await response.json()).data.data;
    expect(configs.map(c => c.version)).toEqual([2, 1]); expect(configs.map(c => c.sellable_capacity)).toEqual([4, 3]);
    await page.request.post('/api/v1/auth/logout', { headers: auth });
    await page.setViewportSize({ width: 390, height: 844 });
    const mobileWidth = await page.evaluate(() => new Promise(resolve => {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            const offenders = [...document.body.querySelectorAll('*')].map(element => {
                const rect = element.getBoundingClientRect();
                return { tag: element.tagName, id: element.id, className: typeof element.className === 'string' ? element.className : '', left: Math.round(rect.left), right: Math.round(rect.right), width: Math.round(rect.width) };
            }).filter(element => element.right > innerWidth + 1 || element.left < -1).slice(0, 20);
            resolve({ viewport: innerWidth, document: document.documentElement.scrollWidth, body: document.body.scrollWidth, offenders });
        }));
    }));
    expect(mobileWidth.document <= mobileWidth.viewport, JSON.stringify(mobileWidth)).toBe(true);
    expect(await page.locator('[id]').evaluateAll(elements => {
        const ids = elements.map(e => e.id); return ids.filter((id, i) => ids.indexOf(id) !== i);
    })).toEqual([]);
    await page.screenshot({ path: evidence + '/uc10-fleet-mobile.png', fullPage: true });
    await page.goto('/admin/aircrafts'); await page.getByLabel('Số đăng ký', { exact: true }).first().fill('VN-BF01');
    await page.getByRole('button', { name: 'Lọc máy bay', exact: true }).click();
    await expect(page.locator('tbody')).toContainText('Ngừng khai thác');
    await page.getByRole('link', { name: 'VN-BF01', exact: true }).click(); expect(page.url()).toBe(detail);
});

test('UAT-UC11: lịch sử sau hoàn thành chuyến, lọc và gửi lại sự kiện không cộng lặp', async ({ page }) => {
    await login(page); const auth = await headers(page);
    const usageResponse = await page.request.get('/api/v1/core/aircrafts/VN-D103/usage', { headers: auth });
    expect(usageResponse.status()).toBe(200); const record = (await usageResponse.json()).data.data[0]; expect(record).toBeTruthy();
    const endpoint = '/api/v1/core/aircrafts/VN-D103';
    const before = (await (await page.request.get(endpoint, { headers: auth })).json()).data;
    for (let i = 0; i < 2; i++) {
        expect((await page.request.post(endpoint + '/usage', { headers: auth, data: { flight_id: record.flight_id, source: 'DEMO', external_id: 'browser-replay-usage' } })).status()).toBe(200);
    }
    const after = (await (await page.request.get(endpoint, { headers: auth })).json()).data;
    expect(after.total_flight_hours).toBe(before.total_flight_hours); expect(after.total_flight_cycles).toBe(before.total_flight_cycles);
    await page.goto('/admin/aircrafts/VN-D103');
    await page.getByLabel('Nguồn', { exact: true }).selectOption('OPERATIONS');
    await page.getByRole('button', { name: 'Lọc lịch sử', exact: true }).click();
    await expect(page.getByRole('table', { name: 'Sổ sử dụng', exact: true })).toContainText('DM105');
    await expect(page.getByRole('table', { name: 'Sổ sử dụng', exact: true })).toContainText('arrival:' + record.flight_id);
    await page.getByLabel('Nguồn', { exact: true }).selectOption('FIDS');
    await page.getByRole('button', { name: 'Lọc lịch sử', exact: true }).click();
    await expect(page.getByText('Chưa có số liệu chuyến bay trong bộ lọc.', { exact: true })).toBeVisible();
    await page.screenshot({ path: evidence + '/uc11-usage-filter.png', fullPage: true });
    await page.request.post('/api/v1/auth/logout', { headers: auth });
    await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click(); await login(page, 'data_demo');
    await page.goto('/admin/aircrafts/VN-D103');
    await expect(page.getByRole('button', { name: 'Lưu hồ sơ', exact: true })).toHaveCount(0);
    const denied = await page.request.put(endpoint, { data: { manufacture_year: 2020 }, headers: { Accept: 'application/json' } });
    expect([401, 403]).toContain(denied.status());
});
