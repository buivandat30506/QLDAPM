# Báo cáo kiểm thử — checkpoint G2, 18/10/2026

**Chưa nghiệm thu toàn bộ dự án.** Phạm vi vẫn M01–M16 / 48 UC, gồm Should/Could Have. Trace hiện có 5 UC Implemented (01/02/03/18/19), 24 InProgress, 19 NotStarted. Implemented không thay Verified/UAT approval hoặc đóng gate release.

Build `6bb83424b2e150bbb28b7a7dda9c4de3837a86ab0ce36d98d728cc970b334461`, commit nền `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`, worktree chưa commit và chứa code đã có của người dùng. [Manifest source](evidence/2026-10-18-g2/build-manifest.json) được chụp trước lượt hồi quy cuối; script record kiểm lại mọi hash trước gắn kết quả. Evidence G1 vẫn giữ; [báo cáo G1](release-test-report-g1.md) là lịch sử.

Môi trường thực: Windows, PHP8.2.12/Laravel12.69.2/PHPUnit11.5.56; MySQL Community8.0.46/InnoDB localhost3318 độc lập; Node24.16.0/Vite7.3.7/Bootstrap5.3.8, Chrome headless/Playwright1.64.1. Không reset DB người dùng hoặc đổi .env/APP_KEY. Demo mới có DB/key/private credentials riêng.

| Kiểm tra thực chạy | Kết quả | Bằng chứng |
| --- | --- | --- |
| Suite PHP unit/feature/integration, profile MySQL | 93 Passed, 632 assertions, exit0 | [mysql.xml](evidence/2026-10-18-g2/mysql.xml), mysql.txt, mysql-environment.json |
| Suite SQLite hỗ trợ phát triển | 86 Passed / 7 Skipped, 561 assertions | [sqlite.xml](evidence/2026-10-18-g2/sqlite.xml), sqlite.txt; skip các race và CHECK cần MySQL |
| Browser acceptance Chrome/MySQL | 6 Passed, 20.3s | [browser.xml](evidence/2026-10-18-g2/browser.xml), browser.txt, screenshots PNG |
| Booking seat race hồi quy | 2 waiters, 1 created/1 rejected | booking-race.json trong cùng evidence |
| Master import/publication races | Mỗi ca 2 connections/2 waiters, 1 committed/1 rejected | import-race.json, master-publish-race.json |
| Aircraft/gate/crew contention | 3 ca, mỗi ca 2 waiters và chỉ1 thao tác chồng giờ hợp lệ | operations-aircraft-race.json, operations-gate-race.json, operations-crew-race.json |
| Local demo/browser/background | Generated credentials login, CRUD pages, customer denied master; real scheduled job xử lý qua DB queue, readiness ready | local-demo.json, runtime-pulse.txt, local-demo-master.png |
| Backup/restore riêng | 44 bảng fingerprint khớp; 52 FK không orphan; assistance giải mã đúng; upload fixture checksum khớp | [restore.json](evidence/2026-10-18-g2/restore.json), restore.txt |
| Source baseline W1/W2 | 19 Passed / 24 Not Run, tổng43 | baseline-mapping.csv; alias không được cộng thành test chạy riêng; UAT approval Pending |
| PHP syntax | 177 file, 0 lỗi | php-lint.txt |
| Vite/Composer | Build exit0; composer.json valid | asset-build.txt, composer-validate.txt |
| Traceability/API docs | 48UC/16module/source flows đúng; YAML/local refs/params của19 API operations đúng | traceability-check.txt, api-docs-check.txt |

Execution ledger giữ các build cũ; build hiện hành có194 dòng: 93 hàng MySQL, 93 hàng SQLite (gồm7 Skipped), 6 browser, 2 rehearsal. **194 dòng không có nghĩa194 test Passed.** Lượt đã verified trước khi sửa mapping được lưu riêng trong initial-verified và ledger trỏ đúng archive, không gắn log mới cho build cũ. Alias baseline/source flows không phải lần thực thi bổ sung. [Execution ledger](test-execution-results.csv), [baseline mapping](baseline-mapping.csv).

Đã sửa lỗi metadata baseline giữ build cũ khi đổi evidence_path; hiện build/time/status lấy cùng JUnit đã hoàn tất. Sau sửa đã chạy lại cả suite PHP/MySQL/SQLite và browser, không nhận file XML đang ghi dở là kết quả.

Browser mới kiểm sân bay tạo/sửa/duplicate/deny write; tài nguyên vị trí đỗ/tạm ngừng; CSV valid/malformed/mixed-quality; atomic import; gửi/duyệt/công bố/revise/history; filter/export. Hồi quy account/role/audit/CSRF và profile/consent/assistance vẫn chạy. Ảnh chỉ dữ liệu tổng hợp, không trace network hoặc credentials. API/DB feature mở rộng kiểm required/ICAO/timezone/coordinate/CHECK/scopes/mass assignment, quality reference, stale revision, immutable snapshots, revalidation, retry và formula injection. Không gọi mọi test là unit.

Lần lỗi ban đầu ở foundation-initial.xml là assertion chưa tính Laravel trim whitespace trước export; đã chỉnh kiểm prefix formula theo dữ liệu thực và thêm vectors whitespace/BOM ở unit. Lần restore thử đầu không có ZipArchive đã thất bại, chuyển TAR/PharData và chạy lại thành công; không sửa php.ini hoặc nhận lần lỗi là Passed. Failure evidence G1 về JSON và booking snapshot vẫn giữ nguyên.

Giới hạn/gate: coverage **Not Measured** (thiếu driver), Docker container build/runtime **Not Run** (không có Docker), CI cloud **Not Run**, load/RPO/RTO/Wokwi **Not Run**; còn24 baseline Not Run. CHECK/race đều có profile MySQL thật, không dùng SQLite làm bằng chứng. Restore chỉ fixture và upload archive fixture, chưa đầy đủ attachment nghiệp vụ. OpenAPI mới bao M01, chưa tất cả API. Bộ seed chưa đủ định mức master.

High defects BOOK-001/002 và QR-001 còn mở; payment vs expiry, late/duplicate callbacks, refund/QR scanner races và nhiều source flows chưa triển khai/kiểm thử. Không suy rộng race submit/assign sang publish khác phương án, tạo flight hoặc disruption. Nguồn DOCX/hình chưa có; phụ lục văn bản đã được dùng, không giả nguồn.

Adapter hiện có PasswordResetDelivery/LocalPasswordResetDelivery (local-simulation/private mailbox), không gửi mail thật. Các payment/SSO/ERP/BHS/MRO/HRM/notification/AI/IoT adapters đầy đủ còn phải xây dựng, không gọi thiếu implementation là thiếu credential. Legacy direct-confirm/hash scanner tiếp tục409 đến khi G3 có workflow an toàn. Demo chạy các phần đã triển khai; không phải toàn bộ sản phẩm48UC.

Release gate line85%/branch75% và toàn bộ baseline/UC acceptance/invariants giữ nguyên; hiện **chưa đạt**. Human UAT approval **Pending**. Điểm tiếp tục ở [implementation-progress](../implementation-progress.md).

