# Airline Smart

Hệ thống quản lý hàng không với khu vực khách hàng, bàn làm việc nhân viên và quản trị tài khoản. PHP 8.4, Laravel 12, Blade/Bootstrap và ba MySQL server độc lập.

[Hướng dẫn trình bày giao diện và code](docs/feature-presentation.md): kịch bản demo 15–20 phút, các file/hàm cần mở, dữ liệu và bản đồ 20 phân hệ.

Khách hàng đăng nhập được đưa đến **Trang chủ** (`/home`): ảnh hàng không, lời chào, tìm chuyến nhanh, truy cập hành trình/thông báo/hỗ trợ, chuyến bay sắp tới và hướng dẫn đặt vé. Thống kê mạng bay lấy từ dữ liệu hiện có; thông tin chuyến đi chỉ hiển thị cho chủ tài khoản. [Ảnh và thiết kế trang chủ](docs/homepage-assets.md). Nếu đang đặt vé dở, đăng nhập sẽ quay lại bước đó.

Trang **Đặt chuyến đi** (`/`) có [bộ lọc tìm chuyến](docs/flight-search.md): khoảng giá, giờ đi/đến, khoảng thời gian quá cảnh và hành trình, hạng vé, loại tàu, điểm dừng và còn đủ chỗ. Luồng [đặt chuyến đi](docs/trip-booking.md) chỉ có **Một chiều / Khứ hồi**: bấm **Chọn** để xem hộp thông tin chuyến bay, tiếp tục chọn lượt về nếu khứ hồi, xem cả hai lượt rồi nhập thông tin hành khách/liên hệ trước thanh toán. Nhãn **Khứ hồi** nền vàng, hỗ trợ bay thẳng hoặc tối đa hai điểm dừng mỗi lượt.

Mỗi lần đặt chỉ dành cho **1 hành khách**, thanh toán một lần cho cả hành trình; có vé, ghế và QR riêng trên từng chuyến. API tạo mới cũng giới hạn một người. PNR nhiều khách đã tạo trước đây vẫn xem và xử lý theo dữ liệu cũ. [Cabin theo tàu bay và PNR](docs/reservations-and-cabins.md) tiếp tục được hỗ trợ. Không còn giao diện tự đặt nhiều chặng; dữ liệu đặt chỗ cũ được giữ nguyên.

## Khách hàng và nhân viên

Đã bổ sung [8 nhóm nghiệp vụ](docs/eight-business-features.md): sửa thông tin có duyệt, hành lý mua thêm, điều kiện đổi/hoàn, sao kê và điều chỉnh độc lập, báo cáo doanh thu, kiểm soát check-in/danh sách lên tàu, phân công hỗ trợ và phương án cho khách bị gián đoạn. Tài liệu có đường dẫn, vai trò và kịch bản kiểm tra.

- **Khách hàng:** đăng ký/xác minh email, tìm chuyến và hạng giá, đặt chỗ, thanh toán VNPay/MoMo/chuyển khoản, theo dõi vé, đổi/hoàn, chọn ghế/làm thủ tục, in vé và thẻ lên tàu, tra cứu hành lý, gửi yêu cầu và nhận thông báo.
- **Nhân viên sân bay:** tra cứu mã đặt chỗ/họ tên/email trong sân bay được phân công, làm thủ tục cho khách, in thẻ, quét QR qua máy quét hoặc camera trên trình duyệt hỗ trợ; xử lý và bàn giao hành lý.
- **Khai thác:** quản lý lịch, duyệt công bố, tổ bay, đội tàu, bảo dưỡng, nhiệm vụ mặt đất, phân bổ tài nguyên và xử lý chậm/hủy/đổi tàu có phê duyệt độc lập.
- **Tài chính:** đối chiếu chuyển khoản, xử lý yêu cầu đổi/hoàn, xác nhận chi hoàn độc lập, sổ thu/hoàn, sai lệch và khóa kỳ đối soát.
- **Quản trị:** tạo tài khoản nhân viên, phân vai trò/sân bay, bắt đổi mật khẩu tạm thời, tạm khóa/mở lại tài khoản, kiểm tra cấu hình dịch vụ và nhật ký.

Giao diện dùng trạng thái và tên nghiệp vụ tiếng Việt. Các mã kỹ thuật và số module không nằm trong luồng sử dụng thông thường. Bản local mở VNPay/MoMo Mock và ngân hàng giả để kiểm thử; chế độ thử nghiệm chỉ được ghi tại bước thanh toán. Mock bị chặn trong production.

[Ảnh bàn làm việc](docs/screenshots/staff-dashboard.png) · [Phục vụ hành khách](docs/screenshots/passenger-desk.png) · [Thẻ lên tàu](docs/screenshots/boarding-pass.png).

## Cài và khởi động

Cần **Docker Desktop/Engine với Compose v2 và Python 3.10+**. Bản cài mặc định tạo dữ liệu demo đầy đủ, gồm 22 sân bay và đủ 10 loại hạ tầng ở từng sân bay, lịch bay, tổ bay, vé, giao dịch mock và kế hoạch định kỳ M02. [Hướng dẫn chạy trên máy khác](docs/portable-demo.md).

```text
git clone --branch feature/vinh --single-branch https://github.com/buivandat30506/QLDAPM.git
cd QLDAPM
python scripts/demo.py
```

