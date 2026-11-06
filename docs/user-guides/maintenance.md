# Bảo dưỡng M05 — UC-12–14

Mở `/admin/maintenance/plans` bằng tài khoản có `maintenance.read`. Demo local dùng `demo_ops`; xem tên tài khoản bằng `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1`. Thêm `-Username demo_ops -CopyPassword` để chép mật khẩu đã giải mã JSON. Không sao chép chuỗi escape từ runtime JSON.

## Lập và điều chỉnh lịch

Chọn tàu bay, xem giờ/chu kỳ và sân bay cơ sở. Khi số liệu không khớp hồ sơ bàn giao và ledger, bổ sung/đối chiếu ở M04; hệ thống từ chối lập kế hoạch trên số liệu thiếu tin cậy. Nhập hạng mục, lịch bắt đầu/kết thúc và hạn hoàn tất **UTC**. API có thể nhận timestamp có offset và chuyển sang UTC.

Chọn ngày, tổng giờ bay, tổng chu kỳ hoặc điều kiện. Với giờ/chu kỳ, nhập **số tích lũy tuyệt đối**; điều kiện đang kích hoạt chặn khai thác ngay. Hạn ngày vẫn là hạn hoàn tất bổ sung cho mọi loại. Ghi từng bước bắt buộc và căn cứ kỹ thuật. Chỉ chọn thiết lập lại mốc giờ/chu kỳ tổng khi hạng mục thực sự là kiểm tra tổng tương ứng; kiểm tra một bộ phận không tự thiết lập lại cả hai mốc.

Xung đột chuyến, lịch đã gửi duyệt/công bố, kế hoạch hoặc lệnh khác bị từ chối trong transaction. Trước khi có lệnh, có thể sửa lịch bằng lý do và revision hiện tại. Yêu cầu đã đến hạn không được dời hạn, đổi loại hoặc xóa điều kiện để né chặn khai thác.

## Lệnh, nhân lực và vật tư

Từ kế hoạch, tạo một lệnh với kỹ thuật viên đang hoạt động và có quyền tại sân bay cơ sở; vị trí là STAND/AVAILABLE cùng sân bay. Nếu chưa có vị trí, bộ phận dữ liệu sân bay bổ sung ở M01. Mỗi kế hoạch có một lệnh để giữ liên kết trách nhiệm; hồ sơ đã có lệnh không được sửa kế hoạch phía sau.

Kho vật tư phân theo sân bay, đơn vị nguyên như cái/bộ. Phiếu nhập có tham chiếu duy nhất theo vật tư: gửi lại cùng nội dung không cộng tồn lần nữa; cùng tham chiếu khác nội dung bị từ chối. Tiền tố `ORDER-` dành cho phiếu xuất nội bộ. Trước khi bắt đầu, có thể sửa người/vị trí/định mức bằng lý do; lịch sử giữ cả trước và sau điều chỉnh.

Khi bắt đầu, server kiểm tra lại lịch, người/vị trí và toàn bộ tồn kho. Thiếu bất kỳ vật tư nào thì **chưa trừ kho**, lưu lệnh Chờ vật tư và cảnh báo, tàu bay không khả dụng. Bổ sung kho rồi kiểm tra lại. Đủ vật tư thì trừ toàn bộ nguyên tử, ghi phiếu xuất duy nhất và chuyển Đang thực hiện. Lặp thao tác hoặc tranh chấp không trừ hai lần. Sau xuất kho không sửa định mức cũ.

## Kết quả, ký và giải phóng

Ghi kết quả từng bước: Chưa đạt giữ lệnh Đang thực hiện. Khi mọi bước đạt, đính kèm PDF/PNG/JPEG tối đa10MiB. Hệ thống kiểm MIME thực, lưu private với SHA256; tải lại cần quyền và scope của tàu bay. Tài liệu không có URL công khai.

