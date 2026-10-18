import assert from 'node:assert/strict';
import { chromium, expect } from '@playwright/test';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

// Local credentials never appear in output, traces, screenshots, or command arguments.
const profilePath = resolve('storage/app/private/demo-runtime.json');
const profileBytes = readFileSync(profilePath);
const runtime = JSON.parse(profileBytes.toString('utf8'));
assert.equal(runtime.mode, 'local-demo');
assert.match(runtime.database, /^airline_demo_[a-zA-Z0-9_]+$/);
const evidence = process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18-login-fix';
mkdirSync(evidence, { recursive: true });
const report = { started_at_utc: new Date().toISOString(), base_url: 'http://127.0.0.1:8000',
    database: runtime.database, engine: runtime.engine, checks: [], status: 'Not Run' };
const hash = bytes => createHash('sha256').update(bytes).digest('hex');
const envHash = hash(readFileSync('.env'));
if (process.platform !== 'win32') throw new Error('Windows clipboard helper verification requires PowerShell.');

// Replace only Set-Clipboard inside this child process: the user's clipboard stays untouched.
const contract = spawnSync('powershell', ['-NoProfile', '-NonInteractive', '-Command', String.raw`
$ErrorActionPreference = 'Stop'
$taskRuntime = Get-Content -LiteralPath $env:DEMO_LOGIN_PROFILE -Raw -Encoding UTF8 | ConvertFrom-Json
$taskChecks = @()
function Set-Clipboard { param([string]$Value) $global:taskCaptured = $Value }
foreach ($taskAccount in $taskRuntime.accounts) {
    $global:taskCaptured = $null
    $taskOutput = @(& $env:DEMO_LOGIN_HELPER -Username $taskAccount.username -CopyPassword)
    if ($global:taskCaptured -cne $taskAccount.password) { throw 'Decoded password mismatch.' }
    if (($taskOutput -join ' ').Contains($taskAccount.password)) { throw 'Secret appeared in output.' }
    $taskChecks += @{ check = 'Decoded clipboard contract: ' + $taskAccount.username; status = 'Passed' }
}
$taskOutput = @(& $env:DEMO_LOGIN_HELPER)
foreach ($taskAccount in $taskRuntime.accounts) {
    if (($taskOutput | Out-String).Contains($taskAccount.password)) { throw 'List exposed a secret.' }
}
$taskChecks += @{ check = 'Listing excludes passwords'; status = 'Passed' }
foreach ($taskArguments in @(@{ Username = '__nonexistent__'; CopyPassword = $true }, @{ CopyPassword = $true })) {
    $global:taskCaptured = $null
    $taskRejected = $false
    try { $null = & $env:DEMO_LOGIN_HELPER @taskArguments } catch { $taskRejected = $true }
    if (-not $taskRejected -or $null -ne $global:taskCaptured) { throw 'Invalid request changed clipboard.' }
    $taskChecks += @{ check = 'Invalid copy request rejected without clipboard change: ' + $taskArguments.Username; status = 'Passed' }
}
ConvertTo-Json -InputObject @($taskChecks) -Depth 5
`], { encoding: 'utf8', env: { ...process.env, DEMO_LOGIN_PROFILE: profilePath,
    DEMO_LOGIN_HELPER: resolve('scripts/demo-credentials.ps1') } });
if (contract.status !== 0) throw new Error('Credential helper contract failed; secrets suppressed.');
report.checks.push(...JSON.parse(contract.stdout.replace(/^\uFEFF/, '')));

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
try {
    const admin = runtime.accounts.find(account => account.username === 'demo_admin');
    const serialized = JSON.stringify(admin.password).slice(1, -1);
    if (serialized !== admin.password) {
        const context = await browser.newContext({ baseURL: report.base_url });
        try {
            const page = await context.newPage();
            await page.goto('/login');
            await page.getByRole('textbox', { name: 'Tên đăng nhập', exact: true }).fill(admin.username);
            await page.locator('[name="password"]').fill(serialized);
            await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
            await expect(page.locator('[role="alert"]')).toContainText('Sai tài khoản/mật khẩu hoặc tài khoản không hoạt động.');
            assert.equal(new URL(page.url()).pathname, '/login');
            report.checks.push({ check: 'Serialized JSON password reproduces the reported login error', status: 'Passed' });
        } finally { await context.close(); }
    }
    const pages = { demo_admin: '/administration/users', demo_data: '/admin/master-data',
        demo_ops: '/admin/workflows', demo_customer: '/portal/profiles' };
    for (const [username, target] of Object.entries(pages)) {
        const account = runtime.accounts.find(account => account.username === username);
        const context = await browser.newContext({ baseURL: report.base_url });
        try {
            const page = await context.newPage();
            await page.goto('/login');
            await page.getByRole('textbox', { name: 'Tên đăng nhập', exact: true }).fill(account.username);
            await page.locator('[name="password"]').fill(account.password);
            await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
            await expect(page).not.toHaveURL(/\/login$/);
            assert.equal((await page.goto(target)).status(), 200);
            await expect(page.getByRole('button', { name: 'Đăng xuất', exact: true })).toBeVisible();
            report.checks.push({ check: username + ' authenticates and accesses ' + target, status: 'Passed' });
            await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
            await expect(page).toHaveURL(/\/login$/);
        } finally { await context.close(); }
    }
    assert.equal(hash(readFileSync(profilePath)), hash(profileBytes));
    assert.equal(hash(readFileSync('.env')), envHash);
    report.checks.push({ check: 'Private credentials and .env bytes unchanged', status: 'Passed' });
    report.status = 'Passed';
    console.log('Demo login verification Passed: 4 real Chrome logins; JSON escape reproduction; clipboard contract (mocked, desktop clipboard untouched).');
} catch (error) {
    report.status = 'Failed';
    // Error text may contain input values; retain only a safe summary.
    throw new Error('Demo browser login verification failed; secrets suppressed.');
} finally {
    report.finished_at_utc = new Date().toISOString();
    writeFileSync(evidence + '/login-verification.json', JSON.stringify(report, null, 2));
    await browser.close();
}
