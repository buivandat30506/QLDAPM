# AGENTS.md — Quy tắc triển khai Quản lý Hàng không Thông minh

## 1. Mục tiêu và phạm vi

Triển khai ứng dụng hoàn chỉnh có thể cài đặt, chạy, thao tác và lưu dữ liệu thật, bao phủ M01–M16 và UC-01–UC-48. Nguồn chi tiết là `CODEX_MASTER_PROMPT_QL_HANG_KHONG.md` cùng phụ lục của nó. File này quy định cách thực hiện, không thay thế đặc tả nghiệp vụ. Không coi scaffold, demo giao diện hoặc Release 1 là sản phẩm cuối.

Đây là phần mềm nghiệp vụ phục vụ dự án, với AI hỗ trợ quyết định và IoT mô phỏng. Không tuyên bố được chứng nhận sử dụng cho hoạt động hàng không thật.

## 2. Khởi đầu mỗi phiên

1. Đọc hướng dẫn đang áp dụng, kiểm tra Git status và cấu trúc repository. Bảo toàn thay đổi của người dùng; không reset, overwrite hoặc khởi tạo lại dự án đang có.
2. Đọc master prompt phần yêu cầu triển khai, toàn bộ đặc tả UC liên quan và baseline Unit/UAT. Lần đầu lập danh mục đủ 48 UC; đọc file dài theo từng đoạn để tránh output bị cắt, ghi phần đã đọc vào progress.
3. Đọc `docs/implementation-progress.md`, `docs/traceability.csv`, `docs/decisions.md`, `docs/testing/defects.md` nếu tồn tại. Đối chiếu với code/test trước khi tin trạng thái cũ.
4. Kiểm kê runtime, lockfile, scripts, database engine, môi trường test. Chỉ chọn phiên bản mới khi chưa có nền tảng; ghi tương thích, khóa dependencies và kiểm tra tài liệu chính thức.
5. Chọn hạng mục chưa hoàn thành có dependency đã sẵn sàng, triển khai ngay sau kiểm kê. Không chỉ trả kế hoạch rồi dừng khi vẫn làm được.

## 3. Stack và kiến trúc bắt buộc

- PHP 8.x + Laravel; MySQL/InnoDB; Blade + Bootstrap + JavaScript.
- Modular Monolith + MVC: Controller → Service/Action → Repository/Eloquent → DB.
- Chính sách quyền ở backend bằng middleware/Policies/Gates; validation bằng Form Request/domain rules.
- Queue/Scheduler xử lý jobs, expiry, notification, integration. Database queue được phép local; không ép dùng hạ tầng phức tạp khi không cần.
- AI adapter/service hỗ trợ và ESP32/Wokwi IoT simulator, không thay thế stack web.
- Không chuyển React/Vue/Next.js, Node backend, PostgreSQL hoặc SQLite thành stack chính. SQLite không được thay MySQL cho test transaction/locking.
- Vite/Node hoặc Playwright được phép phục vụ build asset/QA, không phải backend nghiệp vụ thay Laravel.
- Giữ 20 thực thể ERD nền, mở rộng schema đủ UC. Ghi mapping và lý do bổ sung; không giới hạn dự án ở 20 bảng.

## 4. Quy tắc triển khai từng UC

Mỗi UC phải có:

- Actor và scope truy cập; precondition, trigger, postcondition.
- Luồng chính, thay thế và ngoại lệ nguồn; server validation và thông báo lỗi.
- Migration/constraint/index/model; transaction và kiểm soát race nếu cần.
- Service nghiệp vụ; API/web route; UI thao tác thật; audit cho thay đổi quan trọng.
- Unit/feature/integration phù hợp, UAT và dữ liệu kiểm chứng; traceability đến code/test.

Làm theo lát cắt dọc từ dữ liệu đến UI/test. Không đánh dấu hoàn thành vì có route, model hoặc màn hình. Không để nút không hoạt động, dữ liệu KPI hardcode, trả success giả, TODO nghiệp vụ hoặc mock domain trong bản bàn giao. Mock chỉ dùng ở biên tích hợp và test với nhãn rõ.

Bảng traceability có đủ UC-01…UC-48, actor, BR/NFR, code paths, routes, DB entities, test IDs, trạng thái, evidence, blockers. Trạng thái công việc: NotStarted, InProgress, Implemented, Verified, Blocked. Verified yêu cầu bằng chứng test; “đã viết” không đồng nghĩa “đã chạy”.

