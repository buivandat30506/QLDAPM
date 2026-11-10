const {chromium}=require('@playwright/test');
const fs=require('fs');const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const context=await browser.newContext();await context.addCookies([JSON.parse(fs.readFileSync('backend/storage/app/private/all-airports-http-session.json','utf8')).cookie]);
 const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));const checks=[];
 for(const [name,url] of [['routes','/workspace/catalog?flow=routes'],['route-new','/workspace/catalog/routes/new'],['route-detail','/workspace/catalog/routes/1'],['seasons','/workspace/planning?tab=seasons'],['plan-new','/workspace/planning/plans/new'],['flights','/workspace/scheduling?flow=planning'],['flight-new','/workspace/planning/flights/new'],['flight-detail','/workspace/planning/flights/1'],['review','/workspace/planning?tab=review'],['conflicts','/workspace/planning?tab=conflicts']]){
  const response=await page.goto('http://localhost:8000'+url,{waitUntil:'load',timeout:90000});assert.equal(response.status(),200,name);await page.locator('.m02 h1').waitFor();
  for(const width of [1920,1440,1024,768,390,320]){await page.setViewportSize({width,height:1000});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${name} overflow ${width}`);assert.equal(await page.locator('.skip-link').evaluate(e=>getComputedStyle(e).clipPath==='inset(50%)'),true);if([1440,390].includes(width))await page.screenshot({path:`backend/storage/app/private/m02-${name}-${width}.png`,fullPage:true,animations:'disabled'});}
  if(name==='plan-new'||name==='flight-new'){
   const route=page.locator('[data-plan-route]');const first=await route.locator('option[data-origin]').first().getAttribute('value');await route.selectOption(first);const origin=await route.locator('option:checked').getAttribute('data-origin');assert(await page.locator('[data-origin-zone]').first().textContent());assert.equal(await page.locator('[data-plan-gate] option[data-airport]:not([disabled])').evaluateAll((options,o)=>options.every(e=>e.dataset.airport===o),origin),true);
  }
  if(name==='route-detail'){const panel=page.locator('.m02-disclosure').first();assert.equal(await panel.evaluate(e=>e.open),false);await panel.locator('summary').focus();await page.keyboard.press('Enter');assert.equal(await panel.locator('form').isVisible(),true);}
  if(name==='review'&&await page.locator('input[name="flight_ids[]"]').count()){await page.locator('[data-select-flights]').check();assert.match(await page.locator('[data-selection-count]').textContent(),/Đã chọn/);await Promise.all([page.waitForNavigation({waitUntil:'load',timeout:90000}),page.getByRole('button',{name:'Kiểm tra các chuyến đã chọn'}).click()]);assert(await page.getByText('Kết quả kiểm tra; chưa thay đổi lịch.',{exact:false}).count());}
  if(name==='conflicts'){assert(await page.getByText('Chưa chạy kiểm tra.',{exact:false}).count());await Promise.all([page.waitForNavigation({waitUntil:'load',timeout:90000}),page.locator('button[data-run-conflicts]').click()]);assert(await page.getByText('Đã kiểm tra',{exact:false}).count());}
  checks.push({name,status:response.status(),widths:[1920,1440,1024,768,390,320]});console.log(JSON.stringify(checks.at(-1)));
 }
 await page.goto('http://localhost:8000/workspace/planning',{waitUntil:'load',timeout:90000});await page.keyboard.press('Tab');await page.locator('.skip-link').focus();assert.equal(await page.locator('.skip-link').evaluate(e=>getComputedStyle(e).clipPath),'none');
 assert.deepEqual(errors,[]);fs.writeFileSync('backend/storage/app/private/m02-browser-results.json',JSON.stringify({checks,errors,keyboard:true},null,2));await browser.close();
})().catch(e=>{console.error(e.stack);process.exit(1)});
