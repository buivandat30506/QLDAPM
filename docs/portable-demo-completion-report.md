# Kiểm chứng bản demo khi tải sang máy khác

Ngày: 10/11/2026. Nhánh đích: `feature/vinh`, repository `buivandat30506/QLDAPM`.

## Vấn đề đã sửa

Code mới đã có trên Git nhưng setup cũ chỉ migrate/seed cơ bản, chưa gọi bộ dữ liệu đầy đủ, chưa nối 10 loại hạ tầng và chưa có ví dụ M02 định kỳ. Các manifest demo cũ trong source còn có thể khiến lệnh tưởng database mới đã được khởi tạo.

Đã tạo quy trình `python scripts/demo.py`: cấu hình local riêng → build PHP → Composer theo lock → npm và đồng bộ CSS/JS qua Node container → ba MySQL và Mailpit → migrations → bootstrap admin → dữ liệu nghiệp vụ đầy đủ → chuẩn hóa và bổ sung 10 loại hạ tầng tại 22 sân bay → trạng thái ban đầu → cabin/khứ hồi → kế hoạch M02 và hai chuyến nháp → web/worker → readiness.

Máy mới chỉ cần Docker Desktop/Compose và Python 3.10+. Windows có wrapper PowerShell, Linux/macOS có wrapper Bash. Thư viện/backend/Node/MySQL chạy trong Docker. Hướng dẫn chính: [portable-demo.md](portable-demo.md), README đã cập nhật để mặc định cài bản đầy đủ.

## Kiểm tra thực tế

Tạo clone riêng `.demo-check` từ nhánh code đã đẩy, sao chép phần thay đổi bộ cài cần kiểm tra vào clone, không mang theo `.env`, vendor, node_modules hay SQL database của máy phát triển. Dùng Compose project `airline-portable-check`, các volume mới riêng, web 18080 và Mailpit 18025. Đã tải/cài Composer và npm từ lockfile, build image PHP (các lớp Docker đã cache), áp dụng toàn bộ migration vào ba database mới.

Lần kiểm tra phát hiện và đã sửa:

- Python dùng encoding mặc định Windows không ghi được tiếng Việt: chuyển UTF-8, ghi `.env` qua file tạm trước khi thay thế.
- Windows không có `os.getuid/getgid` và thường không tạo symlink được: có giá trị UID/GID mặc định, root `.env` dạng bản sao trên Windows và hỗ trợ `--env-file`.
- Host Windows không có `/etc/ssl/certs/...` như Linux: dùng CA bundle trong image; CA riêng qua override rõ ràng.
- Copy CSS bằng `copyFileSync` lỗi EPERM trên Docker Desktop bind mount: dùng đọc/ghi buffer; kiểm chứng đồng bộ thành công cả app/modules/M02/Bootstrap.
- Bộ dữ liệu legacy gắn hành lý 23 kg cho vé chỉ cho 20 kg: giảm dữ liệu mẫu về hợp lệ, không nới quy tắc hành lý. Các snapshot hành lý lịch sử cũng nằm trong hạn mức.
- Manifest cũ được Git lưu không đại diện cho DB mới: marker cài đặt lưu trong DB cùng run UUID; state dùng thư mục private theo UUID, tách hoàn toàn khỏi manifest cũ.
- Trang module đặt chỗ lỗi khi có reservation thật vì đọc cột `reservations.status` không tồn tại: tái sử dụng cách tổng hợp trạng thái từ các vé gốc như trang chi tiết hiện có. Không thêm trạng thái database hoặc thay quy tắc thanh toán. Bổ sung kiểm thử hiển thị và scope hành khách/sân bay.

Bản cài mới đã tạo và kiểm chứng:

| Dữ liệu | Số lượng |
|---|---:|
| Sân bay | 22 |
| Đường bay | 44 |
| Loại hạ tầng | 10 |
| Hạng mục hạ tầng | 776 |
| Độ phủ | 22/22 sân bay đều có đủ 10 loại |
| Tàu bay | 33 |
| Chuyến bay | 989 |
| Phân công tổ bay | 5.922 |
| Lịch sử dụng hạ tầng | 2.918 |
| Kế hoạch định kỳ M02 | 1 |
| Chuyến nháp liên kết nguồn M02 | 2 |
| Đặt chỗ | 34.789 |
| Giao dịch thanh toán mock | 34.781 |

Số hạ tầng/chuyến của bản khởi tạo được báo đúng theo dataset mới, không tuyên bố trùng tuyệt đối mọi thao tác riêng ở máy phát triển. Chuyến mới theo ngày cài để còn lịch tương lai; có thêm hai chuyến M02. Các chức năng và đầy đủ 10 loại hạ tầng không còn phụ thuộc database của máy tác giả.

## Kiểm thử tự động

- Python: 3 bài kiểm tra UTF-8/cấu hình đầy đủ, giữ nguyên khóa/mật khẩu và từ chối demo production, đều đạt.
- PHPUnit: 17 bài / 264 assertions của PortableDemoGuardTest, M02PlanningTest và InfrastructureFixturesTest, đều đạt.
- Kiểm tra bổ sung sau khi sửa trang đặt chỗ: 5 bài / 21 assertions của ReservationModuleDisplayTest và PortableDemoGuardTest, đều đạt.
- Cài mới: ghi dữ liệu thực vào ba volume riêng; không giả lập DB hoặc copy dump.
- Chrome qua HTTP localhost: 10 màn hình (sân bay, hạ tầng BMV/HAN, đường bay, kế hoạch và chi tiết nguồn M02, chuyến bay, phê duyệt, cabin, đặt chỗ, trang đặt vé), HTTP 200; kiểm tra 1440/390 px không tràn toàn trang, không lỗi JavaScript. Đăng nhập bằng tài khoản/mật khẩu thực do bản cài mới sinh.
- Replay: thêm ghi chú thử ở BMV trong DB demo riêng rồi chạy lại installer. Hash của 14 bảng (ops và users, gồm ID, dữ liệu, audit và hash mật khẩu) không thay đổi; ghi chú vẫn còn.
- Đối chiếu database vận hành gốc: 11 bảng kiểm kê M02 vẫn giữ nguyên số lượng, ID và hash cột cũ. Không áp dụng bộ dữ liệu mới lên database người dùng đang làm việc.
- Chạy lại toàn bộ `python scripts/demo.py` với build, Composer, npm, migration, bootstrap, installer, web và hai worker: exit 0, readiness HTTP 200 tại cổng 18080; dữ liệu không được sinh lại. Stack kiểm chứng được dừng riêng sau khi hoàn thành, giữ artifacts/volume để đối chiếu; stack đang dùng của người dùng vẫn chạy.

## Giữ dữ liệu và phạm vi

Bộ cài không reset/truncate/xóa volume. Database có dữ liệu nghiệp vụ ngoài bộ cài sẽ bị từ chối ghi đè. Chạy lại bản cài hoàn thành bỏ qua bước sinh dữ liệu; verify là thao tác đọc riêng, không tự vá các chỉnh sửa người dùng. Những lần sửa dữ liệu thủ công sau khi cài ở mỗi máy không tự đồng bộ qua Git.

Tài khoản demo/mật khẩu mới sinh trên từng máy. Không xuất tài khoản thật, .env, cookie, APP_KEY, SQL backup hay dữ liệu riêng tư của máy đang phát triển lên Git. Ảnh/JSON kiểm chứng và clone thử được Git ignore. Database vận hành cũ không được dùng để thử cài demo.
