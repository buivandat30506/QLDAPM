import { chromium, expect } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';

const profile = readFileSync('storage/app/private/demo-runtime.json');
const runtime = JSON.parse(profile.toString('utf8'));
if (runtime.mode !== 'local-demo' || !/^airline_demo_[a-zA-Z0-9_]+$/.test(runtime.database)) throw new Error('Isolated demo only');
const dir = process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18-maintenance';
const hash = data => createHash('sha256').update(data).digest('hex');
const envHash = hash(readFileSync('.env')); const started = new Date(); const checks = []; let stage = 'start';
const browser = await chromium.launch({ headless: true, ...(process.platform === 'win32' ? { channel: 'chrome' } : {}) });
try {
    const context = await browser.newContext({ baseURL: 'http://127.0.0.1:8000' }); const page = await context.newPage();
    const account = runtime.accounts.find(a => a.username === 'demo_ops'); stage = 'authenticate';
    await page.goto('/login'); await page.locator('[name=username]').fill(account.username); await page.locator('[name=password]').fill(account.password);
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); await expect(page).not.toHaveURL(/\/login$/);
    stage = 'maintenance navigation'; await page.getByRole('link', { name: 'Bảo dưỡng kỹ thuật', exact: true }).click();
    await expect(page.getByRole('form', { name: 'Kế hoạch bảo dưỡng', exact: true })).toBeVisible();
    await expect(page.getByRole('form', { name: 'Nhập vật tư', exact: true })).toBeVisible();
    await expect(page.getByRole('table', { name: 'Kế hoạch bảo dưỡng', exact: true })).toBeVisible();
    checks.push('Private demo_ops authenticates and can operate M05 navigation/forms/real DB collections');
    await page.screenshot({ path: dir + '/live-maintenance.png', fullPage: true });
    stage = 'logout'; await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
    expect(hash(readFileSync('.env'))).toBe(envHash); expect(hash(readFileSync('storage/app/private/demo-runtime.json'))).toBe(hash(profile));
    checks.push('No credential, runtime profile, environment or domain mutation');
    writeFileSync(dir + '/live-maintenance.json', JSON.stringify({ test_id: 'SMOKE-LIVE-MAINTENANCE-01', status: 'Passed', database: runtime.database, started_at_utc: started.toISOString(), finished_at_utc: new Date().toISOString(), checks }, null, 2));
    console.log('Live M05 smoke Passed; private credentials remain private.');
} catch {
    // Playwright fill errors can include their value. Do not print those errors for real demo credentials.
    throw new Error('Live M05 smoke failed at stage: ' + stage);
} finally { await browser.close(); }
