# Báo cáo kiểm thử checkpoint M05 — UC-12–14

Build `9f7945bb9d6183efa8922a56ea0d97d4eb768290c4ce921eea2a156877576e72`; commit nền `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`, worktree chưa commit và bảo toàn thay đổi hiện có. PHP8.2.12/Laravel12.69.2, MySQL Community8.0.46/InnoDB3318, Chrome/Windows. Đây là checkpoint **16/48 UC Implemented**, chưa nghiệm thu toàn bộ.

| Lệnh thực chạy (TEST_EVIDENCE_DIR trỏ thư mục dưới) | Kết quả | Bằng chứng |
| --- | --- | --- |
| `php scripts/test-mysql.php` |159Passed/1422assertions/console9m14.672s/exit0; không skipped | [JUnit](evidence/2026-10-18-maintenance/mysql.xml), [log](evidence/2026-10-18-maintenance/mysql.txt) |
| `php vendor/phpunit/phpunit/phpunit --colors=never --log-junit=.../sqlite.xml` |141Passed/18Skipped/1225assertions/console66.160s/exit0 | [JUnit](evidence/2026-10-18-maintenance/sqlite.xml), [log](evidence/2026-10-18-maintenance/sqlite.txt) |
| `php scripts/prepare-browser.php` rồi `npx playwright test` |14Passed/JUnit100.473011s/exit0 | [JUnit](evidence/2026-10-18-maintenance/browser.xml), [log](evidence/2026-10-18-maintenance/browser.txt), PNG cùng thư mục |
| `npm run build`; `php scripts/lint.php` |Vite456ms;215PHPfiles/0syntaxfailure; exit0 | [asset log](evidence/2026-10-18-maintenance/asset-build.txt), [lint](evidence/2026-10-18-maintenance/php-lint.txt) |
| `php scripts/verify-restore.php` |59tables/97FK/0orphan,1attachment M05 thật và encrypted assistance khớp | [restore](evidence/2026-10-18-maintenance/restore.json) |
| `node scripts/verify-demo-login.mjs`; `node scripts/verify-local-demo.mjs`; `node scripts/verify-maintenance-demo.mjs` |4real Chrome logins; decoded clipboard contract mock; live M05/readiness/worker/scheduler đạt, không sửa credentials/.env | [login](evidence/2026-10-18-maintenance/login-verification.json), [runtime](evidence/2026-10-18-maintenance/local-demo.json), [live M05](evidence/2026-10-18-maintenance/live-maintenance.json) |
| `php scripts/verify-api-docs.php`; `php scripts/verify-api-routes.php`; `php scripts/verify-traceability.php`; `php scripts/snapshot-build.php --verify` |68operations khớp registry Laravel;48UC/16module và source flows đúng; toàn bộ file set/API hashes khớp build | [API registry](evidence/2026-10-18-maintenance/api-route-verification.json), [summary](evidence/2026-10-18-maintenance/verification-summary.json) |

DB suite `airline_test_20261018_061204_824d69`; browser `airline_test_browser_20261018_061204_4da96a`; restore `airline_test_restore_20261018_061434_229875`.18SQLite skips gồm16races cần MySQL độc lập và2engine CHECK; tất cả đều thực chạy trên MySQL, không dùng SQLite làm bằng chứng locking.

## Phạm vi đã kiểm

UC12: DATE/HOURS/CYCLES/CONDITION, usage opening/ledger reconciliation, UTC/offset, validation/revision/history, điều chỉnh trước tạo lệnh, không dời hạn đã đến để né chặn; xung đột lịch/flight/maintenance và scope. UC13: lệnh từ plan, nhân lực/vị trí/định mức, duyệt trước thực hiện theo BA9.7; đổi nguồn lực mất authorization; receipt idempotent/conflicting replay; stock shortage không trừ kho; xuất nguyên tử; checklist chưa đạt giữ IN_PROGRESS; file private/MIME/hash/download; xác nhận kỹ thuật có actor. UC14: due/due-soon/condition/usage alerts, projected duration/+1cycle, actual usage→alert trong transaction; không start khi DEPARTED; thiếu approval/evidence/overdue/other requirement không release, tiếp tục không khả dụng; không reset mốc tổng cho hạng mục thường; phê duyệt/release có actor/time/reason/history. Gate khởi hành độc lập resource_id và cờ OPERATIONAL legacy.

Browser dùng tài nguyên M01/tàu M04 thật rồi thực hiện toàn bộ chuỗi M05, sửa lịch, duyệt trước làm, thiếu/bổ sung vật tư, bước chưa đạt, upload/download PDF, ký/duyệt/giữ không khả dụng/release, reload DB và mobile390px/ID unique. Legacy text/checkbox không được dùng để thực hiện/ký/release: review/adopt giữ snapshot và kiểm lại. Không backfill chữ ký hoặc phê duyệt giả khi migrate.

Có16race JSON quan sát2waiters. [Stock race](evidence/2026-10-18-maintenance/maintenance-stock-race.json): cả2request committed,1IN_PROGRESS/1WAITING_MATERIALS, chỉ xuất1đơn vị, không âm tồn. [Submit-vs-modern-plan](evidence/2026-10-18-maintenance/planning-maintenance-plan-race.json):1commit/1reject. Usage duplicate cùngID/cộng1lần; crew changes có thể cả2commit nhưng final mất approval. Không nhận mọi race đều phải1winner.

## Truy vết và các lượt trước

Importer xác minh complete source/API file set trước ghi334executions:316Passed/18Skipped, gồm2rehearsal trong số Passed; aliases không cộng lượt chạy. Trace16Implemented/13InProgress/19NotStarted. Baseline43giữ20Passed/23Not Run, actual_test_type phân biệt feature/DB với nhãn Unit nguồn. Mọi UAT human approval vẫn Pending, coverageNotMeasured.

Giữ nguyên development-01 (fixture GD errors), development-04 (strict selector2alerts), các focused runs/source manifests; [pre-safety-review](evidence/2026-10-18-maintenance/pre-safety-review/mysql.xml) có152Passed và [pre-departure-review](evidence/2026-10-18-maintenance/pre-departure-review/mysql.xml) có156Passed trên build riêng. Build801893 trong superseded-pre-work-approval/ bị chủ động ngắt (exit-1) để bổ sung duyệt trước làm từ BA9.7: **không ghi MySQL Interrupted là Passed**, chỉ SQLite/Chrome đã hoàn tất có execution riêng. Các ca/assertions được tăng, không hạ gate hoặc xóa tests để tạo xanh.

## Giới hạn còn mở

BOOK-001/BOOK-002/QR-001 vẫn High; M03 và M08–M16 còn phần chưa triển khai. M05 numeric safety profiles/24hdisplay là LOCAL_ONLY, chưa có phê duyệt chuyên gia; không kết nối MRO/PKI thật. Lệnh quá hạn vẫn bị chặn, không tự cấp quyền ngoại lệ. Docker runtime/CI cloud/load/Wokwi và coverage gate chưa đạt; human UAT chưa ký. Restore là synthetic rehearsal, chưa đo production RPO/RTO. [Hướng dẫn M05](../user-guides/maintenance.md), [ERD](../erd-maintenance.md), [checkpoint M04/M06](release-test-report-fleet.md).
