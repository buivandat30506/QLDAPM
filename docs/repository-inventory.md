# Kiểm kê ngày 18/10/2026

Đây là snapshot **đầu phiên trước khi sửa**. Hiện trạng sau triển khai và bằng chứng mới nằm trong [implementation-progress.md](implementation-progress.md).

Đã đọc toàn bộ 3.462 dòng master prompt, phụ lục A–E. Không có AGENTS.md trong repository (ngoài vendor/node_modules/.git) hoặc các thư mục cha C:/, C:/Users, C:/Users/Admin, C:/Users/Admin/Desktop. Không có sáu DOCX; dùng nội dung văn bản phụ lục, không nhận đã đối chiếu hình UML.

Commit nền: `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`. Worktree đã bẩn trước phiên: 22 file tracked thay đổi và nhiều file untracked. Không reset/checkout hoặc commit thay người dùng. Bản sao mã đầu phiên, diff và SHA256 tại `storage/app/implementation-baseline-20261018/` (không chứa .env). Không di chuyển cấu trúc dịch vụ cũ.

Runtime đo: Windows/PowerShell, PHP CLI 8.2.12 tại C:/xampp/php/php.exe; Composer 2.10.3; Laravel khóa 12.69.2; PHPUnit 11.5.x; Node 24.16.0. Có pdo_mysql, pdo_sqlite, bcmath. Không có Xdebug/PCOV nên coverage chưa đo. MySQL80 service đang chạy, mysql client 8.0.46; engine server cần query riêng, không suy ra từ client. Frontend cũ Blade + Bootstrap 5.3.3 CDN; package.json còn scaffold Tailwind/Vite, chưa có lockfile npm hoặc node_modules.

| Phạm vi | Hiện trạng đọc từ mã | Thiếu so với hợp đồng đích |
| --- | --- | --- |
| M01–M06 | Airport/resources/routes/aircraft CRUD; schedule workflow; flight events/disruption; maintenance; crew rules/approval/history | Cần kiểm chứng tất cả nhánh; import/export dữ liệu chủ, cấu hình ghế/version, plan/work order/release chi tiết, khả dụng/nghỉ/dự bị, concurrency và scope |
| M07 | Model passenger; được tạo từ booking | CRUD/consent/hỗ trợ/UI/quyền hồ sơ còn thiếu |
| M08 | Giữ chỗ một chuyến bằng khóa flight; 15 phút; xác nhận trực tiếp | Client tự gửi giá; chưa payment adapter/chữ ký/ledger/ticket/refund; chưa nhiều chặng; expiry lỗi rollback; ownership chưa có |
| M09 | API check-in và consume token có transaction | API công khai; DB token hash đang dùng như bearer; chưa QR thật/camera/reissue/revocation/wrong-flight/no-show/offload; ghế chưa map capacity |
| M10 | Tag và event CRUD | API công khai; chưa late/dedupe/segment/incident/return; PII trả thẳng |
| M11–M13 | Chưa có module nghiệp vụ | Toàn bộ task/turnaround/finance/reconciliation/CSKH |
| M14 | Dashboard đếm từ DB | Filter/scope KPI; AI/schema/provenance/registry/governance chưa có |
| M15 | Chưa có simulator hoặc ingest | Contract, Wokwi, scenarios, auth, late/dedupe/offline/alerts |
| M16 | Auth session/Sanctum; middleware roles theo URL; audit/version | ADMIN bypass tất cả; thiếu role permissions/scope/delegation; audit chưa redact/correlation/outcome; reset password, administration UI và integration/config chưa có |

Bộ kiểm thử đầu phiên: `php artisan test --log-junit=docs/testing/evidence/2026-10-18/baseline-sqlite.xml`: **23 tests, 180 assertions, exit 0**. Đây là feature/unit hiện hữu trên SQLite, không phải nghiệm thu UC hoặc chứng minh locking MySQL. Console và JUnit nằm cùng thư mục evidence. Tài liệu README cũ không đại diện kết quả kiểm thử phiên này.
