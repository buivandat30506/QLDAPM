const {chromium}=require('@playwright/test');
const fs=require('fs');
const assert=require('node:assert/strict');
(async()=>{
 const manifest=JSON.parse(fs.readFileSync('backend/storage/app/private/population-preview-manifest.json','utf8'));
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const results=[];
 for(const name of manifest.pages) for(const width of [1440,390]){
  const page=await browser.newPage({viewport:{width,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://localhost:8000/__population_preview',route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(`backend/storage/app/private/population-${name}.html`,'utf8')}));
  await page.goto('http://localhost:8000/__population_preview',{waitUntil:'networkidle'});
  const info=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,text:document.querySelector('main').innerText,title:document.querySelector('main h1').textContent}));
  assert.equal(info.overflow,false,`${name} ${width}: document overflow`);assert.deepEqual(errors,[]);
  assert(!/Dữ liệu (demo|mô phỏng|trình bày)|LOCAL TEST SCENARIO/i.test(info.text));
  if(name==='scenario')assert.equal(await page.locator('.airport-table tbody tr').count(),12);
  if(name==='detail-belt')assert(info.text.includes('Nhà ga A / Khu nhận hành lý A'));
  if(name==='detail-bridge'){
   assert.equal(await page.locator(`dd a[href="/workspace/catalog/infrastructure/items/${manifest.ids.gate}"]`).count(),1);
   assert.equal(await page.locator(`dd a[href="/workspace/catalog/infrastructure/items/${manifest.ids.stand}"]`).count(),1);
  }
  if(['list','scenario','detail-bridge','detail-belt','detail-runway'].includes(name))await page.screenshot({path:`backend/storage/app/private/population-${name}-${width}.png`,fullPage:true});
  results.push({name,width,title:info.title,overflow:false,errors});await page.close();
 }
 await browser.close();fs.writeFileSync('backend/storage/app/private/population-browser-results.json',JSON.stringify(results,null,2));console.log(JSON.stringify({checks:results.length,passed:results.length,groupCounts:manifest.group_counts}));
})().catch(e=>{console.error(e);process.exit(1)});
