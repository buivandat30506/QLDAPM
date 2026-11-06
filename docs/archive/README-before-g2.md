
# Smart Airline Management

Tiến độ triển khai đầy đủ 16 module/48 UC từ ngày 18/10/2026: [docs/implementation-progress.md](docs/implementation-progress.md). Hướng dẫn chạy phần mở rộng, test MySQL cô lập và browser: [docs/runbooks/implementation-local.md](docs/runbooks/implementation-local.md). Tài liệu Module 1 bên dưới giữ để đối chiếu phiên bản cũ; quyền ADMIN, API passenger và asset frontend đã được cập nhật, xem nhật ký mới.

Hướng dẫn chạy và bàn giao Module 1 (XAMPP/MySQL, API, giao diện thử nghiệm, SQL/ZIP): **[README_MODULE1.md](README_MODULE1.md)**.

File bàn giao database: [table1.sql](database/sql/table1.sql) và [table1.zip](database/sql/table1.zip).

Phần tổng quan bên dưới mô tả cả các phân hệ và định hướng mở rộng; phạm vi triển khai và giới hạn hiện tại của Module 1 nằm trong hướng dẫn trên.

1. Giới thiệu dự án
Hệ thống Backend cung cấp dịch vụ API tập trung cho giải pháp Quản lý Hàng không Thông minh, tuân thủ kiến trúc phân tầng (Controller – Service – Eloquent Model / Repository). Mã nguồn được tổ chức hoàn chỉnh cho 2 module bắt buộc theo phạm vi nhóm BTL:

Module 1 (Quản lý cốt lõi - Core Management):

Quản lý danh mục sân bay (Airport), đường bay (Route), dòng tàu bay (AircraftModel) và tàu bay khai thác (Aircraft).
Lập lịch bay kế hoạch (FlightSchedule) và điều phối chuyến bay khai thác thực tế (OperationalFlight).
Tự động kiểm tra thời gian quay đầu tối thiểu (Turnaround Time - BR-006) và phát hiện xung đột lịch khai thác tàu bay (BR-005).
Quản lý mốc trạng thái chuyến bay: SCHEDULED, BOARDING, DEPARTED, ARRIVED, CANCELLED, DELAYED.

Module 2 (Phân hệ Hành khách - Passenger & Booking):

Tra cứu chuyến bay theo tuyến bay và ngày bay.
Đặt chỗ tạo mã đặt chỗ PNR 6 ký tự (OrderBooking, OrderItem), áp dụng khóa cấp dòng (Row-Level Locking) để chống Overbooking và giới hạn thời gian giữ chỗ 15 phút (BR-011).
Thủ tục check-in trực tuyến (CheckInRecord), chọn sơ đồ ghế ngồi thực tế theo dòng tàu bay.
Phát hành thẻ lên tàu bay điện tử (BoardingPassToken) với chữ ký số / token mã QR bảo mật, kiểm soát soát vé một lần tại cửa ra máy bay (BR-014, BR-015).
Đăng ký thẻ hành lý ký gửi (BaggageTag) và theo dõi chuỗi quét điểm hành trình (BaggageTrackingEvent).

Sẵn sàng tích hợp POC (Module 3):

Điểm tiếp nhận API dự báo trễ chuyến từ module AI của Nguyễn Danh Trường (/api/v1/core/flights/{id}/delay-prediction).
Điểm tiếp nhận dữ liệu cảm biến mô phỏng từ module IoT của Hoàng Minh Tú (/api/v1/core/sensors/telemetry).


2. Yêu cầu môi trường
PHP: >= 8.2 (Bật các extension: pdo_mysql, mbstring, openssl, bcmath, xml, ctype, json, curl)
Composer: >= 2.0
Database: MySQL >= 8.0 (hoặc MariaDB >= 10.4 tương đương)
Công cụ khuyến nghị: Postman, DBeaver / phpMyAdmin, XAMPP / Laragon / Docker.


3. Hướng dẫn cài đặt & Khởi chạy (Local Development)
Bước 1: Tải mã nguồn và cài đặt dependencies
# Sau khi clone repo hoặc giải nén thư mục dự án:

cd airline-smart

# Cài đặt các package phụ thuộc (không bao gồm vendor trong mã nguồn bàn giao)

composer install
Bước 2: Thiết lập cấu hình môi trường (.env)
# Tạo file cấu hình từ file mẫu

cp .env.example .env

# Sinh khóa bí mật ứng dụng

php artisan key:generate

Mở file .env và thiết lập kết nối CSDL MySQL tập trung:

APP_NAME="Airline Smart Management"

APP_ENV=local

APP_DEBUG=true

APP_URL=http://localhost:8000

DB_CONNECTION=mysql

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=airline_smart_mgmt

DB_USERNAME=root

DB_PASSWORD=your_password
Bước 3: Khởi tạo Cơ sở dữ liệu và nạp dữ liệu mẫu
Tạo schema cơ sở dữ liệu trên MySQL nếu chưa tồn tại:

CREATE DATABASE airline_smart_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

Thực thi migrations tạo toàn bộ 20 bảng quan hệ và nạp dữ liệu danh mục ban đầu:

php artisan migrate:fresh --seed
Bước 4: Khởi chạy Backend Server
php artisan serve

Backend API sẽ hoạt động tại địa chỉ: http://127.0.0.1:8000


4. Danh mục API Endpoints
Định dạng phản hồi chung (JSON RESTful):

