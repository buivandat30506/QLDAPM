# Prompt triển khai toàn bộ dự án

Đặt `AGENTS.md` và `CODEX_MASTER_PROMPT_QL_HANG_KHONG.md` vào thư mục gốc dự án. Nếu đã có AGENTS.md, hợp nhất hướng dẫn phù hợp và giữ quy tắc cũ thay vì ghi đè mù quáng. Mở đúng thư mục dự án trong môi trường Codex rồi gửi toàn bộ nội dung khối dưới đây.

## Prompt bắt đầu

```text
Hãy trực tiếp triển khai toàn bộ dự án “Quản lý Hàng không Thông minh” thành ứng dụng hoàn chỉnh có thể cài đặt và sử dụng. Không chỉ phân tích hoặc đề xuất kế hoạch.

TÀI LIỆU PHẢI ĐỌC
1. AGENTS.md ở thư mục gốc và hướng dẫn áp dụng trong các thư mục con.
2. CODEX_MASTER_PROMPT_QL_HANG_KHONG.md, bao gồm toàn bộ phụ lục đặc tả 48 use case, công nghệ/ERD, Unit Test, Acceptance Test và kiến trúc.
3. Các tài liệu gốc trong docs/source nếu có; code, README và lockfiles hiện có.
Đọc tài liệu dài thành từng phần, tránh bỏ nội dung do output bị cắt. Nếu thiếu tài liệu bắt buộc, báo đúng file thiếu; không tự sáng tác đặc tả thay thế.

MỤC TIÊU
- Làm đủ 16 phân hệ M01–M16 và 48 use case UC-01–UC-48, gồm basic/alternative/exception flows, quyền và quy tắc nghiệp vụ.
- Stack bắt buộc: PHP 8.x + Laravel + Blade/Bootstrap/JavaScript + MySQL/InnoDB; kiến trúc MVC + Modular Monolith.
- Có UI thao tác thật, backend, CSDL, authentication/RBAC/data scope, audit, integrations, QR, AI hỗ trợ, IoT mô phỏng, tests và hướng dẫn vận hành.
- Quy mô lớn phải có tính nhất quán liên module, xử lý tranh chấp dữ liệu, cấu trúc dễ bảo trì và đủ nghiệp vụ. Không dừng ở MVP/Release 1 hoặc dashboard tĩnh.

HÀNH ĐỘNG NGAY
1. Kiểm kê repo, git status, runtime và môi trường. Bảo toàn code/thay đổi hiện có. Nếu repo trống, khởi tạo đúng stack; nếu đã có code, tiếp tục và sửa phần thiếu.
2. Tạo docs/traceability.csv đủ 48 UC, docs/permissions-matrix.csv, docs/database-gap-analysis.md, docs/decisions.md và docs/implementation-progress.md.
3. Lập kế hoạch G0–G6 có dependency/gate theo master prompt, sau đó bắt đầu code ngay trong phiên này. G0 không phải điểm dừng bàn giao.
4. Triển khai từng lát cắt hoàn chỉnh: migration/model → validation/policy → service/transaction → route/UI → tests → browser verification → cập nhật truy vết.
5. Tiếp tục qua các giai đoạn trong khả năng phiên. Khi cần phiên tiếp theo, lưu checkpoint rõ ràng để tiếp tục, không tuyên bố toàn bộ đã xong.

YÊU CẦU CHẤT LƯỢNG
- Booking/hold 15 phút không overbooking; payment/refund idempotent; callback muộn/trùng có xử lý; tiền tính server-side.
- Kiểm xung đột tàu bay, cửa, turnaround và tổ bay; maintenance overdue chặn khai thác; phê duyệt có người chịu trách nhiệm và lịch sử.
- QR đúng chuyến/thời hạn, thu hồi/cấp lại, chống quét lặp và race; hành lý có timeline/custody và xử lý sự cố.
- Finance đối soát/chênh lệch/điều chỉnh; ground task có SLA/mốc bắt buộc; CSKH có thông báo/khiếu nại/hỗ trợ.
- UI tiếng Việt, rõ ràng, responsive, đầy đủ search/filter/pagination/form/detail/history/error/empty/loading; mọi nút phải hoạt động và lưu dữ liệu thật.
- Đủ 12 vai trò với quyền và data scope kiểm tra ở backend, không chỉ ẩn menu. Không lộ secret/PII.
- AI và IoT chạy được theo profile đã ghi, có provenance/schema/error handling. Mô phỏng bên ngoài phải ghi nhãn; không dùng số giả thay nghiệp vụ thật.

KIỂM THỬ VÀ NGHIỆM THU
Giữ đủ baseline Unit W1/W2 và UAT W1/W2, mở rộng bao phủ mọi UC. Chạy unit, feature, MySQL integration và browser acceptance. Có tests tranh chấp ghế, payment vs expiry, callback trùng, QR đồng thời, quyền sở hữu và lỗi tích hợp. Test ban đầu Not Run, chỉ Passed khi thực chạy có log/build/time/evidence. Không tự hạ gate hoặc bỏ test để báo thành công. UAT tự động không thay xác nhận người nghiệm thu.

KHỞI CHẠY VÀ BÀN GIAO
Cung cấp source, lockfiles, migrations/seeders, env mẫu, local demo data, scripts, Docker Compose, hướng dẫn Windows/XAMPP, worker/scheduler, API docs, data dictionary/ERD, permission matrix, test reports, user guide và backup/restore runbook. Khởi động ứng dụng thật, mở browser kiểm luồng nghiệp vụ liên module, chạy tests/build và thử restore trên DB riêng. Ghi rõ lệnh đã chạy và kết quả.

LÀM VIỆC CHỦ ĐỘNG
Tự quyết định chi tiết kỹ thuật nhỏ phù hợp tài liệu và ghi decisions, không hỏi lại từng bước đã được yêu cầu. Nếu thiếu credential/dịch vụ, dùng simulator adapter đúng contract cho local, ghi Blocked cho kiểm thử thật chưa làm được và tiếp tục phần không phụ thuộc. Không gửi tin/tiền thật, public deploy, xóa DB hoặc ghi đè công việc người khác khi chưa có cho phép phù hợp.

Không trả lời rằng dự án đã hoàn chỉnh chỉ vì đã có scaffold hoặc đã viết tests. Hãy bắt đầu kiểm kê và triển khai ngay, cập nhật tiến độ bằng code và bằng chứng thực tế.
```

