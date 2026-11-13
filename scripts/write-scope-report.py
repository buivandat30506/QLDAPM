"""Build the handoff from recorded database and browser evidence, without secrets."""
import json
import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
private = root / 'backend/storage/app/private'
read = lambda name: json.loads((private / name).read_text(encoding='utf-8-sig'))
before, after = read('scope-local-before-delete.json'), read('scope-after.json')
plan = read('scope-plan.json')
browser, workflows = read('scope-browser/results.json'), read('scope-workflows/results.json')
final_browser = read('scope-browser-final/results.json')
assert final_browser['passed'] and not final_browser['errors']
receipt = read('scope-rehearsal-receipt.json')
final_tests = re.search(r'OK \((\d+) tests, (\d+) assertions\)', (private / 'scope-tests-final.log').read_text(encoding='utf-8-sig'))
assert final_tests
rows = []
for node, meta in before['nodes'].items():
    for table, info in meta['tables'].items():
        final = after['nodes'][node]['tables'][table]
        changed = (info['sha256'], info['ids_sha256']) != (final['sha256'], final['ids_sha256'])
        assert not changed or (node, table) == ('ops', 'telemetry'), (node, table)
        rows.append(f"| {node}.{table} | {info['classification']} | {info['count']} | {final['count']} | {'Xóa đúng ID trong kế hoạch' if changed else 'Khớp SHA-256 nội dung và ID'} |")
assert all(not any(n['fk_orphans'].values()) for n in after['nodes'].values())
assert not any(after['cross_node_orphans'].values())
pages = '\n'.join(f"| `{r['url']}` | {'Màn hình rỗng' if r['empty'] else 'Giữ hoạt động'} | 200 |" for r in browser['results'] if 'empty' in r)
proof = '\n'.join(f"| {node}.{table} | `{info['sha256']}` | `{info['ids_sha256']}` |" for node, meta in before['nodes'].items() for table, info in meta['tables'].items() if table in ['airports','infrastructure_types','airport_infrastructure','routes','flight_seasons','flight_season_occurrences','flights','flight_schedule_changes','flight_actual_events','flight_changes','master_changes','m02_demo_sets','crew_assignments','resource_allocations','inventory','bookings','reservations','users','user_roles','payments','checkout_intents','audit_logs'])
ids = '\n'.join(f"- `{r['id']}` — SHA-256 `{r['sha256']}`" for r in plan['rows'])
screens = '\n'.join(f"- {c['name']}: `{c['url'].replace('http://127.0.0.1:8002','').split('?')[0]}`" for c in workflows['checks'])
previous_capture_bytes = (private / 'scope-workflows-first-run.log').read_bytes()
previous_capture_log = previous_capture_bytes.decode('utf-16' if previous_capture_bytes.startswith(b'\xff\xfe') else 'utf-8-sig')
for name in ['m01-preview', 'm01-approved-history', 'm02-generation-preview', 'm02-submit-preview', 'm02-publish-preview', 'booking-passenger-details']:
    assert name in previous_capture_log and (private / 'scope-workflows' / f'{name}.png').is_file()
    screens += f'\n- {name}: `scope-workflows/{name}.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).'
