# Tải dự án và chạy bản demo đầy đủ trên máy khác

Bản demo khởi tạo đủ dữ liệu để xem các chức năng đã làm: 22 sân bay; đủ 10 loại hạ tầng ở từng sân bay; đường bay, đội tàu, tổ bay, lịch sử và lịch bay sắp tới; hành khách, vé, hành lý, giao dịch mock và các hồ sơ chờ xử lý. M02 có kế hoạch định kỳ dài hạn và chuyến nháp đã phát sinh để mở xem nguồn, kiểm tra và gửi phê duyệt.

## Cài lần đầu

Cài **Docker Desktop** (bật Linux containers, Docker Compose v2) và **Python 3.10 trở lên**. Không cần cài PHP, Composer, MySQL hoặc Node trên máy: các công cụ này chạy trong container. Cần Internet lần đầu để tải image và thư viện. Docker Desktop nên được cấp ít nhất 6 GB RAM cho ba MySQL và các dịch vụ.

Tải đúng nhánh:

```text
git clone --branch feature/vinh --single-branch https://github.com/buivandat30506/QLDAPM.git
cd QLDAPM
```

Windows PowerShell:

```powershell
python scripts/demo.py
```

Linux/macOS:

```bash
python3 scripts/demo.py
# Hoặc: bash scripts/setup.sh
```

Đợi lệnh thông báo `Complete demo ready` và `Ready: http://localhost:8000`. Lần đầu cần chờ tải thư viện và tạo dữ liệu; không đóng cửa sổ giữa quá trình. Nếu mất mạng, sửa nguyên nhân rồi chạy lại cùng lệnh; các bước dữ liệu đã ghi có trạng thái để tiếp tục.

Mở **http://localhost:8000/login**, đăng nhập:

- Email: `demo.admin@airline.test`.
- Mật khẩu: giá trị `LOCAL_DEMO_PASSWORD` trong `backend/.env` vừa được máy đó tự tạo.

Không dùng mật khẩu của máy khác. Mật khẩu/APP_KEY/database secrets được tạo riêng và giữ nguyên khi chạy lại. `admin@airline.test` là tài khoản bootstrap riêng, dùng `LOCAL_ADMIN_PASSWORD` và cần đổi mật khẩu tạm; dùng `demo.admin@airline.test` để demo ngay.

## Những phần cần mở để kiểm tra

| Mục | Địa chỉ | Dữ liệu đi kèm |
|---|---|---|
| Danh sách sân bay | `/workspace/catalog?flow=airports` | 22 sân bay, mã ICAO/quốc gia |
| Hạ tầng | `/workspace/catalog?flow=infrastructure&airport=BMV` | Đủ 10 loại ở BMV và từng sân bay khác; trạng thái ban đầu được khởi tạo |
| Đường bay | `/workspace/catalog?flow=routes` | Các đường bay hai chiều, chi tiết và hồ sơ thay đổi |
| Lịch bay | `/workspace/planning` | Chuyến bay có tàu, tổ bay, giá và trạng thái |
| Lịch định kỳ | `/workspace/planning?tab=seasons` | `DEMO-M02-RECURRING`, hai chuyến nháp liên kết nguồn |
| Kiểm tra xung đột | `/workspace/planning?tab=conflicts` | Chọn phạm vi rồi chạy kiểm tra thực tế |
| Phê duyệt | `/workspace/planning?tab=review` | Chọn chuyến, kiểm tra, xác nhận theo vai trò |
| Đặt vé | `/` | Chuyến mở bán trong 14 ngày tới, giá và tồn chỗ |
| Bàn làm việc | `/staff` | Các chức năng theo tài khoản đang dùng |

Tài khoản nghiệp vụ đều dùng `LOCAL_DEMO_PASSWORD`:

| Tài khoản | Mục đích |
|---|---|
| `demo.data_admin@airline.test` | Sân bay, hạ tầng, đường bay, lập kế hoạch |
| `demo.data_admin.2@airline.test` | Người duyệt thay đổi danh mục độc lập |
| `demo.operations.han@airline.test` | Gửi duyệt lịch và điều hành ở HAN |
| `demo.ops_manager.han@airline.test` | Duyệt/công bố lịch ở HAN |
| `demo.fleet_manager@airline.test` | Đội tàu |
| `demo.crew_scheduler@airline.test` | Tổ bay |
| `demo.finance@airline.test` | Tài chính |
| `demo.passenger@airline.test` | Hành trình của khách |

