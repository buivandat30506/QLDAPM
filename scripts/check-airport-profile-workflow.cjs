const {chromium}=require('@playwright/test');const fs=require('fs');const assert=require('node:assert/strict');
(async()=>{
 const b=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});const c=await b.newContext();await c.addCookies([JSON.parse(fs.readFileSync('backend/storage/app/private/all-airports-http-session.json','utf8')).cookie]);
 const p=await c.newPage();const errors=[];p.on('pageerror',e=>errors.push(e.message));
 let r=await p.goto('http://localhost:8000/workspace/catalog/airports/BMV',{waitUntil:'load',timeout:60000});assert.equal(r.status(),200);const original=await p.locator('h1').innerText();
 await p.getByRole('link',{name:'Đề nghị sửa hồ sơ',exact:true}).click();assert.equal(await p.locator('#airport-edit').getAttribute('open'),'');
 assert(await p.locator('#airport-edit').innerText().then(t=>t.includes('Mã IATA')&&t.includes('Mã ICAO')&&t.includes('Vị trí và múi giờ')));
 assert((await p.locator('#airport-history > article').count())<=5);const system=p.locator('#airport-history > details');if(await system.count())assert.equal(await system.getAttribute('open'),null);
 const changed=original+' cập nhật kiểm tra';await p.locator('#airport-edit [name=name]').fill(changed);await p.locator('#airport-edit [name=reason]').fill('Đối chiếu nội dung trước khi gửi đề nghị');
 for(const width of [1440,390]){await p.setViewportSize({width,height:1000});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await p.screenshot({path:`backend/storage/app/private/airport-profile-edit-${width}.png`,fullPage:true});}
 await Promise.all([p.waitForURL('**/changes/preview'),p.getByRole('button',{name:'Xem trước thay đổi',exact:true}).click()]);
 assert.equal(await p.locator('h1').innerText(),'Xem trước đề nghị sửa hồ sơ');assert.equal(await p.locator('tbody tr').count(),1);assert((await p.locator('tbody').innerText()).includes(changed));assert((await p.locator('tbody').innerText()).includes(original));assert.equal(await p.locator('input[name=preview_token]').count(),1);
 for(const width of [1440,390]){await p.setViewportSize({width,height:1000});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await p.screenshot({path:`backend/storage/app/private/airport-profile-preview-${width}.png`,fullPage:true});}
 await p.getByRole('link',{name:'Chỉnh sửa lại',exact:true}).click();assert.equal(await p.locator('#airport-edit [name=name]').inputValue(),changed);assert.equal(await p.locator('h1').innerText(),original);
 await p.locator('#airport-edit > summary').click();await p.locator('#airport-history').scrollIntoViewIfNeeded();await p.screenshot({path:'backend/storage/app/private/airport-profile-history-390.png',fullPage:true});
 assert.deepEqual(errors,[]);await b.close();console.log(JSON.stringify({status:200,viewports:[1440,390],previewOnly:true,unchangedProfile:true,errors}));
})().catch(e=>{console.error(e.message);process.exit(1)});
