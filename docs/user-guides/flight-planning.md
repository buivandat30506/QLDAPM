# Đường bay và lịch bay — M02

`demo_data` quản lý đường bay, `demo_ops` lập/duyệt/công bố lịch trong bản local. Lấy mật khẩu bằng scripts/demo-credentials.ps1 như README. ADMIN chỉ quản trị hệ thống; cấp role/scope phù hợp trước khi thao tác. Nội dung dưới đây áp dụng phần M02 đã triển khai, không nhận toàn dự án đã nghiệm thu.

Vào **Đường bay & Tuyến** (`/admin/routes`), tìm theo mã/trạng thái và mở Chi tiết. **Thêm đường bay** yêu cầu hai sân bay khác nhau, khoảng cách, thời gian chuẩn. Có thể đặt mùa khai thác, dòng tàu được phép, ngày trong tuần hạn chế và ghi chú. Để bỏ mùa phải xóa cả hai ngày. Cập nhật ghi lịch sử; đường bay đã có lịch/chuyến không được đổi hai đầu hoặc xóa.

Vào **Lập và công bố lịch** (`/admin/schedules`), mở **Thêm lịch bay**. Chọn đường bay/dòng/tàu/cửa, hiệu lực, giờ và các thứ khai thác. Lịch một lần dùng cùng ngày bắt đầu/kết thúc. Ngày loại trừ/bổ sung theo YYYY-MM-DD, phân cách bằng dấu phẩy, nằm trong hiệu lực; không vừa thêm vừa loại một ngày. Mã mùa là nhãn để tìm/lọc.

Mặc định nhập UTC, giờ đến nhỏ hơn hoặc bằng giờ đi tính sang ngày sau. Nếu dùng giờ địa phương, nhập cả hai giờ và múi giờ IANA; cả hai giờ cùng múi giờ nguồn. Chọn số ngày đến cộng thêm. Hệ thống quy đổi từng ngày và chặn giờ DST mơ hồ/không tồn tại; có thể lập lịch UTC riêng cho ngày đó. Giờ mở sân bay dùng múi giờ riêng của sân bay. Dữ liệu chưa xác định giờ hoạt động sẽ chặn kiểm tra hoàn chỉnh.

Lưu nháp → **Kiểm tra xung đột** → xem báo cáo → **Gửi duyệt** → **Phê duyệt lịch** → **Công bố lịch**. Báo cáo nêu lỗi theo ngày và các chuyến liên quan, không chỉ thông báo chung. Tàu/cửa/bảo dưỡng/mùa/ngày/giờ sân bay và turnaround được kiểm lại ở từng bước. Có lỗi thì trạng thái giữ nguyên; báo cáo vẫn được lưu. Khi báo dữ liệu đã đổi, tải lại rồi kiểm tra thông tin trước khi lưu tiếp.

Từ bản đã công bố, nhập lý do rồi **Tạo bản sửa đổi**. Bản công bố cũ vẫn hiệu lực trong khi sửa/duyệt bản mới. Kiểm tra **Đối tượng bị ảnh hưởng**: nếu flight đã tạo cần đổi giờ/tàu/cửa thì phải giải quyết trong điều hành trước; hệ thống không tự cập nhật flight hoặc booking. Khi bản mới công bố, bản cũ chuyển Đã thay thế; xem các bản công bố và lịch sử ở cuối trang. Sửa đổi giữ sân bay đi; đổi sân bay đi cần tạo chuỗi lịch mới.

**Trả về sửa** cần lý do. **Hủy lịch** cần lý do và chỉ dừng tạo chuyến mới; các chuyến/đơn hàng đã có giữ nguyên, xử lý qua nghiệp vụ điều hành/hoàn đổi tương ứng. Chưa xem đây là luồng hoàn đổi M08 đã hoàn chỉnh. Người có scope sân bay chỉ xem/sửa lịch có sân bay đi thuộc phạm vi được cấp; backend vẫn kiểm quyền khi gọi API trực tiếp.

API: [flight-planning.openapi.yaml](../api/flight-planning.openapi.yaml). Cặp API tạo flight địa phương nên gửi service_date (ngày nguồn), hệ thống lưu flight_date là ngày UTC. Mẫu triển khai và giới hạn nằm ở ADR-023–027; profile này chưa có chứng nhận sử dụng trong khai thác hàng không thật.
