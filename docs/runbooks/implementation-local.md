# Chạy phần triển khai bổ sung

Phiên bản đã đo: PHP 8.2.12, Laravel 12.69.2, PHPUnit 11.5.56, MySQL Community 8.0.46, Node 24.16.0, Vite 7.3.7, Bootstrap 5.3.8, Playwright 1.64.1 với Chrome có sẵn. Composer/npm khóa dependency trong lockfiles. Windows CLI PHP tại C:/xampp/php/php.exe có pdo_mysql/pdo_sqlite/bcmath/mbstring/fileinfo. PHP không có Xdebug/PCOV; coverage chưa đo.

## Local với dữ liệu cần giữ

Không chạy migrate:fresh hoặc import lại SQL cũ. Backup trước nâng cấp; giữ .env và APP_KEY cũ vì có dữ liệu mã hóa. Kiểm tra engine bằng SELECT VERSION(), không suy từ nhãn XAMPP. Các lệnh sau chạy trong PowerShell tại repository:

```powershell
composer install
if (-not (Test-Path -LiteralPath .env)) { Copy-Item .env.example .env }
# Chỉ trên cài đặt mới chưa có APP_KEY: php artisan key:generate
# Tự cấu hình MySQL database/port/user/password trong .env trước migrate.
php artisan migrate
php artisan db:seed --class=AccessControlSeeder
npm ci
npm run build
```

MySQL/MariaDB connection đặt session timezone +00:00; các mốc nghiệp vụ mới lưu UTC. Không tự sửa timestamp lịch sử của DB cũ: cần đối chiếu timezone đã dùng khi ghi trước khi chuyển đổi. Không tự sửa DB_PORT hoặc password của người dùng.

Bootstrap quyền trên local: xem [identity-local-adapter.md](identity-local-adapter.md). Tạo quản trị `php artisan access:bootstrap admin_local admin@example.test`, hoặc cấp quyền cho account cũ bằng `--existing` với username/email khớp. ADMIN chỉ quản trị account/audit; muốn vận hành cần gán thêm role cụ thể. M01 sân bay/tài nguyên đã hỗ trợ airport/global ở collection/object; master data cần global. Các legacy core collection còn lại vẫn cần global. API hành khách và hỗ trợ đã kiểm ownership/airport/flight.

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Vào `/login`; quản trị `/administration/users`, audit `/administration/audit`; hành khách `/portal/profiles` và `/portal/assistance`; dữ liệu nền `/admin/airports` và `/admin/master-data`. Demo `php artisan db:seed` chỉ local/testing; dùng random password cho account local mới, khôi phục qua private mailbox; account cũ không đổi password. Bộ demo chưa đủ số lượng theo mục 11.1 master prompt. Đã có RuntimeHeartbeat chạy qua queue/scheduler và readiness; expiry/outbox/payment/AI/IoT nghiệp vụ đầy đủ vẫn chưa triển khai.

## Bản demo riêng không sửa .env

`php scripts/setup-local-demo.php` tạo DB MySQL mới và profile private. Có thể đặt DEMO_DB_HOST/PORT/USER/PASSWORD; mặc định instance cô lập localhost3318. Script từ chối ghi đè profile đã có. Tài khoản demo_admin/demo_data/demo_ops/demo_customer có mật khẩu ngẫu nhiên riêng trong storage/app/private/demo-runtime.json, không in ra log. Key mới chỉ gắn DB mới; không thay key DB người dùng.

Nhập đúng tên đăng nhập, ví dụ `demo_admin`, tại http://127.0.0.1:8000/login. Chạy helper bên dưới trong PowerShell cục bộ, sau đó dán clipboard vào ô mật khẩu. Helper giải mã JSON trước khi sao chép nên dấu nháy/backslash không bị biến thành chuỗi escape. Không in mật khẩu, thay hash hoặc ghi DB. Không dùng mật khẩu fixture testing. Khi đã đổi mật khẩu trong ứng dụng, dùng Quên mật khẩu/[private mailbox](identity-local-adapter.md) nếu profile ban đầu không còn khớp.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1 -Username demo_admin -CopyPassword
```

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 start
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 status
php scripts/demo.php app:health
# Khi cần dừng riêng web/worker/scheduler của demo:
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 stop
```

Browser tại http://127.0.0.1:8000/login; log storage/logs/demo-*.log. Readiness cần heartbeat worker/scheduler, có thể chờ đến nhịp scheduler tiếp theo. Windows launcher xác minh executable/command line/creation time trước dừng PID và descendants; không dừng process chỉ vì chiếm port. Linux có thể chạy ba terminal `php scripts/demo.php serve --host=127.0.0.1 --port=8000`, `php scripts/demo.php queue:work --tries=3`, `php scripts/demo.php schedule:work`.

## Kiểm thử không dùng DB đang có

Có MySQL 8.0 cài trên Windows nhưng thiếu credential dịch vụ hiện hữu: script tạo instance riêng trong workspace, bind localhost:3318, chỉ chứa fixture tổng hợp. Không tạo/sửa Windows service, không sửa MySQL80 đang chạy.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-test-mysql.ps1
php scripts/lint.php
php scripts/verify-traceability.php
php artisan test --log-junit=docs/testing/evidence/2026-10-18/g1-sqlite.xml
php scripts/test-mysql.php
php scripts/prepare-browser.php
npm run test:acceptance
```

`test-mysql.php` và `prepare-browser.php` tạo DB tên `airline_test_*` ngẫu nhiên mới, giữ để đối chiếu. TestCase chặn môi trường/database khác **trước** RefreshDatabase migration. Race test chạy hai tiến trình PHP riêng và quan sát hai lock waiter qua performance_schema.data_lock_waits; cần tài khoản test có quyền CREATE DATABASE và xem performance_schema. SQLite skip race, không coi là bằng chứng khóa.

Máy khác dùng biến TEST_MYSQL_HOST/PORT/USER/PASSWORD cho server test. Linux/CI cài browser bằng `npx playwright install --with-deps chromium`; Windows mặc định dùng Chrome có sẵn, có thể chọn PLAYWRIGHT_CHANNEL=msedge. Browser runner chỉ dùng DB được prepare-browser tạo, server localhost:8018, không reuse server người dùng. Trace network tắt vì chứa session secret; lưu JUnit và screenshots sau login với dữ liệu giả lập.

Tắt instance đã khởi động bằng client cài sẵn:

```powershell
mysqladmin --host=127.0.0.1 --port=3318 --user=root shutdown
```

Không xóa thư mục mysql-testing hoặc DB khác khi còn cần đối chiếu. CI workflow đã viết nhưng chưa được thực thi trên GitHub; Compose YAML đã kiểm nhưng Docker runtime chưa chạy. Restore riêng đã chạy theo [backup-restore.md](backup-restore.md); load/Wokwi/coverage và baseline còn lại chưa được xác minh. Test reports không thay chữ ký UAT. Khi demo đang chạy, giữ instance MySQL cô lập hoạt động; dừng demo trước nếu muốn shutdown instance.
