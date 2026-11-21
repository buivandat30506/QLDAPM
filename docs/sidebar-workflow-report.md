# Báo cáo tổ chức sidebar và luồng chức năng

Ngày cập nhật: 09/11/2026.

## Cách xử lý

Menu dùng tên mục nhỏ theo hai ảnh tham chiếu. Mỗi mục dẫn tới phần nghiệp vụ đã có; không thêm nút chờ, đường dẫn giả hoặc chức năng nghiệp vụ mới để lấp chỗ trống.

Các trang đang chứa nhiều nghiệp vụ được tách phần hiển thị bằng tham số `flow`. Ví dụ, ba đường dẫn `/workspace/catalog?flow=airports`, `?flow=infrastructure`, `?flow=routes` chỉ hiển thị bảng, biểu mẫu và đề nghị sửa đổi của danh mục tương ứng. Các địa chỉ cũ không có `flow` vẫn hoạt động như trước.

Các bước liên quan nằm trong thanh điều hướng bên trong trang, thay vì thành nhiều mục ngang hàng trên sidebar. Tìm kiếm, xóa lọc, phân trang và quay lại sau khi gửi biểu mẫu giữ nguyên luồng hiện tại. Sidebar phân biệt cả đường dẫn và tham số `flow`, tránh đánh dấu đồng thời hai mục dùng chung một trang.

## Ánh xạ chức năng trong ảnh