## 5. Các bất biến không được vi phạm

1. Không bán quá capacity hoặc cấp một ghế cho nhiều người; giữ chỗ mặc định 15 phút. Khóa DB và test concurrency bằng nhiều kết nối MySQL.
2. Server tính tiền. Payment/refund/callback idempotent, kiểm chữ ký/amount/currency; callback đến sau expiry không tự phát vé khi ghế không còn. Unknown phải đối soát.
3. Check-in/QR/boarding đúng hành khách, chuyến, thời hạn, điều kiện; consume token nguyên tử; cấp lại vô hiệu token cũ. Không chứa PII rõ trong QR.
4. Publish lịch, phân công tàu/tổ bay/cửa phải kiểm tra overlap, turnaround, khả dụng và điều kiện lại tại commit/approval, không chỉ khi mở form.
5. Tàu quá hạn bảo dưỡng không khả dụng; thiếu xác nhận kỹ thuật không release. Event hoàn thành chuyến gửi lặp không cộng usage hai lần.
6. Mỗi vai trò có quyền và phạm vi; hành khách chỉ xem dữ liệu của mình. Ẩn menu không thay kiểm quyền API/backend. Ủy quyền hết hạn phải bị từ chối.
7. Tiền dùng decimal/minor units, thời gian lưu UTC và chuyển timezone rõ. DB FK/unique/check bảo vệ dữ liệu cùng validation.
8. Audit có actor/action/time/object/outcome và thay đổi đã che secret. Không sửa/xóa lịch sử nghiệp vụ để làm số liệu khớp.
9. IoT có event ID, source time, received time, sequence; xử lý duplicate/late/out-of-order/offline, không cho event cũ ghi đè latest.
10. AI có version/provenance/schema/quality; người có quyền quyết định; không tự phê duyệt an toàn/tài chính. Rule-based/simulation phải ghi đúng nhãn.
11. External I/O ngoài transaction dài; outbox + retries + dedupe; failure có thể quan sát và phục hồi.

## 6. Giao diện sử dụng được

Passenger, Airport Staff, Operations, Admin có navigation theo role; danh sách/search/filter/sort/pagination, form/detail/history và hành động theo trạng thái. Tiếng Việt có dấu, layout Bootstrap nhất quán, responsive và trạng thái loading/empty/error/success. Seat map, QR, lịch, dashboard lấy dữ liệu DB. Không làm trang rỗng để đủ số module.

Kiểm tra browser thật các hành trình chính và quyền truy cập, không chỉ kiểm HTTP 200. Với camera không có sẵn, có cách nhập token phục vụ local test và nêu rõ chưa kiểm camera.

## 7. Test và bằng chứng

- Giữ đủ 4 case Unit W1, 20 case Unit W2; 4 case UAT W1 và 15 case UAT W2, namespace theo tuần để tránh đè ID.
- Mở rộng đến mọi UC, đặc biệt tài chính UC-34–36, CSKH UC-37–39, quản trị UC-46–48 và mọi alternative/exception nguồn.
- Unit kiểm logic; feature kiểm HTTP/auth/validation; integration kiểm MySQL/queue/adapters; browser UAT kiểm luồng người dùng. Không gọi tất cả là unit test.
- Bắt buộc test tranh chấp ghế, payment vs expiry, callback trùng, quét QR đồng thời, lịch/crew overlap, refund vượt hạn và ownership.
- Tạo test plan, cases, execution report, JUnit/coverage và evidence theo master prompt. Ghi commit/build, command, environment, time, actual result, status, bug ID.
- Mặc định Not Run; Passed chỉ khi đã chạy và đạt. Blocked/Skipped phải có lý do. Không bỏ test, giảm assertion, hạ gate hoặc sửa expected trái đặc tả để tạo kết quả xanh.
- Dùng fake clock cho expiry/thời gian; fixture deterministic; DB test độc lập. Không migrate:fresh trên DB người dùng.
- Kiểm thử có phạm vi theo thay đổi; chạy regression đầy đủ ở gate tích hợp/release. Sửa root cause rồi chạy lại ca lỗi và ca ảnh hưởng.
- UAT tự động không thay chữ ký người nghiệm thu. Không bịa ngày, tester hoặc kết quả chạy Wokwi.

