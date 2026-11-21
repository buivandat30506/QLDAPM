# M01 — Thu gọn giao diện trạng thái vận hành

Ngày: 10/11/2026. Chỉ thay đổi phần trình bày; không migration hoặc cập nhật dữ liệu trạng thái.

## Giao diện

- Card chi tiết gồm tiêu đề “Trạng thái vận hành”, badge tiếng Anh, thời gian cập nhật theo giờ Việt Nam `HH:mm dd/mm/yyyy` và “Đổi trạng thái” khi đúng quyền hiện có.
- Form mặc định thu gọn; mở bằng nút, tự mở khi có lỗi validation. Vẫn giữ lý do, checkbox xác nhận, CSRF và endpoint cập nhật cũ.
- Danh sách dùng cùng badge và partial thời gian. Link “Đổi trạng thái” chỉ hiện theo `AirportStatusAuthority::canUpdate`; mở thẳng form trên trang chi tiết.
- Bỏ chú giải dài, nguồn/người cập nhật/ghi chú nguồn và dòng giải thích quyền khỏi card chính. Bỏ tooltip về nguồn mô phỏng. Không còn các nhãn “Dữ liệu demo”, “Dữ liệu mô phỏng”, “Dữ liệu trình bày”, “Trạng thái phục vụ trình bày chức năng” trong HTML danh sách/chi tiết được kiểm tra.
- Chưa có thời điểm thì hiển thị “Chưa cập nhật”; không tự tạo thời điểm.

## Bảo toàn nghiệp vụ

Không sửa controller/service/permission/database. Bỏ nhãn không đổi source SIMULATION thành MANUAL, không xác nhận dữ liệu và không gọi cơ chế điều phối. Các metadata nguồn, khóa/fingerprint, ghi chú và audit vẫn giữ nguyên trong Backend/Database.

Không cấp quyền cho admin kỹ thuật. Database làm việc hiện có 0 bổ nhiệm thay đổi trạng thái; admin vẫn chỉ xem trạng thái. Các tài khoản Giám đốc dùng kiểm tra trình duyệt chỉ được tạo trên schema riêng `infra10_verify_20261110`.

Đối chiếu hash trước/sau kiểm tra trên database local:

| Bảng | Số dòng | Kết quả |
| --- | ---: | --- |
| ops.airports | 22 | Giữ nguyên toàn bộ dữ liệu, bao gồm metadata trạng thái |
| ops.audit_logs | 1.833 | Giữ nguyên |
| ops.flights | 987 | Giữ nguyên |
| ops.resource_allocations | 2.918 | Giữ nguyên |
| mysql.airport_status_authorities | 0 | Giữ nguyên |

Không thay đổi mã nguồn nghiệp vụ M02/M03/M18. Không commit hoặc push.

## Kiểm tra

- `AirportStatusDisplayTest`: **11/11 PASS, 186 assertions**, gồm quyền theo bổ nhiệm/phạm vi, cập nhật thủ công có xác nhận, nguồn dữ liệu, bốn badge, thời gian Việt Nam và không thay đổi metadata khi chỉ xem trang.
- Chrome: **16 lượt mở trang PASS**, danh sách và chi tiết, hai vai trò chỉ xem/Giám đốc, tại 1440/768/390/320 px. Không lỗi JavaScript, không tràn trang.
- Đã thử mở/đóng form bằng nút, kiểm tra đủ Normal/High/Overload/Maintenance, form mở từ link danh sách và giữ gọn khi đóng.
- Đăng nhập admin thực tế ở localhost:8000: card BMV hiển thị badge/thời gian, không có nội dung nguồn hoặc form trái quyền.
- Đã xem ảnh chụp card desktop/mobile. Preview riêng đã dừng sau kiểm tra.
- `npm run assets` đồng bộ CSS; Pint cho test và `git diff --check` đạt.

File thay đổi: `backend/resources/views/m01/airport-status-panel.blade.php`, `airport-status-time.blade.php` (mới), `status.blade.php`, `index.blade.php`, `airport-detail.blade.php`; `frontend/app.css`, bản sao `backend/public/app.css`; `backend/tests/Feature/AirportStatusDisplayTest.php`.

Bằng chứng riêng tư trong `backend/storage/app/private`: `compact-status-tests.log`, `compact-status-browser-results.json`, `compact-status-main-before.json`, ảnh `compact-status-main-bmv.png`, `compact-status-viewer-*.png`, `compact-status-director-*.png`. Các file này được Git ignore.
