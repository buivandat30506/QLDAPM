# Tiến độ triển khai

Cập nhật: 18/10/2026 (Asia/Bangkok). Commit nền `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`; worktree đã có thay đổi của người dùng. Phạm vi đích giữ đủ M01–M16 / UC-01–48, gồm Should/Could Have. Chưa nghiệm thu toàn bộ.

## Tiếp tục phiên 18/10/2026 — BOOK-001

- Thêm `config/booking.php`: profile local-v1, VND, giá cơ sở 150.000.000 minor units (1.500.000,00 VND) và hành lý ký gửi 0 theo cấu hình. Request không còn chấp nhận giá từ client; chỉ nhận lựa chọn `checked_baggage`. BookingService bỏ float, ghi DECIMAL từ minor units; order lưu currency và pricing_profile qua migration additive.
- Đã thêm regression vào `FleetManagementTest::test_booking_and_checkin_use_sellable_seats_and_reject_missing_or_blocked_seat`, gồm giá client giả mạo, tổng tiền, provenance và không tự tạo baggage extra. `php artisan test --filter=test_booking_and_checkin_use_sellable_seats_and_reject_missing_or_blocked_seat` chạy SQLite: 1 test / 14 assertions PASS.
- `php artisan migrate:status --no-interaction` không kết nối được MySQL tại 127.0.0.1:3307 (connection refused); không chạy migration lên DB ứng dụng. PHP lint các file mới/sửa PASS. BOOK-001 còn High/Open do chưa có fare engine/tariff nghiệp vụ được duyệt và chưa chạy migration/integration MySQL.
- Tiếp theo: hoàn tất adapter thanh toán/idempotency + expiry (BOOK-002), QR token hash/revoke/atomic consume (QR-001), rồi mở rộng UAT G3. Không đánh dấu UC-21/22 Verified.

## Tiếp tục phiên 18/10/2026 — M01–M06 regression

- Kiểm kê cho thấy M01/M02/M04/M05/M06 đã có các lát cắt chính và trace ở trạng thái Implemented (không đồng nghĩa Verified); M03 UC-07–09 vẫn InProgress. Các hạn chế thật còn lại: adapter AODB/FIDS/notification live, kết nối MySQL test được cấu hình hiện hành, các race chưa có evidence MySQL, và một số nguồn lực/liên module.
- Sửa lỗi Blade ở `operations/show.blade.php`: các directive `@endcan`/`@endif` nối liền khiến PHP compile sai khi mở trang chi tiết chuyến. Tách directive; test scope chi tiết chuyến chạy lại đạt.
- Sửa fixture kiểm thử đổi cửa để cửa ở sân bay đi của chuyến, và luồng thay đổi lịch để tạo event cảnh báo thật trước khi giải quyết. Assertion đổi lịch kiểm lịch sử `flight_operation_history` theo hành vi domain `RECOVERY_DELAY`.
- `php vendor/bin/phpunit --colors=never --filter='(FoundationWorkflowTest|FlightPlanningTest|FlightOperationsTest|FleetManagementTest|CrewWorkflowTest|MaintenanceWorkflowTest|DiagramWorkflowTest|Module1Test)' --log-junit=docs/testing/evidence/2026-10-18-core/sqlite.xml`: SQLite 93 Passed, 2 Skipped (MySQL CHECK), 1,075 assertions, exit 0, 25.706s. Xem [báo cáo](testing/release-test-report-core-m01-m06.md) và [JUnit](testing/evidence/2026-10-18-core/sqlite.xml); đây là PHP feature regression, chưa thay MySQL/browser UAT.
- `npm run build` PASS (Vite 330ms); `php scripts/lint.php` PASS (229 files, 0 syntax failures); `php scripts/verify-traceability.php` PASS (48 UC/16 module/source flows); `php scripts/verify-api-routes.php` PASS (68 operations); `git diff --check` PASS.
- Bổ sung `docs/api/flight-operations.openapi.yaml` cho M03 (82 OpenAPI operations hiện bao gồm các contract trước đó và M03); `php scripts/verify-api-docs.php` và `php scripts/verify-api-routes.php` đều parse/map đúng 82 routes. FE có contract thực cho flight search/detail, event, alert/recovery, check-in, close-out và outbox retry; demo integrations vẫn được ghi nhãn mô phỏng.
- MySQL gate chưa chạy: port3306 yêu cầu credential không có trong `.env`; port3307 chạy MariaDB 10.4 qua XAMPP nhưng không được dùng làm bằng chứng MySQL. XAMPP MariaDB đã được tắt bình thường sau chẩn đoán. Không migrate/seed lên DB ứng dụng.
- Đã fetch remote an toàn: `origin/feature/toan`=`e196dbf7a7e799a6ad2ebda95368938e036bdc70`, là tổ tiên của local `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`; local có 1 commit chưa push. Worktree vẫn có nhiều thay đổi tracked/untracked chưa commit, gồm công việc đã có trước phiên; chưa stage/commit/push.
- Tiếp theo ưu tiên: cung cấp runner MySQL riêng có quyền CREATE DATABASE/test credential; hoàn thiện và kiểm MySQL/browser cho M03; sau đó chạy race test/resource conflict MySQL và UI UAT. Full M01–M06 chưa nghiệm thu, branch còn dirty và chưa push.

**Checkpoint nền trước phiên:** 16/48 UC Implemented; 13 InProgress; 19 NotStarted. Báo cáo M05 build `9f7945bb` là evidence lịch sử. Phiên hiện hành thêm regression M01–M06, OpenAPI M03 và các evidence SQLite ở bên dưới; MySQL/browser của worktree hiện hành chưa chạy. Chưa nghiệm thu toàn bộ; baseline20Passed/23Not Run, coverageNotMeasured và human UAT Pending. Không dùng số menu làm số nghiệp vụ hoàn thành.

| Giai đoạn | Trạng thái | Chi tiết |
| --- | --- | --- |
| G0 | Hoàn thành kiểm kê/truy vết ban đầu | 48 UC/16 module, priority/actor/BR/NFR và main/alternative/exception nguồn; rules/permission register, schema gap, decisions, baseline W1/W2 riêng namespace. Script verify đã chạy. |
| G1 | Triển khai phần lớn nền; chưa đóng gate | Auth, token revoke/expiry, password change/reset qua adapter local có contract; Gate + scopes theo role; delegation expiry/revoke/recheck grantor; audit redact/correlation/query/export; UI quản trị lưu DB. Còn unit scope, service identities, chứng minh CI cloud và coverage. |
| G2 | InProgress | M01/M02/M04/M06 và M05 safety/workflow có lát cắt đầy đủ theo profile local và hồi quy thực chạy; M03 nền còn thiếu, safety numeric profiles/coverage/load/UAT chưa đóng gate. |
| G3 | Existing Partial + lát cắt M07 đã triển khai | UC-18/19 có portal, consent/history, hồ sơ guest/ownership, hỗ trợ encrypted, chuyển bộ phận nội bộ, update/cancel/complete; feature và browser đã chạy. Booking/check-in/baggage còn nhiều khoảng trống. |
| G4 | InProgress, M05 đã triển khai | M05 UC12–14 đã có workflow/stock/files/approvals; M03 mở rộng, ground, finance và CSKH còn triển khai. |
| G5 | Chưa triển khai | Dashboard đếm DB cũ còn Partial; AI/governance/IoT/integration/config chưa triển khai. |
| G6 | Chưa đóng gate | CI/Compose đã viết; build/lint/MySQL/browser local và restore DB riêng đã chạy. Docker runtime, CI cloud, load, Wokwi, coverage, full acceptance48UC chưa đạt gate. |

## Bằng chứng đầu phiên

- `docs/testing/evidence/2026-10-18/baseline-sqlite.xml` và `.txt`: 23 tests / 180 assertions, exit 0, SQLite in-memory.
- Không chạy migrate/seed trên DB người dùng. Không có test MySQL/browser/Wokwi/coverage/load/restore trong mốc ban đầu.
- Backup mã trước sửa: `storage/app/implementation-baseline-20261018/`.

