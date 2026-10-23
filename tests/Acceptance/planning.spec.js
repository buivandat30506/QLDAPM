import { test, expect } from '@playwright/test';
import { openSidebar } from './ui.js';

const evidence = process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18-planning';
async function login(page, username = 'toan_lead') {
    await page.goto('/login');
    await page.locator('[name="username"]').fill(username);
    await page.locator('[name="password"]').fill(username === 'toan_lead' ? 'Password@123' : 'Demo@12345');
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function apiHeaders(page, username = 'toan_lead') {
    const response = await page.request.post('/api/v1/auth/login', { data: { username, password: username === 'toan_lead' ? 'Password@123' : 'Demo@12345' } });
    expect(response.status()).toBe(200);
    return { Authorization: 'Bearer ' + (await response.json()).data.token, Accept: 'application/json' };
}

test('UAT-UC04: tạo, sửa mùa/hạn chế, tìm và xóa đường bay qua giao diện', async ({ page }) => {
    await login(page, 'data_demo');
    await page.goto('/admin/routes');
    await page.getByText('Thêm đường bay', { exact: true }).click();
    await page.getByLabel('Mã đường bay', { exact: true }).fill('UAT-PQC-HAN');
    await page.getByLabel('Sân bay đi', { exact: true }).selectOption('PQC');
    await page.getByLabel('Sân bay đến', { exact: true }).selectOption('HAN');
    await page.getByLabel('Khoảng cách (km)', { exact: true }).fill('1250');
    await page.getByLabel('Thời gian chuẩn (phút)', { exact: true }).fill('130');
    await page.getByLabel('Mùa khai thác từ ngày', { exact: true }).fill('2026-11-01');
    await page.getByLabel('Mùa khai thác đến ngày', { exact: true }).fill('2027-03-31');
    await page.getByLabel('Ngày hạn chế khai thác', { exact: true }).selectOption(['7']);
    await page.getByLabel('Ghi chú hạn chế', { exact: true }).fill('Hạn chế chủ nhật theo dữ liệu thử nghiệm');
    await page.getByRole('button', { name: 'Tạo đường bay', exact: true }).click();
    await page.getByLabel('Tìm mã đường bay', { exact: true }).fill('UAT-PQC-HAN');
    await page.getByRole('button', { name: 'Lọc đường bay', exact: true }).click();
    await page.locator('tr').filter({ hasText: 'UAT-PQC-HAN' }).getByRole('link', { name: 'Chi tiết', exact: true }).click();
    await expect(page.getByLabel('Ngày hạn chế khai thác', { exact: true })).toHaveValues(['7']);
    await page.getByLabel('Trạng thái đường bay', { exact: true }).selectOption('SUSPENDED');
    await page.getByLabel('Ghi chú hạn chế', { exact: true }).fill('Tạm ngừng theo mùa thử nghiệm');
    await page.getByRole('button', { name: 'Lưu đường bay', exact: true }).click();
    await page.getByLabel('Tìm mã đường bay', { exact: true }).fill('UAT-PQC-HAN');
    await page.getByRole('button', { name: 'Lọc đường bay', exact: true }).click();
    const row = page.locator('tr').filter({ hasText: 'UAT-PQC-HAN' });
    await expect(row).toContainText('Tạm ngừng');
    await row.getByRole('link', { name: 'Chi tiết', exact: true }).click();
    await expect(page.getByLabel('Ghi chú hạn chế', { exact: true })).toHaveValue('Tạm ngừng theo mùa thử nghiệm');
    await expect(page.locator('tbody')).toContainText('UPDATE');
    await page.screenshot({ path: evidence + '/uc04-route-season.png', fullPage: true });
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Xóa đường bay', exact: true }).click();
    await expect(page.locator('tbody')).not.toContainText('UAT-PQC-HAN');
});

test('UAT-UC05-06: lịch lặp, báo cáo, phê duyệt, công bố và phiên bản sửa đổi', async ({ page }) => {
    await login(page);
    const headers = await apiHeaders(page);
    const aircraft = (await (await page.request.get('/api/v1/core/aircrafts', { headers })).json()).data.data.find(item => item.tail_number === 'VN-D104');
    expect((await page.request.post('/api/v1/core/aircrafts', { headers, data: { tail_number: 'VN-BP01', model_id: aircraft.model_id,
        manufacture_year: 2020, total_flight_hours: 0, total_flight_cycles: 0, operational_status: 'OPERATIONAL' } })).status()).toBe(201);
    const gate = await page.request.post('/api/v1/core/airports/HAN/resources', { headers, data: { resource_type: 'GATE', resource_code: 'BP-GATE', status: 'AVAILABLE' } });
    expect(gate.status()).toBe(201); const gateId = (await gate.json()).data.id;
    const routes = (await (await page.request.get('/api/v1/core/routes?search=HAN-DAD', { headers })).json()).data.data;
    expect((await page.request.post('/api/v1/auth/logout', { headers })).status()).toBe(200);
    await page.goto('/admin/workflows');
    await openSidebar(page); await page.getByRole('link', { name: 'Quản lý chuyến bay', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/schedules$/);
    expect(await page.locator('[id]').evaluateAll(elements => {
        const ids = elements.map(element => element.id);
        return ids.filter((id, index) => ids.indexOf(id) !== index);
    })).toEqual([]);
    await page.getByText('Thêm lịch bay', { exact: true }).click();
    await page.getByLabel('Số hiệu', { exact: true }).fill('BP901');
    await page.getByLabel('Mã mùa lịch', { exact: true }).fill('UAT-W26');
    await page.getByLabel('Đường bay', { exact: true }).selectOption(String(routes[0].id));
    await page.getByLabel('Dòng máy bay', { exact: true }).selectOption(String(aircraft.model_id));
    await page.getByLabel('Máy bay', { exact: true }).selectOption('VN-BP01');
    await page.getByLabel('Cổng ra máy bay', { exact: true }).selectOption(String(gateId));
    await page.getByLabel('Hiệu lực từ ngày', { exact: true }).fill('2026-12-14');
    await page.getByLabel('Hiệu lực đến ngày', { exact: true }).fill('2026-12-28');
    await page.getByLabel('Giờ đi UTC', { exact: true }).fill('11:00:00');
    await page.getByLabel('Giờ đến UTC', { exact: true }).fill('13:00:00');
    await page.getByLabel('Ngày trong tuần', { exact: true }).fill('1');
    await page.getByLabel('Ngày loại trừ', { exact: true }).fill('2026-12-21');
    await page.getByLabel('Ngày khai thác bổ sung', { exact: true }).fill('2026-12-22');
    await page.getByRole('button', { name: 'Lưu lịch nháp', exact: true }).click();
    const original = page.url(); await expect(page.locator('[data-status="DRAFT"]')).toBeVisible();
    await expect(page.locator('dl')).toContainText('UAT-W26');
    await page.reload(); await expect(page.locator('dl')).toContainText('UAT-W26');
    await page.getByRole('button', { name: 'Kiểm tra xung đột', exact: true }).click();
    await expect(page.locator('summary')).toContainText(['3 lần khai thác']);
    for (const name of ['Gửi duyệt', 'Phê duyệt lịch', 'Công bố lịch']) { await page.getByRole('button', { name, exact: true }).click(); }
    await expect(page.locator('[data-status="PUBLISHED"]')).toBeVisible();
    await page.getByLabel('Lý do tạo phiên bản mới', { exact: true }).fill('Điều chỉnh tên mùa sau công bố');
    await page.getByRole('button', { name: 'Tạo bản sửa đổi', exact: true }).click();
    await expect(page).not.toHaveURL(original); const revision = page.url();
    await page.goto(original); await expect(page.locator('[data-status="PUBLISHED"]')).toBeVisible();
    await page.goto(revision);
    await page.getByText('Sửa lịch nháp', { exact: true }).click();
    await page.getByLabel('Mã mùa lịch', { exact: true }).fill('UAT-W26-V2');
    await page.getByRole('button', { name: 'Lưu thay đổi lịch', exact: true }).click();
    for (const name of ['Gửi duyệt', 'Phê duyệt lịch', 'Công bố lịch']) { await page.getByRole('button', { name, exact: true }).click(); }
    await expect(page.locator('[data-status="PUBLISHED"]')).toBeVisible();
    await expect(page.getByText('Phiên bản công bố 2', { exact: false })).toBeVisible();
    await expect(page.getByText('Phiên bản 2', { exact: false })).toBeVisible();
    await page.screenshot({ path: evidence + '/uc05-06-published-v2.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('heading', { name: 'Lịch bay BP901 — HAN-DAD' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: evidence + '/uc05-mobile.png', fullPage: true });
    await page.goto(original); await expect(page.locator('[data-status="SUPERSEDED"]')).toBeVisible();
});

test('UAT-UC06-EX: thiếu nguồn lực giữ nháp, data admin bị chặn thao tác lịch', async ({ page }) => {
    await login(page, 'ops_demo');
    const headers = await apiHeaders(page, 'ops_demo');
    const schedules = (await (await page.request.get('/api/v1/core/schedules?search=DM901', { headers })).json()).data.data;
    expect((await page.request.post('/api/v1/auth/logout', { headers })).status()).toBe(200);
    const id = schedules[0].id; await page.goto('/admin/schedules/' + id);
    await page.getByRole('button', { name: 'Kiểm tra xung đột', exact: true }).click();
    await expect(page.getByText('Có lỗi / chưa kiểm tra đầy đủ', { exact: false })).toBeVisible();
    await page.getByRole('button', { name: 'Gửi duyệt', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Thiếu máy bay');
    await expect(page.locator('[data-status="DRAFT"]')).toBeVisible();
    await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
    await login(page, 'data_demo');
    await page.goto('/admin/schedules/' + id);
    await expect(page.getByRole('button', { name: 'Gửi duyệt', exact: true })).toHaveCount(0);
    const deniedHeaders = await apiHeaders(page, 'data_demo');
    expect((await page.request.post('/api/v1/core/schedules/' + id + '/submit', { headers: deniedHeaders })).status()).toBe(403);
    expect((await page.request.post('/api/v1/auth/logout', { headers: deniedHeaders })).status()).toBe(200);
});
