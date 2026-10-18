import { test, expect } from '@playwright/test';

const evidence = process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18-g2';
async function login(page, username = 'data_demo') {
    await page.goto('/login');
    await page.locator('[name="username"]').fill(username);
    await page.locator('[name="password"]').fill('Demo@12345');
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}
function csv(rows) {
    return Buffer.from('category,code,payload\r\n' + rows.map(row => [row.category, row.code, JSON.stringify(row.payload)]
        .map(value => '"' + value.replaceAll('"', '""') + '"').join(',')).join('\r\n') + '\r\n');
}

test('UAT-UC01-02: tạo sân bay, chỉnh trạng thái và quản lý vị trí đỗ thật', async ({ page }) => {
    await login(page); await page.goto('/admin/airports');
    await page.getByText('Thêm sân bay', { exact: true }).click();
    await page.getByLabel('Mã IATA', { exact: true }).fill('TXA');
    await page.getByLabel('Mã ICAO', { exact: true }).fill('VVXA');
    await page.getByLabel('Tên sân bay', { exact: true }).fill('Sân bay mô phỏng UAT');
    await page.getByLabel('Thành phố', { exact: true }).fill('Thành phố mô phỏng');
    await page.getByLabel('Vĩ độ', { exact: true }).fill('21.2212');
    await page.getByLabel('Kinh độ', { exact: true }).fill('105.8072');
    await page.getByRole('button', { name: 'Tạo sân bay', exact: true }).click();
    await expect(page.locator('tbody')).toContainText('TXA');
    await page.getByText('Thêm sân bay', { exact: true }).click();
    await page.getByLabel('Mã IATA', { exact: true }).fill('TXA');
    await page.getByLabel('Tên sân bay', { exact: true }).fill('Bản trùng không được lưu');
    await page.getByLabel('Thành phố', { exact: true }).fill('Thành phố mô phỏng');
    await page.getByRole('button', { name: 'Tạo sân bay', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Đã xảy ra lỗi kiểm tra dữ liệu');
    await page.goto('/admin/airports');
    await page.locator('tr').filter({ hasText: 'TXA' }).getByRole('link', { name: 'Chi tiết' }).click();
    await page.getByLabel('Trạng thái sân bay').selectOption('INACTIVE');
    await page.getByRole('button', { name: 'Lưu sân bay', exact: true }).click();
    await page.goto('/admin/airports/TXA');
    await expect(page.getByLabel('Trạng thái sân bay')).toHaveValue('INACTIVE');
    await page.getByLabel('Loại tài nguyên', { exact: true }).selectOption('STAND');
    await page.getByLabel('Mã tài nguyên', { exact: true }).fill('UAT-STAND-1');
    await page.getByRole('button', { name: 'Thêm tài nguyên', exact: true }).click();
    const row = page.locator('tr[data-resource]').filter({ hasText: 'UAT-STAND-1' });
    await expect(row).toContainText('Vị trí đỗ');
    await row.getByText('Sửa tài nguyên', { exact: true }).click();
    await row.locator('select[name="status"]').selectOption('MAINTENANCE');
    await row.getByRole('button', { name: 'Lưu tài nguyên', exact: true }).click();
    await expect(page.locator('tr[data-resource]').filter({ hasText: 'UAT-STAND-1' })).toContainText('Tạm ngừng / bảo trì');
    await page.screenshot({ path: evidence + '/uc01-02-airport.png', fullPage: true });
    await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
    await login(page, 'ops_demo');
    await page.goto('/admin/airports/TXA');
    await expect(page.getByRole('button', { name: 'Lưu sân bay', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Thêm tài nguyên', exact: true })).toHaveCount(0);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const denied = await page.request.put('/admin/airports/TXA', { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, form: { name: 'Forbidden overwrite' } });
    expect(denied.status()).toBe(403);
});

test('UAT-UC03: nhập CSV, duyệt công bố, bản sửa đổi, lịch sử và xuất theo bộ lọc', async ({ page }) => {
    await login(page); await page.goto('/admin/master-data');
    await page.getByLabel('Tệp CSV', { exact: true }).setInputFiles({ name: 'uat-valid.csv', mimeType: 'text/csv', buffer: csv([
        { category: 'CABIN', code: 'UAT_CABIN', payload: { name: 'Hạng khoang mô phỏng UAT' } },
        { category: 'CURRENCY', code: 'UAT_CURRENCY', payload: { name: 'Tiền tệ mô phỏng', minor_units: 0 } },
    ]) });
    await page.getByRole('button', { name: 'Kiểm tra tệp', exact: true }).click();
    await expect(page.locator('main, .content-wrapper').last()).toContainText('READY');
    await page.getByRole('button', { name: 'Xác nhận nhập toàn bộ bản nháp', exact: true }).click();
    await expect(page.locator('.content-wrapper')).toContainText('COMMITTED');
    await page.locator('tr').filter({ hasText: 'UAT_CABIN' }).getByRole('link', { name: 'Mở bản nháp đã nhập' }).click();
    for (const name of ['Gửi phê duyệt', 'Phê duyệt', 'Công bố']) {
        await page.getByRole('button', { name, exact: true }).click();
    }
    await expect(page.locator('.content-wrapper')).toContainText('Phiên bản 1');
    await page.getByLabel('Lý do tạo bản sửa đổi', { exact: true }).fill('Cập nhật tên hạng khoang UAT');
    await page.getByRole('button', { name: 'Tạo bản sửa đổi', exact: true }).click();
    await page.getByLabel('Thuộc tính JSON', { exact: true }).fill('{"name":"Hạng khoang UAT đã sửa"}');
    await page.getByRole('button', { name: 'Lưu thay đổi', exact: true }).click();
    for (const name of ['Gửi phê duyệt', 'Phê duyệt', 'Công bố']) {
        await page.getByRole('button', { name, exact: true }).click();
    }
    await expect(page.locator('.content-wrapper')).toContainText('Phiên bản 2');
    await expect(page.locator('.content-wrapper')).toContainText('Cập nhật tên hạng khoang UAT');
    await page.screenshot({ path: evidence + '/uc03-version-history.png', fullPage: true });
    await page.goto('/admin/master-data?search=UAT_CABIN');
    await expect(page.locator('tbody').first()).toContainText('UAT_CABIN');
    await expect(page.locator('tbody').first()).not.toContainText('UAT_CURRENCY');
    const downloaded = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Xuất CSV theo bộ lọc', exact: true }).click();
    expect((await downloaded).suggestedFilename()).toBe('master-data.csv');
});

test('UAT-UC03-EX: tệp sai và lô có dòng lỗi bị chặn; role khác bị từ chối', async ({ page }) => {
    await login(page); await page.goto('/admin/master-data');
    await page.getByLabel('Tệp CSV', { exact: true }).setInputFiles({ name: 'wrong.csv', mimeType: 'text/csv', buffer: Buffer.from('wrong,header\n') });
    await page.getByRole('button', { name: 'Kiểm tra tệp', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Header bắt buộc');
    await page.getByLabel('Tệp CSV', { exact: true }).setInputFiles({ name: 'mixed.csv', mimeType: 'text/csv', buffer: csv([
        { category: 'CABIN', code: 'UAT_GOOD_BLOCKED', payload: { name: 'Không được nhập một phần' } },
        { category: 'SERVICE', code: 'UAT_BAD_REF', payload: { name: 'Quan hệ lỗi', cabin_code: 'MISSING' } },
    ]) });
    await page.getByRole('button', { name: 'Kiểm tra tệp', exact: true }).click();
    await expect(page.locator('.content-wrapper')).toContainText('INVALID');
    await expect(page.locator('.content-wrapper')).toContainText('Hạng khoang tham chiếu');
    await expect(page.getByRole('button', { name: 'Xác nhận nhập toàn bộ bản nháp' })).toHaveCount(0);
    await page.screenshot({ path: evidence + '/uc03-import-errors.png', fullPage: true });
    await page.goto('/admin/master-data?search=UAT_GOOD_BLOCKED');
    await expect(page.locator('tbody').first()).not.toContainText('UAT_GOOD_BLOCKED');
    await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click(); await login(page, 'ops_demo');
    expect((await page.goto('/admin/master-data')).status()).toBe(403);
});