## Tiếp tục

Đọc master prompt, tài liệu này, traceability.csv và decisions.md; kiểm tra git diff trước chỉnh sửa. Đối chiếu JUnit thực tế, không suy ra Passed từ trạng thái code. Cập nhật kết quả cuối phiên bên dưới.

## Kết quả phiên 18/10/2026

Không reset Git, không xóa file cũ hoặc migrate/seed DB người dùng. Backup đầu phiên giữ nguyên tại storage/app/implementation-baseline-20261018. Đã so SHA256 với đầu phiên: không có file bị mất; .env.example và các phần core không liên quan giữ nguyên thay đổi của người dùng. .env và APP_KEY không bị sửa. Không tạo commit thay người dùng.

Thay đổi chạy được:

- UC-45/46 (Partial): 12 role nguồn + CREW_MEMBER legacy, permission/scope theo từng role, mặc định deny; ADMIN không bypass nghiệp vụ. Quản trị create user/assign roles+scopes/delegation/revoke qua Blade và DB transaction. CLI bootstrap/scope local có audit, không thay password account cũ. Token API 8 giờ/revoke; login session regenerate; password change/reset broker single use và private mailbox local-simulation.
- UC-18/19: hồ sơ cá nhân và guest không ép mọi passenger có account; giữ consent cấp/rút theo version; hỗ trợ có category/flight/department/state, kiểm booking và ownership/airport/flight, encrypted details/resolution. CSKH được cấp quyền thao tác trên queue nội bộ; không giả xác nhận từ đối tác ngoài. Chính sách consent local-policy-v1 là mẫu, cần chủ nghiệp vụ duyệt.
- Bảo vệ API passenger cũ: login/ACTIVE + kiểm owner/scopes; không cho người đặt khác ghi đè hồ sơ theo passport. Chỉ đánh dấu kiểm thử những tình huống đã thực thi; regression legacy overwrite passport cần mở rộng thêm ở G3.
- Sửa lỗi JSON idempotency của flight events trên MySQL (native JSON chuẩn hóa khác chuỗi). Không lưu trường ngoài allowlist vào event payload.
- Sửa race booking do consistent snapshot tạo trước mutex flight: khóa trước khi đọc customer/model/count. Hai tiến trình riêng cùng chờ row lock, capacity=1 chỉ tạo một booking. Các race payment/refund/scanner/publish/crew chưa được suy ra là đạt.
- Blade/Bootstrap/JavaScript local bundle, bỏ Tailwind scaffold khỏi build, có package-lock. PHP syntax/lint, JUnit và browser screenshots dùng dữ liệu tổng hợp.

## Bằng chứng cuối phiên

| Kiểm tra | Kết quả thực thi | Bằng chứng |
| --- | --- | --- |
| Baseline trước sửa | 23 Passed / 180 assertions, SQLite | evidence/2026-10-18/baseline-sqlite.xml, .txt |
| Hồi quy SQLite | 59 Passed, 1 Skipped / 368 assertions | evidence/2026-10-18/g1-sqlite.xml, .txt; skip race vì cần MySQL |
| Hồi quy MySQL thật | 60 Passed / 381 assertions, exit0 | evidence/2026-10-18/mysql.xml, .txt, mysql-environment.json |
| IT-BOOK-RACE-01 | 2 separate connections, 2 lock waiters, 1 created + 1 rejected, capacity1 | evidence/2026-10-18/booking-race.json |
| Browser Chrome/MySQL | 3 Passed, login/roles/audit/CSRF/profile/consent/assistance | evidence/2026-10-18/browser.xml, .txt và PNG |
| PHP syntax | 149 files / 0 failed | evidence/2026-10-18/php-lint.txt |
| Vite build | Exit0, bundle Bootstrap local | evidence/2026-10-18/asset-build.txt |
| Traceability | Đúng 48 UC / 16 module / priority/source flows | evidence/2026-10-18/traceability-check.txt |

Đường dẫn evidence trong bảng tính từ `docs/testing/`. Build fingerprint và file hashes tại `docs/testing/evidence/2026-10-18/build-manifest.json`; commit nền vẫn 0bd32bc..., worktree chưa commit. Các lần fail MySQL ban đầu được giữ riêng (`mysql-first-failed.*`, `mysql-race-first-failed.*`, `mysql-race-wait-observation-failed.txt`) để không che lịch sử. `test-execution-results.csv` lấy từ JUnit cuối; `baseline-mapping.csv` giữ 24 baseline UT + 19 baseline UAT: **18 Passed / 25 Not Run**. Nhãn UT nguồn không biến feature/integration thành unit; báo cáo ghi loại thực. Các alias cùng scenario không được cộng thành số test thực thi riêng.

UAT approval vẫn **Pending**. Coverage **Not Measured** (không có driver). CI workflow GitHub chưa chạy; không nhận Docker/restore/load/Wokwi hoặc toàn bộ 48 UC đã nghiệm thu. Không đồng nhất 60 test với 48 UC hoàn thành.

## Blockers và khoảng trống còn lại

- DB trong .env cổng3307 không kết nối; service MySQL80 cổng3306 từ chối credential hiện có. Đã gỡ chặn kiểm thử bằng instance MySQL8.0.46 riêng, data trong storage/app/mysql-testing, cổng3318. Không đổi credential hoặc service đang có. DB test ngẫu nhiên giữ để đối chiếu; instance tắt sau kiểm thử, có script khởi động lại.
- DOCX nguồn/hình UML chưa có; phần văn bản A–E đã đọc đủ, không chặn triển khai độc lập.
- Payment/airport/BHS/MRO/HRM/ERP/notification/IdP adapters đầy đủ **chưa triển khai**, không gọi đây là lỗi thiếu credentials để giấu việc chưa làm. Local password reset là adapter duy nhất mới, có contract/runbook. G3/G5 tiếp tục local simulator có chữ ký/idempotency/timeout và hướng dẫn sandbox/live.
- Legacy direct payment-confirm và scanner token_hash trả409 để không nhận payment/boarding chưa được xác minh. G3 phải thay bằng workflow payment và opaque QR thật, không để endpoint bị khóa là trạng thái sản phẩm cuối.
- Wokwi UAT chưa có artifact hoặc bằng chứng; AI/IoT/local replay chưa viết. Không tuyên bố Wokwi hoạt động.

## Điểm tiếp tục cụ thể

1. Đối chiếu build-manifest/JUnit với code; không chạy lại initialize-traceability.php vì sẽ bị từ chối khi CSV đã tồn tại. Script update-implementation-evidence.php là mapping của phiên này; phiên sau cần sửa mapping có chủ đích để không hạ trạng thái mới.
2. Hoàn thiện G2 theo source flow: UC-01/02 validation/tài nguyên, UC-03 import/export atomic + CSV formula, mùa lịch/phiên bản/approval và scope theo đối tượng; seat configurations, maintenance plan/work order/release, crew availability/history. Thêm race publish/crew/gate trên MySQL.
3. G3 ưu tiên server pricing bằng money exact, inventory/holds nhiều chặng/expiry job, payment adapter có signed callback/idempotency/late-payment/reconciliation/ticket, change/cancel/refund; sau đó QR opaque/reissue/revoke/wrong-flight/two scanners, baggage segments/incidents/return. Không nhận legacy helpers hiện tại là hoàn thành các UC này.
4. M11/M12/M13, KPI/AI/model registry/IoT/config/integration, seed đầy đủ, OpenAPI toàn bộ API, user guides, Docker/CI/restore/load và baseline Not Run còn lại vẫn trong phạm vi. Giữ nguyên Should/Could Have.

Lệnh tái lập tại [runbooks/implementation-local.md](runbooks/implementation-local.md). Báo cáo release tại [testing/release-test-report.md](testing/release-test-report.md).

## Phiên tiếp tục G2 — 18/10/2026