text = f'''# Airline Smart — báo cáo tinh gọn phạm vi M01–M03

Ngày thực hiện: 13/11/2026 (Asia/Ho_Chi_Minh). Triển khai trên dự án local Docker trong Ubuntu WSL, cổng 8000. Không commit/push; không reset/stash Git. Các thay đổi M02/M03 có sẵn trước nhiệm vụ được giữ.

## 1. Phạm vi và kết quả

Giữ M01 sân bay và hạ tầng 10 loại; M02 đường bay/kế hoạch/phát sinh/kiểm tra/phê duyệt/công bố; M03 theo dõi/trạng thái/thời gian thực tế/nhật ký/điều phối; Dashboard, đặt chuyến, xác thực, hồ sơ, quyền và cấu hình thiết yếu.

Các trang quản trị độc lập M04–M20 dùng chung `scope-empty.blade.php`: **Chưa có thông tin hiển thị** / **Không có nội dung tại khu vực này trong phiên bản hiện tại.** Thông điệp mô tả phiên bản giao diện, không khẳng định Database không có dữ liệu phụ thuộc.

Đã xóa thực tế **{len(plan['rows'])} bản ghi telemetry cũ**, không xóa bảng. **122/123 bảng giữ nguyên toàn bộ SHA-256 nội dung và tập ID** giữa hai bản kiểm kê ngay trước/sau xóa. Telemetry là bảng duy nhất thay đổi. Không thay ID/mã, không thay trạng thái chuyến hiện có, không xóa audit hoặc tài khoản.

## 2. Database và kiến trúc

| Service | Laravel connection | Database | Vai trò |
|---|---|---|---|
| db1 | ops | airline | Sân bay, hạ tầng, đường bay, lịch, khai thác, tổ bay, tài nguyên |
| db2 | mysql (mặc định) | airline | Xác thực, đặt chỗ, tồn chỗ, vé, thông báo |
| db3 | finance | airline | Checkout, thanh toán, hoàn tiền, sổ giao dịch và đối soát |

Đã xác minh ba `server_uuid` khác nhau bằng `/health/ready` và SQL. Đây là ba nguồn ghi nghiệp vụ độc lập, không phải ba replica đọc. `Events`, `WorkflowRelay`, `OutboxRelay`, `airline:work` chuyển sự kiện qua outbox/inbox và cập nhật giá/tồn chỗ khi công bố hoặc thay đổi chuyến; mail-worker xử lý hàng đợi email. Các service và worker được giữ.

Kiểm kê trực tiếp information_schema gồm bảng, PK, FK (kể cả FK tổng hợp), view, trigger, routine; cả ba node có **0 view, 0 trigger, 0 routine**. Có 56 bảng ops, 51 bảng mysql và 16 bảng finance. Migration lịch sử được giữ nguyên; không thêm migration phá hủy hay thay đổi schema.

### Phân loại toàn bộ bảng và số lượng

Số lượng dưới đây lấy tại cửa sổ bảo trì áp dụng xóa. `REMOVE_SAFE` ở telemetry chỉ áp dụng cho ID đo lường quá 30 phút trong kế hoạch đã thử; không tự động xóa bản ghi mới. Mọi bảng `NEEDS_REVIEW` được giữ nguyên.

| Node.bảng | Phân loại | Trước | Sau | Đối chiếu |
|---|---|---:|---:|---|
{chr(10).join(rows)}

### Dữ liệu phụ thuộc phải giữ

- Tàu bay, mô hình cabin, bảo dưỡng và vật tư/hạng mục: kiểm tra khả dụng và sức chứa; không bỏ kiểm tra chỉ để công bố lịch.
- Nhân sự, năng định, thời hạn giấy phép, phân công, khả dụng: M02 công bố cần tối thiểu phi công/tiếp viên hợp lệ; đổi tàu/tổ bay còn kiểm tra tương thích và xung đột.
- Nhiệm vụ mặt đất bắt buộc và bằng chứng: M03 không được đóng cửa/khởi hành khi chưa hoàn tất.
- M18 tài nguyên/phân bổ, cửa, hạ tầng chuẩn hóa, lịch sử: kiểm tra cửa và xung đột khi công bố/điều phối.
- Giá, hạng vé, tồn chỗ, đặt chỗ, hành khách, checkout, thanh toán, vé và sổ giao dịch: luồng đặt chuyến thực tế.
- Baggage, boarding, thay đổi hành khách, recovery: còn được saga đổi tàu/gián đoạn/hoàn tiền sử dụng; không xóa theo mã phân hệ.
- Audit, outbox/inbox, tài khoản/quyền, fixture M01/M02 và kho lưu chuẩn hóa: bảo toàn khả năng kiểm chứng, đồng bộ và khôi phục.
- Lịch sử hỗ trợ, phân tích, đối soát: chưa đủ căn cứ để xóa an toàn; phân loại NEEDS_REVIEW, kể cả bảng hiện trống. Không ép xóa bảng để giảm số lượng.

### Dữ liệu đã xóa

Nguồn sử dụng telemetry đã rà soát: AnalyticsService (forecast quy tắc, chỉ đọc đo trong 30 phút), màn hình IoT/AI cũ và seeder opt-in. Không có service M01–M03/đặt chuyến, worker/event consumer đang hoạt động đọc telemetry. Telemetry không phải `flight_actual_events` hay audit; không có FK trỏ vào hoặc trigger trên bảng. Giữ `devices` và trạng thái/sequence để không tái sử dụng định danh. Plan khóa cutoff `{plan['cutoff']}` UTC, ID và hash từng bản ghi; apply kiểm tra lại metadata, fingerprint và local topology, dùng transaction, xóa `WHERE id = ...`, hỗ trợ chạy lại không xóa thêm.

{ids}

### Hash bảo toàn dữ liệu cốt lõi

Hash nội dung được tạo từ từng hàng chuẩn hóa key, theo thứ tự PK; hash ID được tạo độc lập. Hai hash dưới đây khớp hoàn toàn trước/sau trên database local. Toàn bộ 123 bảng, PK, FK và hash đầy đủ ở các artifact private nêu bên dưới.

| Node.bảng | SHA-256 nội dung | SHA-256 ID |
|---|---|---|
{proof}

Phân bố trạng thái chuyến trước/sau giống nhau: `{json.dumps(after['flight_states'], ensure_ascii=False)}`.

Toàn vẹn: **0 bản ghi mồ côi ở tất cả FK thực**, bao gồm FK tổng hợp. Các kiểm tra liên node `inventory.flight_id`, `bookings.flight_id`, `fare_classes.flight_id`, `payments.booking_id`, `checkout_intents.booking_id` đều **0 mồ côi** trước/sau. Đây là các quan hệ liên node đã kiểm tra trực tiếp; không coi chúng là FK do MySQL thực thi giữa các server. ID/hash của toàn bộ bảng còn lại được bảo toàn. Audit đăng nhập phát sinh khi kiểm tra trình duyệt trước cửa sổ xóa được giữ trong bản kiểm kê ngay trước xóa.

### Sao lưu, diễn tập và rollback

- Bản sao lưu logic ba node: `backend/storage/app/private/scope-backup/db1.sql`, `db2.sql`, `db3.sql`. Tổng khoảng 60 MB. Đã dừng app/worker/mail-worker khi sao lưu. Export sắp xếp bảng theo phụ thuộc; không dùng DROP/TRUNCATE và không tắt khóa ngoại.
- Đã khôi phục vào database **airline_scope_copy** riêng trên cả ba node, dùng tài khoản DB quản trị vốn có, không tạo/grant quyền mới. **123/123 bảng khớp count, hash nội dung và hash ID** với bản gốc. Đây là chứng cứ khôi phục thực, không chỉ kiểm tra file tồn tại.
- Đã dry-run bằng `airline:scope-data plan`, xóa thử đúng 18 ID trên bản sao, xác nhận 122 bảng còn lại nguyên vẹn, kiểm thử hồi quy sau xóa rồi mới áp dụng local.
- Receipt `scope-rehearsal-receipt.json` kiểm chứng SHA-256 plan, cả ba backup, khôi phục và kết quả kiểm thử. Lệnh apply local bắt buộc receipt; chỉ chấp nhận môi trường local/testing, database airline/airline_scope_copy và host db1 đúng UUID.
- Bổ sung bản sao lưu ngay trước xóa: `backend/storage/app/private/scope-final-backup/`, cùng bản kiểm kê `scope-local-before-delete.json`. Các file private không được commit.
- Rollback dữ liệu đã xóa: dừng app và worker; chạy `docker compose run --rm --no-deps app php tools/scope-telemetry-archive.php restore`. Công cụ dùng `scope-deleted-rows.json`, đối chiếu từng ID/hash với plan, không ghi đè hàng đã thay đổi, chèn trong transaction với FK còn bật; chạy lại không tạo trùng. Đã thử khôi phục 18 hàng trên bản sao, lần hai khôi phục 0 hàng, rồi áp dụng lại plan xóa đúng 18 hàng. Log: `scope-rollback-test.log`. Không chạy lại toàn bộ dump lên database đang có dữ liệu. Bản sao đầy đủ được phục hồi vào **schema rỗng riêng** bằng `scripts/scope-copy.sh restore`; lệnh CREATE cố ý thất bại nếu schema đã tồn tại, không reset schema.
- Rollback giao diện: gỡ middleware EnforceReleaseScope khỏi bootstrap và khôi phục Dashboard bằng diff riêng của nhiệm vụ; source/view/service cũ vẫn còn. Không dùng git reset/stash để tránh ảnh hưởng thay đổi có sẵn.

## 3. Frontend, Sidebar và URL

Giữ nguyên `StaffNavigation` cùng biểu tượng, tên, thứ tự, submenu, related links và kiểm tra quyền. Trình duyệt xác nhận **5 nhóm (tính cả Tổng quan), 13 mục chính**. Không thêm/xóa mục Sidebar do phạm vi mới. Hamburger, active state, Topbar và shell hiện hữu được giữ.

Dashboard dùng dữ liệu thật và kiểm tra vai trò/sân bay: sân bay, đường bay, kế hoạch, lịch chờ duyệt, chuyến đã công bố trong ngày theo UTC+7, chuyến đang khai thác, chuyến chậm, phương án chờ duyệt và thay đổi danh mục. KPI dẫn đến nghiệp vụ tương ứng; không còn KPI tài chính/AI/hỗ trợ/bảo dưỡng hoặc nhãn tình trạng hệ thống hardcode. Mục quản trị tài khoản vẫn giữ.

Các lối phụ thuộc tối thiểu đặt trong Dashboard, không thay Sidebar: `/workspace/planning/crew-assignments` (crew_scheduler), `/workspace/operations/ground-tasks` (ground_agent, chỉ người nhận được thao tác), `/staff/payments` (finance, xác nhận chuyển khoản thật). Màn hình đội bay/tổ bay/mặt đất/tài chính độc lập vẫn rỗng. Không tự cấp role mới.

M03 chi tiết hiển thị thời gian kế hoạch/thực tế, các sự kiện `flight_actual_events`, nhật ký và form ghi nhận giờ qua service hiện có. Giữ các luồng gián đoạn/đổi nguồn lực/hỗ trợ khách là phụ thuộc M03.

Đã sửa lỗi biểu mẫu chung trong `frontend/app.js`: bảo toàn name/value của nút được chọn trước khi vô hiệu hóa nút chống bấm lặp. Trước sửa, nút xác nhận mock thanh toán mất trường `result` và bị validation từ chối; sau sửa, callback ký đúng và phát hành vé theo luồng thật. Cùng sửa này giữ lựa chọn approve/reject trên các biểu mẫu hiện hữu. Đã build lại assets từ source. Audit của sự kiện `flight.actual_time.updated` mới được gắn đúng ID chuyến để xuất hiện trong nhật ký M03; không sửa các audit lịch sử.

Luồng đặt chuyến trên giao diện hiện tại hỗ trợ một hành khách, một chiều/khứ hồi, chọn hạng vé, xem lại lựa chọn, nhập thông tin hành khách/liên hệ, giữ chỗ, chọn thanh toán và tra cứu. Không có bước chọn ghế/dịch vụ bổ sung riêng trong `trip-passengers.blade.php`; không giả định đã có hoặc thêm bước giả. Backend reservation vẫn giữ khả năng nhiều coupon/hành khách theo triển khai hiện hữu.

| URL kiểm tra trực tiếp | Kết quả | HTTP |
|---|---|---:|
{pages}

## 4. Backend và API

Middleware `EnforceReleaseScope` áp dụng sau session và AccountAccess; người dùng chưa đăng nhập/chưa xác minh vẫn qua auth/verified hiện có. Trước khi trả view rỗng, kiểm tra vai trò của trang đích. JSON màn hình tạm ngừng trả metadata/trạng thái cùng `data: []`, không chạy truy vấn dữ liệu nghiệp vụ. GET/HEAD đều được kiểm soát.

Chặn 403 các action quản trị độc lập cũ (tạo tàu, bảo dưỡng, giá vé/hạng vé quản trị, telemetry/AI, đối soát, hỗ trợ độc lập...), bao gồm `/workspace/actions/*`, `/api/v1/actions/*`, REST aliases và `/modules/*/actions/*`. Generic master_propose/master_approve chỉ còn entity sân bay/hạ tầng/đường bay, không thể lách qua entity aircraft/crew. Các export đội bay/tổ bay cũ cũng trả màn hình rỗng.

Giữ action M01–M03 và phụ thuộc cụ thể: lập lịch/season/submit/publish, kiểm tra, sửa lịch, actual_time, flight_state, allocate/unallocate, assign_crew, ground_state, resource_propose/resource_decide, disruption/approve_disruption, recovery_propose/recovery_decide. Service tự kiểm tra quyền, scope, người duyệt độc lập và điều kiện nghiệp vụ như trước.

Không đổi service thanh toán, chữ ký callback VNPay/MoMo, CSRF/session, IPN, giá/tồn chỗ, reserve/pay/cancel, tra cứu hành trình và tài liệu vé. Phương thức mô phỏng thanh toán vẫn là cơ chế local sẵn có; không tự gán trạng thái thành công. Màn hình đối chiếu chuyển khoản tối thiểu dùng GatewayPayments thật; không dùng đối soát tổng hợp tạm ngừng để xác nhận giả.

Lưu ý: `/modules/m1`, `/modules/m2`, `/modules/m3` là các màn hình tổng hợp cũ (core nhiều phân hệ / vé / AI), **không tương ứng M01/M02/M03** trong phạm vi hiện tại. Đã xử lý theo nghiệp vụ thực; M01/M02/M03 hoạt động ở các URL workspace nói trên.

## 5. Kiểm thử và bằng chứng

| Hạng mục | Kết quả | Phạm vi bằng chứng |
|---|---|---|
| Kiểm kê/khôi phục ba node | PASS | 123 bảng khớp nội dung và ID |
| Dry-run/xóa thử | PASS | 18 ID; 122 bảng còn lại khớp hash; replay không xóa thêm |
| Backend trước xóa thử | PASS | 4 test, 194 assertions; gồm kiểm tra stale fingerprint và replay trên telemetry còn dữ liệu |
| Backend sau xóa thử / phiên bản cuối | PASS | Sau xóa thử 5 test/202 assertions; phiên bản cuối {final_tests.group(1)} test/{final_tests.group(2)} assertions; PHPUnit cấu hình tests/scope.xml |
| M01 nghiệp vụ | PASS | HTTP + trình duyệt preview, gửi đề nghị, tài khoản khác phê duyệt, lịch sử; xem hạ tầng |
| M02 nghiệp vụ | PASS | HTTP + trình duyệt tạo kế hoạch, phát sinh, phân công tổ bay, kiểm tra/gửi duyệt/công bố |
| M02 → M03 | PASS | Inventory/phân bổ/tổ bay xuất hiện đúng; trình duyệt cập nhật BOARDING và xem nhật ký |
| Điều phối đổi tàu | PASS | HTTP test thực hiện prepare/commit saga, kiểm tra nguồn lực, phê duyệt độc lập, outbox và kết quả tàu mới |
| Đặt chuyến | PASS | Trình duyệt tìm/chọn chuyến đã công bố, nhập hành khách, giữ chỗ, thanh toán VNPay mock gốc, xác nhận và tra cứu; backend kiểm tra tồn chỗ/giá |
| Sidebar/URL cũ/phân quyền | PASS | {len([r for r in browser['results'] if 'empty' in r])} URL GET trả 200; API ghi độc lập trả 403; passenger không vào trang staff trái quyền |
| Responsive | PASS | 1920/1440/1024/768/390/320 px; không tràn ngang toàn trang ở các màn hình đã ghi nhận |
| JavaScript | PASS | Không có pageerror trong các lượt kiểm tra |
| Kiểm tra website local sau xóa | PASS | 10 URL nghiệp vụ/hỗ trợ ở sáu kích thước, 4 endpoint ghi tạm ngừng trả 403; scope-browser-final/results.json |
| Frontend build | PASS | npm run assets, đồng bộ từ frontend/ theo quy trình repo |
| Blade compile / Git whitespace | PASS | artisan view:cache và git diff --check |
| Gateway live, chuyển khoản tiền thật | CHƯA KIỂM TRA | Không thực hiện giao dịch thật; giữ nguyên cơ chế đối chiếu/chữ ký/service |
| Toàn bộ test suite lịch sử | CHƯA CHẠY | Harness gốc xóa/truncate test database và tắt FK; dùng suite rollback trên bản sao để tuân thủ ràng buộc nhiệm vụ |

Suite mới mở transaction trên cả ba node và rollback từng bài, không reset DB, không tắt FK. Chuẩn bị mật khẩu cho sáu tài khoản demo **chỉ trên bản sao** để đăng nhập trình duyệt; giữ nguyên ID/role/scope. Không đổi mật khẩu hay quyền trên airline local. Container web kiểm thử cổng 8002 được dừng sau nghiệm thu; database copy giữ làm bằng chứng.

Màn hình nghiệp vụ đã chạy bằng trình duyệt trên bản sao sau xóa:

{screens}

Artifact private (không chứa trong Git): `scope-before.json`, `scope-restored.json`, `scope-plan.json`, `scope-copy-after.json`, `scope-local-before-delete.json`, `scope-after.json`, `scope-rehearsal-receipt.json`, `scope-tests-before.log`, `scope-tests-after.log`, `scope-tests-final.log`, `scope-browser/results.json`, `scope-workflows/results.json`; screenshot trong hai thư mục browser/workflows. Hai inventory ngay trước/sau xóa là đối chiếu chính thức. Script tái kiểm tra: `scripts/scope-evidence.py`, `scripts/check-scope-reduction.cjs`, `scripts/check-scope-workflows.cjs`.

## 6. Giới hạn và khôi phục về sau

Database chỉ giảm 18 phép đo IoT cũ; phần lớn dung lượng nằm trong đặt chỗ/vé/thanh toán/lịch sử và các phụ thuộc bắt buộc. Không hứa giảm mạnh dung lượng vật lý InnoDB bằng DELETE; không OPTIMIZE, DROP bảng hoặc xóa volume. Những bảng NEEDS_REVIEW và cấu trúc trống được giữ vì chưa chứng minh được quyền xóa an toàn/tương thích.

Các màn hình rỗng là quyết định phạm vi, không phải kết luận các phân hệ không có dữ liệu. Các API phụ thuộc còn hoạt động vẫn tuân thủ quyền hiện hữu; người dùng chỉ có vai trò thuộc phân hệ tạm ngừng sẽ thấy ít công việc trên Dashboard. Chưa mở rộng vai trò hoặc bỏ điều kiện bảo dưỡng/tổ bay/mặt đất để tạo kết quả thành công.

Không chạy migrate:fresh, db:wipe, TRUNCATE, DELETE không giới hạn, không tắt FK, không xóa migration/volume và không chạm production/database dùng chung. Toàn bộ source/view cũ được giữ để có thể mở lại sau một quyết định phạm vi mới.
'''
(root / 'docs/airline-smart-m01-m03-scope-reduction-report.md').write_text(text, encoding='utf-8')
print('Wrote scope reduction report with all 123 table classifications and before/after hashes.')
