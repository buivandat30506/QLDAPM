// Opt-in browser exercise: creates one plan/flight, assigns its crew and publishes it.
// Only the explicitly prepared local M02 dataset is used; existing flights are untouched.
const { chromium } = require('@playwright/test');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve('backend/storage/app/private/m02-complete-browser');
const base = 'http://localhost:8000';
const env = Object.fromEntries(fs.readFileSync('backend/.env', 'utf8').split(/\r?\n/).filter(x => x.includes('=') && !x.startsWith('#')).map(x => { const i = x.indexOf('='); return [x.slice(0, i), x.slice(i + 1).replace(/^"|"$/g, '')]; }));
const fixture = JSON.parse(fs.readFileSync('backend/storage/app/private/m02-local-fixture.json', 'utf8').replace(/^\uFEFF/, ''));
const manifest = fixture.manifest;
const widths = [1920, 1440, 1024, 768, 390, 320];
fs.mkdirSync(root, { recursive: true });
const progressPath = path.join(root, 'exercise.json');
const progress = fs.existsSync(progressPath) ? JSON.parse(fs.readFileSync(progressPath, 'utf8')) : {};
const save = () => fs.writeFileSync(progressPath, JSON.stringify(progress, null, 2));
const results = [], errors = [];
(async () => {
  assert(process.argv.includes('--exercise-local'), 'Pass --exercise-local to authorize writes to this local demo');
  assert(env.LOCAL_DEMO_PASSWORD, 'LOCAL_DEMO_PASSWORD is required');
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  try {
    async function login(alias) {
      const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
      const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
      if (process.argv.includes('--role-sessions')) {
        const sessions = JSON.parse(fs.readFileSync('backend/storage/app/private/m02-role-sessions.json', 'utf8'));
        await context.addCookies([sessions[alias].cookie]);
        return { context, page };
      }
      await page.goto(base + '/login', { timeout: 90000 });
      const response = await context.request.post(base + '/login', { form: { _token: await page.locator('input[name=_token]').inputValue(), email: `demo.${alias}@airline.test`, password: env.LOCAL_DEMO_PASSWORD }, maxRedirects: 0, timeout: 90000 });
      assert.equal(response.status(), 302, 'Real role login: ' + alias);
      assert(response.headers().location.endsWith('/staff'), 'Account must already be ready: ' + alias);
      return { context, page };
    }
    async function go(page, url) { const r = await page.goto(base + url, { timeout: 90000, waitUntil: 'load' }); assert.equal(r.status(), 200, url); return r; }
    async function click(page, locator) { await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 90000 }), locator.click()]); assert(!(await page.locator('h1').innerText()).includes('500'), 'No server failure'); }
    async function json(context, url) { const r = await context.request.get(base + url, { headers: { Accept: 'application/json' }, timeout: 90000 }); assert.equal(r.status(), 200, url); return r.json(); }
    async function capture(page, name) {
      for (const width of widths) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(350);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${name}: overflow at ${width}`);
        if ([1440, 390].includes(width)) await page.screenshot({ path: path.join(root, `${name}-${width}.png`), fullPage: true, animations: 'disabled' });
      }
      results.push({ name, widths, url: page.url(), status: 200 });
      console.log(JSON.stringify(results.at(-1)));
    }
    const creator = await login('data_admin');
    const operator = await login('operations.han');
    const reviewer = await login('ops_manager.han');
    const scheduler = await login('crew_scheduler');
    const cp = creator.page, op = operator.page, rp = reviewer.page;
    const planCode = 'M02-UI-' + fixture.key;
    if (!progress.planId) {
      const existing = await json(creator.context, '/workspace/planning?tab=seasons&q=' + encodeURIComponent(planCode));
      if (existing.plans.total) progress.planId = existing.plans.data[0].id;
      else {
        const source = await json(creator.context, '/workspace/planning/plans/' + manifest['plan.manual']);
        await go(cp, '/workspace/planning/plans/new');
        const date = new Date(fixture.starts_on + 'T00:00:00Z'); date.setUTCDate(date.getUTCDate() + 20);
        progress.date = date.toISOString().slice(0, 10); save();
        await cp.locator('[name=code]').fill(planCode);
        await cp.locator('[name=number]').fill('AS95001');
        await cp.locator('[name=route_id]').selectOption(String(source.plan.route_id));
        await cp.locator('[name=aircraft_id]').selectOption(String(manifest['plane.manual']));
        for (const field of ['from_date', 'to_date']) await cp.locator(`[name=${field}]`).fill(progress.date);
        for (const box of await cp.locator('[name="weekdays[]"]').all()) await box.check();
        await cp.locator('[name=local_departure]').fill('09:00');
        await cp.locator('[name=duration_minutes]').fill(String(source.plan.duration_minutes));
        await cp.locator('[name=gate]').selectOption(source.plan.gate);
        await cp.locator('[name=sale_price]').fill('1590000');
        await capture(cp, 'create-plan');
        await click(cp, cp.getByRole('button', { name: 'Lưu kế hoạch', exact: true }));
        assert.match(cp.url(), /\/plans\/\d+$/); progress.planId = Number(cp.url().split('/').at(-1));
      }
      save();
    }
    let detail = await json(creator.context, '/workspace/planning/plans/' + progress.planId);
    progress.date ||= detail.plan.from_date;
    if (!detail.occurrences.total) {
      await go(cp, '/workspace/planning/plans/' + progress.planId);
      await cp.locator('[name=from]').fill(progress.date); await cp.locator('[name=to]').fill(progress.date);
      await click(cp, cp.getByRole('button', { name: 'Xem trước lịch chuyến bay', exact: true }));
      await capture(cp, 'generation-preview');
      assert.equal((await json(creator.context, '/workspace/planning/plans/' + progress.planId)).occurrences.total, 0, 'Preview does not write');
      await click(cp, cp.locator('form[action$="/generate"] button'));
      detail = await json(creator.context, '/workspace/planning/plans/' + progress.planId);
    }
    assert.equal(detail.occurrences.total, 1); progress.flightId = detail.occurrences.data[0].flight_id; save();
    const flightUrl = '/workspace/planning/flights/' + progress.flightId;
    let current = await json(operator.context, flightUrl);
    if (current.flight.status === 'DRAFT') {
      await go(scheduler.page, '/workspace/crew?flow=assignments');
      // Each real assignment is a separate normal M06 form submission.
      for (const member of [manifest['crew.manual.0'], manifest['crew.manual.1']]) {
        if ((progress.assigned || []).includes(member)) continue;
        const form = scheduler.page.locator('form[action="/workspace/actions/assign_crew"]');
        await form.locator('[name=flight_id]').selectOption(String(progress.flightId));
        await form.locator('[name=crew_member_id]').selectOption(String(member));
        await click(scheduler.page, form.locator('button'));
        progress.assigned = [...(progress.assigned || []), member]; save();
      }
      await go(op, flightUrl);
      await click(op, op.locator('form[action="/workspace/planning/batch"] button'));
      assert.equal((await json(operator.context, flightUrl)).flight.status, 'DRAFT', 'Batch preview does not submit');
      await capture(op, 'submit-preview');
      await click(op, op.locator('form input[name=apply]').locator('..').locator('button'));
      assert.equal((await json(operator.context, flightUrl)).flight.status, 'PENDING');
    }
    current = await json(reviewer.context, flightUrl);
    if (!current.flight.published_at) {
      await go(rp, flightUrl); await click(rp, rp.locator('form[action="/workspace/planning/batch"] button'));
      await capture(rp, 'publish-preview');
      await click(rp, rp.locator('form input[name=apply]').locator('..').locator('button'));
      assert(await rp.locator('a[href^="/workspace/operations/flights?"]').count(), 'Actual M03 result link');
    }
    current = await json(reviewer.context, flightUrl);
    assert.equal(current.flight.status, 'SCHEDULED'); assert(current.flight.published_at); assert.equal(current.source.season_id, progress.planId);
    const m03 = await json(operator.context, '/workspace/operations/flights/' + progress.flightId);
    assert.equal(m03.flight.number, current.flight.number); assert.equal(m03.flight.departure, current.flight.departure); assert.equal(m03.flight.gate, current.flight.gate); assert.equal(m03.crew.length, 2); assert.equal(m03.allocations.length, 1);
    for (const lane of ['draft', 'pending', 'published']) { await go(op, `/workspace/planning?tab=review&dataset=${fixture.key}&lane=${lane}`); await capture(op, 'review-' + lane); if (lane !== 'draft') assert.equal(await op.locator('[name="flight_ids[]"]').count(), 0, 'Operator cannot publish'); }
    await go(rp, `/workspace/planning?tab=review&dataset=${fixture.key}&lane=pending`); assert(await rp.locator('[name="flight_ids[]"]').count());
    for (const tab of ['seasons', 'flights']) { await go(op, `/workspace/planning?tab=${tab}&dataset=${fixture.key}`); await capture(op, tab); }
    await go(op, `/workspace/planning?tab=conflicts&dataset=${fixture.key}`);
    await click(op, op.locator('[data-run-conflicts]'));
    const checkedUrl = op.url(); assert.match(checkedUrl, /check=/);
    const checkData = await json(operator.context, checkedUrl.replace(base, ''));
    assert.equal(checkData.checked.count, 19); assert.equal(checkData.checked.remaining, 0);
    assert.equal(checkData.checked.flight_counts.conflict, 3); assert.equal(checkData.checked.flight_counts.condition, 2); assert.equal(checkData.checked.flight_counts.warning, 5);
    await capture(op, 'checks-all');
    for (const group of ['conflict', 'condition', 'warning', 'valid']) { await go(op, checkedUrl.replace(base, '') + '&group=' + group); await capture(op, 'checks-' + group); }
    // A real blocking overlap cannot be submitted; no confirmation form is offered.
    await go(op, '/workspace/planning/flights/' + manifest['problem.conflict.0']);
    await click(op, op.locator('form[action="/workspace/planning/batch"] button'));
    assert.equal(await op.locator('input[name=apply]').count(), 0); await capture(op, 'blocked-conflict');
    assert.equal((await json(operator.context, '/workspace/planning/flights/' + manifest['problem.conflict.0'])).flight.status, 'DRAFT');
    await go(op, '/workspace/planning/flights/' + manifest['generated.daily'][3]); assert((await op.locator('main').innerText()).includes('Đã duyệt sửa lịch')); await capture(op, 'edit-history');
    await go(op, flightUrl); await capture(op, 'published-detail');
    await go(op, '/workspace/operations/flights?flight_id=' + progress.flightId); await capture(op, 'm03-list');
    await go(op, '/workspace/operations/flights/' + progress.flightId); await capture(op, 'm03-detail');
    assert.equal((await creator.context.request.get(base + '/workspace/planning?tab=review')).status(), 403);
    assert.equal((await creator.context.request.get(base + '/workspace/operations/flights/' + progress.flightId)).status(), 403);
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(root, 'results.json'), JSON.stringify({ results, progress, errors, checks: checkData.checked.flight_counts, realRoleLogins: !process.argv.includes('--role-sessions'), authenticatedExistingRoleSessions: true, m03: { crew: m03.crew.length, allocations: m03.allocations.length } }, null, 2));
    console.log(JSON.stringify({ pass: true, screens: results.length, widths, progress }));
  } finally { await browser.close(); }
})().catch(e => { console.error(e.stack); process.exitCode = 1; });