Đầu phiên đọc AGENTS.md mới ở gốc, kiểm tra ancestor/subdirectory (không có hướng dẫn bổ sung), Git dirty, runtime/lockfiles/README, decisions/defects/trace/progress. Master vẫn 3462 dòng, SHA256 `e098ebc94fc172b14cce7c484702f312ce79f44f62f8be5cb17e5e986a72d4c1`, khớp bản đã đọc toàn bộ phụ lục A–E ở phiên trước. Đọc lại yêu cầu triển khai, UC-01/02/03, baseline C và thứ tự M01 ở D theo từng phần. Không khởi tạo lại repo/reset/commit. Backup trước sửa: `storage/app/session-baseline-20261018-075008/source.zip` + git-status.txt; README trước sửa được lưu trong docs/archive.

`docs/source/` không có. Chưa có các DOCX/hình gốc: `UseCase_va_DacTa (2).docx`, `Đề xuất công nghệ - ERD.docx`, `Bao_cao_kien_truc_tong_the_QL_Hang_Khong_Thong_Minh (1).docx`, `Bao_cao_Activity_Sequence_6_module_dau (1).docx`, `Unit_Test_Plan_Template.docx`, `Acceptance_Test_Plan_Template.docx`. Các phụ lục văn bản đủ để tiếp tục phần độc lập; không tự vẽ lại sơ đồ rồi nhận là nguồn.

Thay đổi:

- UC-01: ICAO unique ở DB/service, IATA bất biến/normalize, timezone IANA, tọa độ nullable cặp có CHECK MySQL; validation tiếng Việt, CRUD có transaction/audit. Danh sách search/filter/sort/pagination và chi tiết/lịch sử thật. Quyền airport/global lọc collection, kiểm từng object trước validation; tạo sân bay mới cần global.
- UC-02: đủ TERMINAL/GATE/STAND/BAGGAGE_CAROUSEL/CHECKIN_COUNTER và RUNWAY legacy. Tạo/sửa mã/trạng thái, tạm ngừng, tìm/lọc/phân trang, lịch sử kể cả tài nguyên đã xóa. Chặn xóa tài nguyên có lịch/chuyến và sân bay còn liên kết. Loại/sân bay sở hữu không bị mass assignment thay đổi.
- UC-03: schema chất lượng v1 CABIN/SERVICE/CURRENCY, validation required/duplicate/IANA/reference; CSV max1MiB/1000records, UTF8/quote strict, preview có lỗi từng record, commit atomic/revalidate dưới mutex, retry không nhân đôi. Lô lỗi không ghi danh mục; lỗi commit được giữ diagnostics. Export có lọc/formula protection; khác header template nhập. Quy trình gửi/duyệt/công bố/trả sửa, actor/time/reason, optimistic revision, published snapshots bất biến; bản sửa không xóa phiên bản đang hiệu lực.
- MySQL concurrency: thêm import/công bố dữ liệu chủ; schedule submissions dùng chung aircraft/gate; crew assignment chồng giờ. Không suy ra payment/expiry/QR/refund đạt. Bỏ eager load model trước mutex gate ở tạo flight; chưa có race trực tiếp riêng cho tạo flight/disruption.
- UI navigation theo quyền, bỏ dropdown tài khoản hardcode/nút logout giả, thêm feedback submit. Giữ view cũ để đối chiếu, route sân bay dùng view foundation mới. Bootstrap local assets vẫn build thật.
- Chạy local bằng DB airline_demo_* mới và private runtime/key/password, không đổi `.env`/APP_KEY của người dùng. Composer setup không sinh lại key đang có; đường giữ key đã chạy và so hash .env không đổi. Nhánh DB cũ mất key/DB mới chưa key cần mở rộng kiểm thử riêng.
- Web/worker/scheduler chạy qua launcher ẩn có PID identity check. Real database queue xử lý RuntimeHeartbeat; readiness kiểm MySQL/schema và heartbeat <=150s. Đây là hạ tầng runtime, chưa là expiry/outbox nghiệp vụ.
- Compose/init/worker/scheduler/volumes đã viết, YAML đọc được; Docker CLI/daemon không có nên build/runtime **Not Run**. OpenAPI M01 có 19 operations, kiểm YAML/local refs/params; chưa là OpenAPI đầy đủ các module.
- Backup/restore rehearsal dùng mysqldump + TAR/PharData trên DB test mới, kiểm fingerprints/FK/decrypt/upload fixture. Không sửa php.ini để thêm ZipArchive, không xóa DB hiện hữu. User guide/dictionary/gap/decisions/permission matrix/runbooks được cập nhật.

Evidence phiên này tách tại `docs/testing/evidence/2026-10-18-g2/`, giữ nguyên evidence G1. Chạy script ghi kết quả mới `scripts/record-session-evidence.php` sau build snapshot + JUnit thực tế; importer G1 đã đóng băng để không ghi đè tiến độ mới. Trạng thái trace chuẩn hóa NotStarted/InProgress/Implemented; approval vẫn Pending, không tự nhận nghiệm thu.

### Kết quả kiểm thử cuối checkpoint G2

Build `6bb83424b2e150bbb28b7a7dda9c4de3837a86ab0ce36d98d728cc970b334461`; base commit không đổi. Hồi quy cuối sau snapshot:

| Kiểm tra | Kết quả thực chạy | Evidence trong docs/testing/evidence/2026-10-18-g2/ |
| --- | --- | --- |
| MySQL suite | **93 Passed /632 assertions**, exit0 | mysql.xml/.txt; DB airline_test_20261018_012810_282279 |
| SQLite suite | **86 Passed /7 Skipped /561 assertions** | sqlite.xml/.txt; skips cần MySQL race/CHECK |
| Chrome/MySQL | **6 Passed**, 20.3s | browser.xml/.txt; DB airline_test_browser_20261018_012810_7fc699 |
| Race booking/master import/master publish | Mỗi ca2 independent sessions/2 waiters; chỉ1 mutation hợp lệ | booking-race.json, import-race.json, master-publish-race.json |
| Race aircraft/gate/crew | 3 ca đạt, mỗi ca2 waiters/1 committed/1 rejected | operations-*-race.json |
| Restore rehearsal | **44 bảng** khớp fingerprint, **52 FK** không orphan, assistance decrypt + upload fixture khớp | restore.json/.txt; target airline_test_restore_20261018_012956_d8b517 |
| Local demo smoke | Đăng nhập bằng generated credentials, role denial, real queue heartbeat, readiness ready | local-demo.json, runtime-pulse.txt, local-demo-master.png |
| PHP/Vite/Composer | 177 PHP files/0 lỗi; build exit0; composer validate đạt | php-lint.txt, asset-build.txt, composer-validate.txt |
| Truy vết/OpenAPI YAML | 48UC/16module;19 operations refs/params; Compose chỉ syntax | traceability-check.txt, api-docs-check.txt |

Baseline nguồn **19 Passed/24 Not Run** (43 hàng). Ledger thêm194 hàng gồm7 Skipped và2 rehearsal, không phải194 test Passed; aliases không cộng thành số lần chạy. Trace hiện **5 Implemented /24 InProgress /19 NotStarted**; không dùng số test thay số UC hoàn thành. Coverage **Not Measured**; Docker runtime/CI cloud/load/Wokwi **Not Run**, UAT human approval **Pending**.

Đã sửa lỗi metadata baseline còn giữ build cũ khi đổi link evidence, cập nhật nhất quán build/time/profile/actual result. Toàn bộ suite đã chạy lại sau sửa. Lượt verified trước đó nằm trong evidence/2026-10-18-g2/initial-verified, ledger của lượt cũ trỏ archive này; build hiện hành có194 hàng, không phải toàn checkpoint chỉ có một lượt. Chỉ tổng hợp khi JUnit đã ghi xong; một lần gọi importer quá sớm gặp XML rỗng đã thất bại trước mọi cập nhật, sau đó chạy lại khi suite hoàn tất. Không tính lỗi tổng hợp đó là test nghiệp vụ Passed/Failed.

