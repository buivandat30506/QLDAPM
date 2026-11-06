import { chromium, expect } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const runtime = JSON.parse(readFileSync('storage/app/private/demo-runtime.json', 'utf8'));
if (runtime.mode !== 'local-demo' || !/^airline_demo_[a-zA-Z0-9_]+$/.test(runtime.database)) throw new Error('Isolated local demo only');
const evidence = process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18-g2';
const started = new Date(); const checks = [];
let pulseLog = '';
for (const command of [['schedule:run'], ['queue:work', '--stop-when-empty', '--tries=3'], ['app:health']]) {
    const result = spawnSync(process.env.PHP_BINARY || 'php', ['scripts/demo.php', ...command], { encoding: 'utf8' });
    pulseLog += result.stdout + result.stderr;
    if (result.status !== 0) throw new Error('Demo background verification failed: ' + command[0]);
}
writeFileSync(evidence + '/runtime-pulse.txt', pulseLog);
const browser = await chromium.launch({ headless: true, ...(process.platform === 'win32' ? { channel: 'chrome' } : {}) });
try {
    const context = await browser.newContext({ baseURL: 'http://127.0.0.1:8000', viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage(); const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    async function login(username) {
        const account = runtime.accounts.find(account => account.username === username);
        await page.goto('/login');
        await page.locator('[name="username"]').fill(account.username);
        await page.locator('[name="password"]').fill(account.password);
        await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
        await expect(page).not.toHaveURL(/\/login$/);
        checks.push({ check: username + ' authenticates with private generated credentials', status: 'Passed' });
    }
    await login('demo_data');
    for (const url of ['/admin/airports', '/admin/airports/HAN', '/admin/master-data', '/admin/routes']) {
        expect((await page.goto(url)).status()).toBe(200);
        await expect(page.locator('h5').first()).toBeVisible();
        checks.push({ check: url + ' renders real demo data', status: 'Passed' });
    }
    await page.goto('/admin/master-data');
    await page.screenshot({ path: evidence + '/local-demo-master.png', fullPage: true });
    expect((await page.request.get('/health/ready')).status()).toBe(200);
    await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
    await login('demo_customer');
    expect((await page.goto('/portal/profiles')).status()).toBe(200);
    expect((await page.goto('/admin/master-data')).status()).toBe(403);
    checks.push({ check: 'Customer profile portal and denied master data', status: 'Passed' });
    expect(errors).toEqual([]);
    writeFileSync(evidence + '/local-demo.json', JSON.stringify({ test_id: 'SMOKE-LOCAL-DEMO-01', status: 'Passed',
        database: runtime.database, engine: runtime.engine, started_at_utc: started.toISOString(), finished_at_utc: new Date().toISOString(),
        base_url: 'http://127.0.0.1:8000', checks, browser_errors: errors, readiness: 'ready', background: 'Database queue processed scheduled RuntimeHeartbeat' }, null, 2));
    console.log('Local demo browser verification Passed; generated credentials remain private.');
} finally { await browser.close(); }
