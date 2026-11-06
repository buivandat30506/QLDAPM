# Báo cáo kiểm thử — M02, 18/10/2026

**Chưa hoàn tất/nghiệm thu48UC.** Hiện8Implemented (UC01–06,18–19),21InProgress,19NotStarted. M02 đã triển khai từ DB/service/quyền/UI đến kiểm thử; G2 còn M03–M06. UAT human approval Pending.

Build `817554fe930bb2ce2e8e22f6956d2e47ce4fec91bb17f901f4f087f815f6f60d`, base commit `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`; worktree chưa commit, bảo toàn code người dùng. [Manifest](evidence/2026-10-18-planning/build-manifest.json) / [xác minh hash sau tests](evidence/2026-10-18-planning/verification-summary.json). [G2 trước M02](release-test-report-g2.md), [G1](release-test-report-g1.md) giữ riêng.

Runtime thực: Windows, PHP8.2.12, Laravel12.69.2, MySQL Community8.0.46/InnoDB localhost3318, PHPUnit11.5.56, Chrome/Playwright1.64.1, Vite7.3.7/Bootstrap5.3.8. Không reset DB người dùng hoặc xoay key/password. Demo được dump riêng trước migration000005.

| Kiểm tra thực chạy | Kết quả | Bằng chứng trong evidence/2026-10-18-planning |
| --- | --- | --- |
| MySQL unit/feature/integration | **115Passed /867assertions**, exit0,4m51.727s | mysql.xml/.txt; DB airline_test_20261018_023918_51060f |
| SQLite hỗ trợ phát triển | **104Passed /11Skipped /762assertions**,34.242s | sqlite.xml/.txt; skips gồm9race/2MySQL CHECK, không dùng làm bằng chứng locking |
| Chrome/MySQL acceptance | **9Passed**,45.1s | browser.xml/.txt; DB airline_test_browser_20261018_023918_972b3b |
| Booking/import/master publish và aircraft/gate/crew races | 6ca hồi quy, mỗi ca2waiters/1winner | booking-race, import-race, master-publish-race, operations-*-race.json |
| Commit lịch: publish trùng, tạo flight trùng, submit vs maintenance | 3ca mới, mỗi ca2kết nối MySQL thực đợi mutex;1committed/1rejected | planning-publish-race.json, planning-flight-race.json, planning-maintenance-race.json |
| Restore riêng | **45tables**,59FK,0orphan, fingerprint/giải mã assistance/upload fixture khớp | restore.json/.txt; target airline_test_restore_20261018_024051_7d72da |
| Demo thật localhost8000 | Generated credentials login/read/deny; heartbeat queue/scheduler; demo_ops bấm menu M02 và xem collection/detail/history | local-demo.json, runtime-pulse.txt, login-verification.json, live-planning.json/.png |
| PHP/Vite/API/source | 188PHP files/0syntax errors; Vite exit0;31API operations YAML/refs/params;48UC/16module/12DOCX hashes | php-lint.txt, asset-build.txt, api-docs-check.txt, traceability-check.txt; source-register.json |

Phạm vi M02 được kiểm: route CRUD/duplicate/unknown airport/mùa/hạn chế/linked-delete/audit, airport collection/object scope trước validation, once/weekly/excluded/extra dates, nguồn local/UTC/DST/overnight và service_date khác ngày UTC; optimistic revision, missing resources và báo cáo nhiều lỗi; trách nhiệm actor/time, reject/cancel/revise, published snapshots bất biến, giữ bản cũ khi sửa, impacts chặn đổi flight âm thầm và giải quyết qua điều hành trước công bố. Kiểm lại thay đổi nguồn lực/mùa ở publication. Browser kiểm cả desktop/mobile, nhập/lưu mã mùa rồi reload, không trùng DOM IDs, accessible navigation, dữ liệu persist. Các ca cũ M01/M07/auth/audit/CSRF vẫn chạy.

Ledger của build này có **241hàng**:115MySQL +115SQLite (gồm11Skipped) +9browser +2rehearsal; không phải241test Passed. Baseline nguồn W1/W2 vẫn **19Passed/24Not Run** trên43hàng; aliases không cộng thành test thực thi mới. Source DOCX bổ sung24flow được tạo Not Run, chỉ map nhánh có actual execution phù hợp. UAT tự động không thay người nghiệm thu.

Lượt verified trước sửa hai lỗi UI có build aa763652…, giữ đầy đủ ở initial-verified và ledger241hàng riêng. Initial browser chưa assert mã mùa đầu tiên nên bỏ sót season_code NULL; lỗi tìm thêm bằng đối chiếu DB, sửa duplicate id và thêm reload assertion. Lỗi exact accessible-name click có icon dù link thực tế tồn tại; thêm aria-label/aria-hidden và smoke thật đạt. Mobile failure được sửa wrap, giữ assertion scrollWidth. Lỗi phát triển khác giữ ở development-02 (expected date format của test), browser-development-01 (fixture password/API bearer), browser-development-02 (overflow). Không coi các lần fail hoặc bước chưa thực chạy là Passed.

Lệnh tái lập của checkpoint (dùng thư mục evidence mới nếu chạy lại để giữ lịch sử):

```powershell
$env:TEST_EVIDENCE_DIR = Join-Path (Get-Location) 'docs/testing/evidence/2026-10-18-planning'
php scripts/snapshot-build.php
python scripts/review-source-documents.py
php scripts/test-mysql.php
php vendor/phpunit/phpunit/phpunit --log-junit=docs/testing/evidence/2026-10-18-planning/sqlite.xml
php scripts/prepare-browser.php
npm run test:acceptance
php scripts/verify-restore.php
node scripts/verify-local-demo.mjs
node scripts/verify-demo-login.mjs
php scripts/lint.php
php scripts/verify-api-docs.php
npm run build
php scripts/record-planning-evidence.php
php scripts/verify-traceability.php
```

Importer hiện gắn riêng checkpoint này, kiểm source hash và JUnit hoàn tất trước promote. Không dùng nó cho code phiên sau khi source đã đổi. Source case/trace CSV được cập nhật, giữ các ID baseline và kết quả build lịch sử.

Giới hạn: coverage Not Measured (không Xdebug/PCOV), load/RPO/RTO/CI cloud/Docker runtime/Wokwi Not Run; human approval Pending. Global planning mutex ưu tiên tính nhất quán local, chưa chứng minh throughput NFR. API legacy cho phép thiếu expected_revision; UI luôn gửi. Profile mùa/giờ sân bay/DST ghi ADR023–027, không phải quy tắc khai thác được chứng nhận. OpenAPI mới31operations M01/M02, chưa toàn bộ API. Restore chỉ dữ liệu tổng hợp/fixture upload, chưa các attachment nghiệp vụ còn thiếu.

BOOK-001/002, QR-001 và các khoảng trống maintenance/crew/finance/customer care/AI/IoT/integration vẫn mở. Adapter hiện có PasswordResetDelivery local-simulation/private mailbox, không gửi mail thật; clipboard thật Not Run, contract mock đã kiểm. Payment/expiry/callback/refund/QR an toàn và các module còn lại cần triển khai, không lấy endpoint409, dữ liệu mẫu hoặc thiếu credential để thay nghiệp vụ. Gate coverage85%line/75%branch và full48UC giữ nguyên, hiện chưa đạt.
