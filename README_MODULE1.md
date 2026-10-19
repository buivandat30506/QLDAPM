# Module 1 — Quản lý cốt lõi hàng không

> Tài liệu bản thử nghiệm lịch sử. Phạm vi đích hiện hành giữ đủ M01–M16/48UC, gồm M05 và mọi Should/Could; xem [README](README.md), [tiến độ](docs/implementation-progress.md) và [hướng dẫn M04/M06](docs/user-guides/fleet-crew.md), [M05 mới](docs/user-guides/maintenance.md). API bảo dưỡng legacy có đăng nhập phải review/adopt; không dùng checkbox/text thay vật tư, duyệt trước làm hoặc tài liệu thật. Các mục MariaDB/dump chín bảng bên dưới mô tả bản cũ, không là bằng chứng nghiệm thu bản hiện hành. Hiện đã có test MySQL8 riêng, Vite/Bootstrap và migration bổ sung; không dùng SQL legacy để ghi đè DB có dữ liệu.

## 1. Phạm vi và công nghệ

PHP **8.2 trở lên**, Laravel 12, MySQL 8.x hoặc MariaDB đi kèm XAMPP, Blade + Bootstrap 5. Không cần Node/npm để chạy giao diện quản trị tạm.

Theo yêu cầu bàn giao, “Module 1” ở đây gom quản lý sân bay/tài nguyên, đường bay, lịch/chuyến bay, dòng/đội tàu bay và tổ bay. Báo cáo `Bao_cao_Activity_Sequence_6_module_dau.docx` chia các nghiệp vụ này thành M01–M04 và M06; tài liệu được dùng để đối chiếu luồng nghiệp vụ. Quy trình bảo dưỡng M05, phê duyệt dữ liệu chủ/phiên bản, tích hợp AODB/FIDS và các nghiệp vụ tổ bay nâng cao không nằm trong bản thử nghiệm này.

## 2. Chạy bằng XAMPP trên Windows

1. Cài XAMPP có PHP >= 8.2 và Composer. Bật MySQL trong XAMPP Control Panel.
2. Mở PowerShell tại thư mục dự án. Kiểm tra:

```powershell
php -v
composer install
Copy-Item .env.example .env
php scripts/ensure-app-key.php
```

Chỉ sao chép `.env.example` khi chưa có `.env`; nếu đã cấu hình thì giữ file hiện tại.

3. Tạo database `airline_smart` trong phpMyAdmin, collation `utf8mb4_unicode_ci`, hoặc dùng client XAMPP:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -e 'CREATE DATABASE IF NOT EXISTS airline_smart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
```

4. Cấu hình `.env`:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=airline_smart
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
CACHE_STORE=database
```

Máy triển khai hiện tại dùng cổng **3307**. XAMPP khác có thể dùng 3306: xem `xampp/mysql/bin/my.ini`, cập nhật cả cấu hình và các lệnh tương ứng. Nếu tài khoản có mật khẩu, cấu hình mật khẩu thật trong `.env`; với mysql CLI thêm `-p` để nhập tương tác.

5. Chạy:

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Mở **http://127.0.0.1:8000/admin/dashboard**. Server cần được giữ chạy trong terminal. Nếu dùng Apache, DocumentRoot phải trỏ tới thư mục `public`.

Dữ liệu mẫu: 5 sân bay, 19 tài nguyên, 4 đường bay, 2 dòng tàu bay, 4 tàu bay, 10 nhân sự, 5 lịch bay, 5 chuyến bay và 3 phân công. Chuyến bay mẫu sử dụng ngày UTC lúc chạy seed. Seed dùng `firstOrCreate`, không ghi đè hồ sơ đã tồn tại; số bản ghi có thể cao hơn nếu database đã có dữ liệu.

Migration `2026_10_13_000000_upgrade_existing_module1_schema` bổ sung cột/bảng còn thiếu cho database từ phiên bản trước, đổi trạng thái tàu bay ACTIVE sang OPERATIONAL và chuyển ngày trong tuần sang JSON. Không cần xóa database để nâng cấp. Migration này giữ nguyên cột khi rollback để tránh mất dữ liệu. Không dùng `migrate:fresh` trên database có dữ liệu cần giữ.

## 3. Giao diện thử nghiệm

