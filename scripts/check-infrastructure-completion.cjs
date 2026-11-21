const {chromium} = require('@playwright/test');
const fs = require('fs');
const assert = require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const views=['TERMINAL','ZONE','GATE','COUNTER','JET_BRIDGE','BELT','RUNWAY','TAXIWAY','APRON','STAND','empty','import'];
 const results=[];
 for(const view of views) for(const width of [1440,390]){
  const page=await browser.newPage({viewport:{width,height:1000}}); const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://localhost:8000/__completion_preview',route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(`backend/storage/app/private/completion-${view}.html`,'utf8')}));
  await page.goto('http://localhost:8000/__completion_preview',{waitUntil:'networkidle'});
  const info=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,text:document.querySelector('main').textContent,buttons:[...document.querySelectorAll('main button[type="submit"]')].map(b=>b.textContent)}));
  assert.equal(info.overflow,false,`${view} ${width}: overflow`);assert.deepEqual(errors,[]);
  assert(!/Dữ liệu (demo|mô phỏng|trình bày)/i.test(info.text));
  assert(info.buttons.length>0,`${view}: no actionable form`);
  if(view==='empty'){assert(info.text.includes('Chưa có hạng mục nào được ghi nhận'));assert(info.text.includes('Thêm hạng mục'));}
  if(view==='JET_BRIDGE'){assert.equal(await page.locator('[name="gate_ids[]"]').count(),1);assert.equal(await page.locator('[name="stand_ids[]"]').count(),1);}
  if(view==='TERMINAL'){await page.selectOption('[name="terminal_scope"]','INTERNATIONAL');assert.equal(await page.inputValue('[name="terminal_scope"]'),'INTERNATIONAL');}
  if(['JET_BRIDGE','TERMINAL','empty','import'].includes(view)) await page.screenshot({path:`backend/storage/app/private/completion-${view}-${width}.png`,fullPage:true});
  results.push({view,width,overflow:info.overflow,errors:errors.length});await page.close();
 }
 await browser.close();fs.writeFileSync('backend/storage/app/private/infrastructure-completion-browser.json',JSON.stringify(results,null,2));console.log(JSON.stringify({checks:results.length,passed:results.length}));
})().catch(e=>{console.error(e);process.exit(1)});
