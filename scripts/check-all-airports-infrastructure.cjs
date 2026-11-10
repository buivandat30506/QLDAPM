const {chromium}=require('@playwright/test');const fs=require('fs');const assert=require('node:assert/strict');
const folder='backend/storage/app/private',base='http://localhost:8000';
const groups={terminal:['TERMINAL','ZONE'],passenger:['GATE','COUNTER','JET_BRIDGE'],baggage:['BELT'],airfield:['RUNWAY','TAXIWAY','APRON','STAND']};
(async()=>{
 const expected=JSON.parse(fs.readFileSync(`${folder}/all-airports-records.json`,'utf8'));
 const auth=JSON.parse(fs.readFileSync(`${folder}/all-airports-http-session.json`,'utf8'));
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const context=await browser.newContext();await context.addCookies([auth.cookie]);
 const result={baseURL:base,httpAirports:[],searchChecks:0,browserChecks:[],screenshots:[]};
 const persist=()=>fs.writeFileSync(`${folder}/all-airports-http-results.json`,JSON.stringify(result,null,2));
 const json=async(path)=>{const r=await context.request.get(base+path,{headers:{Accept:'application/json'},timeout:120000});assert.equal(r.status(),200,path);return r.json();};
 for(const airport of expected.matrix){
  const data=await json(`/api/v1/airport-infrastructure?airport=${airport.airport}`);
  assert.equal(data.items.total,Object.values(airport.counts).reduce((a,b)=>a+Number(b),0));assert.equal(data.choices.length,expected.matrix.length);
  assert.equal(data.types.length,10);
  for(const [group,types]of Object.entries(groups))assert.equal(Number(data.groupCounts[group]||0),types.reduce((sum,t)=>sum+Number(airport.counts[t]),0));
  for(const row of data.items.data){const raw=expected.records.find(r=>r.id===row.canonical_id);assert(raw);assert.equal(row.code,raw.code);assert.equal(row.name,raw.name);assert.equal(row.type_code,raw.type_code);}
  result.httpAirports.push({airport:airport.airport,status:200,total:data.items.total,groupCounts:data.groupCounts});persist();console.log(JSON.stringify({stage:'airport-http',checked:result.httpAirports.length,airport:airport.airport}));
 }
 const ui=async(airport,type,width,detail=false)=>{
  const rows=expected.records.filter(r=>r.airport_code===airport&&(!type||r.type_code===type)).sort((a,b)=>a.type_code.localeCompare(b.type_code)||a.code.localeCompare(b.code));
  const selected=rows[0];
  const path=detail?`/workspace/catalog/infrastructure/items/${selected.id}`:`/workspace/catalog?flow=infrastructure&airport=${airport}${type?'&kind='+type:''}`;
  const page=await context.newPage();await page.setViewportSize({width,height:1000});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const response=await page.goto(base+path,{waitUntil:'load',timeout:120000});assert.equal(response.status(),200);assert(!page.url().includes('/login'));
  await page.waitForFunction(()=>document.documentElement.classList.contains('js'));
  const layout=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,text:document.querySelector('main').innerText}));
  assert.equal(layout.overflow,false,`${path} ${width}: overflow`);assert.deepEqual(errors,[]);assert(!/Dữ liệu (demo|mô phỏng|trình bày)|LOCAL TEST SCENARIO/i.test(layout.text));
  if(detail){assert.equal(await page.locator('h1').innerText(),selected.display_name);assert(layout.text.includes(selected.code));}
  else{
   const cells=page.locator('.airport-table tbody tr td:first-child');assert.equal(await cells.count(),Math.min(rows.length,12));
   for(const cell of await cells.all()){
    const code=await cell.locator('small.infrastructure-code').innerText();const raw=rows.find(r=>r.code===code);assert(raw,`Unexpected code: ${code}`);
    assert.equal(await cell.locator('a.airport-name strong').innerText(),raw.display_name);
    assert((await cell.locator('a.airport-name').getAttribute('href')).includes(`/items/${raw.id}`));
   }
  }
  if(!type||['COUNTER','BELT','JET_BRIDGE'].includes(type)){
   const image=`${folder}/all-airports-${airport}-${type||'all'}-${detail?'detail':'list'}-${width}.png`;
   await page.screenshot({path:image,fullPage:true});result.screenshots.push(image);
  }
  result.browserChecks.push({airport,type,width,detail,status:200,overflow:false,errors});persist();await page.close();
 };
 // Counts/API are checked for every airport. UI covers all ten types on both viewport sizes,
 // and representative list/detail navigation at BMV, HAN and SGN through the actual server.
 for(const airport of ['BMV','HAN','SGN'].filter(a=>expected.matrix.some(row=>row.airport===a))){
  for(const width of [1440,390])await ui(airport,null,width);
 }
 const representative=expected.matrix.some(a=>a.airport==='HAN')?'HAN':expected.matrix[0].airport;
 for(const type of Object.values(groups).flat()){
  await ui(representative,type,1440);await ui(representative,type,390);await ui(representative,type,390,true);
  console.log(JSON.stringify({stage:'browser-type',airport:representative,type,checks:result.browserChecks.length}));
 }
 for(const airport of ['BMV','SGN'].filter(a=>expected.matrix.some(row=>row.airport===a)))for(const type of ['COUNTER','BELT','JET_BRIDGE'])await ui(airport,type,1440,true);
 const counter=expected.records.find(r=>r.airport_code===representative&&r.type_code==='COUNTER'&&!r.name);
 for(const q of [counter.code,counter.display_name]){const data=await json(`/api/v1/airport-infrastructure?airport=${representative}&q=${encodeURIComponent(q)}`);assert(data.items.data.some(row=>row.canonical_id===counter.id));result.searchChecks++;}
 result.done=true;persist();console.log(JSON.stringify({done:true,httpAirports:result.httpAirports.length,browserChecks:result.browserChecks.length,searchChecks:result.searchChecks}));await browser.close();
})().catch(e=>{console.error(e.message);process.exit(1)});
