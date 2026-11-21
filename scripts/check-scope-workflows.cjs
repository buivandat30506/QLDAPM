// All business writes are restricted to the restored database served on port 8002.
const {chromium}=require('@playwright/test');
const fs=require('node:fs');const path=require('node:path');const assert=require('node:assert/strict');
const base='http://127.0.0.1:8002';
const dir=path.resolve(process.env.SCOPE_WORKFLOW_DIR||'backend/storage/app/private/scope-workflows');fs.mkdirSync(dir,{recursive:true});
const env=Object.fromEntries(fs.readFileSync('backend/.env','utf8').split(/\r?\n/).filter(x=>x.includes('=')&&!x.startsWith('#')).map(x=>{const i=x.indexOf('=');return [x.slice(0,i),x.slice(i+1).replace(/^"|"$/g,'')];}));
const fixture=JSON.parse(fs.readFileSync('backend/storage/app/private/m02-local-fixture.json','utf8').replace(/^\uFEFF/,''));
const manifest=fixture.manifest;
const progressPath=path.join(dir,'progress.json');
const progress=fs.existsSync(progressPath)?JSON.parse(fs.readFileSync(progressPath,'utf8')):{};
const save=()=>fs.writeFileSync(progressPath,JSON.stringify(progress,null,2));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const errors=[],checks=[];
 try{
  async function go(page,url){const r=await page.goto(base+url,{timeout:90000,waitUntil:'load'});assert.equal(r.status(),200,url);}
  async function click(page,locator){await locator.evaluate(el=>{for(let p=el.parentElement;p;p=p.parentElement)if(p.tagName==='DETAILS')p.open=true;});await Promise.all([page.waitForNavigation({waitUntil:'load',timeout:90000}),locator.click()]);assert(!(await page.locator('h1').innerText()).includes('500'));}
  async function login(alias){const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await go(page,'/login');const token=await page.locator('input[name=_token]').inputValue();const r=await context.request.post(base+'/login',{form:{_token:token,email:`demo.${alias}@airline.test`,password:env.LOCAL_DEMO_PASSWORD},maxRedirects:0,timeout:90000});assert.equal(r.status(),302,alias);assert(!r.headers().location.endsWith('/login'),'Login failed for '+alias);console.log('Logged in '+alias);return {context,page};}
  async function json(actor,url){const r=await actor.context.request.get(base+url,{headers:{Accept:'application/json'},timeout:90000});assert.equal(r.status(),200,url);return r.json();}
  async function capture(page,name){for(const width of [1920,1440,1024,768,390,320]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false,`${name} ${width}`);}await page.screenshot({path:path.join(dir,name+'.png'),fullPage:true});checks.push({name,url:page.url(),widths:[1920,1440,1024,768,390,320]});console.log(name);await page.setViewportSize({width:1440,height:1000});}
  const creator=await login('data_admin'), reviewer=await login('data_admin.2'), operator=await login('operations.han'), manager=await login('ops_manager.han'), scheduler=await login('crew_scheduler'), passenger=await login('passenger');
  if(!progress.airport){
   const p=creator.page;await go(p,'/workspace/catalog/airports/HAN?edit_proposal=1');
   const form=p.locator('form[action$="/changes/preview"]');await p.locator('#airport-edit').evaluate(el=>el.open=true);await form.locator('[name=name]').fill('Nội Bài · nghiệm thu bản sao');await form.locator('[name=reason]').fill('Kiểm thử phạm vi trên bản sao Database riêng');await click(p,form.locator('button[type=submit],button:not([type])').last());await capture(p,'m01-preview');
   await click(p,p.locator('form[action$="/changes"] button'));
   await go(reviewer.page,'/workspace/catalog/airports/HAN');
   const approve=reviewer.page.locator('form[action="/workspace/actions/master_approve"]').last();await click(reviewer.page,approve.locator('button'));
   assert((await reviewer.page.locator('main').innerText()).includes('Nội Bài · nghiệm thu bản sao'));await capture(reviewer.page,'m01-approved-history');progress.airport=true;save();
  }
  if(!progress.planId){
   const source=await json(creator,'/workspace/planning/plans/'+manifest['plan.manual']);
   const date=new Date();date.setUTCDate(date.getUTCDate()+180);progress.date=date.toISOString().slice(0,10);progress.code='SCOPE-UI-'+Date.now();
   const p=creator.page;await go(p,'/workspace/planning/plans/new');
   await p.locator('[name=code]').fill(progress.code);await p.locator('[name=number]').fill('AS96001');await p.locator('[name=route_id]').selectOption(String(source.plan.route_id));await p.locator('[name=aircraft_id]').selectOption(String(manifest['plane.manual']));
   for(const f of ['from_date','to_date'])await p.locator(`[name=${f}]`).fill(progress.date);
   for(const box of await p.locator('[name="weekdays[]"]').all())await box.check();
   await p.locator('[name=local_departure]').fill('09:00');await p.locator('[name=duration_minutes]').fill(String(source.plan.duration_minutes));await p.locator('[name=gate]').selectOption(source.plan.gate);await p.locator('[name=sale_price]').fill('1590000');await click(p,p.getByRole('button',{name:'Lưu kế hoạch',exact:true}));assert.match(p.url(),/\/plans\/\d+$/);progress.planId=Number(p.url().split('/').at(-1));save();
  }
  let detail=await json(creator,'/workspace/planning/plans/'+progress.planId);
  if(!detail.occurrences.total){const p=creator.page;await go(p,'/workspace/planning/plans/'+progress.planId);await p.locator('[name=from]').fill(progress.date);await p.locator('[name=to]').fill(progress.date);await click(p,p.getByRole('button',{name:'Xem trước lịch chuyến bay',exact:true}));await capture(p,'m02-generation-preview');await click(p,p.locator('form[action$="/generate"] button'));detail=await json(creator,'/workspace/planning/plans/'+progress.planId);}
  progress.flightId=detail.occurrences.data[0].flight_id;save();const url='/workspace/planning/flights/'+progress.flightId;
  let current=await json(operator,url);
  if(current.flight.status==='DRAFT'){
   const p=scheduler.page;await go(p,'/workspace/planning/crew-assignments?flight_id='+progress.flightId);
   for(const id of [manifest['crew.manual.0'],manifest['crew.manual.1']]){const form=p.locator('form[action$="/assign_crew"]');await form.locator('[name=flight_id]').selectOption(String(progress.flightId));await form.locator('[name=crew_member_id]').selectOption(String(id));await click(p,form.locator('button'));}
   await go(operator.page,url);await click(operator.page,operator.page.locator('form[action$="/batch"] button'));await capture(operator.page,'m02-submit-preview');await click(operator.page,operator.page.locator('form input[name=apply]').locator('..').locator('button'));
  }
  current=await json(manager,url);
  if(!current.flight.published_at){await go(manager.page,url);await click(manager.page,manager.page.locator('form[action$="/batch"] button'));await capture(manager.page,'m02-publish-preview');await click(manager.page,manager.page.locator('form input[name=apply]').locator('..').locator('button'));}
  const operation=await json(operator,'/workspace/operations/flights/'+progress.flightId);assert.equal(operation.crew.length,2);assert.equal(operation.allocations.length,1);assert(operation.flight.published_at);
  await go(operator.page,'/workspace/operations/flights/'+progress.flightId);await capture(operator.page,'m03-published-detail');
  if(operation.flight.status==='SCHEDULED'){const p=operator.page;const form=p.locator('form[action$="/flight_state"]');await form.locator('..').evaluate(el=>el.open=true);await form.locator('[name=reason]').fill('Mở đón khách trên bản sao nghiệm thu');await click(p,form.locator('button'));}
  assert.equal((await json(operator,'/workspace/operations/flights/'+progress.flightId)).flight.status,'BOARDING');await capture(operator.page,'m03-updated-history');
  if(!progress.reservationId){
   const p=passenger.page;await go(p,'/?origin=HAN&destination=SGN&date='+progress.date+'&trip_type=ONE_WAY&passenger_count=1');
   await p.getByRole('button',{name:'Chọn',exact:true}).first().click();await p.locator('[data-trip-preview-continue]').click();
   await p.locator('input[name="passengers[0][surname]"]').fill('Nguyen');await p.locator('input[name="passengers[0][given_name]"]').fill('Scope Browser');await p.locator('input[type=radio][value=F]').check();await p.locator('#trip-contact-name').fill('Nguyen Scope Browser');await p.locator('#trip-contact-phone').fill('0912345678');await p.locator('input[name=consent]').check();await capture(p,'booking-passenger-details');await click(p,p.getByRole('button',{name:'Tiếp tục',exact:true}));assert.match(p.url(),/\/reservations\//);progress.reservationId=p.url().split('/').at(-1);save();
  }
  const p=passenger.page;await go(p,'/reservations/'+progress.reservationId);await capture(p,'booking-held');
  if(await p.locator('#group-payment').count()){await p.locator('#group-payment').selectOption('VNPAY');await click(p,p.locator('form[action$="/pay"] button'));}
  let paymentState=await json(passenger,'/api/v1/reservations/'+progress.reservationId);
  if(!JSON.stringify(paymentState).includes('CONFIRMED')){
   for(let n=0;n<8 && !(await p.locator('a[href*="/payments/mock/"]').count());n++){await p.waitForTimeout(1000);await go(p,'/reservations/'+progress.reservationId);}
   await click(p,p.locator('a[href*="/payments/mock/"]').first());await capture(p,'payment-original-mock-provider');await click(p,p.locator('button[value=approve]'));
  }
  const result=await json(passenger,'/api/v1/reservations/'+progress.reservationId);assert(JSON.stringify(result).includes('CONFIRMED'));await capture(p,'booking-confirmed');
  await go(p,'/journeys');assert((await p.locator('main').innerText()).includes(result.reservation.reference));await capture(p,'booking-lookup');
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(dir,'results.json'),JSON.stringify({passed:true,checks,progress,errors},null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