{

  "success": true,

  "message": "Thành công",

  "data": { ... }

}
4.1. Module 1: Quản lý Cốt lõi (Core Management)
Phương thức
Endpoint
Chức năng & Tham số chính
GET
/api/v1/core/airports
Lấy danh sách sân bay (hỗ trợ query search, phân trang per_page)
POST
/api/v1/core/airports
Thêm mới sân bay (iata_code, icao_code, name, city, country, timezone)
GET
/api/v1/core/airports/{iataCode}
Chi tiết sân bay theo mã IATA 3 ký tự
GET
/api/v1/core/routes
Danh sách các đường bay khai thác
POST
/api/v1/core/routes
Khởi tạo đường bay (origin_airport_code, dest_airport_code, distance_km, standard_duration_minutes)
GET
/api/v1/core/aircrafts
Danh sách máy bay & trạng thái khai thác (OPERATIONAL, MAINTENANCE)
GET
/api/v1/core/flights
Tra cứu danh sách chuyến bay khai thác (hỗ trợ lọc flight_date, status, route_id)
GET
/api/v1/core/flights/{id}
Chi tiết chuyến bay (kèm thông tin đường bay, tàu bay, ghế ngồi, hành khách)
POST
/api/v1/core/flights
Khởi tạo chuyến bay (tự động kiểm tra xung đột tàu bay BR-005 & quay đầu BR-006)
PATCH
/api/v1/core/flights/{id}/status
Chuyển mốc trạng thái chuyến bay (SCHEDULED -> BOARDING -> DEPARTED -> ARRIVED)

4.2. Module 2: Phân hệ Hành khách & Đặt chỗ (Passenger & Booking)
Phương thức
Endpoint
Chức năng & Tham số chính
GET
/api/v1/passenger/flights/search
Tìm kiếm chuyến bay theo origin, destination, date
POST
/api/v1/passenger/bookings
Tạo đơn đặt vé giữ chỗ 15 phút PNR (Khóa cấp dòng ACID chống overbooking)
GET
/api/v1/passenger/bookings/{orderCode}
Tra cứu đơn hàng theo mã PNR 6 ký tự
POST
/api/v1/passenger/bookings/{id}/confirm
Xác nhận thanh toán và hoàn tất đơn vé
GET
/api/v1/passenger/checkin/seat-map/{flightId}
Xem sơ đồ ghế theo dòng tàu bay và danh sách ghế còn trống
POST
/api/v1/passenger/checkin
Check-in chọn ghế ngồi, sinh mã QR Boarding Pass Token bảo mật (BR-014)
POST
/api/v1/passenger/boarding/verify
Quét mã QR tại cửa lên máy bay, đánh dấu đã lên tàu và chống tái sử dụng (BR-015)
POST
/api/v1/passenger/baggage/tags
Khởi tạo thẻ hành lý ký gửi kèm mã định danh tag
GET
/api/v1/passenger/baggage/track/{tagCode}
Xem toàn bộ lịch sử quét chặng của kiện hành lý
POST
/api/v1/passenger/baggage/events
Thêm điểm quét hành lý (CHECKED_IN, SECURITY_CLEARED, LOADED, OFFLOADED, DELIVERED)



5. Dữ liệu mẫu (Database Seeder)
Hệ thống đã chuẩn bị sẵn bộ dữ liệu khởi tạo thông qua php artisan db:seed:

Tài khoản quản trị & nhân sự:
admin@airline.ptit.edu.vn (Mật khẩu: password123) – Quản trị viên hệ thống.
dispatcher@airline.ptit.edu.vn (Mật khẩu: password123) – Nhân viên điều phối bay.
Danh mục sân bay chuẩn: HAN (Hà Nội), SGN (TP. Hồ Chí Minh), DAD (Đà Nẵng), CXR (Cam Ranh), PQC (Phú Quốc).
Dòng máy bay & Đội tàu bay: A321-200 (VN-A601, VN-A602), Boeing 787-9 (VN-A861).
Các chuyến bay mẫu: Các chặng HAN - SGN, HAN - DAD với trạng thái sẵn sàng để Frontend và QA tiến hành kiểm thử.


6. Cấu trúc thư mục dự án
airline-smart/

├── app/

│   ├── Http/

│   │   ├── Controllers/Api/

│   │   │   ├── BaseApiController.php

│   │   │   ├── Core/

│   │   │   │   ├── AirportController.php

│   │   │   │   ├── OperationalFlightController.php

│   │   │   │   └── RouteController.php

│   │   │   └── Passenger/

│   │   │       ├── BaggageController.php

│   │   │       ├── BookingController.php

│   │   │       └── CheckInController.php

│   │   └── Requests/ (Validation lớp dữ liệu vào)

│   ├── Models/ (20 thực thể quan hệ Eloquent ORM)

│   ├── Services/

│   │   ├── Core/ (Nghiệp vụ điều phối bay, kiểm tra quay đầu & xung đột)

│   │   └── Passenger/ (Nghiệp vụ giữ chỗ PNR, check-in QR, hành lý)

│   └── Traits/ (ApiResponse chuẩn hóa format trả về)

├── database/

│   ├── migrations/ (Toàn bộ schema 20 bảng CSDL MySQL)

│   └── seeders/ (Bộ dữ liệu mẫu phục vụ phát triển & kiểm thử)

├── routes/

│   └── api.php (Khai báo API RESTful v1 cho Core và Passenger)

├── .env.example

├── composer.json

└── README.md
