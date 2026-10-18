# Quản lý Hàng không Thông minh

Ứng dụng Laravel + Blade/Bootstrap/JavaScript + MySQL/InnoDB. Phạm vi đích là 16 phân hệ / 48 use case, gồm Should/Could Have. **Dự án đang triển khai, chưa hoàn thành hoặc nghiệm thu toàn bộ.**

Tiến độ và điểm tiếp tục: [implementation-progress](docs/implementation-progress.md). Truy vết đủ 48 UC: [traceability.csv](docs/traceability.csv). Tài liệu nguồn: [master prompt và phụ lục A–E](CODEX_MASTER_PROMPT_QL_HANG_KHONG.md).

Hiện có nền đăng nhập/quyền/scope/ủy quyền/audit, M01 sân bay–tài nguyên–dữ liệu chủ, M02 lịch/phiên bản, M04 cấu hình ghế/usage, M05 kế hoạch/lệnh/vật tư/tài liệu/ký duyệt/giải phóng, M06 hồ sơ/khả dụng/phân công và portal hồ sơ/consent/hỗ trợ M07. M03 và một số API booking/check-in/hành lý còn từng phần. Payment/refund, QR an toàn và các phần mở rộng M11–M16 chưa đầy đủ. Các ngưỡng an toàn local chưa được chuyên gia phê duyệt. Xem tiến độ thay vì suy ra hoàn thành từ tên menu; [hướng dẫn M04/M06](docs/user-guides/fleet-crew.md), [hướng dẫn M05](docs/user-guides/maintenance.md).

## Chạy bản local độc lập trên Windows

Runtime đã chạy: PHP 8.2.12, Laravel 12.69.2, MySQL Community 8.0.46, Node 24.16.0. Dùng dependencies trong composer.lock/package-lock.json; PHP cần pdo_mysql, mbstring, bcmath, xml, curl, fileinfo, openssl. Vite và Playwright chỉ phục vụ asset/QA.

```powershell
composer install
npm ci
npm run build
# Nếu cần instance MySQL riêng từ MySQL 8.0 đã cài tại Program Files:
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-test-mysql.ps1
php scripts/setup-local-demo.php
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 start
```

Mở http://127.0.0.1:8000/login. `setup-local-demo.php` tạo DB mới `airline_demo_*`, không sửa .env/APP_KEY hoặc DB hiện hữu. Tài khoản demo_admin, demo_data, demo_ops, demo_customer dùng mật khẩu ngẫu nhiên riêng trong **storage/app/private/demo-runtime.json**. Không commit/chia sẻ file này. Lệnh setup từ chối ghi đè profile đã có; chạy lại dùng launcher hiện hữu.

Để đăng nhập quản trị, nhập tên `demo_admin`, chạy lệnh sau rồi dán vào ô mật khẩu. Helper giải mã JSON và sao chép đúng ký tự vào clipboard, không in mật khẩu. Không sao chép chuỗi JSON có dấu nháy/escape, không dùng mật khẩu fixture trong tests. Thay `demo_admin` bằng `demo_data` để quản lý sân bay/dữ liệu chủ; ADMIN chỉ có quyền quản trị hệ thống.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1 -Username demo_admin -CopyPassword
```

Chạy helper không có `-CopyPassword` để liệt kê tên/vai trò. Nếu đã đổi mật khẩu trong ứng dụng, mật khẩu trong profile ban đầu có thể không còn khớp: dùng luồng Quên mật khẩu và [mailbox mô phỏng cục bộ](docs/runbooks/identity-local-adapter.md), không chạy lại setup để ghi đè tài khoản.

Với server MySQL khác, đặt DEMO_DB_HOST/PORT/USER/PASSWORD trước setup. Xem [hướng dẫn local](docs/runbooks/implementation-local.md). Bản demo chưa đạt số lượng dữ liệu mục 11.1 master prompt.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 status
php scripts/demo.php app:health
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 stop
```

Launcher chạy web, database worker và scheduler ẩn, ghi PID/log và chỉ dừng cây tiến trình đã xác minh do chính launcher tạo. `app:health`/`/health/ready` yêu cầu MySQL/schema và heartbeat worker/scheduler còn mới khi dùng database queue. Chưa có outbox nghiệp vụ/payment expiry đầy đủ; heartbeat chỉ chứng minh hạ tầng queue/scheduler.

## Nâng cấp cài đặt đang có

Giữ nguyên .env, APP_KEY và backup trước nâng cấp. Không dùng migrate:fresh hoặc nhập lại SQL cũ. Cấu hình credential MySQL hợp lệ rồi chạy:

```powershell
php artisan migrate
php artisan db:seed --class=AccessControlSeeder
```

ICAO trùng sẽ chặn migration để sửa dữ liệu có kiểm soát. ADMIN không tự có quyền khai thác/ký kỹ thuật: cấp từng role/scope qua UI hoặc CLI local. [Hướng dẫn bootstrap/quyền](docs/runbooks/identity-local-adapter.md).

## Docker và kiểm thử

[Compose/runbook](docs/runbooks/docker-local.md) có web, MySQL, init migration, worker, scheduler và volume bền vững. Máy triển khai hiện không có Docker; profile này **chưa chạy xác minh**. [Báo cáo kiểm thử](docs/testing/release-test-report.md) phân biệt ca đã thực chạy với Not Run/Skipped và UAT approval Pending.

```powershell
$taskRunId = Get-Date -Format 'yyyy-MM-dd-HHmmss'
$env:TEST_EVIDENCE_DIR = Join-Path (Get-Location) "docs/testing/evidence/$taskRunId"
New-Item -ItemType Directory -Path $env:TEST_EVIDENCE_DIR -Force | Out-Null
php scripts/lint.php
php scripts/verify-traceability.php
php scripts/test-mysql.php
php scripts/prepare-browser.php
npm run test:acceptance
php scripts/verify-restore.php
```

Các script tạo DB test riêng; không reset DB người dùng. [Backup/restore](docs/runbooks/backup-restore.md) đã có rehearsal trên dữ liệu tổng hợp, không thay nghiệm thu phục hồi môi trường thật.

Tài liệu M01: [hướng dẫn sử dụng](docs/user-guides/foundation.md), [API](docs/api/foundation.openapi.yaml). M02: [hướng dẫn đường bay/lịch bay](docs/user-guides/flight-planning.md), [API](docs/api/flight-planning.openapi.yaml). M03: [API chuyến bay, event/cảnh báo và recovery](docs/api/flight-operations.openapi.yaml); vào `/admin/routes` bằng demo_data, `/admin/schedules` và `/admin/flights` bằng demo_ops. [Dictionary](docs/data-dictionary.md), [quyền](docs/permissions-matrix.csv), [nguồn DOCX và các flow bổ sung](docs/requirements-docx-supplement.json). Tài liệu Module 1 cũ giữ tại [README_MODULE1](README_MODULE1.md) và [README trước G2](docs/archive/README-before-g2.md); trạng thái hiện hành nằm trong progress.