| URL | Chức năng |
| --- | --- |
| /admin/dashboard | Thống kê, chuyến bay hôm nay và cảnh báo bảo dưỡng |
| /admin/airports | Thêm/sửa/xóa sân bay; xem/thêm/xóa tài nguyên |
| /admin/routes | Thêm/sửa/xóa đường bay |
| /admin/aircrafts | Thêm dòng tàu bay; thêm/sửa/xóa tàu bay, cảnh báo giờ bay/chu kỳ |
| /admin/flights | Tạo lịch/chuyến bay, lọc và cập nhật trạng thái |
| /admin/crew | Thêm/sửa/xóa hồ sơ, phân công tổ bay |

Nút **Sửa** tại đường bay, tàu bay và tổ bay mở form ngay trong dòng. Form web có CSRF và hiển thị lỗi xác thực. Bootstrap và biểu tượng hiện tải từ CDN, cần Internet để hiển thị đầy đủ và mở các modal.

Đây là bản chạy thử nội bộ: các trang admin và API core chưa áp dụng đăng nhập/phân quyền. Trước khi triển khai công khai cần bổ sung authentication, authorization và kiểm soát truy cập. Việc nâng cấp giao diện có thể giữ nguyên API và tầng Service.

## 4. SQL và ZIP bàn giao cho FE / Agent

- `database/sql/table1.sql`: cấu trúc, index, khóa ngoại và dữ liệu mẫu của **9 bảng nghiệp vụ core**.
- `database/sql/table1.zip`: chứa đúng file `table1.sql`.
- Không chứa tài khoản, session, token hoặc dữ liệu phân hệ hành khách.

Các bảng: `airports`, `airport_resources`, `routes`, `aircraft_models`, `aircrafts`, `flight_schedules`, `operational_flights`, `crew_members`, `flight_crew_assignments`.

Tạo lại SQL và ZIP từ database đang cấu hình:

```powershell
php artisan export:module1-sql
# Hoặc chọn đường dẫn:
php artisan export:module1-sql --path=database/sql/custom.sql
```

Export yêu cầu đủ bảng, thất bại nếu thiếu bảng, dùng snapshot transaction và giữ nguyên UTF-8/ký tự đặc biệt. Xuất dữ liệu thật đang có: chỉ bàn giao dữ liệu phù hợp.

### Import chỉ để xem database

Tạo database mới, chọn nó trong phpMyAdmin → Import → chọn `table1.sql` sau khi giải nén. Hoặc:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -e 'CREATE DATABASE airline_smart_fe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root airline_smart_fe -e 'source database/sql/table1.sql'
```

Chạy lệnh từ thư mục dự án. Script SQL **thay thế 9 bảng core nếu chúng đã tồn tại**; chỉ import vào database mới/dành riêng cho bàn giao.

### Chạy Laravel với dữ liệu SQL bàn giao

Tạo database mới và trỏ `.env` tới database đó → `php artisan config:clear` → `php artisan migrate` để có cả các bảng hạ tầng Laravel → import SQL vào database vừa tạo → `php artisan serve`. Không chạy lại seed nếu muốn giữ nguyên tập dữ liệu trong SQL.

## 5. Hợp đồng API cho FE

Base URL: `http://127.0.0.1:8000/api/v1/core`. Gửi `Accept: application/json`, với body JSON thêm `Content-Type: application/json`.

Phản hồi thành công:

```json
{"success": true, "message": "Thành công", "data": {}, "errors": null}
```

HTTP 200 khi đọc/sửa/xóa, 201 khi tạo, 422 khi lỗi xác thực/nghiệp vụ, 404 khi không tìm thấy và 409 khi vi phạm ràng buộc database. Lỗi dùng cùng envelope với `success=false`, `data=null` và `errors` theo tên trường. Danh sách nằm trực tiếp trong `data`, chưa phân trang.