Nhập kết luận, xác nhận kỹ thuật bằng tài khoản đang đăng nhập, rồi phê duyệt kết quả. Chữ ký là xác nhận trong ứng dụng có actor/time và lịch sử; chưa tích hợp PKI. Người có `maintenance.approve` quyết định tiếp tục không khả dụng hoặc giải phóng. Không có bypass cho ADMIN. Thiếu bước, tài liệu, chữ ký, phê duyệt hoặc tài liệu bị sửa/mất đều không giải phóng. Lệnh đã quá hạn, kế hoạch khác đến hạn hoặc lệnh kỹ thuật khác chưa giải phóng cũng chặn; không có nút tự gia hạn để vượt quy tắc.

Giải phóng thành công chốt kế hoạch, lưu người/lý do/thời điểm, giải quyết cảnh báo và cập nhật khả dụng. Chỉ hạng mục đã khai báo kiểm tra tổng mới đặt lại mốc sử dụng. Yêu cầu quá hạn cần bộ phận chuyên môn xử lý theo quy trình ngoại lệ được phê duyệt; ứng dụng không tự giả định quyền override chưa có trong hồ sơ.

## Hồ sơ cũ và vận hành

Danh sách cuối trang hiển thị hồ sơ tương thích. Bằng chứng chữ/ô vật tư cũ được giữ để tra cứu, không được coi là file, phiếu xuất hoặc chữ ký mới. Bộ phận đội tàu bổ sung sân bay cơ sở trước; kỹ thuật chọn vị trí, ghi căn cứ rà soát và chuyển hồ sơ. Sau chuyển, rà soát lại nguồn lực/định mức, thực hiện các bước và đính kèm/ký như lệnh mới. Event giữ nguyên hồ sơ trước chuyển. Hồ sơ đã hoàn tất/phê duyệt theo quy trình cũ cần rà soát hồ sơ gốc, không tự phát sinh chứng nhận thay thế.

Scheduler kiểm cảnh báo mỗi5phút. Chạy thủ công local: `php scripts/demo.php maintenance:alerts`. Ngưỡng hiển thị sắp đến hạn ngày24h là LOCAL_ONLY; ngưỡng giờ/chu kỳ tổng kế thừa profile M04 và cần chuyên môn phê duyệt. Cổng lập/công bố lịch, tạo chuyến và khởi hành kiểm tra trực tiếp trong transaction, không chờ scheduler.

Backup cần đồng thời DB, `storage/app/private/maintenance`, manifest hash và khóa mã hóa cất riêng. `php scripts/backup-local-demo.php` chỉ sao lưu DB demo. `php scripts/verify-restore.php` chỉ phục hồi sang DB test mới, có kiểm hash các tài liệu thật từ hành trình browser. Không chạy `migrate:fresh` trên demo hoặc DB người dùng. Không tích hợp MRO/PKI hoặc gửi thông điệp bên ngoài trong M05 hiện tại.


Runtime Windows hiện đã đo upload_max_filesize/post_max_size40M, đủ cho giới hạn ứng dụng10MiB. Khi cài máy khác cần cấu hình PHP upload_max_filesize ít nhất10M, post_max_size lớn hơn giới hạn upload và khởi động lại PHP. Docker runtime chưa chạy; cần cấu hình/kiểm lại các giá trị này trong container ở gateG6, không suy ra từ PHP của Windows. Gate lập lịch xét thời lượng bay kế hoạch và1chu kỳ dự kiến; chỉ actual usage được cộng ledger. Khi còn chuyến DEPARTED thì không bắt đầu bảo dưỡng dù giờ hạ cánh dự kiến đã qua.


Trước khi bắt đầu phải chọn **Duyệt cho phép thực hiện**, nhập căn cứ bằng tài khoản có maintenance.approve đúng scope. Đây là duyệt lệnh/nguồn lực trước công việc (BA9.7), khác với duyệt kết quả sau ký. Nếu điều chỉnh người, vị trí hoặc định mức, authorization bị thu hồi và phải duyệt lại; lịch sử giữ bản duyệt cũ. Không tự coi người tạo lệnh là người duyệt. Hồ sơ nhập cũ không được tự bịa ngày/người duyệt; cần rà soát. API legacy start với người dùng đăng nhập bị409, phải chuyển sang hồ sơ đầy đủ.
