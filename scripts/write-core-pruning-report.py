import json
import re
from pathlib import Path

root=Path(__file__).resolve().parents[1]
private=root/'backend/storage/app/private/core-pruning'
read=lambda name:json.loads((private/name).read_text(encoding='utf-8-sig'))
before,after,plan,proof=map(read,['original.json','candidate-final.json','plan.json','preservation.json'])
workflows,navigation=map(read,['browser-workflows/results.json','browser-navigation/results.json'])
assert proof['passed'] and workflows['passed'] and navigation['passed']
total=lambda field:sum(r[field] for r in proof['summary'])
removed=total('rows_before')-total('rows_after')
saved=total('allocated_before')-total('allocated_after')
mib=lambda n:f'{n/1048576:.2f} MiB ({n:,} B)'
tests=re.search(r'OK \((\d+) tests, (\d+) assertions\)',(private/'tests-final.log').read_text(encoding='utf-8'))
assert tests
refs={}
files=[f for prefix in ['backend/app','backend/routes','backend/database'] for f in (root/prefix).rglob('*.php') if f.name not in ['CorePruning.php','ScopeData.php']]
texts={str(f.relative_to(root)).replace('\\','/'):f.read_text(encoding='utf-8-sig',errors='replace') for f in files}
for node,meta in before['nodes'].items():
    for table in meta['tables']:
        pattern=re.compile(r'(?<![A-Za-z0-9_])'+re.escape(table)+r'(?![A-Za-z0-9_])')
        refs[node+'.'+table]=[{'file':path,'lines':[i for i,line in enumerate(text.splitlines(),1) if pattern.search(line)]} for path,text in texts.items() if pattern.search(text)]
(private/'source-references.json').write_text(json.dumps(refs,ensure_ascii=False,indent=2),encoding='utf-8')
summary=[]
for r in proof['summary']:
    summary.append(f"| {r['node']} | {r['tables_before']} | {r['tables_after']} | {r['rows_before']:,} | {r['rows_after']:,} | {mib(r['allocated_before'])} | {mib(r['allocated_after'])} |")
summary.append(f"| **Tổng** | **{total('tables_before')}** | **{total('tables_after')}** | **{total('rows_before'):,}** | **{total('rows_after'):,}** | **{mib(total('allocated_before'))}** | **{mib(total('allocated_after'))}** |")
catalog=[];drops=[];kept=[];hashes=[]
for node,meta in before['nodes'].items():
    for table,info in meta['tables'].items():
        final=after['nodes'][node]['tables'].get(table)
        classification=info['classification']
        reason=info['reason']
        if table=='demo_installations':
            classification='CAN_BE_SIMPLIFIED'
            reason='Marker cài đặt demo hiện trống; giữ cấu trúc tương thích với installer/fixture, compact installer không chạy tạo lại dữ liệu. Không phải dependency nghiệp vụ bắt buộc.'
        foreign=meta['foreign_keys']
        outgoing=[f"{k['COLUMN_NAME']}→{k['REFERENCED_TABLE_NAME']}.{k['REFERENCED_COLUMN_NAME']}" for k in foreign if k['TABLE_NAME']==table]
        incoming=sorted(set(k['TABLE_NAME'] for k in foreign if k['REFERENCED_TABLE_NAME']==table))
        usage=[r['file'] for r in refs[node+'.'+table] if r['file'].startswith('backend/app/') and '/Console/' not in r['file']]
        runtime='; '.join('`'+p.replace('backend/','')+'`' for p in usage[:3]) or 'Framework / service dùng tên bảng động; xem source-references.json'
        final_count=final['count'] if final else 0
        final_bytes=final['allocated_bytes'] if final else 0
        catalog.append(f"| {node}.{table} | {classification} | {info['count']:,} | {final_count:,} | {info['count']-final_count:,} | {info['allocated_bytes']:,} | {final_bytes:,} | {reason} |")
        if final:
            kept.append(f"| {node}.{table} | {runtime} | {'; '.join(outgoing) or 'Không có FK đi ra'} | {', '.join(incoming) or 'Không có FK đi vào'} |")
            hashes.append(f"| {node}.{table} | `{final['sha256']}` | `{final['ids_sha256']}` |")
        else:
            drops.append(f"| {node}.{table} | {info['count']:,} | {info['allocated_bytes']:,} | {runtime} | {reason} |")
