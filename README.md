# Quản lý Hàng không Thông minh

> Ứng dụng nghiệp vụ xây dựng bằng PHP 8.2+, Laravel 12, Blade, Bootstrap, JavaScript và MySQL 8/InnoDB. Đây là dự án đang triển khai; tài liệu tiến độ liệt kê phần đã làm, phần còn thiếu và trạng thái kiểm chứng. Chưa coi hệ thống là hoàn chỉnh/nghiệm thu cho vận hành hàng không thật.

## Mục lục

- [Yêu cầu máy](#yêu-cầu-máy)
- [Cài mới trên Windows](#cài-mới-trên-windows)
- [Mở ứng dụng và đăng nhập demo](#mở-ứng-dụng-và-đăng-nhập-demo)
- [Chạy bằng MySQL/XAMPP có sẵn](#chạy-bằng-mysqlxampp-có-sẵn)
- [Dừng ứng dụng](#dừng-ứng-dụng)
- [Kiểm thử](#kiểm-thử)
- [Docker](#docker)
- [Cập nhật dự án](#cập-nhật-dự-án)
- [Tài liệu cho người phát triển FE](#tài-liệu-cho-người-phát-triển-fe)
- [Phạm vi và giới hạn hiện tại](#phạm-vi-và-giới-hạn-hiện-tại)

## Yêu cầu máy

Đã có trên môi trường phát triển Windows của repo: PHP 8.2, Laravel 12, MySQL Community 8.0, Node.js 24, npm, Composer, Chrome. Dependency phiên bản chính xác được khóa trong composer.lock và package-lock.json.

- PHP 8.2 trở lên, Composer 2; bật ít nhất pdo_mysql, mbstring, bcmath, xml, curl, fileinfo, openssl.
- MySQL 8.0/InnoDB và quyền tạo database. Bộ demo độc lập dùng cổng 3318 mặc định; không cần đụng vào MySQL đang chứa dữ liệu của bạn.
- Node.js/npm chỉ cần để cài và build asset giao diện hoặc chạy Playwright.
- Trên Windows, chạy lệnh trong PowerShell ở thư mục gốc repository.

> Không commit .env, mật khẩu demo, storage/app/private/demo-runtime.json, credential, database dump hoặc dữ liệu hành khách. .env.example là mẫu cấu hình không chứa secret.

## Cài mới trên Windows

### 1. Lấy mã nguồn

Nếu chưa clone repository:

```powershell
git clone --branch feature/toan https://github.com/buivandat30506/QLDAPM.git
Set-Location QLDAPM
```

Nếu repository đã có trên máy, mở PowerShell tại thư mục gốc dự án. Không chạy git reset --hard hoặc xóa .env để cài lại.

### 2. Cài dependency PHP và frontend

```powershell
composer install
npm ci
npm run build
```

Nếu máy báo thiếu PHP extension, bật extension tương ứng trong php.ini đang được CLI PHP dùng, rồi mở terminal mới và xác minh:

```powershell
php -v
php --ini
php -m
composer --version
node --version
npm --version
```

### 3. Khởi động MySQL thử nghiệm riêng

Script dưới đây dùng MySQL 8 cài sẵn trên Windows, tạo/chạy instance local riêng ở cổng 3318; không thay đổi Windows service/MySQL hiện hữu. Mặc định đăng nhập root không password. Nếu cấu hình máy khác, xem scripts/start-test-mysql.ps1 và đặt các biến DEMO_DB_HOST, DEMO_DB_PORT, DEMO_DB_USER, DEMO_DB_PASSWORD phù hợp trước bước tạo demo.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-test-mysql.ps1
```

### 4. Tạo database và tài khoản demo riêng

```powershell
php scripts/setup-local-demo.php
```

Lệnh này tạo một database mới tên airline_demo_*, chạy migrations/seeders và tạo tài khoản local với mật khẩu ngẫu nhiên. Nó không ghi đè .env, APP_KEY hay database hiện có. Thông tin runtime/mật khẩu được lưu riêng tại storage/app/private/demo-runtime.json; không commit hoặc gửi file này cho người khác.

Setup từ chối chạy đè profile đã tạo. Nếu profile tồn tại, dùng bộ demo hiện tại; không xóa profile/database chỉ để chạy setup lại.

### 5. Khởi chạy web, queue worker và scheduler

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 start
```

Mở http://127.0.0.1:8000/login. Launcher chạy web, queue worker và scheduler ở nền, lưu PID/log trong storage. Trạng thái và health:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 status
php scripts/demo.php app:health
```

Health có thể cần chờ scheduler và worker phát/nhận heartbeat. Heartbeat xác minh dịch vụ nền đang chạy; nó không chứng minh các nghiệp vụ queue/outbox/payment chưa triển khai đã hoạt động.

## Mở ứng dụng và đăng nhập demo

Các tài khoản được tạo cục bộ, mật khẩu khác nhau và ngẫu nhiên. Liệt kê username/vai trò (không in mật khẩu):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1
```

Ví dụ để sao chép mật khẩu quản trị demo vào clipboard, sau đó dán vào form đăng nhập:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1 -Username demo_admin -CopyPassword
```

| Username | Vai trò demo | Ghi chú |
| --- | --- | --- |
| demo_admin | ADMIN | Quản trị tài khoản/audit; không tự có quyền khai thác hoặc ký kỹ thuật. |
| demo_data | DATA_ADMIN | Dữ liệu sân bay và dữ liệu chủ. |
| demo_ops | FLIGHT_OPS, FLEET_MANAGER, CREW_OPS, TECHNICIAN | Vận hành/lịch, đội tàu, tổ bay, kỹ thuật theo role/scope đã seed. |
| demo_customer | CUSTOMER | Portal khách hàng với phạm vi bản thân. |

Đổi mật khẩu sau khi đăng nhập theo chính sách ứng dụng. Mật khẩu ban đầu trong profile không tự cập nhật khi đổi trong UI. Nếu quên, dùng luồng Quên mật khẩu và mailbox mô phỏng theo docs/runbooks/identity-local-adapter.md; không chạy setup lại để ghi đè user.

## Chạy bằng MySQL/XAMPP có sẵn

> Dùng cách này khi muốn kết nối database bạn tự cấu hình. Hãy xác nhận đúng host, port, database và có backup trước khi migrate. Không dùng database production để phát triển.

1. Cài dependency và build asset theo bước trên.
2. Tạo file cấu hình nếu chưa có:

```powershell
if (-not (Test-Path -LiteralPath .env)) { Copy-Item .env.example .env }
```

3. Mở .env; đặt APP_URL, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD theo MySQL local của bạn. Tạo database rỗng trước. Giữ APP_KEY nếu .env đã có key. Chỉ tạo key cho ứng dụng mới chưa từng có dữ liệu mã hóa:

```powershell
php artisan key:generate
```

4. Chạy migration cộng dồn và quyền nền tảng:

```powershell
php artisan migrate
php artisan db:seed --class=AccessControlSeeder
```

5. Khởi động web:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Mở http://127.0.0.1:8000/login. Cấu hình queue là database; để xử lý jobs/scheduler khi chạy theo cách thủ công, mở thêm hai PowerShell tại repo:

```powershell
php artisan queue:work --tries=3 --timeout=30
```

```powershell
php artisan schedule:work
```

Không dùng php artisan migrate:fresh, db:wipe hoặc nhập SQL cũ vào database đang có dữ liệu. Migration có thể dừng khi phát hiện mã ICAO trùng; xử lý dữ liệu theo hướng dẫn migration thay vì xóa/ghi đè.

Tạo admin local khi cần bằng CLI (lệnh sẽ hỏi mật khẩu):

```powershell
php artisan access:bootstrap admin_local admin@example.test
```

ADMIN chỉ quản trị tài khoản/audit. Gán thêm role nghiệp vụ và data scope qua giao diện quản trị theo docs/runbooks/identity-local-adapter.md.

## Dừng ứng dụng

Nếu chạy qua launcher demo:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-demo.ps1 stop
```

Dừng MySQL demo riêng sau khi đã dừng ứng dụng và không còn cần dữ liệu demo:

```powershell
mysqladmin --host=127.0.0.1 --port=3318 --user=root shutdown
```

Nếu chạy bằng php artisan serve, queue:work, schedule:work trong các terminal riêng, nhấn Ctrl+C trong từng terminal. Không dừng process chỉ dựa trên số cổng; không xóa database/profile nếu còn cần dữ liệu.

## Kiểm thử

Lệnh nhanh cho PHP tests:

```powershell
php artisan test
```

Build frontend:

```powershell
npm run build
```

Bộ kiểm thử MySQL và browser dùng database test riêng được tạo bởi script; không dùng SQLite để chứng minh locking/concurrency:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-test-mysql.ps1
php scripts/lint.php
php scripts/verify-traceability.php
php scripts/test-mysql.php
php scripts/prepare-browser.php
npm run test:acceptance
php scripts/verify-restore.php
```

Playwright cần trình duyệt đã cài. Trên Linux/CI có thể cài Chromium bằng npx playwright install --with-deps chromium; Windows dùng Chrome có sẵn hoặc đặt PLAYWRIGHT_CHANNEL=msedge. Xem docs/testing/release-test-report.md để phân biệt Passed, Not Run, Blocked, Skipped và UAT đang chờ người nghiệm thu. Không coi test đã viết là test đã chạy.

Muốn lưu evidence test theo lượt:

```powershell
$taskRunId = Get-Date -Format 'yyyy-MM-dd-HHmmss'
$env:TEST_EVIDENCE_DIR = Join-Path (Get-Location) "docs/testing/evidence/$taskRunId"
New-Item -ItemType Directory -Path $env:TEST_EVIDENCE_DIR -Force | Out-Null
```

Sau đó chạy các lệnh test ở trên. Script tạo DB test có tên riêng; giữ lại evidence và DB khi cần điều tra. Chỉ xóa tài nguyên test sau khi đã xác minh đúng tên và chắc chắn không còn cần.

## Cập nhật dự án

Trước khi cập nhật: backup database và file riêng tư, giữ nguyên .env/APP_KEY, kiểm tra Git working tree. Lấy thay đổi từ branch đã giao:

```powershell
git status --short
git switch feature/toan
git pull --ff-only origin feature/toan
composer install
npm ci
npm run build
php artisan migrate
```

Không chạy migrate:fresh trên dữ liệu cần giữ. Nếu có thay đổi local chưa commit, xem xét/commit hoặc backup trước khi pull; không reset để ép cập nhật.

Backup và restore xem docs/runbooks/backup-restore.md. Thực hiện restore thử trên database riêng, không thử trực tiếp lên DB đang dùng.

## Tài liệu cho người phát triển FE

- Laravel web routes và Blade: routes/, resources/views/.
- JavaScript/CSS/Vite: resources/js/, resources/css/, vite.config.js, package.json.
- OpenAPI: docs/api/foundation.openapi.yaml, docs/api/flight-planning.openapi.yaml, docs/api/flight-operations.openapi.yaml, docs/api/fleet-crew.openapi.yaml.
- Data dictionary: docs/data-dictionary.md; ERD: docs/erd-maintenance.md; permission matrix: docs/permissions-matrix.csv; hướng dẫn: docs/user-guides/.
- Laravel endpoint authorization is enforced in backend; do not rely on hiding buttons/menu in the browser as access control.

Asset watch mode during UI development:

```powershell
npm run dev
```

Start Laravel in another terminal with the configured database:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## Phạm vi và giới hạn hiện tại

Ứng dụng đang được phát triển theo 16 phân hệ/48 use case. Trạng thái từng UC và việc kiểm chứng nằm trong docs/implementation-progress.md, docs/traceability.csv, docs/testing/release-test-report.md và docs/testing/defects.md. Không suy ra hoàn thành từ route/menu hoặc số lượng test.

Hiện có nền đăng nhập, quyền/scope/audit, dữ liệu lõi M01, lịch M02, một phần M03, cấu hình ghế/usage M04, bảo dưỡng M05, đội tàu/tổ bay M06 và một phần portal. Booking, payment/refund, QR, và nhiều phần mở rộng M08–M16 chưa hoàn tất theo đặc tả. Tích hợp bên ngoài chỉ được dùng simulator khi được ghi nhãn; không gửi tiền/tin thật. AI/IoT và các ngưỡng an toàn local không thay thế phê duyệt chuyên gia hoặc chứng nhận vận hành.

Các tài liệu trạng thái quan trọng:

- docs/implementation-progress.md
- docs/traceability.csv
- docs/permissions-matrix.csv
- docs/database-gap-analysis.md
- docs/decisions.md
- docs/api/