Danh sách đầy đủ và tình huống demo được máy mới tự tạo tại `backend/storage/app/private/demo-install/<run_id>/`. Các file `state.json` cũ có sẵn từ lần demo khác không được dùng làm bằng chứng rằng database mới đã có dữ liệu.

## Khởi động lại và kiểm tra

```text
docker compose --env-file backend/.env up -d db1 db2 db3 mailpit app worker mail-worker
docker compose --env-file backend/.env exec app php artisan airline:demo-install --verify
```

Lệnh verify đọc database và báo số lượng/độ phủ thực tế. Chạy lại `python scripts/demo.py` giữ dữ liệu, tài khoản, mật khẩu và các chỉnh sửa đã thử; không reset lịch sử. Nếu dữ liệu bị thay đổi hoặc lịch demo đã hết hạn, verify báo rõ thay vì âm thầm tạo lại dữ liệu.

Nếu cổng đang được ứng dụng khác dùng, chọn cổng khác ngay khi cài:

```text
python scripts/demo.py --project airline-demo --port 18080 --mailpit-port 18025
```

Dùng cùng `--project` ở các lần chạy lại; với lệnh Docker Compose thủ công, thêm `--project-name airline-demo`. Mỗi project dùng các volume database riêng. Để cài bản độc lập trên máy vốn đã có dữ liệu, **tải thêm một thư mục dự án riêng**, chọn project/cổng mới. Không dùng chung thư mục `.env` đang phục vụ bản cũ. Không cần xóa volume cũ.

Nếu muốn cài ứng dụng với database trống và chỉ tài khoản quản trị, dùng `python scripts/demo.py --minimal`. Đây là tùy chọn riêng; mặc định là bản demo đầy đủ.

## Phạm vi dữ liệu

Các thay đổi code và bộ tạo dữ liệu demo được đưa lên Git. Cài mới tự tạo dữ liệu nghiệp vụ, hạ tầng bổ sung và các tình huống minh họa; không cần SQL riêng trên máy tác giả. Những chức năng và danh mục mới không còn chỉ xuất hiện ở máy đã phát triển.

Ngày lịch bay được tạo tương đối theo ngày cài, để còn chuyến sắp tới khi demo. ID vé, mã đặt chỗ và mật khẩu sinh riêng; không yêu cầu trùng từng ký tự với máy tác giả. Các chỉnh sửa thủ công thực hiện sau khi cài ở mỗi máy thuộc database máy đó; Git không tự đồng bộ những lần sửa dữ liệu về sau. Muốn chuyển nguyên trạng một phiên làm việc cụ thể phải dùng backup/restore riêng.

Toàn bộ khách, nhân viên, tàu bay, giao dịch và bố trí hạ tầng demo là dữ liệu mô phỏng. Chỉ tên/mã sân bay là danh mục tham khảo. Bộ cài chỉ chạy local, thanh toán mock và Mailpit; không gửi thanh toán/email ra dịch vụ thật. Các khóa bí mật và dữ liệu cá nhân của máy đang phát triển không được xuất lên GitHub.

## Khi xảy ra lỗi

- `docker` không nhận hoặc daemon không chạy: mở Docker Desktop, bật Linux containers và chờ Docker sẵn sàng.
- Lỗi tải Composer/npm/image: kiểm tra mạng rồi chạy lại. Với mạng có CA riêng, đặt `AIRLINE_CA_BUNDLE` trỏ đến file CA hợp lệ; script dùng `compose.trust.yaml`. Không tắt xác thực TLS.
- `Existing business data detected`: database đó đã có dữ liệu ngoài bản cài demo; lệnh từ chối ghi đè. Dùng bản tải/thư mục/project riêng theo hướng dẫn trên.
- Không đăng nhập được: dùng đúng `demo.admin@airline.test` và `LOCAL_DEMO_PASSWORD` của bản tải hiện tại.
- Các tình huống giữ chỗ/check-in có cửa sổ thời gian thật và sẽ hết hạn; bảng lịch vẫn có các chuyến tương lai để thử tiếp. Quy trình cài lại không tự đặt lại trạng thái nghiệp vụ.
