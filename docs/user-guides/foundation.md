# Sử dụng dữ liệu nền M01

Đăng nhập với DATA_ADMIN có scope global hoặc sân bay được cấp. ADMIN quản trị account không tự có quyền sửa sân bay/dữ liệu chủ.

## Sân bay và tài nguyên

Mở Sân bay & Tài nguyên. Tìm theo mã/tên/thành phố, lọc trạng thái, sắp xếp và chuyển trang. Danh sách chỉ có sân bay thuộc scope được cấp. Thêm sân bay mới cần global scope.

Mở Chi tiết để sửa tên, thành phố, quốc gia, múi giờ IANA (ví dụ Asia/Ho_Chi_Minh), giờ hoạt động, tọa độ hoặc trạng thái. IATA/ICAO chuẩn hóa in hoa; định danh trùng bị từ chối. Tọa độ nhập cả vĩ/kinh độ; phạm vi -90…90/-180…180. Hồ sơ cũ chưa có tọa độ được giữ null, không tự tạo số liệu địa lý. IATA không đổi khi sửa. Lịch sử dùng UTC; timezone là thông tin nghiệp vụ riêng của sân bay.

Trong tài nguyên, chọn nhà ga/cửa/vị trí đỗ/băng chuyền/quầy/đường băng, nhập mã và trạng thái. Mã không trùng trong cùng sân bay và loại. Sửa tài nguyên để tạm ngừng/bảo trì; loại và sân bay sở hữu giữ nguyên. Có lọc loại/trạng thái/mã và phân trang. Tài nguyên có lịch/chuyến liên kết không xóa; tạm ngừng để giữ lịch sử và chặn lần kiểm tra lịch tiếp theo. Xóa sân bay chỉ khi không còn tài nguyên, đường bay hoặc tổ bay liên quan.

Lịch sử phía dưới gồm thay đổi sân bay/tài nguyên và người thực hiện. Người ngoài scope bị từ chối ở backend kể cả gọi URL/API trực tiếp.

## Dữ liệu chủ

Dữ liệu chủ cần quyền global; không tự mở toàn bộ dữ liệu chủ cho grant sân bay. Ba nhóm schema v1: CABIN, SERVICE, CURRENCY. Đây là quyết định triển khai khi nguồn không liệt kê bộ danh mục chi tiết; mở rộng schema có validation thay vì nhận JSON tùy ý.

Tạo dữ liệu bằng mã và JSON theo hướng dẫn cạnh form. Mọi nhóm cần name; timezone IANA tùy chọn. CABIN cho phép cờ demo; SERVICE có cabin_code tùy chọn trỏ tới hạng khoang đã công bố; CURRENCY cần minor_units từ 0–4. Dữ liệu chủ này là danh mục, không tự đặt giá hoặc thay đổi tiền trong đơn hàng.

Lưu → DRAFT. Gửi phê duyệt → PENDING. Người có master.approve phê duyệt → APPROVED; công bố → PUBLISHED. Mọi bước kiểm tra lại chất lượng. Có thể trả về sửa với lý do. Người gửi/duyệt/công bố được lấy từ phiên đăng nhập và lưu lịch sử; không tin người duyệt do browser truyền lên. Nguồn chưa yêu cầu hai tài khoản khác nhau; cùng DATA_ADMIN được cấp quyền có thể thực hiện các bước, vẫn truy vết từng trách nhiệm.

Sau công bố, chọn Tạo bản sửa đổi và nhập lý do. Snapshot công bố trước vẫn bất biến/có thể đọc qua API published; bản sửa mới phải đi lại đầy đủ quy trình. Lưu với dữ liệu trang đã cũ trả409 để tải lại, tránh ghi đè thay đổi của người khác. Trạng thái/phiên bản không sửa bằng form thuộc tính.

## Nhập và xuất

Tải mẫu CSV từ màn hình. Dùng UTF-8 (có thể BOM), header đúng `category,code,payload`, tối đa 1 MiB/1.000 bản ghi. JSON có dấu nháy phải escape theo CSV; dùng tệp mẫu làm chuẩn.

Chọn tệp → Kiểm tra tệp. Kết quả hiển thị từng bản ghi CSV (số bản ghi, không phải số dòng vật lý khi có multiline), dữ liệu và lỗi. READY chỉ có nghĩa kiểm tra ban đầu đạt; chưa nhập danh mục. INVALID chặn cả lô, không nhập một phần. Sửa tệp rồi kiểm tra lại thành lô mới.

Xác nhận nhập toàn bộ bản nháp kiểm tra lại dữ liệu DB trong transaction có khóa. Nếu mã đã được tạo sau lúc preview, cả lô bị chặn và kết quả lỗi mới được lưu. Retry một lô đã COMMITTED trả cùng kết quả, không nhân đôi dữ liệu. Từng bản nháp vẫn cần phê duyệt/công bố.

Xuất CSV theo bộ lọc tạo bản đọc để kiểm tra ngoài hệ thống; có name/status/version và payload, được bảo vệ CSV formula. Tệp xuất báo cáo có header khác mẫu nhập; dùng mẫu nhập khi cần nạp lại. Không đưa PII/secret vào tên danh mục hoặc tệp import. Tệp không được giữ công khai; hệ thống chỉ lưu metadata/hash, dữ liệu đã che trường nhạy cảm và lỗi theo dòng.

Tích hợp payment/ERP/SSO thật chưa được nối từ M01 này. UAT tự động không thay xác nhận người nghiệm thu; trạng thái approval còn Pending.
