import { test, expect } from '@playwright/test';
const evidence=process.env.TEST_EVIDENCE_DIR||'docs/testing/evidence/2026-10-18-fleet';
async function login(page) {
    await page.goto('/login');await page.locator('[name="username"]').fill('crew_ops_demo');await page.locator('[name="password"]').fill('Demo@12345');
    await page.getByRole('button',{name:'Đăng nhập',exact:true}).click();await expect(page).not.toHaveURL(/\/login$/);
}
async function token(page,username='toan_lead') {
    const r=await page.request.post('/api/v1/auth/login',{data:{username,password:username==='toan_lead'?'Password@123':'Demo@12345'}});expect(r.status()).toBe(200);
    return {Authorization:'Bearer '+(await r.json()).data.token,Accept:'application/json'};
}
test('UAT-UC15: hồ sơ năng định, nghỉ phép, dự bị và hủy khoảng có lịch sử',async({page})=>{
    await login(page);await page.goto('/admin/crew');
    await page.getByLabel('Mã nhân sự',{exact:true}).fill('CB-PROFILE');await page.getByLabel('Họ tên',{exact:true}).fill('Nhân sự tổng hợp UAT');
    await page.getByLabel('Email',{exact:true}).fill('cb-profile@example.test');await page.getByLabel('Điện thoại',{exact:true}).fill('SYNTHETIC');
    await page.getByLabel('Số giấy phép',{exact:true}).fill('UAT-CB-PROFILE');await page.getByLabel('Sân bay cơ sở',{exact:true}).selectOption('HAN');
    await page.getByLabel('Giấy phép hết hạn',{exact:true}).fill('2035-12-31');await page.getByLabel('Đào tạo hết hạn',{exact:true}).fill('2035-12-31');
    const models=page.getByLabel('Năng định dòng tàu',{exact:true});await models.selectOption({label:'A321-200'});
    await page.getByRole('button',{name:'Lưu nhân sự',exact:true}).click();await expect(page.getByRole('heading',{level:1})).toContainText('CB-PROFILE');
    await page.getByLabel('Họ tên',{exact:true}).fill('Nhân sự tổng hợp UAT đã sửa');await page.getByRole('button',{name:'Lưu hồ sơ tổ bay',exact:true}).click();
    await page.reload();await expect(page.getByLabel('Họ tên',{exact:true})).toHaveValue('Nhân sự tổng hợp UAT đã sửa');
    const form=page.getByRole('form',{name:'Lịch khả dụng',exact:true});
    await form.getByLabel('Loại khoảng thời gian',{exact:true}).selectOption('LEAVE');await form.getByLabel('Bắt đầu UTC',{exact:true}).fill('2026-12-10T00:00');
    await form.getByLabel('Kết thúc UTC',{exact:true}).fill('2026-12-12T23:00');await form.getByLabel('Lý do lịch khả dụng',{exact:true}).fill('Nghỉ phép tổng hợp UAT');
    await form.getByRole('button',{name:'Ghi lịch khả dụng',exact:true}).click();
    const table=page.getByRole('table',{name:'Lịch khả dụng đã ghi',exact:true});await expect(table).toContainText('Nghỉ phép tổng hợp UAT');
    await table.locator('input[name="reason"]').fill('Đã đổi lịch nghỉ');await table.getByRole('button',{name:/Hủy khoảng/}).click();
    await expect(table).toContainText('Đã hủy');await expect(table).toContainText('Đã đổi lịch nghỉ');
    await form.getByLabel('Loại khoảng thời gian',{exact:true}).selectOption('STANDBY');await form.getByLabel('Bắt đầu UTC',{exact:true}).fill('2026-12-15T00:00');
    await form.getByLabel('Kết thúc UTC',{exact:true}).fill('2026-12-16T23:00');await form.getByLabel('Lý do lịch khả dụng',{exact:true}).fill('Dự bị tổng hợp UAT');
    await form.getByRole('button',{name:'Ghi lịch khả dụng',exact:true}).click();await page.reload();await expect(table).toContainText('Dự bị tổng hợp UAT');
    await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:evidence+'/uc15-crew-calendar-mobile.png',fullPage:true});
});