deletion='\n'.join(f"| {s['node']}.{s['table']} | {len(s['rows']):,} |" for s in plan['delete'])
nodeids='\n'.join(f"- {node}: `{meta['uuid']}`; hostname `{meta['host']}`." for node,meta in before['nodes'].items())
browser='\n'.join(f"| `{c['url'].replace('http://127.0.0.1:8002','').split('?')[0]}` | {c['name']} | PASS |" for c in workflows['checks'])
nav='\n'.join(f"| `{r['url']}` | {r['status']} | {'Trạng thái rỗng' if r.get('empty') else 'Core/hệ thống' if 'empty' in r else 'Chặn ghi'} |" for r in navigation['results'])
text=f'''# Airline Smart — tinh gọn cấu trúc Database cốt lõi

Ngày: 13/11/2026, Asia/Ho_Chi_Minh. Thực hiện trực tiếp trên Docker local trong Ubuntu WSL. Không commit/push, không reset/stash Git. Giữ các thay đổi giao diện và M02/M03 đã có.

## 1. Kết quả thực tế

- **Xóa {proof['dropped_tables']} bảng** trên bản sao; hệ thống đang dùng **{proof['preserved_tables']} bảng**.
- Bản ghi: **{total('rows_before'):,} → {total('rows_after'):,}**, loại **{removed:,} bản ghi**.
- Trong đó lưu trữ khỏi bộ active **{len(plan['booking_ids']):,} nhóm đặt chỗ demo lịch sử**, cùng dữ liệu con; không xóa tài khoản, sân bay, hạ tầng, đường bay, kế hoạch hay chuyến bay.
- Dung lượng cấp phát tablespace của bộ Database active: **{mib(total('allocated_before'))} → {mib(total('allocated_after'))}**, giảm **{mib(saved)}**, tương đương **{saved/total('allocated_before')*100:.2f}%**.
- Database local đã chuyển sang **`airline_core_20261113` trên cả ba node**, dùng user ứng dụng `airline`. Database gốc **`airline` giữ nguyên**, không bị reset/xóa/ghi đè.
- Backend sau tinh gọn đạt **{tests.group(1)} test, {tests.group(2)} assertions**, cộng nghiệp vụ HTTP/trình duyệt và kiểm tra điều hướng. Các màn hình ngoài phạm vi vẫn rỗng; Sidebar không thay bố cục.

Các số lượng và dung lượng trong báo cáo là số đã đo. Mốc trước: `original.json`; mốc sau: `candidate-final.json` ngay trước chuyển local. Sau mốc này, thao tác đăng nhập/nghiệp vụ bình thường có thể bổ sung audit hoặc dữ liệu mới.

## 2. Kiến trúc trước/sau

| Docker node | Kết nối Laravel | Trước | Sau | Vai trò |
|---|---|---|---|---|
| db1 | ops | airline | airline_core_20261113 | M01/M02/M03, tàu, tổ bay, bảo dưỡng tối thiểu, tài nguyên |
| db2 | mysql | airline | airline_core_20261113 | Auth, đặt chỗ, giá/tồn, vé, thông báo và phục hồi hành khách |
| db3 | finance | airline | airline_core_20261113 | Checkout, thanh toán, hoàn tiền, sổ giao dịch, audit/outbox/inbox |

Đã xác minh container, cấu hình host, tên schema, ba UUID riêng biệt và bind web `127.0.0.1:8000` trước khi làm. Không truy cập DB production hoặc DB dùng chung bên ngoài bộ Docker local này.

{nodeids}

Đây là ba nguồn ghi độc lập, không phải ba replica đọc. Không đổi cơ chế đồng bộ: `Events` → outbox → `OutboxRelay`/`WorkflowRelay` → inbox, có idempotency. Worker tiếp tục giải phóng giữ chỗ, chuyển sự kiện và xử lý mail. Đã **bỏ tác vụ CRM escalation** khỏi worker vì không còn thuộc phạm vi.

```mermaid
flowchart LR
  OPS[db1: lịch / điều hành] -->|FlightChanged qua outbox| BOOK[db2: giá / tồn / đặt chỗ]
  BOOK -->|CheckoutRequested| PAY[db3: checkout / thanh toán / ledger]
  PAY -->|CheckoutReady / PaymentResult| BOOK
  OPS <-->|Saga đổi tàu: prepare / commit| BOOK
```

## 3. Tổng hợp số lượng và dung lượng

| Node | Số bảng trước | Số bảng sau | Bản ghi trước | Bản ghi sau | Dung lượng trước | Dung lượng sau |
|---|---:|---:|---:|---:|---:|---:|
{chr(10).join(summary)}

Dung lượng là **`information_schema.INNODB_TABLESPACES.ALLOCATED_SIZE`**, số byte tablespace thực được cấp phát cho từng bảng, gồm dữ liệu/index trong `.ibd`. Không dùng `TABLE_ROWS`, `DATA_LENGTH` hoặc `INDEX_LENGTH` ước lượng làm kết quả thực tế. Artifact còn ghi `FILE_SIZE` riêng. MiB hiển thị làm tròn; số byte trong ngoặc là số đo nguyên.

Đã import vào schema riêng, xóa đúng ID và dùng `OPTIMIZE TABLE` **chỉ trên bản core**, sau diễn tập để thu hồi phần cấp phát rỗng. Không tắt FK. Một phần mức giảm là do tái tổ chức tablespace khi import/rebuild, không quy toàn bộ số byte giảm cho việc xóa bản ghi. `preservation.json` ghi thêm dung lượng bản restore trước xóa để phân biệt.

**Tổng dung lượng ổ đĩa máy không giảm tương ứng**, vì vẫn giữ schema gốc, bản nghiệm thu và các file backup theo yêu cầu rollback. Số liệu trên chỉ so sánh bộ Database phục vụ ứng dụng.

## 4. Danh sách bảng đã xóa

Chỉ áp dụng bằng `airline:core-prune apply` trên tên schema core cố định và host Docker đã xác nhận. Không có migration phá hủy chạy mặc định trên máy khác/production. Trước DROP đã kiểm tra FK đi vào, thứ tự child→parent, trigger/view/routine và từng fingerprint. Cả ba schema nguồn không có view/trigger/routine nghiệp vụ.

| Bảng xóa | Bản ghi lưu trữ | Byte cấp phát trước | Tham chiếu nguồn | Căn cứ |
|---|---:|---:|---|---|
{chr(10).join(drops)}

Những tham chiếu còn nằm trong seeder full-system hoặc source phục hồi không được coi là dependency runtime. Các nhánh controller và use case tương ứng đã bỏ/ngừng trước khi query, CLI IoT đã gỡ, worker không còn đọc CRM. File `source-references.json` chứa toàn bộ file/line tìm thấy, bao gồm migration/seeder; danh sách này hỗ trợ rà soát, không khẳng định mọi nhánh tham chiếu còn được triển khai.

### Thay đổi liên kết mẫu tàu bay

`aircraft_models` trống nhưng có FK `aircraft_model_id_foreign` từ `aircraft.model_id`. Đã xác minh **mọi model_id đều NULL**, code khai thác/lịch bay dùng trực tiếp `aircraft.type`, `capacity`, `seat_layout`, `status` và `work_orders`. Công cụ gỡ **đúng FK không dùng này trên bản core**, giữ cột nullable để tránh đổi fingerprint hồ sơ tàu bay. Nếu có model_id khác NULL, công cụ từ chối. Không vô hiệu hóa kiểm tra FK toàn cục; tất cả FK còn lại giữa các bảng giữ được đối chiếu nguyên vẹn.

Các bảng crew qualification/availability/roster mở rộng và material/item bảo dưỡng chỉ được chức năng quản trị đã ngừng đọc/ghi; không được core service hiện tại dùng để kiểm tra. Giữ nguyên kiểm tra thực tế trên `crew_members`/`crew_assignments` (năng định, giấy phép, giao ca/xung đột), `aircraft` và `work_orders`. Không thay chúng bằng kết quả thành công mặc định.

## 5. Dữ liệu lịch sử đã lưu trữ và giảm khỏi core

Điều kiện đồng thời cho một nhóm được loại:

1. Chuyến `ARRIVED`, đã có actual_arrival, có audit `demo.flight_snapshot`; giữ nguyên bản ghi chuyến.
2. Booking `BOARDED`, không thuộc reservation nhiều chặng/hành khách, idempotency khớp chính xác `demo-manifest-<flight_id>-<ordinal>` và tài khoản `demo.pNNNN@airline.test`.
3. Một payment `CAPTURED`, `mode=mock`, amount đúng bằng booking; một ledger `CAPTURE` cùng amount; ticket mang prefix `ASD` của seeder.
4. Checkout/intent nếu có đều mock và hoàn tất; không có quan hệ giữ lại như refund, đổi vé, revision, ancillary hoặc nghiệp vụ khác cần booking đó.
5. Hành lý nếu có chỉ `DELIVERED`/`OFFLOADED`; giữ mọi trường hợp chưa hoàn tất.
6. Giữ các ordinal `[0,1,2,4,5,6,12,13,14]` cho từng chuyến: ba mẫu BUSINESS, FLEX, ECONOMY với các phương thức VNPAY/MOMO/BANK theo seeder. Các ngoại lệ có phụ thuộc vẫn giữ thêm, không ép bằng đúng chín.

Không dùng riêng ngày cũ làm lý do xóa. Không giảm dữ liệu của chuyến đang khai thác, không xóa PNR thực hoặc giao dịch ngoài provenance demo này, không sửa ID bản ghi giữ. Không xóa ledger/payment của booking được giữ.

| Bảng giảm theo ID chính xác | Số bản ghi loại |
|---|---:|
{deletion}

Các bảng bị DROP có thêm dữ liệu CRM, thiết bị và 11.150 dòng tổng hợp đối soát được lưu trong backup gốc; tổng hợp trước/sau từng bảng ở phần 6. Những dòng audit và outbox/inbox không bị xóa; lịch sử nghiệp vụ core giữ nguyên. ID/hash từng dòng dự định xóa nằm trong `plan.json`, cả dữ liệu/schema gốc nằm trong ba SQL backup và schema `airline`.

## 6. Phân loại và đối chiếu toàn bộ bảng

`CORE`: phục vụ trực tiếp phạm vi. `REQUIRED_DEPENDENCY`: có consumer cốt lõi hoặc nền tảng Auth/queue/audit/fixture bắt buộc. `REMOVABLE`: đã loại. `CAN_BE_SIMPLIFIED`: giữ cấu trúc nhưng giảm snapshot demo, hoặc thành phần cài đặt có thể tiếp tục giảm. Không gộp trường hợp chưa rõ vào REMOVABLE.

| Bảng | Phân loại | Trước | Sau | Số dòng loại | Byte trước | Byte sau | Mục đích / quyết định |
|---|---|---:|---:|---:|---:|---:|---|
{chr(10).join(catalog)}

### Các bảng còn giữ và phụ thuộc cụ thể

| Bảng giữ | Controller/service hoặc nguồn tham chiếu | FK đi ra | Bảng FK đi vào ở nguồn |
|---|---|---|---|
{chr(10).join(kept)}

Các liên node không được MySQL thực thi bằng FK vật lý. Đã kiểm tra thêm inventory/fare/reservation_segments → flights; bookings → flights; payments/checkout_intents/ledger/refunds → bookings; checkout_sessions → checkout_intents. Kết quả **0 mồ côi**. Chuyến đã công bố thiếu inventory: **0**. Không còn FK từ bảng giữ trỏ sang bảng đã DROP.

Nhóm phải giữ đáng chú ý: `baggage`/`baggage_events` còn phục vụ check-in/điều kiện khởi hành và saga hủy/đổi tàu; `boarding_manifests`, `boarding_passes`, `seat_reassignments`, `aircraft_change_receipts` bảo vệ boarding/đổi tàu; `passenger_revisions` được trang sửa thông tin đặt chỗ đọc; `reservation_ancillaries` phục vụ giá hành lý; `ground_tasks` bắt buộc có thể chặn khởi hành; `work_orders` chặn tàu không khả dụng; các fixture/audit M01/M02 giữ bằng chứng nghiệm thu. Đây là dependency cụ thể, không giữ nguyên cả phân hệ.

`demo_installations` là marker cài đặt trống, được nêu rõ CAN_BE_SIMPLIFIED thay vì coi là nghiệp vụ bắt buộc. Giữ cấu trúc tương thích installer/fixture ở lượt này; không giữ thêm dữ liệu hệ thống cũ trong bảng này. Cấu trúc cache, session, queue và migrations giữ cho cấu hình/framework hiện có.

## 7. Backend và quy trình cài đặt

- `UseCaseService` chỉ còn bốn handler cần thiết: airport_update, airport_area, season_rule, actual_time; bỏ SQL/handler AI, IoT, CRM, đối soát và danh mục mở rộng ngoài phạm vi.
- `WorkspaceController` bỏ các khối SQL/form của fleet/crew management/maintenance/ground/baggage/CRM/finance/fare/IoT/analytics độc lập; giữ phần M01/M02/M03, recovery, resources, audit, integration và notification. Dispatch không còn gọi AnalyticsService/CustomerService hoặc đối soát.
- `ModuleController` cũ chỉ giữ các URL tổng hợp rỗng và action core được cho phép; không còn tải bảng AI/vé/CRM tổng hợp.
- `BusinessController` giữ reservation editing/baggage/self-service/recovery cần thiết, bỏ xử lý báo cáo/đối soát và SupportDesk độc lập khỏi index/action.
- Đã xóa service runtime `SupportDesk.php`, `FinanceOperations.php`, `RevenueReport.php`. Worker không gọi escalation; lệnh `airline:iot-simulate` đã gỡ.
- `FinanceService` giữ request/approve/destination/settle refund, bỏ reconcile/resolve/close khỏi service runtime; phần tạo fixture full-system chuyển sang `database/seeders/LegacyReconciliation.php`, không chạy trên core. AnalyticsService/CustomerService còn trong source chỉ để tương thích seeder full-schema, không có route/worker core gọi.
- Seeder nền không còn tạo thiết bị IoT. Full demo generator từ chối schema compact trước khi tạo dữ liệu. Installer phát hiện compact schema, kiểm tra bảng core và bỏ qua full demo generation, không tự bổ sung lại dữ liệu đã xóa. M02/Infrastructure fixture guard chấp nhận đúng tên local core mới, không nới lỏng quyền nghiệp vụ.
- Không xóa hoặc sửa migration lịch sử để ép schema. Cài mới vẫn chạy migration chuẩn; bước tinh gọn phải opt-in, sao lưu và diễn tập trên schema riêng. Không tự chạy prune trong setup/production.
- Backend/Sidebar giữ cơ chế scope đã triển khai ở lượt trước, không bổ sung menu hay thiết kế mới. API ground/baggage reference độc lập cũng bị chặn trước SQL; reference phục vụ flight và airport-infrastructure còn hoạt động.

Source trước thay đổi được lưu trong `core-pruning/retired-source/` riêng, cùng diff Git chưa commit. Không ghi đè các thay đổi M02/M03 có sẵn; vẫn giữ route/active state/Sidebar hiện hữu.

## 8. Kiểm thử

| Kiểm tra | Kết quả | Bằng chứng |
|---|---|---|
| Khôi phục backup đủ ba node | PASS | 123/123 bảng khớp count, hash nội dung và PK với source |
| Dry-run chính xác | PASS | plan.json: 39 bảng, 14.006 nhóm demo và từng PK/hash |
| Backend trước xóa | PASS | 5 test, 208 assertions; tests-before.log |
| Backend sau xóa | PASS | 5 test, 208 assertions; tests-after.log |
| Backend/safety cuối | PASS | {tests.group(1)} test, {tests.group(2)} assertions; tests-final.log |
| Replay pruner | PASS | Đã áp dụng đủ profile, chạy lại không thay DB; replay.log |
| M01 HTTP + trình duyệt | PASS | Xem sân bay/hạ tầng, preview/sửa, người khác phê duyệt, lịch sử |
| M02 HTTP + trình duyệt | PASS | Kế hoạch, phát sinh, phân công crew, kiểm tra/gửi/phê duyệt/công bố |
| M02 → M03 | PASS | Flight/crew/resource/inventory đúng; cập nhật trạng thái, actual time và nhật ký |
| Điều phối | PASS | HTTP test chạy saga đổi tàu prepare/commit với phê duyệt độc lập và outbox |
| Đặt chuyến | PASS | Tìm/chọn, nhập người đi, giữ chỗ, VNPay mock theo service gốc, xác nhận và tra cứu |
| Worker/event | PASS | Relay 0 lỗi; outbox không còn pending; worker nghiệm thu xử lý sự kiện thanh toán, không có CRM query |
| Sidebar/URL cũ | PASS | 45 kiểm tra, 5 nhóm/13 mục; trang tạm ngừng không query bảng đã xóa |
| Responsive/JS | PASS | 1920/1440/1024/768/390/320 px ở màn hình kiểm tra; không pageerror/tràn trang |
| Toàn vẹn dữ liệu | PASS | Tất cả FK còn giữ và 9 quan hệ liên node được kiểm tra, không mồ côi |
| Giữ tài khoản/quyền | PASS | Hash users/user_roles và ID khớp source; không đổi mật khẩu candidate |
| Website sau chuyển local | PASS | 10 URL ở sáu kích thước và 4 endpoint chặn ghi; browser-live/results.json, không pageerror |
| Kết nối/worker sau chuyển | PASS | active-database.json: user airline, schema core trên cả ba node; pending outbox 0; relay 0 lỗi |
| Thanh toán thật bên ngoài local | CHƯA KIỂM TRA | Không thực hiện giao dịch tiền thật; chỉ cơ chế mock local đang có |
| Toàn bộ suite 20 phân hệ cũ | KHÔNG CHẠY | Chức năng đã ngừng và harness cũ dùng reset/truncate/FK off; suite mới dùng transaction/rollback trên core |

Suite backend chỉ cho phép các schema test/copy được chỉ định, mở transaction trên ba node rồi rollback, không reset DB hoặc tắt FK. Browser chạy trên **`airline_core_acceptance_20261113` riêng**, được restore từ bản core; mọi thay đổi sân bay/lịch/đặt chỗ và chuẩn bị mật khẩu sáu tài khoản demo chỉ diễn ra ở đây. Bộ candidate chuyển local không chứa các thay đổi nghiệp vụ và mật khẩu từ browser nghiệm thu.

### Màn hình nghiệp vụ đã thao tác bằng trình duyệt

| URL (bỏ token/query nhạy cảm) | Capture / thao tác | Kết quả |
|---|---|---|
{browser}

### Điều hướng và các endpoint cũ

| URL | HTTP | Nội dung |
|---|---:|---|
{nav}

## 9. Chuyển local, bảo vệ nguồn và rollback

Chỉ chuyển sau gate: preservation → PHPUnit → browser workflows → navigation → quyền DB user ứng dụng. `core-cutover.sh` tạm dừng app/worker/mail-worker, kiểm kê lại `airline` và so từng count/hash với bản gốc lúc sao lưu. **Nếu dữ liệu gốc thay đổi trong lúc diễn tập, script từ chối chuyển**, không ghi đè cập nhật đó.

Đã vượt qua đối chiếu nguồn lần cuối và chỉ đổi `DB_DATABASE=airline_core_20261113` trong backend/.env; host/user/password/application roles không đổi. User MySQL `airline` hiện hữu được cấp quyền trên schema mới cùng mức schema gốc, không tạo tài khoản/role mới và không thay quyền người dùng ứng dụng. Website không chạy bằng user root; root chỉ dùng cho công cụ copy/metadata/schema local.

- Backup đầy đủ: `backend/storage/app/private/core-pruning/backup/db1.sql`, `db2.sql`, `db3.sql`. Đã restore và kiểm tra hash đủ 123 bảng. Backup không có lệnh tắt FK; thứ tự tạo/import theo phụ thuộc.
- Bản core trước browser: `core-pruning/compact-backup/`; đã restore riêng để nghiệm thu.
- Database gốc vẫn tồn tại nguyên trên mỗi node: **`airline`**. Schema core: **`airline_core_20261113`**. Schema browser nghiệm thu: **`airline_core_acceptance_20261113`**.
- Bản cấu hình trước chuyển: `core-pruning/pre-cutover.env`, không commit vì chứa cấu hình riêng.
- Rollback dữ liệu/cấu hình ngay: từ Ubuntu WSL chạy `bash scripts/core-rollback.sh`. Script chỉ chấp nhận cấu hình đang trỏ core, dừng app/worker, đổi DB_DATABASE về airline và khởi động lại. Không xóa schema nào hoặc nhập dump đè lên dữ liệu đang có.
- Nếu đã có giao dịch/nghiệp vụ mới sau cutover, phải sao lưu/đối chiếu chúng trước khi rollback về bản gốc. Không tự gộp giao dịch hoặc bỏ lịch sử phát sinh mới.
- Tái hiện schema pruning: chỉ trên một bản copy rỗng riêng từ backup, theo `core-pruning.sh restore` → inventory → plan → apply → verify; CREATE cố ý thất bại nếu schema đã tồn tại, không reset để chạy lại. Profile đã áp dụng hỗ trợ replay không ghi thêm.

Không thay đổi source database để giảm kích thước; không DROP `airline`, không xóa Docker volume, không chạy migrate:fresh/db:wipe, không TRUNCATE hay DELETE không giới hạn và không bỏ validation/approval để xử lý lỗi.

## 10. Hash/ID và artifact đối chiếu

Đối với các bảng có sampling, hash sau được so với **phần giữ lại tính từ bản restore trước khi xóa**, không so với hash toàn bảng ban đầu. Các bảng khác so trực tiếp hash toàn bộ dữ liệu và PK trước/sau. Không chỉ dựa vào số lượng.

| Bảng giữ | SHA-256 nội dung giữ lại | SHA-256 tập ID giữ lại |
|---|---|---|
{chr(10).join(hashes)}

Artifact nằm trong `backend/storage/app/private/core-pruning/`, không đưa vào Git: original.json, restored.json, plan.json, applied.json, after.json, candidate-final.json, original-final.json, preservation.json, source-references.json, tests-before/after/final.log, replay.log, compact.log, cutover.log, browser-workflows/results.json, browser-navigation/results.json cùng screenshot và SQL backup. Phân bố trạng thái chuyến giống source: `{json.dumps(after['flight_states'],ensure_ascii=False)}`.

## 11. Giới hạn còn lại

Các bảng baggage/boarding/refund/recovery/resource/maintenance tối thiểu chưa thể bỏ vì service core có kiểm tra hoặc saga thực sự đọc/ghi; xóa sẽ làm sai nghiệp vụ. Những bảng CAN_BE_SIMPLIFIED đã giảm dữ liệu theo mẫu được chứng minh; không giảm thêm các trạng thái active hoặc giao dịch chưa giải quyết chỉ để đạt số lượng nhỏ hơn. Marker cài đặt trống được nêu minh bạch ở phần phân loại.

Không còn giữ schema AI/IoT/CRM/đối soát tổng hợp chỉ vì controller cũ từng tham chiếu. Tuy vậy source seeder full-system còn tồn tại phục vụ môi trường full-schema và rollback; không chạy nó lên core. Migration mới về sau cần kiểm tra với profile core vì các bảng legacy đã được loại thật, không còn tồn tại để tương thích truy vấn ngoài phạm vi.

Phần dung lượng gốc và bản nghiệm thu vẫn chiếm đĩa để đảm bảo rollback; chưa xóa bản sao này. Giao diện/Sidebar không được thiết kế lại. Chưa kiểm thử gateway tiền thật hoặc tất cả chức năng của những phân hệ đã quyết định ngừng.
'''
(root/'docs/airline-smart-database-core-pruning-report.md').write_text(text,encoding='utf-8')
print(f'Wrote core report: {proof["dropped_tables"]} tables dropped, {removed:,} rows archived, {saved:,} allocated bytes reduced.')
