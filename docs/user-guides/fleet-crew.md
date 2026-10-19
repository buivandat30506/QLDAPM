# Đội tàu bay và tổ bay — M04/M06

Ứng dụng local: http://127.0.0.1:8000/login. `demo_ops` có các vai trò khai thác/đội bay/tổ bay/kỹ thuật được cấp rõ; `demo_admin` chỉ quản trị hệ thống. Dùng helper `scripts/demo-credentials.ps1 -Username demo_ops -CopyPassword` để sao chép đúng mật khẩu đã giải mã JSON. Không dùng mật khẩu fixture của PHPUnit trên demo riêng.

## Đội tàu bay

1. Mở **Đội tàu bay**, tìm/lọc theo số đăng ký, cơ sở, trạng thái hoặc ngưỡng chú ý. Danh sách có phân trang; chọn số đăng ký để mở hồ sơ.
2. Thêm tàu với dòng, năm, cơ sở, số dư giờ/chu kỳ. Số dư lớn hơn 0 cần nguồn/chứng từ; số dư quá hạn tự đưa tàu vào bảo dưỡng. Sau tiếp nhận không sửa trực tiếp các tổng sử dụng.
3. Trên hồ sơ, nhập cấu hình theo hồ sơ vật lý: tên, các dải hàng/cột/hạng khoang, ghế khóa và lý do. Không dùng cùng hàng/cột cho hai vùng. Không vượt sức chứa dòng tàu bay.
4. Công bố tạo phiên bản bất biến. Chuyến mới giữ ID cấu hình và sức chứa đã chốt. Tàu còn chuyến chưa kết thúc sẽ bị từ chối đổi cấu hình; cần giải quyết kế hoạch/chỗ trước. Đổi tàu của chuyến có vé/dịch vụ/check-in cũng bị chặn tại nghiệp vụ điều hành hiện có, chờ workflow chuyển chỗ G3.
5. Hồ sơ nhập từ phiên bản cũ chưa có sơ đồ ghế vẫn giữ nguyên. Không tự suy đoán ghế từ sức chứa. Check-in của chuyến thiếu cấu hình bị chặn; không coi dữ liệu legacy là sơ đồ đã xác nhận.
6. Khi chuyến chuyển sang ARRIVED hợp lệ, giờ bay từ actual departure/arrival UTC được làm tròn 0,01 giờ cho từng chuyến; mỗi chuyến cộng một chu kỳ. Gửi lại không cộng lặp. Sổ lưu nguồn, mã sự kiện, thời điểm phát sinh/nhận, actor và cảnh báo.
7. Đối chiếu = số dư tiếp nhận + sổ sử dụng; chênh lệch được hiển thị, không tự sửa tổng cho khớp. Ngưỡng local đang kế thừa 3.000 giờ/1.500 chu kỳ sau bảo dưỡng, cảnh báo từ 90%; cần chuyên gia duyệt trước khai thác thật.
8. Đổi sang bảo dưỡng/ngừng khai thác cần lý do. Trở lại OPERATIONAL qua giải phóng kỹ thuật. Chỉ xóa hồ sơ chưa có tham chiếu; lịch sử/cấu hình không được xóa qua API.

## Tổ bay

1. **Phi hành đoàn**: tạo/sửa hồ sơ, số giấy phép, hạn giấy phép/đào tạo, năng định dòng tàu và khoảng khả dụng UTC. Hồ sơ có revision; dữ liệu cũ trên form bị từ chối ghi đè.
2. Trong chi tiết nhân sự, ghi LEAVE (nghỉ phép), UNAVAILABLE hoặc STANDBY (dự bị). Các khoảng có thể giao nhau; LEAVE/UNAVAILABLE luôn chặn phân công, kể cả có STANDBY. Hủy khoảng cần lý do và giữ lịch sử.
3. Thay điều kiện hồ sơ hoặc ghi khoảng không khả dụng ảnh hưởng chuyến chưa cất cánh sẽ bỏ duyệt/xác nhận cũ, lưu sự kiện và thông báo nội bộ; không âm thầm giữ phê duyệt. Khoảng ảnh hưởng chuyến đang bay bị từ chối để điều hành xử lý riêng.
4. **Phương án tổ bay**: tìm chuyến, chọn vị trí và kiểm tra nhân sự. Danh sách là bộ lọc quy tắc xác định, ưu tiên dự bị, không phải AI và không tự phân công. Khi thay thế, chọn cả phân công cũ và vị trí tương ứng.
5. Phân công → Kiểm tra và duyệt phương án → Ghi nhận xác nhận. Hệ thống kiểm lại năng định, hạn giấy phép/đào tạo, cửa sổ khả dụng, nhiệm vụ, nghỉ ngơi và vị trí tại commit/duyệt/xác nhận/cất cánh.
6. Người duyệt, phiên bản quy tắc, người xác nhận và chế độ SELF/OPERATOR được lưu. Nút điều hành là **Ghi nhận xác nhận**, không tự nhận là chữ ký của thành viên. Thành viên liên kết tài khoản có thể xem thông báo và tự xác nhận trong `/admin/workflows`; không xác nhận thay người khác.
7. Thay bằng dự bị/người khác cần lý do. Giao dịch thất bại giữ nguyên phân công cũ. Người mới về ASSIGNED, cần duyệt và xác nhận lại. Lịch sử snapshot vẫn giữ khi cùng người được phân công lại.
8. `local-crew-v1` là LOCAL_ONLY: nhiệm vụ tối đa 12 giờ, nghỉ tối thiểu 12 giờ, báo cáo trước 60 phút/kết thúc sau 30 phút, tối thiểu cơ trưởng/cơ phó. Đây là giả định kỹ thuật đã ghi nguồn; chưa có xác nhận chuyên gia và không đại diện tiêu chuẩn pháp lý/khai thác được phê duyệt.

## Quyền và tích hợp

Đội bay hỗ trợ global hoặc airport theo cơ sở; tàu legacy chưa có cơ sở cần global. Sửa danh mục dòng cần global. Hồ sơ tổ bay theo cơ sở; kế hoạch theo sân bay đi hoặc flight. Phân công đòi cả phạm vi nhân sự và chuyến; có quyền theo một chuyến không cấp quyền xem toàn bộ hồ sơ cá nhân. ADMIN không bypass.

`POST /api/v1/core/aircrafts/{tailNumber}/usage` là API nội bộ phục hồi/nhận lại sự kiện đã có facts ARRIVED, cần aircraft.write. Contract yêu cầu flight_id/source/external_id, cấm client hours/cycles; cùng source/external_id đổi chuyến trả409. Alias cùng chuyến có receipt riêng và không cộng lại. DEMO là mô phỏng. Nhận các nhãn AODB/FIDS không phải bằng chứng kết nối hãng/sân bay thật; adapter nguồn và outbox M03/M13 còn trong phạm vi triển khai tiếp. Không gửi mail/SMS hay tiền thật ở checkpoint này.

OpenAPI: [fleet-crew.openapi.yaml](../api/fleet-crew.openapi.yaml). Trạng thái kiểm thử hiện hành và các phần chưa làm: [implementation-progress.md](../implementation-progress.md).