test('UAT-UC16-17: đề xuất, phân công, duyệt, xác nhận và thay thế giữ lịch sử',async({page})=>{
    await login(page);const headers=await token(page);
    const allAircraft=(await(await page.request.get('/api/v1/core/aircrafts',{headers})).json()).data.data;
    const model=allAircraft.find(a=>a.tail_number==='VN-D104').model_id;
    const routes=(await(await page.request.get('/api/v1/core/routes?search=HAN-DAD',{headers})).json()).data.data;
    expect((await page.request.post('/api/v1/core/aircrafts',{headers,data:{tail_number:'VN-CB01',model_id:model,manufacture_year:2020}})).status()).toBe(201);
    const gate=await page.request.post('/api/v1/core/airports/HAN/resources',{headers,data:{resource_type:'GATE',resource_code:'CB-GATE',status:'AVAILABLE'}});expect(gate.status()).toBe(201);
    const schedule=await page.request.post('/api/v1/core/schedules',{headers,data:{flight_number:'CB901',route_id:routes[0].id,model_id:model,aircraft_tail:'VN-CB01',resource_id:(await gate.json()).data.id,
        departure_time_utc:'12:00:00',arrival_time_utc:'14:00:00',effective_from:'2026-12-15',effective_to:'2026-12-15',days_of_week:[1,2,3,4,5,6,7]}});expect(schedule.status()).toBe(201);
    const scheduleId=(await schedule.json()).data.id;for(const action of ['submit','approve','publish']){expect((await page.request.post('/api/v1/core/schedules/'+scheduleId+'/'+action,{headers})).status()).toBe(200);}
    const flight=await page.request.post('/api/v1/core/flights',{headers,data:{schedule_id:scheduleId,flight_date:'2026-12-15'}});expect(flight.status()).toBe(201);const flightId=(await flight.json()).data.id;
    for(const [code,rank] of [['CB-CAP','CAPTAIN'],['CB-FO','FIRST_OFFICER'],['CB-RES','CAPTAIN']]){
        const member=await page.request.post('/api/v1/core/crew-members',{headers,data:{crew_code:code,full_name:'Nhân sự tổng hợp '+code,email:code.toLowerCase()+'@example.test',phone:'SYNTHETIC',license_number:'LIC-'+code,
            base_airport_code:'HAN',rank,license_expires_on:'2035-12-31',training_expires_on:'2035-12-31',qualified_model_ids:[model]}});expect(member.status()).toBe(201);
        if(code==='CB-RES'){
            expect((await page.request.post('/api/v1/core/crew-members/'+(await member.json()).data.id+'/availability',{headers,data:{type:'STANDBY',starts_at_utc:'2026-12-15 10:00:00',ends_at_utc:'2026-12-15 17:00:00',reason:'Dự bị tổng hợp UAT',expected_revision:1}})).status()).toBe(201);
        }
    }
    await page.request.post('/api/v1/auth/logout',{headers});await page.getByRole('link',{name:'Phương án tổ bay',exact:true}).click();
    await page.getByLabel('Tìm chuyến',{exact:true}).fill('CB901');await page.getByRole('button',{name:'Tìm chuyến',exact:true}).click();await page.getByRole('link',{name:'CB901',exact:true}).click();
    await page.getByRole('button',{name:'Phân công CB-CAP',exact:true}).click();
    await page.getByLabel('Vị trí cần tìm',{exact:true}).selectOption('FIRST_OFFICER');await page.getByRole('button',{name:'Kiểm tra nhân sự',exact:true}).click();
    await page.getByRole('button',{name:'Phân công CB-FO',exact:true}).click();await page.getByRole('button',{name:'Kiểm tra và duyệt phương án',exact:true}).click();
    await expect(page.locator('[data-assignment-status="APPROVED"]')).toHaveCount(2);
    for(const code of ['CB-CAP','CB-FO']){await page.getByRole('button',{name:'Ghi nhận xác nhận '+code,exact:true}).click();}
    await expect(page.locator('[data-assignment-status="CONFIRMED"]')).toHaveCount(2);
    await page.getByLabel('Thay thế phân công (tùy chọn)',{exact:true}).selectOption({label:'CB-CAP / Cơ trưởng'});
    await page.getByRole('button',{name:'Kiểm tra nhân sự',exact:true}).click();
    await expect(page.getByRole('table',{name:'Nhân sự hợp lệ',exact:true}).locator('tr').filter({hasText:'CB-RES'})).toContainText('Có');
    await page.getByLabel('Lý do thay thế CB-RES',{exact:true}).fill('Thay thế nhân sự theo UAT');await page.getByRole('button',{name:'Thay bằng CB-RES',exact:true}).click();
    await expect(page.locator('[data-assignment-status="REPLACED"]')).toHaveCount(1);await expect(page.locator('[data-assignment-status="ASSIGNED"]')).toHaveCount(1);
    await page.getByRole('button',{name:'Kiểm tra và duyệt phương án',exact:true}).click();await page.getByRole('button',{name:'Ghi nhận xác nhận CB-RES',exact:true}).click();
    await page.reload();await expect(page.locator('[data-assignment-status="CONFIRMED"]')).toHaveCount(2);await expect(page.locator('main')).toContainText('Thay thế nhân sự theo UAT');
    await expect(page.locator('[data-event-action="OPERATOR_RECORDED_CONFIRMATION"]')).toHaveCount(3);await expect(page.locator('[data-event-action="OPERATOR_RECORDED_CONFIRMATION"]').first()).toContainText('Điều hành ghi nhận xác nhận');await page.screenshot({path:evidence+'/uc16-17-approved-replacement.png',fullPage:true});
    const deniedHeaders=await token(page,'data_demo');expect((await page.request.post('/api/v1/core/flights/'+flightId+'/crew/approve',{headers:deniedHeaders})).status()).toBe(403);
    await page.request.post('/api/v1/auth/logout',{headers:deniedHeaders});
});