| Chức năng chính | Mục nhỏ | Địa chỉ vào luồng | Nội dung đã nối |
| --- | --- | --- | --- |
| Quản lý sân bay và tài nguyên | Danh sách sân bay | `/workspace/catalog?flow=airports` | Xem, tìm kiếm, thêm sân bay; đề nghị sửa và duyệt độc lập; nhập CSV sân bay. |
| Quản lý sân bay và tài nguyên | Cơ sở hạ tầng | `/workspace/catalog?flow=infrastructure` | Danh mục nhà ga, cửa, vị trí đỗ và quầy; thêm tài nguyên; đề nghị sửa và duyệt. |
| Quản lý sân bay và tài nguyên | Phân bổ tài nguyên | `/workspace/resources` | Xem lịch; phân bổ/gỡ phân bổ cho chuyến; dùng kiểm tra giao thời gian hiện có. |
| Quản lý chuyến bay | Tuyến đường bay | `/workspace/catalog?flow=routes` | Danh sách đường bay; thêm tuyến; đề nghị sửa trạng thái, thời lượng và khoảng cách; duyệt độc lập. |
| Quản lý chuyến bay | Lập và công bố lịch bay | `/workspace/scheduling?flow=planning` | Lập lịch nháp/lặp theo kỳ → trình duyệt một hoặc nhiều chuyến → phê duyệt, công bố theo quyền. |
| Điều hành chuyến bay | Theo dõi chuyến bay | `/workspace/scheduling?flow=monitoring` | Xem lịch và trạng thái; nhân viên điều hành cập nhật đón khách, đóng cửa, khởi hành, hạ cánh. Không lẫn biểu mẫu lập lịch. |
| Điều hành chuyến bay | Gián đoạn và phục hồi | `/workspace/disruptions` | Lập/duyệt chậm hoặc hủy → liên kết đổi tàu/tổ bay (`/workspace/recovery`) và hỗ trợ khách bị gián đoạn (`/workspace/passenger-recovery`). |
| Quản lý máy bay | Đội máy bay | `/workspace/fleet` | Hồ sơ, thêm tàu, sửa/duyệt dữ liệu; bước cấu hình khoang khách tại `/workspace/fleet-cabin`. |
| Quản lý máy bay | Bảo dưỡng máy bay | `/workspace/maintenance` | Mở lệnh → ghi nhận hoàn tất và bằng chứng → giải phóng kỹ thuật bởi người độc lập. |
| Nhân sự phi hành đoàn | Hồ sơ phi hành đoàn | `/workspace/crew?flow=profiles` | Hồ sơ, vị trí, năng định, hạn giấy phép; thêm hồ sơ và sửa/duyệt dữ liệu. |
| Nhân sự phi hành đoàn | Phân công phi hành đoàn | `/workspace/crew?flow=assignments` | Bảng phân công và biểu mẫu chọn chuyến/nhân sự; tái sử dụng kiểm tra năng định, giấy phép và thời gian nghỉ. |
| Dịch vụ mặt đất | Nhiệm vụ mặt đất | `/workspace/ground?flow=tasks` | Xem nhiệm vụ; người điều hành giao việc, người thực hiện và hạn SLA. |
| Dịch vụ mặt đất | Theo dõi công việc | `/workspace/ground?flow=tracking` | Xem trạng thái, việc quá hạn và bằng chứng; nhân viên mặt đất cập nhật nhiệm vụ của mình. |
| Quản lý hành khách | Hồ sơ hành khách | `/staff/passengers` | Tra cứu tên/email/mã đặt chỗ trong phạm vi được phân quyền; xem hành trình, trạng thái, vé và thẻ lên tàu khi đủ điều kiện. Đây là tra cứu theo đặt chỗ, chưa phải hồ sơ khách hàng độc lập. |
| Quản lý hành khách | Check-in và lên máy bay | `/workspace/boarding-control` | Kiểm soát cửa sổ check-in và chốt danh sách → liên kết làm thủ tục tại `/staff/passengers` → quét thẻ tại `/staff#boarding-console`. |
| Quản lý hành khách | Quản lý hành lý | `/workspace/baggage` | Tạo thẻ → quét/bàn giao → truy vết; dùng luồng xử lý khách không đến và dỡ hành lý hiện có. |
| Quản lý hành khách | Chăm sóc khách hàng | `/workspace/customer` | Tiếp nhận/xử lý yêu cầu, trao đổi → phân công và hạn hỗ trợ (`/workspace/support-desk`) → duyệt sửa thông tin (`/workspace/passenger-revisions`). |
| Vé và thanh toán | Đặt chỗ và vé | `/modules/m2?flow=bookings` | Trang tổng hợp booking và hồ sơ đặt chỗ theo hành trình; bỏ biểu mẫu hành lý/hỗ trợ khỏi màn hình này. |
| Vé và thanh toán | Giá vé và tồn chỗ | `/workspace/fare` | Thiết lập hạng giá, giá vé, hạn mức, đóng/mở bán → điều kiện vé và hành lý tại `/workspace/fare-rules`. |
| Giám sát và kiểm soát | Cảnh báo thông minh | `/modules/m3?flow=alerts` | 20 cảnh báo cảm biến gần nhất trong phạm vi được xem; khuyến nghị; kiểm tra điểm rủi ro theo quyền → quản lý thiết bị tại `/workspace/iot`. Không hiển thị biểu mẫu huấn luyện mô hình trong luồng cảnh báo. |
| Giám sát và kiểm soát | Phân tích và báo cáo | `/workspace/analytics` | KPI khai thác, đúng giờ, chuyến hủy, SLA và nguồn dữ liệu hiện có. |
| Tài chính và đối soát | Tài chính nghiệp vụ | `/workspace/finance?flow=transactions` | Xác nhận chuyển khoản, xử lý đổi/hoàn vé và sổ giao dịch → điều chỉnh tài chính tại `/workspace/financial-adjustments`. |
| Tài chính và đối soát | Đối soát doanh thu | `/workspace/finance?flow=reconciliation` | Tạo kỳ → xử lý sai lệch → khóa kỳ/xuất kết quả → nhập sao kê (`/workspace/bank-statements`) và báo cáo doanh thu (`/workspace/revenue`). |
| Cá nhân | Tài khoản và hồ sơ cá nhân | `/profile` | Hồ sơ và đổi mật khẩu. Các mục quản trị tài khoản, cấu hình kết nối, kiểm toán và thông báo hiện có vẫn nằm trong nhóm tài khoản theo quyền. |

## Những phần chưa bổ sung

- **Cơ sở hạ tầng:** chỉ nối danh mục tài nguyên sân bay đã có giao diện. Chưa xây thêm bản đồ, sơ đồ mặt bằng hay quản lý công trình/bảo trì hạ tầng. Các API khu vực sân bay nâng cao chưa được đưa thành một giao diện mới trong lần này.
- **Hồ sơ hành khách:** chưa bổ sung hồ sơ khách hàng tập trung, lịch sử CRM toàn diện hoặc chức năng CRUD khách hàng độc lập. Nhãn menu sử dụng khả năng tra cứu hành khách theo đặt chỗ hiện có.
- **Đặt chỗ và vé:** màn hình dành cho nhân viên hiện là tổng hợp 12 booking và 8 hồ sơ đặt chỗ gần nhất. Chưa bổ sung quy trình nhân viên tạo đặt chỗ/xuất vé thay khách hoặc bộ tìm kiếm toàn bộ kho vé. Luồng khách tự đặt và thanh toán hiện có vẫn ở khu vực hành khách.
- **Cảnh báo thông minh:** điểm rủi ro sử dụng bộ quy tắc mô phỏng hiện có. Chưa thêm hệ thống cảnh báo thời gian thực, xác nhận/đóng cảnh báo hoặc mô hình an toàn được chứng nhận. Giao diện huấn luyện cũ vẫn truy cập được tại `/modules/m3`, không nằm trong menu cảnh báo.
- **Theo dõi công việc:** sử dụng trạng thái, SLA và bằng chứng của nhiệm vụ mặt đất; chưa có bảng Kanban hoặc lịch điều phối mới.