## Prompt tiếp tục khi hết phiên

```text
Tiếp tục dự án theo AGENTS.md và CODEX_MASTER_PROMPT_QL_HANG_KHONG.md. Đọc docs/implementation-progress.md, docs/traceability.csv, docs/decisions.md và test report gần nhất; đối chiếu repo thực tế. Xử lý hạng mục đang dở, sửa tests fail rồi làm các UC chưa Verified theo dependency. Không khởi tạo lại, không bỏ phạm vi 48 UC và không dừng ở báo cáo kế hoạch. Lưu checkpoint mới trước khi kết thúc phiên.
```

## Prompt kiểm tra trước bàn giao

```text
Kiểm tra độc lập phạm vi đã triển khai so với 48 UC, AGENTS.md và master prompt. Không tin trạng thái Done nếu không có code và evidence. Liệt kê mọi UC/flow thiếu, UI giả, route không có policy, validation thiếu, test chưa chạy và integration chỉ mô phỏng. Sửa các lỗi trong phạm vi, chạy lại regression liên quan và full release gate. Cài/chạy từ môi trường sạch riêng theo README, test browser end-to-end, build, worker/scheduler và restore. Xuất release-test-report với Passed/Failed/Blocked/Not Run thật. Không tự ký nghiệm thu hoặc tuyên bố hoàn chỉnh khi gate chưa đạt.
```
