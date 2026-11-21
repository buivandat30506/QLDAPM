const {chromium}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const base=process.env.SCOPE_BASE_URL||'http://127.0.0.1:8000';
const smoke=process.argv.includes('--smoke');
const dir=path.resolve(process.env.SCOPE_BROWSER_DIR||('backend/storage/app/private/'+(smoke?'scope-browser-final':'scope-browser')));
fs.mkdirSync(dir,{recursive:true});
const env=Object.fromEntries(fs.readFileSync('backend/.env','utf8').split(/\r?\n/).filter(x=>x.includes('=')&&!x.startsWith('#')).map(x=>{const i=x.indexOf('=');return [x.slice(0,i),x.slice(i+1).replace(/^"|"$/g,'')];}));
const widths=[1920,1440,1024,768,390,320];
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const errors=[],results=[];
 try {
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  page.on('pageerror',e=>errors.push(e.message));
  async function go(url){const r=await page.goto(base+url,{timeout:90000,waitUntil:'load'});assert.equal(r.status(),200,url);}
  await go('/login');
  const tokenLogin=await page.locator('input[name=_token]').inputValue();
  const login=await page.request.post(base+'/login',{form:{_token:tokenLogin,email:'demo.admin@airline.test',password:env.LOCAL_DEMO_PASSWORD},maxRedirects:0,timeout:90000});
  assert.equal(login.status(),302,'Real login');
  await go('/staff');
  const structure=await page.locator('.sidebar-nav').evaluate(el=>({groups:el.querySelectorAll('.sidebar-section').length,items:el.querySelectorAll('.sidebar-section > .sidebar-link, .sidebar-section > details').length,links:[...el.querySelectorAll('a[href]')].map(a=>a.getAttribute('href'))}));
  assert.equal(structure.groups,5,'Five original sidebar groups including Overview');
  assert.equal(structure.items,13,'Thirteen original main navigation items including Dashboard');
  fs.writeFileSync(path.join(dir,'navigation.json'),JSON.stringify(structure,null,2));
  const old=['/workspace/fleet-cabin','/workspace/crew?flow=assignments','/workspace/infrastructure-reference/ground','/workspace/infrastructure-reference/baggage','/workspace/reports/999/export','/workspace/passenger-revisions/00000000-0000-0000-0000-000000000001','/workspace/master/export/aircraft','/workspace/master/export/crew_members','/staff/payments','/workspace/planning/crew-assignments','/workspace/operations/ground-tasks'];
  const urls=smoke?['/staff','/workspace/catalog?flow=airports','/workspace/catalog?flow=routes','/workspace/planning','/workspace/operations/flights','/plan','/workspace/fleet','/staff/payments','/workspace/planning/crew-assignments','/workspace/operations/ground-tasks']:[...new Set([...structure.links,...old,'/plan'])];
  for(const url of urls){
   await go(url);
   const empty=await page.getByRole('heading',{name:'Chưa có thông tin hiển thị',exact:true}).count()>0;
   if(empty){assert.equal(await page.locator('main table').count(),0,url);assert.equal(await page.locator('main form').count(),0,url);}
   for(const width of widths){
    await page.setViewportSize({width,height:1000});
    await page.waitForTimeout(350);
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
    assert.equal(overflow,false,`${url} overflows at ${width}`);
   }
   results.push({url,status:200,empty,widths});
   console.log(JSON.stringify(results.at(-1)));
  }
  await go('/workspace/fleet');
  for(const width of [1440,390,320]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);await page.screenshot({path:path.join(dir,`empty-${width}.png`),fullPage:true});}
  await page.getByRole('button',{name:'Mở menu',exact:true}).click();
  assert.equal(await page.locator('body').evaluate(el=>el.classList.contains('sidebar-open')),true,'Hamburger opens sidebar');
  await page.getByRole('button',{name:'Đóng menu',exact:true}).click();
  await go('/staff');
  const token=await page.locator('form[action="/logout"] input[name=_token]').inputValue();
  for(const url of ['/api/v1/actions/aircraft','/api/v1/aircraft','/modules/m3/actions/telemetry','/workspace/business/statement_confirm']){
   const response=await page.request.post(base+url,{form:{_token:token},headers:{Accept:'application/json'},timeout:90000});
   assert.equal(response.status(),403,url);results.push({url,status:403});
  }
  assert.deepEqual(errors,[],'No JavaScript exceptions');
  fs.writeFileSync(path.join(dir,'results.json'),JSON.stringify({base,structure,widths,results,errors,passed:true},null,2));
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