## 8. Tích hợp và chế độ chạy

Local phải chạy được không cần tài khoản trả phí: payment simulator, notification mail catcher/delivery simulator, external adapter fixtures. Có các chế độ local-simulation/sandbox/live rõ ràng. Mô phỏng chỉ thay hệ thống bên ngoài; booking, tiền, inventory, QR, workflow nội bộ vẫn xử lý thật.

Bàn giao AI/IoT configs, Wokwi sketch/diagram/libraries, replay scenarios và hướng dẫn kết nối. Replay CLI không được nhận là bằng chứng Wokwi. Nếu thiếu part Wokwi hoặc credential thì ghi giới hạn cụ thể, giữ contract và hoàn thành phần còn lại.

Không gửi email/SMS thật, thanh toán thật, public deploy hoặc thay đổi dữ liệu production nếu chưa có cho phép cụ thể. Không đưa secret vào Git/log hoặc yêu cầu người dùng dán secret vào báo cáo.

## 9. Tiến độ và quyền tự chủ

Chủ động thực hiện công việc local, sửa lỗi, viết tests và docs trong phạm vi đã giao; không hỏi lại lựa chọn kỹ thuật nhỏ đã có nguyên tắc. Ghi giả định nhỏ vào decisions. Với quyết định làm thay đổi nghiệp vụ quan trọng hoặc cần quyền truy cập không có, báo rõ blocker; tiếp tục phần độc lập.

Không dừng sau G0/G1 và gọi là hoàn thành toàn dự án. Khi giới hạn phiên, lưu checkpoint trung thực gồm:

- Đã hoàn thành và verified theo UC.
- File/thay đổi đang dở, test chạy gần nhất.
- Dependency/blocker và quyết định còn thiếu.
- Danh sách việc tiếp theo theo ưu tiên, command tái lập.

Dùng `docs/implementation-progress.md` làm nhật ký, không dựa vào trí nhớ hội thoại. Không cần tạo commit nếu môi trường không cho phép; ghi hash hiện tại và dirty status khi phù hợp. Không push/merge/deploy chỉ vì đã viết xong code.

## 10. Các giai đoạn

G0: kiểm kê + truy vết + thiết kế/gap analysis.
G1: runtime + auth/RBAC/audit + DB + CI.
G2: M01/M02/M04/M06, nền M03 và kiểm soát bảo dưỡng M05.
G3: M07–M10 end-to-end.
G4: hoàn thiện M03/M05/M11/M12/M13.
G5: M14/M15 và phần còn lại M16.
G6: regression, performance, restore, UI QA và bàn giao.

Có thể thay thứ tự khi dependency đòi hỏi nhưng không loại scope. Ưu tiên giai đoạn không đồng nghĩa UC Should/Could Have được bỏ.

## 11. Sản phẩm bàn giao

- Source code đầy đủ, lockfiles, migrations/factories/seeders, `.env.example` không secret.
- README cài/chạy Windows/XAMPP và Docker; xác minh MySQL thực tế, versions/extensions/ports.
- Compose hoặc scripts chạy web, DB, worker, scheduler và profile tích hợp; health/readiness.
- Tài khoản demo local và cách lấy mật khẩu an toàn; sample data tái lập.
- OpenAPI, web routes, ERD, data dictionary, permission matrix, decisions, traceability.
- Unit/UAT plans, results, evidence, defects; user guides theo role, backup/restore/rollback runbooks.
- Bằng chứng browser end-to-end, build, tests, khởi động từ DB sạch riêng và restore thử.

## 12. Gate hoàn thành

Ứng dụng chỉ hoàn thành khi đủ 48 UC đã truy vết và xác minh; baseline và tests mở rộng đạt; không còn lỗi critical/high; đúng stack; cài/chạy theo README thành công; không còn nghiệp vụ giả; môi trường mô phỏng và giới hạn được công khai. Gate coverage theo master prompt. Nếu thiếu bằng chứng bắt buộc, báo chưa nghiệm thu thay vì tuyên bố hoàn chỉnh.

Báo cáo cuối ngắn gọn: phạm vi hoàn thành, cách chạy, tests thực chạy, bằng chứng, mô phỏng/tích hợp thật, phần còn thiếu. Không dùng số dòng code/số file làm bằng chứng chất lượng.