| Method | Endpoint (sau base URL) | Chức năng |
| --- | --- | --- |
| GET, POST | /airports | Danh sách / tạo sân bay |
| GET, PUT, DELETE | /airports/{iataCode} | Chi tiết / sửa / xóa |
| GET, POST | /airports/{iataCode}/resources | Xem / thêm tài nguyên |
| DELETE | /airports/resources/{resourceId} | Xóa tài nguyên |
| GET, POST | /routes | Danh sách / tạo đường bay |
| GET, PUT, DELETE | /routes/{id} | Chi tiết / sửa / xóa |
| GET, POST | /aircraft-models | Xem / thêm dòng tàu bay |
| GET, POST | /aircrafts | Danh sách / thêm tàu bay |
| GET, PUT, DELETE | /aircrafts/{tailNumber} | Chi tiết / sửa / xóa tàu bay |
| GET | /aircrafts/maintenance-alerts | Cảnh báo bảo dưỡng |
| GET, POST | /schedules | Xem / tạo lịch mẫu |
| GET, POST | /flights | Danh sách / tạo chuyến bay |
| GET | /flights/{id} | Chi tiết chuyến bay |
| PUT | /flights/{id}/status | Cập nhật trạng thái |
| POST | /flights/check-conflict | Kiểm tra xung đột |
| GET, POST | /crew-members | Danh sách / tạo hồ sơ tổ bay |
| GET, PUT, DELETE | /crew-members/{id} | Chi tiết / sửa / xóa hồ sơ |
| GET | /flights/{flightId}/crew | Phân công và kết quả kiểm tra tổ bay tối thiểu |
| POST | /flights/{flightId}/crew/assign | Phân công nhân sự |
| DELETE | /flights/crew-assignments/{assignmentId} | Hủy phân công |

Sân bay dùng khóa IATA; tàu bay dùng số đăng ký; đường bay và dòng tàu bay dùng integer; lịch/chuyến bay, nhân sự và phân công dùng UUID. Dùng ID trả về từ API, không hardcode.

Bộ lọc: sân bay `search,status`; đường bay `origin,dest,status`; tàu bay `search,model_id,status`; chuyến bay `date,origin,destination,flight_status,aircraft_tail` (lọc cặp sân bay cần cả origin và destination); tổ bay `search,rank,status,base_airport`.

### Ví dụ body tạo mới

**Sân bay**:

```json
{"iata_code":"VDO","icao_code":"VVVD","name":"Sân bay Vân Đồn","city":"Quảng Ninh","country":"Vietnam","timezone":"Asia/Ho_Chi_Minh","operating_hours":"24/7","status":"ACTIVE"}
```

**Đường bay** (`route_code` có thể bỏ để tự sinh):

```json
{"origin_airport_code":"PQC","dest_airport_code":"CXR","distance_km":500,"standard_duration_minutes":65,"status":"ACTIVE"}
```

PUT cho phép gửi riêng trường cần sửa, ví dụ `{"distance_km":550,"status":"SUSPENDED"}`. Không đổi hai đầu sân bay khi tuyến đã có lịch/chuyến bay.

**Tài nguyên**:

```json
{"resource_type":"GATE","resource_code":"G12","status":"AVAILABLE"}
```

Loại: TERMINAL, GATE, RUNWAY, BAGGAGE_CAROUSEL. Trạng thái: AVAILABLE, MAINTENANCE, OCCUPIED.

**Dòng tàu bay / tàu bay**:

```json
{"model_code":"A320-200","manufacturer":"Airbus","seat_capacity":180,"turnaround_time_minutes":45}
```

```json
{"tail_number":"VN-A999","model_id":1,"manufacture_year":2020,"total_flight_hours":0,"total_flight_cycles":0,"operational_status":"OPERATIONAL"}
```

**Lịch bay** (ngày ISO: thứ Hai = 1, Chủ nhật = 7):

```json
{"flight_number":"VN999","route_id":1,"model_id":1,"departure_time_utc":"08:00:00","arrival_time_utc":"10:00:00","days_of_week":[1,2,3,4,5,6,7],"status":"DRAFT","is_active":true}
```

Lịch hỗ trợ DRAFT, APPROVED, PUBLISHED khi tạo; chưa có workflow phê duyệt riêng.

**Chuyến bay**:

```json
{"flight_number":"VN999","route_id":1,"aircraft_tail":"VN-A999","scheduled_dep_utc":"2030-01-01 08:00:00","scheduled_arr_utc":"2030-01-01 10:00:00","gate_number":"G12"}
```

Các mốc giờ bay lưu **UTC**, API tạo chuyến dùng `YYYY-MM-DD HH:mm:ss`. `flight_date` mặc định lấy ngày cất cánh UTC. Có thể thêm `schedule_id`; khi có lịch gốc, lịch phải PUBLISHED, đang hoạt động và khớp số hiệu/tuyến/dòng tàu bay/ngày trong tuần. Bản thử nghiệm vẫn cho phép tạo chuyến trực tiếp không có lịch gốc.

**Nhân sự / phân công tổ bay**:

```json
{"crew_code":"CRW-011","full_name":"Nguyễn Văn An","email":"an@example.test","phone":"0901234567","rank":"CAPTAIN","base_airport_code":"HAN","license_number":"LIC-011","status":"AVAILABLE","total_flight_hours":0}
```