## Phân quyền và tính tương thích

- Mục sidebar và các bước liên quan được lọc theo quyền hiện tại. Kiểm tra quyền tại controller/service vẫn có hiệu lực khi truy cập URL trực tiếp.
- Khi một vai trò chỉ được dùng một bước trong luồng, điểm vào chuyển sang bước đó: chuyên viên phân tích vào báo cáo doanh thu; điều phối tổ bay vào phục hồi tổ bay. Không dẫn những vai trò này tới trang tài chính hoặc gián đoạn mà họ không có quyền.
- Danh sách sửa/duyệt tại luồng sân bay, cơ sở hạ tầng và đường bay được giới hạn đúng danh mục; các đề nghị do chính người duyệt tạo vẫn bị loại khỏi lựa chọn duyệt.
- Admin được hiển thị thao tác công bố một chuyến trong luồng lập lịch, tương ứng quyền đã được service hỗ trợ. Quy tắc người lập/người duyệt độc lập vẫn do service kiểm tra.
- Không thêm migration, dữ liệu mẫu hay tài khoản vào ứng dụng chính; không thay đổi mật khẩu người dùng hoặc `.env`. Kiểm thử sử dụng riêng ba schema/tài khoản DB `airline_test` và tài khoản đăng nhập tạm trong DB test.

## Thành phần triển khai

- [`StaffNavigation.php`](../backend/app/Support/StaffNavigation.php): nhãn menu, điểm vào, bước liên quan và trạng thái đang chọn.
- [`WorkspaceFlow.php`](../backend/app/Support/WorkspaceFlow.php): phạm vi bảng/biểu mẫu của từng luồng con.
- [`WorkspaceController.php`](../backend/app/Http/Controllers/WorkspaceController.php), [`WorkspaceForms.php`](../backend/app/Support/WorkspaceForms.php): chọn nội dung và danh mục duyệt phù hợp.
- [`workflow-links.blade.php`](../backend/resources/views/partials/workflow-links.blade.php): điều hướng các bước bên trong chức năng.
- [`ModuleController.php`](../backend/app/Http/Controllers/ModuleController.php), [`bookings.blade.php`](../backend/resources/views/modules/bookings.blade.php), [`alerts.blade.php`](../backend/resources/views/modules/alerts.blade.php): màn hình đặt chỗ/vé và cảnh báo chuyên biệt.
- [`SidebarWorkflowTest.php`](../backend/tests/Feature/SidebarWorkflowTest.php): kiểm tra tách luồng, phân quyền và chọn đúng mục điều hướng.

## Kiểm chứng

- PHPUnit: **7 bài / 59 kiểm tra đạt**, chạy trên ba DB `airline_test` riêng biệt. Bao gồm tách bảng/biểu mẫu, quyền truy cập trực tiếp, luồng không hợp lệ, giới hạn lựa chọn duyệt đúng danh mục và loại người tự duyệt, hai màn hình module chuyên biệt, đánh dấu đúng một mục sidebar và điểm vào phù hợp từng vai trò.
- Laravel Pint: đạt trên các lớp điều hướng, định nghĩa luồng và bộ kiểm thử mới; các view Blade biên dịch thành công.
- Chromium: **30/30 điểm vào sidebar trả HTTP 200**; mỗi trang chỉ đánh dấu một mục điều hướng và mở tối đa một nhóm. Đã kiểm tra giữ `flow` sau khi lọc/xóa lọc, các bước liên quan của luồng phục hồi, gửi kiểm tra rủi ro rồi hiển thị kết quả ngay trong luồng cảnh báo.
- Giao diện điện thoại 390 × 844: mở/đóng sidebar bằng nút và phím Escape đạt; không tràn ngang. Không ghi nhận lỗi JavaScript trong lượt kiểm tra. Có ảnh xem trước lưu cục bộ trong `backend/storage/app/private/sidebar-workflow-desktop.png` và `sidebar-workflow-mobile.png`.
- Dữ liệu và tài khoản phục vụ kiểm thử nằm trong DB test; dịch vụ web tạm ở cổng 18081 được dừng sau kiểm tra. Ứng dụng chính vẫn ở cổng 8000.
