# Báo cáo kiểm thử checkpoint M04/M06

Build `bdc0e08b911859ab76ebd0593d4ba70c268b4b75c9b7bcfd2023d9de0068c73c`; commit nền `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`, worktree chưa commit và bảo toàn thay đổi trước đó. UTC snapshot 2026-10-18T03:55:22+00:00. Laravel12.69.2/PHP8.2.12, MySQL Community8.0.46/InnoDB3318, Playwright Chrome trên Windows. Đây là checkpoint triển khai, **không nghiệm thu toàn bộ48UC**.

| Profile / lệnh thực chạy | Kết quả | Bằng chứng |
| --- | --- | --- |
| `php scripts/test-mysql.php` | 138Passed, 1173assertions, console6m22.667s, exit0 | [mysql.xml](evidence/2026-10-18-fleet/mysql.xml), [log](evidence/2026-10-18-fleet/mysql.txt) |
| `php vendor/phpunit/phpunit/phpunit --colors=never --log-junit=.../sqlite.xml` | 122Passed,16Skipped,999assertions; console45.813s, exit0 | [sqlite.xml](evidence/2026-10-18-fleet/sqlite.xml), [log](evidence/2026-10-18-fleet/sqlite.txt) |
| `php scripts/prepare-browser.php` rồi `npx playwright test` | 13Passed, JUnit78.687s, exit0 | [browser.xml](evidence/2026-10-18-fleet/browser.xml), [log](evidence/2026-10-18-fleet/browser.txt), PNG trong cùng thư mục |
| `npm run build`; `php scripts/lint.php` | Vite build đạt,203PHPfiles/0syntaxfailure, exit0 | [asset-build.txt](evidence/2026-10-18-fleet/asset-build.txt), [php-lint.txt](evidence/2026-10-18-fleet/php-lint.txt) |
| `php scripts/verify-restore.php` | 51tables/76FK/0orphan, encrypted assistance và binary upload fixture khớp | [restore.json](evidence/2026-10-18-fleet/restore.json) |
| `node scripts/verify-local-demo.mjs`; `node scripts/verify-demo-login.mjs` | Demo thật8000, queue/scheduler fresh;4Chrome login, JSON escaping/clipboard contract mock đạt | [local-demo.json](evidence/2026-10-18-fleet/local-demo.json), [login-verification.json](evidence/2026-10-18-fleet/login-verification.json) |
| Smoke Chrome đọc M04/M06 với demo_ops thật | Navigation/detail/usage/nhãn tiếng Việt/logout đạt; không sửa domain demo | [live-resources.json](evidence/2026-10-18-fleet/live-resources.json), PNG cùng thư mục |
| `php scripts/verify-api-docs.php` + actual route registry check; `php scripts/verify-traceability.php` | 58APIoperation/48UC/16module/source priorities và flows khớp | [api-route-verification.json](evidence/2026-10-18-fleet/api-route-verification.json), traceability/cases/source register |

DB MySQL suite: `airline_test_20261018_035522_709b3a`; browser: `airline_test_browser_20261018_035600_81cd85`; restore mới: `airline_test_restore_20261018_040130_1a2c4a`. Không dùng SQLite để nhận Passed cho transaction/CHECK MySQL.16Skipped là14race cần MySQL và2engine CHECK; tất cả đã có execution MySQL, không xóa/skips các test để tạo xanh.

Importer đã xác minh mọi source hash so với build manifest trước promotion. Ledger thêm291execution records của build này:275Passed,16Skipped, gồm2operational rehearsal; aliases main/alternative/exception không cộng thành executions. Trace hiện **13Implemented/16InProgress/19NotStarted**, không có tuyên bố Verified toàn bộ. Baseline43 giữ19Passed/24Not Run; human UAT Pending; coverage NotMeasured.

## Phạm vi được kiểm

M04: duplicate registration/validation/revision/actor audit, intake provenance, unavailable maintenance release boundary, immutable configurations/cabins/blocked seats/model capacity, flight pinning/reconfiguration guard, capacity/check-in membership, ledger from actual facts/alias receipts/idempotency/threshold/unavailable/reconciliation, filters/scopes. Browser tạo/sửa hồ sơ và hai cấu hình, đổi trạng thái, lịch sử/filter/replay, mobile390px/unique IDs.

M06: profile/qualification/license/training/calendar/revision, LEAVE/UNAVAILABLE/STANDBY, suggestions/standby/no auto assignment, invalid replacements rollback, approve/notification/SELF versus OPERATOR ack, new replacement needs reapproval, immutable events, invalidation after profile/calendar changes, backend scopes. Browser tạo/sửa profile, nghỉ/dự bị/hủy, tìm chuyến/phân công/duyệt/ack/thay dự bị, actor và lịch sử tiếng Việt. Quy tắc local-crew-v1 là LOCAL_ONLY, không nhận là chuẩn được chuyên gia phê duyệt.

14race records dùng hai kết nối MySQL độc lập và quan sát2waiters: booking last-seat, master import/publication, aircraft/gate/crew overlap, planning publish/create-flight/maintenance, configuration concurrent edits/configuration-versus-flight, usage duplicate, leave/profile-versus-crew-approval. Usage duplicate được phép cả hai request trả thành công cùng record ID nhưng chỉ cộng1lần; crew approval-versus-change phải kết thúc ở ASSIGNED với approvals bị hủy, không ép mọi race thành một winner.

## Lượt lỗi được bảo toàn

Build `6bf97d504a34b965399fa3fd1d2f9066734425385db5b0da39dc54b7f8eef34b`: MySQL138tests/1Failed/1167assertions. Fixture old last-seat sửa model capacity sau khi flight đã chốt capacity; không còn mô tả chuyến1ghế. Giữ source fixture/log/JUnit/manifest/46artifact tại [initial-regression](evidence/2026-10-18-fleet/initial-regression/build-manifest.json), ledger có Failed và importer đã từ chối promotion. Fixture mới dùng cấu hình vật lý1ghế/chốt capacity1, vẫn kiểm1created/1rejected và một seat item. Focused recheck1test/16assertions rồi full MySQL138 đạt; không hạ invariant.

Development folders giữ lỗi route usage trùng (scope/filter), MySQL index quá64ký tự, gợi ý lại người đang phân công, locator test chưa đúng semantic layout và DECIMAL SQLite response. Defects ghi root cause và lượt sửa; không coi những lượt lỗi đã Passed.

## Chưa đạt gate phát hành

Chưa hoàn tất M03/M05, M08–M16 và đủ48UC; BOOK001/002/QR001 vẫn High, payment/expiry/callback/refund/QR concurrency chưa thực chạy. Baseline24NotRun, coverage driver/gate, load, Docker runtime/CI cloud, Wokwi và ký UAT còn mở. NFR tải lớn/RPO/RTO chưa chứng minh. Restore chỉ fixture độc lập, không nghiệm thu restore production hay workflow attachment chưa triển khai. Clipboard thật không bị đụng tới, mới kiểm contract mock. AODB/FIDS và outbound mail/SMS/payment thật chưa kết nối; nhãn DEMO/local profile được ghi rõ, không dùng số giả thay ledger nội bộ.

Demo đã backup private rồi migrate000006/000007 additive, không reset/reseed hoặc đổi .env/APP_KEY/tài khoản. URL http://127.0.0.1:8000/login; dùng demo_ops cho nghiệp vụ được cấp quyền rõ. Tham khảo [hướng dẫn M04/M06](../user-guides/fleet-crew.md) và [checkpoint](../implementation-progress.md). Bản báo cáo M02 trước giữ ở release-test-report-planning.md.
