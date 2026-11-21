const {chromium}=require('@playwright/test');
const fs=require('fs');const path=require('path');const assert=require('node:assert/strict');
(async()=>{
 const checkout=path.resolve(process.argv[2]||'.demo-check');
 const env=Object.fromEntries(fs.readFileSync(path.join(checkout,'backend/.env'),'utf8').split(/\r?\n/).filter(s=>s.includes('=')&&!s.startsWith('#')).map(s=>{const i=s.indexOf('=');return[s.slice(0,i),s.slice(i+1).replace(/^"|"$/g,'')]}));
 const base=new URL(env.APP_URL);assert(['localhost','127.0.0.1'].includes(base.hostname),'Local demo only');
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const context=await browser.newContext();const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(new URL('/login',base).href,{waitUntil:'load',timeout:90000});
 const csrf=await page.locator('input[name=_token]').inputValue();
 const login=await context.request.post(new URL('/login',base).href,{form:{_token:csrf,email:'demo.admin@airline.test',password:env.LOCAL_DEMO_PASSWORD},maxRedirects:0,timeout:90000});assert.equal(login.status(),302,'Demo login');assert(login.headers().location.endsWith('/staff'),'Demo account should be ready without password reset');
 const directory=path.join(checkout,'backend/storage/app/private/portable-verification');fs.mkdirSync(directory,{recursive:true});const checks=[];
 for(const [name,url,expected] of [
  ['airports','/workspace/catalog?flow=airports','Danh sách sân bay'],
  ['infrastructure-bmv','/workspace/catalog?flow=infrastructure&airport=BMV','Cơ sở hạ tầng'],
  ['infrastructure-han','/workspace/catalog?flow=infrastructure&airport=HAN','Cơ sở hạ tầng'],
  ['routes','/workspace/catalog?flow=routes','Đường bay'],
  ['seasons','/workspace/planning?tab=seasons','DEMO-M02-RECURRING'],
  ['flights','/workspace/planning','Danh sách chuyến bay'],
  ['review','/workspace/planning?tab=review','Phê duyệt và công bố'],
  ['cabins','/workspace/fleet-cabin','cabin'],
  ['reservations','/modules/m2?flow=bookings','đặt'],
  ['public','/','chuyến'],
 ]){
  const response=await page.goto(new URL(url,base).href,{waitUntil:'load',timeout:90000});assert.equal(response.status(),200,name);assert((await page.locator('main').innerText()).toLowerCase().includes(expected.toLowerCase()),name+' content');
  if(name.startsWith('infrastructure')){assert.equal(await page.locator('select[name="kind"] option[value]:not([value=""])').count(),10,'All ten infrastructure types are usable');for(const label of ['Đường băng','Cầu ống lồng','Băng chuyền hành lý'])assert((await page.locator('main').innerText()).includes(label),name+': '+label);}
  if(name==='seasons'){const href=await page.locator('.m02-table tr').filter({hasText:'DEMO-M02-RECURRING'}).locator('a[href^="/workspace/planning/plans/"]').getAttribute('href');await page.goto(new URL(href,base).href,{waitUntil:'load',timeout:90000});assert((await page.locator('main').innerText()).includes('Chuyến đã phát sinh'));assert.equal(await page.locator('a[href^="/workspace/planning/flights/"]').count(),2);}
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,name+' overflow');await page.screenshot({path:path.join(directory,name+'-'+width+'.png'),fullPage:true,animations:'disabled'});}
  checks.push({name,status:200});console.log(JSON.stringify(checks.at(-1)));
 }
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(directory,'browser.json'),JSON.stringify({checks,errors},null,2));await browser.close();
})().catch(e=>{console.error(e.stack);process.exit(1)});
