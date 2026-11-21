const {chromium} = require('@playwright/test');
const fs = require('fs');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const results=[];
 for(const view of ['display','detail']) for(const width of [1440,390]){
 const page=await browser.newPage({viewport:{width,height:1000}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('http://localhost:8000/__infrastructure_preview',route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(`backend/storage/app/private/infrastructure-${view}-preview.html`,'utf8')}));
 await page.goto('http://localhost:8000/__infrastructure_preview',{waitUntil:'networkidle'});
 const info=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,rows:document.querySelectorAll('.airport-table tbody tr').length,text:document.querySelector('.airport-table').textContent}));
 if(info.overflow || (view==='display' && info.text.includes('Chưa khai báo')) || errors.length) throw Error(JSON.stringify({width,info,errors}));
 await page.screenshot({path:`backend/storage/app/private/infrastructure-${view}-${width}.png`,fullPage:true});
 results.push({view,width,overflow:info.overflow,rows:info.rows,errors});await page.close();
 }
 console.log(JSON.stringify(results));await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