Linux/macOS dùng `python3 scripts/demo.py` hoặc `bash scripts/setup.sh`; Windows có thể dùng `python scripts/demo.py` hoặc `scripts/setup.ps1`. PHP/Composer/Node/MySQL chạy trong Docker, không cần cài riêng trên máy. Lần đầu cần Internet và thời gian tạo dữ liệu. Script tạo cấu hình riêng, giữ nguyên APP_KEY/mật khẩu cũ, cài theo lockfile, áp dụng migration và tạo dữ liệu mô phỏng có kiểm chứng trước khi khởi động web cổng **8000**, Mailpit **8025** và các worker. Đăng nhập `demo.admin@airline.test`, mật khẩu là `LOCAL_DEMO_PASSWORD` trong `backend/.env` của máy đó. Dùng `--minimal` nếu chủ động muốn ứng dụng trống thay vì demo. Database đã có dữ liệu riêng sẽ không bị ghi đè. Khi khởi động lại:

```bash
docker compose --env-file backend/.env up -d db1 db2 db3 mailpit app worker mail-worker
curl --fail http://127.0.0.1:8000/health/ready
```

Đây là runtime phát triển. Để phục vụ công khai, cần tên miền HTTPS, web runtime phù hợp và cấu hình nhà cung cấp theo [hướng dẫn triển khai](docs/deployment.md). Bản local dùng Mailpit và Mock nên không cần credential bên ngoài. [Hướng dẫn sử dụng local](docs/local-usage.md).

## Khởi tạo người quản trị và dữ liệu kinh doanh

Bộ [chuyến bay nhiều hãng](docs/multi-airline-demo.md) có 4 hãng, 6 sân bay, bay thẳng/1–2 điểm dừng và hai chiều. Bổ sung bằng `php artisan airline:demo-flights --days=14`; chạy lại giữ dữ liệu/vé hiện có. Giờ và giá là dữ liệu mô phỏng để test.

Bộ cài demo mặc định đã nối bộ dữ liệu đầy đủ (22 sân bay, đội tàu, tổ bay, lịch sử và 14 ngày lịch bay, hành khách, vé, hành lý, thanh toán, hồ sơ chờ duyệt) với bước chuẩn hóa/bổ sung 10 loại hạ tầng và dữ liệu M02. Xem [bản demo giữa các máy](docs/portable-demo.md). Lệnh legacy `airline:demo-data` vẫn giữ để tương thích; không cần tự chạy một chuỗi lệnh bổ sung khi cài bằng `scripts/demo.py`.

Bản local đã có quản trị viên đầu tiên với credential ngẫu nhiên trong `backend/.env` (`LOCAL_ADMIN_EMAIL`, `LOCAL_ADMIN_PASSWORD`). Đăng nhập và đổi mật khẩu tạm thời. Setup không đặt lại tài khoản đã tồn tại. Có thể chủ động khởi tạo tài khoản ngoài local bằng CLI `php artisan airline:user EMAIL --role=admin` với mật khẩu nhập ẩn.

Sau đó đăng nhập, tạo tài khoản nhân viên trong **Quản lý tài khoản**. Các vai trò và sân bay được chọn từ danh sách; nhân viên đổi mật khẩu tạm thời khi đăng nhập lần đầu. Khách hàng tự đăng ký tài khoản, không tự cấp quyền nhân viên.

Quản lý danh mục tạo sân bay, đường bay và tài nguyên. Đội tàu thêm tàu, điều phối thêm phi công/tiếp viên có giấy phép và năng định phù hợp. Nhân viên khai thác lập lịch, tổ bay phân công, quản lý khác người lập duyệt công bố. Sau xác nhận, chuyến mới xuất hiện cho khách đặt vé. Không tạo dữ liệu chuyến bay giả để lấp màn hình trống.

## Email, thu và hoàn tiền

Mailpit nhận email đăng ký/OTP/khôi phục mật khẩu và thông báo trên local. VNPay/MoMo Mock không gọi cổng ngoài. Thiết lập SMTP thật, merchant Sandbox/live và tài khoản nhận chuyển khoản trong cấu hình máy chủ theo [deployment.md](docs/deployment.md). Không lưu khóa bí mật vào Git. Trang **Email & thanh toán** dành cho quản trị viên cho biết dịch vụ nào đã có cấu hình.

Chuyển khoản chỉ xuất vé sau khi tài chính đối chiếu sao kê. VNPay/MoMo chỉ xác nhận bằng IPN đã kiểm tra chữ ký/số tiền. Hoàn tiền thật qua chuyển khoản ngân hàng: tài chính phê duyệt và người khác xác nhận chi; chưa có hoàn tự động vào ví/thẻ. Worker ghi nhận từng bước, thử lại khi mất kết nối và chống xử lý trùng.

## Kiểm thử và sao lưu

```bash
./scripts/test.sh
./scripts/e2e.sh
./scripts/e2e.sh tests/e2e/portals.spec.js
./scripts/backup.sh
./scripts/restore-test.sh backups/THU_MUC_DA_TAO
```

Test/E2E dùng riêng user/schema `airline_test` trên ba node; không chạy đồng thời với nhau hoặc restore. Dữ liệu mẫu và simulator chỉ bật trong kiểm thử. E2E có web/worker riêng, Chromium và CSRF thật; tự xóa dịch vụ test sau chạy. Backup ignored/quyền riêng, không globally atomic; giữ APP_KEY riêng. Restore chỉ ghi vào DB test.

[Kiểm chứng](docs/validation.md) · [Kiến trúc](docs/architecture.md) · [ERD](docs/erd.md) · [Phân quyền](docs/rbac-matrix.md) · [API](docs/api.md) · [Phạm vi/giới hạn](docs/implementation-progress.md).
