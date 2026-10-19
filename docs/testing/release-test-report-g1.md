# Báo cáo kiểm thử phiên 18/10/2026

**Chưa nghiệm thu toàn bộ dự án.** Phạm vi đích vẫn 16 module/48 UC, gồm Should/Could Have. G0 đã hoàn tất kiểm kê ban đầu; G1 và lát cắt UC-18/19 có mã/UI/DB thật. Các module đích còn thiếu giữ trạng thái Not Implemented/Partial trong traceability.

Môi trường thực: Windows, PHP8.2.12/Laravel12.69.2/PHPUnit11.5.56; MySQL Community8.0.46 InnoDB cô lập cổng3318; Node24.16.0, Vite7.3.7, Bootstrap5.3.8; Chrome headless qua Playwright1.64.1. Không dùng credential DB đang có để reset dữ liệu. Hash build và commit/worktree tại evidence/2026-10-18/build-manifest.json.

| Profile | Kết quả đã chạy | Giới hạn |
| --- | --- | --- |
| SQLite | 59 Passed, 1 Skipped, 368 assertions | Race chỉ chạy MySQL, không suy luận khóa từ SQLite |
| MySQL | 60 Passed, 381 assertions | 60 test không tương đương 48 UC đầy đủ |
| Chrome/MySQL | 3 Passed | UC-45 account/role/self/deny audit; UC-46 filter/export/CSRF; UC-18/19 profile/consent/update/handoff/cancel |
| Source baseline W1/W2 | 18 Passed / 25 Not Run, tổng43 | Có alias cùng scenario; thực tế Feature/Integration được ghi đúng loại, không đổi thành unit giả |
| PHP syntax/Vite | 149 file 0 lỗi; build exit0 | CI cloud chưa chạy; không là static analysis/coverage/load |

Evidence: [JUnit MySQL](evidence/2026-10-18/mysql.xml), [SQLite](evidence/2026-10-18/g1-sqlite.xml), [browser](evidence/2026-10-18/browser.xml), [execution ledger](test-execution-results.csv), [baseline mapping](baseline-mapping.csv), [race](evidence/2026-10-18/booking-race.json). Screenshot PNG chỉ fixture tổng hợp; trace network tắt để không lưu session secret. UAT human approval **Pending**; coverage **Not Measured**.

Hai lỗi quan trọng phát hiện ngoài baseline SQLite: JSON string comparison không tương thích native MySQL JSON; snapshot trước khóa khiến hai booking cùng nhận ghế cuối. Đã có sửa và bằng chứng hồi quy, giữ failure logs cũ. Payment/QR/refund/publish/crew concurrency, late/offline IoT và restore/load chưa chạy; không suy rộng kết quả race booking.

Adapter mới: PasswordResetDelivery → LocalPasswordResetDelivery, mode local-simulation, private mailbox, single-use Laravel broker, không gửi email thật. Payment direct-confirm/scanner hash cũ trả409 cho đến G3 có contract an toàn. Chưa có payment/AI/IoT/ERP/notification/SSO thật hoặc simulator đầy đủ cho các module chưa làm. Wokwi UAT-15 chưa có bằng chứng. Docker Compose, runbook restore đã thử, full OpenAPI và seed đầy đủ chưa hoàn tất.

Release gate giữ nguyên master prompt, hiện **chưa đạt**: chưa đủ48 UC/source flows, baseline còn25 Not Run, coverage/load/restore/Wokwi chưa đo, BOOK-001/002 và QR-001 còn mở. Không hạ gate hoặc ghi production-ready. Xem [implementation-progress.md](../implementation-progress.md) để tiếp tục.