`foundation-initial.xml` giữ lần assertion fail do kỳ vọng whitespace trước Laravel trim; đã kiểm formula prefix theo input thực và bổ sung unit vectors whitespace/BOM. Restore đầu dùng ZipArchive không có extension nên fail; bản TAR/PharData đã thực chạy lại và kiểm dữ liệu. Không tính hai lần lỗi này là Passed. Giữ failure G1, không che lịch sử.

### Ứng dụng đang chạy và cách tiếp tục

- Demo riêng `airline_demo_20261018_010122_1556ab`, MySQL8.0.46 localhost3318, web http://127.0.0.1:8000/login. Web/worker/scheduler chạy ẩn bằng scripts/local-demo.ps1, PID/identity tại storage/app/private/demo-services.json; không đưa key/password vào progress. Lấy tài khoản generated trong storage/app/private/demo-runtime.json cục bộ. `.env` và APP_KEY người dùng không đổi; script ensure-app-key đã so hash trước/sau.
- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 status` / `stop` / `start` quản lý riêng các process demo; `php scripts/demo.php app:health` kiểm MySQL/background. Giữ instance MySQL cô lập khi demo đang chạy; không dừng service MySQL80 có sẵn. Không xóa DB test/restore hoặc reset repo khi tiếp tục.
- Lát cắt M01 hiện Implemented có bằng chứng, human approval Pending. M07 UC18/19 giữ implementation; M16 UC45/46 partial. Không có tệp code đang viết dở ở checkpoint; các khoảng trống dưới đây là việc tiếp theo, không phải kết quả giả lập thành công.

Ưu tiên phiên sau:

1. Đọc checkpoint/AGENTS/master hash/trace/defects; kiểm worktree và fingerprint trước sửa. Không chạy lại initialize-traceability hoặc importer G1. Script record-session-evidence hiện cố định checkpoint G2; phiên sau tạo thư mục evidence/build/mapping riêng và chỉ thêm kết quả thực chạy.
2. G2: hoàn thiện UC04 route restrictions/mùa; UC05/06 season/version/approval/revision UI, revalidation/actor/history và scope. Race đã có cho submit chung aircraft/gate và crew assignment; còn race publish khác phương án/tạo flight/disruption, cross-module locks/maintenance/crew approval và timezone/turnaround branches. M04 seat configuration/usage và M06 qualifications/training/availability/history; maintenance due/work orders/release chi tiết.
3. G3: money exact/server pricing, multi-leg holds15m/expiry scheduler, payment simulator contract/chữ ký/idempotency/late callback/ticket/reconciliation, change/cancel/refund; QR opaque issue/revoke/reissue/boarding race/no-show/offload; baggage segments/incidents/return. BOOK-001/002 và QR-001 vẫn High, không coi409 endpoint là trạng thái sản phẩm cuối.
4. G4/G5: M11 ground/SLA/turnaround, M12 finance đối soát/adjustment/ERP, M13 notification/complaint/history, KPI/AI model registry/provenance/governance, IoT Wokwi/replay/abnormal/offline, integration/config/unit scopes/service identities. Ngoại biên chưa có adapter phải viết local simulator rõ contract, không đổ cho thiếu credential.
5. G6: seed đủ quy mô nguồn, OpenAPI còn lại/ERD đầy đủ, Docker runtime/CI cloud, coverage/load và baseline24NotRun. Restore rehearsal đã đạt nhưng còn restore attachment/workflow đầy đủ và RPO/RTO. Mở rộng test nhánh bootstrap DB mất key/new key, migration legacy-data upgrades và retention/audit cũ. Không hạ gate hoặc loại Should/Could.

Lệnh đã chạy và kết quả chi tiết tại [release-test-report.md](testing/release-test-report.md); hướng dẫn sử dụng M01, setup Windows/Docker và restore đã liên kết trong README.

## Sửa hướng dẫn đăng nhập demo — 18/10/2026

Người dùng báo lỗi “Sai tài khoản/mật khẩu hoặc tài khoản không hoạt động.” tại http://127.0.0.1:8000/login, xác nhận username `demo_admin`. Kiểm tra riêng profile và DB đang chạy: cả 4 account ACTIVE, password_verify khớp; demo_admin/demo_ops/demo_customer có ký tự được escape khi serialize JSON. Chrome tái hiện cùng thông báo khi nhập chuỗi JSON chưa decode, và đăng nhập thành công bằng giá trị decode. Đây là nguyên nhân có thể tái hiện, chưa có xác nhận người dùng đã sao chép theo cách đó. Không reset mật khẩu, đổi status, seed lại DB, sửa .env hoặc key.

- Thêm `scripts/demo-credentials.ps1`: mặc định chỉ liệt kê username/roles; `-Username demo_admin -CopyPassword` giải mã JSON rồi Set-Clipboard, không in secret. Không có HTTP endpoint cung cấp mật khẩu. README/runbook hướng dẫn thao tác và xử lý profile cũ sau khi đổi password.
- Label form đổi thành “Tên đăng nhập”, có hướng dẫn rõ; backend authentication/RBAC không đổi.
- Thêm `scripts/verify-demo-login.mjs` kiểm 4 tài khoản bằng Chrome thật, tái hiện JSON escape, kiểm helper bằng Set-Clipboard mô phỏng trong process con (không đụng clipboard người dùng), từ chối yêu cầu sai và không lộ password trong output. Chạy lúc 01:40 UTC /08:40 giờ địa phương.
- Build `9c1a02d822fe1cb6ee139bb0a1dfbc1014a7b24fa86e8ec110960f05d7b6ddc7`, fingerprint sau test không đổi. `php scripts/test-mysql.php --filter=AccessFoundationTest`: **25 Passed /108 assertions /20.095s**, exit0, MySQL8.0.46 DB mới `airline_test_20261018_014004_872c50`. `node scripts/verify-demo-login.mjs`: **Passed**, cả 4 login và trang theo vai trò đạt; lỗi JSON tái hiện được. Clipboard hệ điều hành thật **Not Run**, contract mock đã chạy; xác nhận đăng nhập từ người dùng còn Pending.
- Evidence riêng [login-fix](testing/evidence/2026-10-18-login-fix/report.md), không ghi đè báo cáo/ledger build G2 hoặc suy ra full regression đã chạy ở build mới. Trace UC/phạm vi 48UC giữ nguyên; các bước tiếp theo ở checkpoint G2 vẫn còn.
- `docs/source/` hiện đã có 12 DOCX (gồm `UseCase_va_DacTa.docx`, ERD, kiến trúc, activity và baseline Unit/UAT). Nội dung lịch sử G2 nói nguồn thiếu là trạng thái lúc đó; chưa đọc các DOCX mới trong phiên sửa login, cần đối chiếu ở phiên triển khai tiếp theo.

## Phiên hoàn thiện 48 UC — M02, 18/10/2026

Phạm vi đích vẫn48UC/16module, gồm Should/Could; không đổi stack hoặc khởi tạo lại repo. Đã đọc AGENTS gốc, kiểm worktree/lockfiles/runtime và checkpoint/trace/decisions/defects; không có AGENTS con. Source baseline commit vẫn `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`, code/user changes chưa commit được giữ. Backup trước sửa: `storage/app/session-baseline-20261018-020539/source.zip`, git-status và các CSV/rules trước cập nhật. SQL demo trước migration tại cùng thư mục,242947bytes; không reset DB/đổi key hoặc mật khẩu. Migration000005 đã áp dụng vào demo riêng sau khi feature MySQL ban đầu đạt.

### Nguồn đã đối chiếu

Master hash vẫn `e098ebc94fc172b14cce7c484702f312ce79f44f62f8be5cb17e5e986a72d4c1`, toàn bộ phụ lục đã đọc ở phiên trước; phiên này đọc lại yêu cầu, UC04–06, baseline Unit/UAT và Appendix D. Nhận đủ12DOCX, trích text/kiểm kê media riêng trong storage/app/source-review-20261018; lỗi console encoding sau lần trích đầu không làm mất các text đã ghi, sau đó kiểm lại UTF-8.

`review-source-documents.py` so sánh **đủ48UC/tất cả thuộc tính** DOCX với baseline master đã đọc: các trường không trống khớp, chỉ có24ô flow bị bỏ trống (23Exception,1Alternative). Đã đọc các phần khác biệt, lưu supplement, thêm24case mới ban đầu Not Run; không sửa master/DOCX. Đã đọc định nghĩa25BR/18NFR cùng các yêu cầu FR liên quan trong Business Analysis và cập nhật rules-register từ nguồn. Đã xem trực tiếp hai ảnh Activity/Sequence M02 (word/media/image11.png và image7.png). Các DOCX khác đã kiểm kê/trích text; không nhận đã kiểm chứng toàn bộ ảnh/sơ đồ. Tiếp tục đối chiếu chi tiết BA/phỏng vấn/biểu mẫu và ảnh của module tiếp theo khi triển khai; không coi dữ liệu nguồn nay có sẵn là còn thiếu credential.

### Thay đổi đã triển khai

- UC04: CRUD đường bay có transaction/audit/unique, mùa ngày cặp có CHECK, dòng tàu cho phép, ngày hạn chế, ghi chú; không đổi hai đầu khi có lịch/chuyến; không xóa khi có liên kết. UI list/filter/sort/paging/detail/edit/history thực.
- UC05: lịch một lần/lặp mùa, ngày thêm/loại, múi giờ nguồn/UTC/DST/overnight; actor/time từng bước; DRAFT→PENDING→APPROVED→PUBLISHED, trả sửa/hủy có reason. Mở revision thành UUID mới, giữ bản công bố cũ cho đến công bố mới, snapshot bất biến. Preview impacted flights và chặn thay đổi chưa điều hành giải quyết; đã có test giải quyết đổi giờ rồi công bố. Hủy lịch không tự hủy flight/booking.
- UC06: báo cáo tổng hợp theo field/ngày, thiếu dữ liệu chặn kiểm tra hoàn chỉnh; aircraft/gate/airport giờ mở/route mùa/hạn chế/maintenance/turnaround/recurrence; revalidate lúc submit/approve/publish. Diagnostics lưu kể cả transition từ chối.
- PlanningLock chung trước consistent read cho các transaction liên quan; airport scope theo origin ở collection/object, trước validation; kiểm stale revision khi client gửi, UI luôn gửi. Các ngưỡng/profile/ràng buộc chuỗi và giới hạn legacy API ghi ADR022–027. Chưa có load chứng minh mutex đạt tải lớn.
- Migration additive000005 thêm flight_schedule_checks và các trường mô tả ở dictionary; không sửa schema cũ để giả migration. API M02 có12operations trong flight-planning.openapi.yaml, verifier tổng31operations M01+M02. README/permissions/gap/guide/web-routes/source registers được cập nhật.
- Phát hiện và sửa navbar mobile tràn ngang; ID season_code trùng giữa filter và form; tên truy cập link lập lịch có icon. Lượt browser đầu không kiểm initial season nên đã bỏ sót lỗi này: giữ lịch sử AA build/ảnh/log, thêm assert sau reload và kiểm ID không trùng, không nhận lượt cũ đã chứng minh lưu mùa.

### Xác minh cuối đang tổng hợp

Build hiện hành `817554fe930bb2ce2e8e22f6956d2e47ce4fec91bb17f901f4f087f815f6f60d`; evidence `docs/testing/evidence/2026-10-18-planning/`. Lượt trước `aa763652e0ab6dc0aa7131fb98f2452c6acd109f2b8d73b4908d15a5a8669846` được giữ trong initial-verified; ledger241rows của lượt này là thực chạy trước sửa UI, không phải kết quả build mới.

Lúc ghi mục này: Chrome **9Passed**, SQLite **104Passed/11Skipped/762assertions**, restore **45tables/59FK/0orphan**, live demo+login+M02 navigation đạt; full MySQL lượt cuối đang chạy, chưa tổng hợp hoặc gắn Passed cho build mới. Các kết quả cuối được ghi ở mục chốt ngay dưới đây sau khi JUnit hoàn tất; không chạy importer khi XML còn rỗng.

Các failure lưu riêng development-02 (test kỳ vọng sai format ngày), browser-development-01 (sai fixture password/thiếu API bearer), browser-development-02 (mobile overflow); root causes được sửa/test lại. Fixture password chỉ tồn tại testing, không áp dụng demo riêng. Clipboard thật vẫn Not Run, contract mô phỏng và Chrome login thật đã chạy. Không gửi mail/tiền thật hoặc publish ngoài máy.

### Chốt bằng chứng M02

JUnit đã hoàn tất, importer đã kiểm mọi hash source khớp build817554fe… và ghi kết quả. **MySQL115Passed/867assertions/4m51.727s**, SQLite104Passed/11Skipped/762assertions, Chrome9Passed/45.1s; restore45bảng/59FK/0orphan, encrypted assistance và upload fixture khớp. Ba race mới publish/createflight/submit-vs-maintenance cùng sáu race hồi quy đều quan sát2waiters/kết nối độc lập và1winner. Baseline vẫn19Passed/24NotRun/43hàng; không tự hạ gate.

UC04–06 chuyển Implemented có evidence; tổng **8Implemented/21InProgress/19NotStarted**, không có UC tự nhận human acceptance. Ledger build mới241rows gồm11Skipped và2rehearsal; build AA trước sửa UI cũng241rows, trỏ initial-verified, không gắn log mới cho build cũ. Các ID baseline ở trace được giữ cùng IDs tests mới. Báo cáo hiện hành [release-test-report](testing/release-test-report.md); G2 trước M02 đã archive riêng. Coverage/load/CI cloud/Docker runtime/Wokwi còn thiếu như báo cáo; phần chưa có implementation không gán Blocked do credential.

Demo web/worker/scheduler vẫn chạy bằng PID được xác minh của launcher; MySQL riêng3318, readiness ready, failed/pending jobs0 tại kiểm cuối. Mở http://127.0.0.1:8000/admin/schedules bằng demo_ops, /admin/routes bằng demo_data. Lấy password qua helper cục bộ như README; giữ private profile/key. Hướng dẫn [flight-planning](user-guides/flight-planning.md), API M02/dictionary/quyền đã cập nhật. Không reset seed, tạo lại profile hoặc đổi .env để tiếp tục.

### Việc tiếp theo để đạt đủ48UC

1. Tiếp tục G2/M04 UC10–11: cấu hình ghế có lịch sử/phiên bản, khả dụng, usage đối chiếu dữ liệu khai thác, ngưỡng cảnh báo và UI/detail/history/scope. Phải nối cấu hình ghế vào capacity/seat map sau này, không tạo một danh mục ghế rời nghiệp vụ.
2. M06 UC15–17: giấy phép/đào tạo/availability/nghỉ/dự bị và bộ rule duty/rest có phiên bản/chủ phê duyệt theo BR009 mới có nguồn; kiểm lại ở approve/replace/confirm. M05 UC12–14: kế hoạch theo ngày/giờ/chu kỳ, tasks/materials/evidence/technical responsibility/release history. M03 cần source scope/preview/bulk/disruption impact và resource changes đầy đủ. M02 hoàn tất không đóng các UC này.
3. G3/M08–M10: server pricing/money exact, multi-leg inventory holds15m/expiry, signed payment simulator/callback/idempotency/late-vs-expiry/unknown/reconciliation/ticket, đổi/hủy/hoàn; seat map/QR opaque/reissue/revoke/scanner race/no-show/offload; baggage segments/custody/incidents/return. High defects BOOK001/002/QR001 còn mở.
4. G4–G5: M11 ground/SLA/milestones/exception authority; M12 finance reconciliation/adjustments/ERP; M13 notification/complaint/interaction; M14 AI provenance/model registry/quality/human decision; M15 Wokwi+HTTP/MQTT adapter/replay/offline/late/dedupe; M16 unit scopes/service identities/SSO/integration/config/retention. Không bỏ Could/Should hoặc dùng simulation thay domain thật.
5. G6: bổ sung demo đủ định mức, API/ERD còn lại, baseline24NotRun, coverage driver/gate, load, Docker/CI chạy thật, restore attachment/workflow/RPO/RTO và human UAT. Tiếp tục đọc/đối chiếu các tài liệu BA/biểu mẫu/ảnh gốc theo module; source đã có đủ trong docs/source.

Đã hoàn tất lát cắt M02 trong checkpoint này; **chưa hoàn tất yêu cầu48nghiệp vụ**. Trước thay đổi tiếp theo tạo evidence/build riêng, không chạy lại initialize-traceability hoặc importer lịch sử để ghi đè trạng thái mới.

## Phiên tiếp tục M04/M06 — 18/10/2026

Đọc lại AGENTS, git status, master UC10–11/15–17 và nguồn DOCX/BA; xem thêm bốn sơ đồ Activity/Sequence M04/M06 (image10/12 và image6/1 trong DOCX). Master/source hash không đổi. Git status trong backup đầu phiên có138hàng thay đổi đã có sẵn, không reset/commit/push. Backup source đầy đủ516file tại `storage/app/session-baseline-20261018-101850/source-readable.zip` + inventory hashes; Compress-Archive ban đầu gặp sharing lock của helper demo nên giữ file dở và tạo lại bản đọc được bằng ZIP Python, không nhận bản lỗi là backup thành công.

### Đã triển khai trong phiên

- M04 UC10/11: migration000006 additive; cơ sở/scope thật, opening ledger có nguồn, hồ sơ/revision, trạng thái có lý do, cấu hình ghế vật lý/hạng/khóa theo phiên bản bất biến; pin configuration/capacity vào flight và dùng khi giữ chỗ/check-in. Không đoán sơ đồ cho legacy; chưa xác nhận physical map thì check-in bị chặn. Tàu còn chuyến chưa kết thúc không đổi cấu hình; đổi tàu có vé/dịch vụ/check-in bị chặn chờ chuyển chỗ G3.
- Usage: facts ARRIVED tính từ actual UTC, một chu kỳ/chuyến, unique flight và receipts source/external ID kể cả aliases, replay không cộng; lịch sử/filter/provenance, persisted threshold alerts, reconciliation không sửa tổng ngầm. Giữ ngưỡng local3.000h/1.500cycle và cảnh báo90%, chưa có chuyên gia duyệt.
- M06 UC15/16/17: migration000007 additive; profile/năng định/hạn giấy phép/đào tạo/revision; calendar LEAVE/UNAVAILABLE/STANDBY/hủy có lịch sử; gợi ý xác định có kiểm điều kiện và ưu tiên dự bị; assign/approve/confirm/replace/reset approval atomically. Actor, SELF/OPERATOR, rule version và snapshots append-only; thông báo nội bộ hiện trên workflow của thành viên liên kết. Không tự coi proxy là chữ ký thành viên.
- Scope personnel theo cơ sở; plan theo origin/flight; assign cần cả personnel và flight scopes; API plan chỉ trả profile tối thiểu. PlanningLock trước các transaction profile/calendar/assignment để không duyệt hồ sơ cũ. local-crew-v1 giữ provenance/payload/LOCAL_ONLY, duty/rest12h là giả định chưa duyệt.
- UI Blade/Bootstrap thật có filters/paging/forms/detail/history/error/empty/loading/mobile; trạng thái/nhân sự/decision labels tiếng Việt, hiển thị username actor thay UUID. Contract27API M04/M06 +31API có trước =58operation; data dictionary/ERD/permission scopes/ADR028–033/user guide đã cập nhật.

### Kiểm thử và bằng chứng đang chốt

Evidence riêng: `docs/testing/evidence/2026-10-18-fleet/`. Development MySQL: FleetManagement10/125assertions, CrewWorkflow8/108 rồi mở rộng standby alias, resource/crew races5/66; Chrome4 luồng mới đã chạy. Lỗi route usage legacy, index MySQL quá64ký tự, gợi ý lại người đang phân công, hours response SQLite không đồng nhất đã sửa; giữ các lượt lỗi ở development folders và defects.

Lượt full đầu build `6bf97d504a34b965399fa3fd1d2f9066734425385db5b0da39dc54b7f8eef34b`: MySQL138tests/1Failed/1167assertions/8m24.940s, SQLite122Passed16Skipped/999assertions, Chrome13Passed. Importer ghi291execution gồm1Failed và từ chối promotion. Tất cả46artifact giữ ở `initial-regression/`, ledger tham chiếu đúng archive. Lỗi test last-seat: sửa model capacity sau khi flight đã pin capacity không còn tạo chuyến1ghế. Không hạ invariant; fixture mới tạo cấu hình vật lý1ghế/chốt trên flight; focused MySQL race đạt1test/16assertions, quan sát2waiters và1created/1rejected.

Build chạy lại sau sửa fixture và hoàn thiện nhãn UI/actor: `bdc0e08b911859ab76ebd0593d4ba70c268b4b75c9b7bcfd2023d9de0068c73c`. Lúc ghi mục này full MySQL/SQLite/Chrome đang chạy, chưa gắn Passed cho build này. Chỉ chạy record-resource-evidence.php khi đủ JUnit và restore/local-demo hoàn tất, source hashes khớp; script sẽ từ chối nếu có Failed. Phần chốt bên dưới là kết quả cuối khi đủ bằng chứng.

### Runtime và giới hạn

Backup demo trước migration: `storage/app/private/backups/before-fleet-crew-20261018-034528.sql`263721bytes, SHA256 `3714c53b4574e5f44924c11b20e35396e03b103b99b4c5cdc8b6bb60cd355f1e`. Đây là file private chứa dữ liệu demo, không commit. Đã chạy `php scripts/demo.php migrate --force`:000006/000007 DONE; không migrate:fresh/reseed hoặc sửa .env/APP_KEY/tài khoản. Launcher chỉ restart các process đã xác minh danh tính, web146312/worker157892/scheduler154224 ở mốc này, URL8000. app:health ready, pending/failed jobs0; login thật4account và smoke navigation M04/M06 đã thực chạy ở lượt đầu, sẽ kiểm lại build cuối. MySQL3318 là MySQL8.0.46 riêng.

48UC vẫn là phạm vi đích; BOOK001/002/QR001, M03/M05 và toàn bộ mở rộng M08–M16 chưa hoàn tất. AODB/FIDS chưa kết nối thật; API usage là phục hồi nội bộ được phân quyền, nhãn DEMO mô phỏng không thay nghiệp vụ ledger. Ngưỡng an toàn local, coverage, load, Docker runtime/CI cloud, Wokwi, baseline24NotRun và human UAT vẫn mở. Không gửi tiền/mail/SMS thật hoặc public deploy.

### Chốt checkpoint M04/M06

Build `bdc0e08b911859ab76ebd0593d4ba70c268b4b75c9b7bcfd2023d9de0068c73c`: **MySQL138Passed/1173assertions/6m22.667s/exit0**; SQLite122Passed/16Skipped/999assertions/45.813s; Chrome13Passed/78.687s; Vite build đạt;203PHPfiles/0syntaxfailure. Restore DB riêng51bảng/76FK/0orphan, encrypted assistance/binary fixture khớp.14race JSON quan sát2waiters; usage duplicate2success/cùngID/cộng1lần, profile/leave-vs-approval kết thúc mất approval; không nhận mọi race là1winner. DB test `airline_test_20261018_035522_709b3a`, browser `airline_test_browser_20261018_035600_81cd85`, restore `airline_test_restore_20261018_040130_1a2c4a` được giữ để kiểm tra.

Đã chạy importer khi JUnit hoàn tất và mọi source hash khớp; ghi291execution gồm275Passed/16Skipped/2rehearsal trong số Passed, không coi aliases là lượt chạy thêm. Trace **13Implemented/16InProgress/19NotStarted**. UC10/11/15/16/17 chuyển Implemented; approval của mọi UAT vẫn Pending, baseline19Passed/24NotRun và coverageNotMeasured không tự thay. Test failure build trước giữ nguyên trong initial-regression/ledger, không bị ghi đè bởi kết quả mới.

Local demo migration/status,4account Chrome login, helper JSON decode/clipboard mock, actual M04/M06 navigation/usage/Vietnamese labels/logout và health ready/worker-scheduler fresh đã xác minh lại build cuối. `api-route-verification.json` đối chiếu58operation với Laravel registry thật; trace verify48UC/16module nguồn không đổi. [Báo cáo](testing/release-test-report.md), [verification-summary](testing/evidence/2026-10-18-fleet/verification-summary.json), [hướng dẫn](user-guides/fleet-crew.md) là điểm bàn giao kiểm tra được. Không tuyên bố full48 hoặc đạt G6.

### Tiếp tục từ đây

1. Kiểm git/AGENTS/checkpoint; tạo source/evidence/build mới. Không chạy initialize-traceability hoặc importer cũ để reset CSV. Giữ các evidence directories và failed builds.
2. Hoàn thiện M05 UC12–14 (đã đọc lại đầy đủ master/service khi chốt): maintenance_plans tách work orders; trigger ngày/giờ/cycles/condition, revision điều chỉnh; tasks/nhân lực/vật tư/vị trí/bằng chứng file có scope, completion/signoff/approve/release history; cảnh báo thiếu vật tư/quá hạn và tiếp tục không khả dụng. Source Exception UC12/14 từ DOCX supplement bắt buộc. Không dùng technician/location/evidence text hiện có làm bằng chứng đã đủ work order. Đồng bộ threshold readiness với M04 và khóa PlanningLock, không cho maintenance release né kế hoạch quá hạn.
3. M03 UC07–09: đủ lifecycle/checkin open/close, disruption choices/approval/impacts, outbox thông báo và adapter AODB/FIDS local contract/provenance/retries; profile airport/flight scopes trên query/object và browser cross-module. Reassignment có vé đang bị chặn an toàn đến khi có chuyển chỗ G3.
4. G3: fare/inventory server money, holds15m/payment/refund/callback idempotency + expiry/late race, ticket/change/refund, physical seat assignment/QR issue-revoke-reissue/boarding/no-show, baggage timeline/custody/incident/return. BOOK001/002/QR001 chưa đóng; legacy confirm/hash-scanner409 vẫn là guard, không là nghiệp vụ hoàn tất.
5. G4–G5: M11 ground/SLA/milestones/exceptions, M12 finance/reconciliation/adjustment/ERP, M13 notification/complaint/interactions, M14 AI registry/provenance/quality/human decision, M15 Wokwi/IoT schema/dedupe/late/offline, M16 unit scopes/service identities/SSO/integration/config/retention và liên kết identity nhân sự. Giữ toàn bộ Should/Could.
6. G6: baseline24NotRun, coverage driver/gate, load, Docker/CI thực chạy, demo đúng định mức, attachment restore/RPO/RTO/backup vận hành, API/guide còn lại và human UAT. Mọi test mới mặc định NotRun, chỉ Passed sau execution có build/time/evidence.



## Phiên tiếp tục UC-12–14 — M05, 2026-10-18

Đã kiểm lại AGENTS root (không có hướng dẫn con), git/runtime/lockfile/checkpoint; bảo toàn worktree. Source backup trước thay đổi: `storage/app/session-baseline-20261018-122144/source-readable.zip`711files, git status và inventory cùng thư mục; .env/private/vendor/node_modules không đưa vào archive. Master/source hashes không đổi. Đọc lại master UC12–14, DOCX UseCase/BA tương ứng và cả activity image8 + sequence image3 trong DOCX6module; đã xem ảnh, không coi extract text là xem sơ đồ.

Đã code kế hoạch bốn loại hạn, revision/usage reconciliation/conflicts, lệnh/checklist/nhân sự/vị trí, kho nguyên và ledger idempotent, chờ vật tư, file private/MIME/hash/download scope, actor ký/phê duyệt/release/giữ không khả dụng, history và cảnh báo/safety gate đồng bộ. UI Blade tiếng Việt search/filter/pagination/form/detail/history/mobile. Legacy không được ký bằng text; có review/adopt giữ snapshot và tái kiểm nguồn lực/tài liệu. Kiểm tra bộ phận không tự reset mốc giờ/chu kỳ tổng. M05 có API và guide/ERD/dictionary/permission scopes mới.

Đã chạy focused MySQL18tests/200assertions đạt trên build `f48b3e49f4b7e475ef73aec849880c8bc38108d2206de68fcebd47bf8031684a` trước bổ sung ca timezone/filter. Chrome hành trình M05 đạt ở development-05, source manifest ghi build riêng; không chuyển kết quả đó sang build cuối. Stock race quan sát2waiters, một IN_PROGRESS/một WAITING_MATERIALS, xuất đúng1vật tư; API cả hai có thể committed vì trạng thái chờ là kết quả hợp lệ. Development-01 có4error do fixture cần GD; sửa bằng PNGbytes thực không cần GD. Development-04 Chrome lỗi selector nhiều alerts, sửa chọn đúng thông báo lỗi; không hạ assertion. Giữ log thất bại. Script cập nhật baseline sai tên cột đã phục hồi từ source-readable.zip trước sửa và ghi atomic đủ43rows; không mất baseline cũ.

Backup DB demo trước000008: `storage/app/private/backups/before-maintenance-20261018-053653-a356db.sql`,288844bytes, SHA256 `4aca06135513c8b73d6abcd04724387edf4da53faa048f7da3a1bc8fb0d638cd`. Đã migrate additive000008 DONE và maintenance:alerts (0plan hiện hữu), không reseed/reset hoặc sửa .env/APP_KEY/tài khoản. Backup này chỉ DB, không thay backup attachment/khóa riêng.

Build regression đang chạy: `580d61ffe38d91bc10ba61fd8c85c07695d0e73919fd9a775fc5a2ffb1e0eba7`, evidence `docs/testing/evidence/2026-10-18-maintenance/`. Full MySQL/SQLite/Chrome chưa chốt ở mục này; chưa promote UC12–14. Trace vẫn13Implemented/16InProgress/19NotStarted đến khi importer kiểm source hashes/JUnit/restore/local-demo. Giữ đủ48UC và source Should/Could; BOOK001/002/QR001 còn mở, human UAT Pending và coverageNotMeasured.


### Sau safety review M05

Lượt regression trước: MySQL152Passed/1349assertions/10m04.748s; SQLite135Passed/17Skipped; Chrome14Passed; restore59bảng/96FK/1attachment thật. Đã giữ root evidence tại `pre-safety-review/`, source zip `storage/app/session-baseline-20261018-122144/maintenance-pre-safety-source.zip` xác minh từng manifest hash trước lưu, và ghi318JUnit executions vào ledger mà chưa promote UC. Đây không phải kết quả build cuối.

Rà soát bổ sung airborne guard (DEPARTED không được start dù scheduled arrival đã qua), projected flight hours/+1cycle không sửa ledger, actual usage→plan alerts trong transaction, chống xóa/chuyển base tàu có plan. Focused MySQL26Passed/267assertions/1m46.570s, build `a7d24d085f980806fd971972b6a70790e0ad065d0dc5cd84d75a625187fc64e1`, có race submit với maintenance_plans và race stock2waiters. Thêm bcmath ngưỡng M04/prefix LEGACY- do server quản lý/nhãn UI trước freeze cuối. Snapshot verify nay kiểm cả file thêm/xóa và contract API, không chỉ các hash từng file đã có.

Build cuối đang regression `ef479f92f5fefa7d3c3eff5c7f6866734c49c2fab0dd0b0551ec5d206885972f`: SQLite138Passed/18Skipped/1187assertions/63.889s; Chrome14Passed (JUnit thời gian xem XML), build450ms/PHP214files0syntaxerror; actual registry68APIoperations. Restore đã chạy lại59bảng/96FK/1attachment hash/decrypt khớp.4Chrome login, helper decode/clipboard mock, live M05 và ready/queueworker/scheduler đã chạy lại; .env/runtime credentials giữ nguyên. Managed web166868/worker166724/scheduler161084 restart đúng danh tính. Full MySQL cuối chưa chốt ở mốc này, chưa promoteUC12–14.


### Legacy departure và BA9.7 trước nghiệm thu lát cắt

Build ef479... đã chạy full MySQL156Passed/1384assertions/10m50.544s, SQLite138Passed/18Skipped/1187assertions, Chrome14Passed; source/evidence giữ tại pre-departure-review/ và ledger326executions (không promote). Rà soát FlightManagementService phát hiện gate maintenance chỉ đầy đủ trong nhánh resource_id. Đã bổ sung Readiness trực tiếp ở khởi hành, đọc cả plan và orders legacy quá hạn/chưa giải phóng kể cả cờ aircraft OPERATIONAL cũ. Focused MySQL18Passed/195assertions, build8018932d25cd522a7f06cf1e57dc4da5e8fcf81078a385dc45ca4e8d926f35ab, hai dataset no-resource plan/legacy-order.

Build801893 full MySQL bị chủ động ngắt (exit-1) sau xác minh đúng2process PHP của runner, để hoàn thiện pre-work approval từ BA9.7. Không nhận MySQL Interrupted là Passed; rawlog/manifest/interruption JSON ở superseded-pre-work-approval/. SQLite140Passed/18Skipped/1199assertions/64.832s và Chrome14Passed của build này đã hoàn tất, ledger ghi172execution đó, không chuyển sang source mới. Nguồn đọc lại UseCase UC07–09/BA9.2,9.6–9.8 cho thấy BA9.7 yêu cầu duyệt trước thực hiện. M05 bổ sung authorize khác approve kết quả, revision/history/actor và invalidate khi đổi nguồn lực; migration000009 nullable, không bịa phê duyệt lịch sử. Chưa promote UC13 trước khi test đủ bước mới.


### Freeze M05 có duyệt trước thực hiện

Build `9f7945bb9d6183efa8922a56ea0d97d4eb768290c4ce921eea2a156877576e72`. Focused MySQL25Passed/267assertions/90.932s và Chrome M05 1Passed/18.1s ở authorization-focused/. Full SQLite141Passed/18Skipped/1225assertions/66.160s; full Chrome14Passed; full MySQL159case đang chạy, chưa promote. Restore cuối59bảng/97FK/0orphan/1attachment thật và decrypt/hash đạt, target airline_test_restore_20261018_061434_229875.

Backup trước000009: storage/app/private/backups/before-maintenance-20261018-061020-6c4f59.sql,291122bytes SHA256029c4f475f0d068564b1d019663330383fdf4240cb81b1a7ae9fbaeada4f6663. Đã migrate000009 DONE, không backfill authorized_by/time cho legacy. .env/APP_KEY/runtime accounts giữ nguyên. Có file source zip theo manifest cho các build đã thay thế, tất cả kết quả cũ giữ riêng, run bị ngắt không nhận Passed.


### Chốt M05 — điểm tiếp tục hiện hành

Build `9f7945bb9d6183efa8922a56ea0d97d4eb768290c4ce921eea2a156877576e72`. Full MySQL159Passed/1422assertions/9m14.672s/exit0; SQLite141Passed/18Skipped/1225assertions/66.160s; Chrome14Passed/JUnit100.473011s; assets456ms/215PHPfiles0syntaxfailure.16race JSON đều2waiters, stock1IN_PROGRESS/1WAITING và xuất đúng1đơn vị; modern plan-vs-submit1winner. Restore59tables/97FK/0orphan,1M05attachment thật và encrypted assistance khớp.4real login/helper clipboard mock/live M05/health ready đã chạy lại. Source/API complete file set khớp sau tests và importer.

Đã ghi334executions gồm316Passed/18Skipped (2rehearsals nằm trong Passed), không cộng source aliases như executions. Trace **16Implemented/13InProgress/19NotStarted**; UC12–14 sang Implemented, không Verified toàn bộ. Baseline20Passed/23Not Run, human UAT Pending, coverageNotMeasured. [Report](testing/release-test-report.md) và [summary](testing/evidence/2026-10-18-maintenance/verification-summary.json) là bằng chứng cuối, các lượt cũ và Interrupted giữ nguyên với build riêng.

**Tiếp theo:** (1) Kiểm git/AGENTS/runtime/backup mới, không reset CSV hoặc dùng importer giai đoạn cũ trên source mới. (2) M03 UC07–09: đọc lại master/DOCX và xem activityimage9/sequenceimage2 (chưa xem ảnh M03 trong phiên này); hoàn thiện lifecycle/check-in windows, flight/airport scopes, revision/history; AODB/FIDS adapter local contract/provenance/source time/sequence/late/duplicate/retry; disruption impact/proposal/approval/outbox. Source UseCase UC07–09 và BA9.2/9.8 đã đọc lại, code FlightEventService/FlightManagementService hiện còn partial. Không cho future/late facts ghi đè latest hoặc tự chấp thuận thay đổi có vé. (3) G3: server fare/money, inventory/15mhold, signed idempotent payment/refund/late/expiry races; ticket/change/refund, QR issue/revoke/reissue/consume concurrent/no-show, baggage custody/timeline/incidents/returns. BOOK001/002/QR001 chưa đóng. (4) G4–G5: M11ground/SLA, M12finance/reconciliation, M13CSKH, M14AI registry/quality/provenance, M15IoT/Wokwi, M16identity/unit scopes/integration/config/retention; giữ Should/Could. (5) G6:23baselineNotRun, coverage driver/gate, load, Docker/CI thực chạy (cấu hình PHP upload phù hợp10MiB trước nghiệm thu), production backup/RPO/RTO và human UAT. Không gọi checkpoint này là full48.

### M03 đang triển khai — checkpoint khi chuyển sang yêu cầu kiểm tra Git

Ngày 18/10/2026: backup trước sửa tại `storage/app/session-baseline-20261018-133645/source-readable.zip` (985 file, tên Unicode đọc qua git NUL; kèm git-status/inventory). Đã đọc lại UC07–09 master/DOCX, BA9.2/9.8 và xem trực tiếp activity image9 + sequence image2. Runtime kiểm lại PHP8.2.12/Laravel12.69.2/Node24.16.0. Giữ nguyên dữ liệu và credentials demo.

Thay đổi **đang dở, Not Run**: migration000010 thêm revision/estimated/history/proposals/milestones/outbox/receipts; các service mới FlightAccess/FlightOperations/FlightRecovery; nối revision/history/scope vào FlightManagementService, CoreAccess, OperationalFlight và request trạng thái. Chưa migrate000010 trên demo, chưa chạy lint/test cho source mới, chưa nối đầy đủ routes/UI/adapter/delivery/retry hoặc hoàn thiện FlightEventService. Không dùng kết quả build9f7945... để chứng nhận những thay đổi mới này. M03 UC07–09 giữ InProgress; không promote traceability.

Khi tiếp tục: rà soát các thay đổi trên, hoàn thiện event adapter/order/late/retry, controller/routes/UI và outbox delivery; bổ sung tests/fixtures, chạy MySQL/browser/regression với evidence/build riêng trước migrate demo và bàn giao. Không commit/push như một bản Module1 hoàn tất khi chưa qua kiểm thử. Kiểm Git tại checkpoint: branch `feature/toan`, origin `https://github.com/buivandat30506/QLDAPM.git`, chưa có file staged; không thực hiện commit/push.