```json
{"crew_id":"UUID_TU_API_CREW_MEMBERS","assigned_position":"CAPTAIN","notes":"Phân công thử nghiệm"}
```

Cấp bậc/vị trí: CAPTAIN, FIRST_OFFICER, PURSER, FLIGHT_ATTENDANT. Trạng thái nhân sự: AVAILABLE, ON_DUTY, RESTING, LEAVE.

### Quy tắc được áp dụng

- Chặn mã/cặp tuyến trùng, sân bay đi bằng sân bay đến; khóa ngoại bảo vệ dữ liệu liên quan khi xóa.
- Tàu bay phải OPERATIONAL; tuyến và hai sân bay phải ACTIVE. Kiểm tra trùng giờ và thời gian quay đầu ở cả hai phía; đúng bằng thời gian quay đầu tối thiểu được phép.
- Chặn số hiệu trùng trong cùng ngày bay; khóa tàu bay trong transaction khi xếp chuyến.
- Phân công kiểm tra trạng thái nhân sự, cấp bậc/vị trí và trùng ca trực. Ca trực tính từ 60 phút trước cất cánh tới 30 phút sau hạ cánh.
- SCHEDULED → BOARDING/DELAYED/CANCELLED; BOARDING → DEPARTED/DELAYED/CANCELLED; DELAYED → SCHEDULED/BOARDING/DEPARTED/CANCELLED; DEPARTED → ARRIVED. ARRIVED/CANCELLED là trạng thái kết thúc. Gửi lại cùng trạng thái được phép.
- DEPARTED cần giờ cất cánh thực tế và ít nhất một CAPTAIN, một FIRST_OFFICER; ARRIVED cần giờ hạ cánh thực tế sau giờ cất cánh.
- Khi chuyển sang ARRIVED, cộng giờ bay thực tế và một chu kỳ cho tàu bay đúng một lần. Không sửa lại mốc giờ sau khi đã ARRIVED.
- Cảnh báo bảo dưỡng ở 3.000 giờ hoặc 1.500 chu kỳ; khi ghi nhận chuyến hoàn tất vượt ngưỡng, tàu bay OPERATIONAL chuyển MAINTENANCE. Đây là ngưỡng minh họa, chưa phải chương trình bảo dưỡng thực tế.
- Chưa kiểm tra hạn giấy phép, năng định theo dòng tàu bay, giới hạn nhiệm vụ/nghỉ ngơi, đào tạo và vị trí thực tế của tàu bay; chưa triển khai quy trình thay thế/lịch sử/phê duyệt tổ bay đầy đủ trong báo cáo.

## 6. Kiểm thử và xác minh SQL

```powershell
php artisan test
php scripts/verify-module1-sql.php
```

Test mặc định dùng SQLite trong bộ nhớ, không xóa dữ liệu MySQL đang chạy. Script SQL dùng thông tin kết nối MySQL hiện tại, yêu cầu quyền CREATE DATABASE; tạo **hai database mới có tên ngẫu nhiên**, chạy migration/seed, xuất–nhập và so sánh toàn bộ dữ liệu chín bảng, kiểm tra UTF-8/ký tự đặc biệt, khóa ngoại và nội dung ZIP. Script giữ database kiểm thử để kiểm tra lại và in tên ở cuối. Script cũng cập nhật SQL/ZIP bàn giao từ tập dữ liệu mẫu sạch.

Đã kiểm chứng tại máy phát triển: PHP 8.2.12, MariaDB 10.4.32 của XAMPP, cổng 3307. Bộ feature test đã chạy cả SQLite và MariaDB trong database riêng. Chưa chạy trên một máy MySQL 8.x độc lập.

## 7. Xử lý lỗi thường gặp

- **Connection refused / 2002:** bật MySQL XAMPP, đối chiếu port và chạy `php artisan config:clear`.
- **Unknown column / thiếu airport_resources:** chạy `php artisan migrate` để áp dụng migration nâng cấp.
- **Could not find driver:** bật `pdo_mysql` trong PHP CLI; kiểm tra `php --ini`.
- **419 Page Expired:** tải lại trang để lấy CSRF/session mới; kiểm tra APP_KEY và bảng sessions.
- **SQL không có bảng sessions/cache:** dump chỉ gồm nghiệp vụ core; chạy migrate trước khi import nếu dùng Laravel.
- **Không xóa được bản ghi:** gỡ liên kết hoặc phân công trước; không vô hiệu hóa khóa ngoại để xóa cưỡng bức.
