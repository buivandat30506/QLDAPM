# Cập nhật giao diện sản phẩm và trạng thái hạ tầng — 10/11/2026

**Lưu ý phiên bản:** phần nhãn/nguồn trạng thái sân bay đã được yêu cầu mới thay thế bằng nhãn tiếng Anh và nhận biết Demo. Xem [báo cáo mới nhất](airport-operational-status-reset-report.md). Các thay đổi khởi tạo trạng thái hạ tầng trong tài liệu này vẫn giữ nguyên.

Yêu cầu mới thay thế phần trình bày nguồn dữ liệu ở lượt trước: giao diện sản phẩm không hiển thị nhãn/ghi chú mô phỏng hoặc thử nghiệm. Nguồn và audit nội bộ được giữ nguyên, không đổi thành xác nhận thủ công của Giám đốc.

Đã thực hiện:

- Bỏ nhãn, tooltip, chú giải và ghi chú kỹ thuật đó trên danh sách/chi tiết/lịch sử sân bay. Sự kiện khởi tạo được trình bày là “Khởi tạo trạng thái sân bay”, actor hệ thống không bị đổi thành tên người dùng.
- Bỏ nội dung thử nghiệm trên thẻ chuyến bay, chứng từ, hướng dẫn/thông báo thanh toán và mô tả huấn luyện mô hình; không đổi cấu hình cổng thanh toán hoặc quy tắc nghiệp vụ.
- Bốn ghi chú tạo dữ liệu có sẵn được `ProductText::reason` chuyển thành cách diễn đạt nghiệp vụ khi hiển thị. Không sửa reason trong phân bổ/đề nghị hoặc audit; không thay nội dung tự do khác của người dùng.
- Badge hạ tầng dùng cùng kiểu chấm tròn/màu với sân bay. Mã condition vẫn là NORMAL/RESTRICTED/MAINTENANCE/OUT_OF_SERVICE; nhãn thống nhất Bình thường/Hạn chế sử dụng/Đang bảo trì/Tạm ngừng hoạt động. Ngừng hiệu lực vẫn ưu tiên khi active=false. Không đưa tải cao/quá tải của sân bay vào tình trạng kỹ thuật hạ tầng.
- Hồ sơ tạo mới mặc định NORMAL nếu request không chỉ định condition; form mặc định Bình thường. Các API legacy tạo resource/area cũng đồng bộ mặc định đó. Request chủ động khai báo condition khác hoặc NULL vẫn giữ theo lựa chọn.
- `airline:initialize-infrastructure-statuses` mặc định chỉ xem trước; `--apply` mới ghi, chỉ local/testing. Chỉ chọn hồ sơ active=true và technical_state=NULL. Mỗi thay đổi tăng version, cập nhật thời gian và ghi snapshot trước/sau với action infrastructure.status.initialized. Không ghi đè condition có sẵn hoặc đổi active.

## Kết quả áp dụng cục bộ

Đã sao lưu ba node vào `backups/20261109T170057Z-19667` và lập snapshot trước khi ghi. Lúc **00:07:49 ngày 10/11/2026 (UTC+7)**, hoàn tất khởi tạo 644 hồ sơ đang có hiệu lực từ NULL sang NORMAL theo yêu cầu thiết lập trạng thái ban đầu. Chạy lại cập nhật 0 hồ sơ.

Đối chiếu: giữ nguyên 644 ID/hồ sơ, mọi trường hạ tầng ngoài technical_state/version/updated_at; hash của 16 bảng liên quan không thay đổi, gồm sân bay, tài nguyên nguồn, chuyến bay, phân bổ, người dùng, hành lý và thanh toán. Nội dung audit cũ được giữ nguyên; chỉ bổ sung audit khởi tạo. Không thay status/nguồn của 22 sân bay, không xóa dữ liệu, không gán vị trí hoặc thay lịch M18. Đây là khởi tạo giá trị danh mục theo yêu cầu, không phải phép đo hay xác nhận hiện trạng kỹ thuật thực địa.

## Kiểm tra

- PASS: 27 tests, 338 assertions; khởi tạo chọn lọc, không ghi đè trạng thái đã có, không đổi dữ liệu liên quan, audit, replay, default tạo mới, quyền cập nhật sân bay và hồi quy hạ tầng.
- PASS: 4 trang danh sách/chi tiết sân bay và hạ tầng ở 1920/1440/1024/768/390/320 px; không có nội dung thử nghiệm trên HTML của các trang kiểm tra, không pageerror JavaScript hoặc tràn ngang trang. Kiểm tra danh sách hạ tầng BMV hiển thị Bình thường.
- PASS: 99 Blade templates compile/parse; node --check; assets đồng bộ; git diff --check không lỗi whitespace.

Artifacts trong `backend/storage/app/private/product-*`: snapshot, kết quả so sánh, log tests/browser và ảnh kiểm tra. Không commit/push. Các báo cáo trước mô tả lịch sử triển khai; quy tắc giao diện hiện hành được cập nhật trong tài liệu này.
