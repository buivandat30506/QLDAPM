# CODEX MASTER PROMPT — HỆ THỐNG QUẢN LÝ HÀNG KHÔNG THÔNG MINH

> Tài liệu giao việc triển khai, không phải báo cáo sản phẩm đã hoàn thành. Ngôn ngữ giao diện/tài liệu: tiếng Việt có dấu. Code và định danh: tiếng Anh nhất quán. Phạm vi đích: 16 phân hệ, 48 use case, Unit Test và Acceptance Test/UAT.

## 1. Cách sử dụng

Đặt file này vào thư mục gốc repository. Có thể bổ sung 6 DOCX nguồn vào `docs/source/` để đối chiếu sơ đồ; không sửa các tài liệu gốc. Phụ lục A của file này chứa nội dung đặc tả dạng văn bản của đủ 48 UC, nên không được bỏ qua phụ lục khi không có DOCX.

Gửi Codex câu lệnh sau:

```text
Đọc toàn bộ CODEX_MASTER_PROMPT_QL_HANG_KHONG.md, bao gồm các phụ lục. Kiểm tra repository hiện có và AGENTS.md trước khi thay đổi. Thực hiện dự án theo tài liệu, đúng Laravel + Blade/Bootstrap/JavaScript + MySQL, đủ 48 use case. Bắt đầu kiểm kê, tạo ma trận truy vết, ghi các giả định rồi triển khai từng giai đoạn có kiểm thử. Không chỉ trả kế hoạch, không dựng giao diện giả và không bỏ các UC Should/Could Have khỏi phạm vi đích. Bảo toàn code đang có. Ghi tiến độ vào docs/implementation-progress.md để có thể tiếp tục qua nhiều phiên. Chỉ báo Passed cho test đã chạy có bằng chứng. Nếu thiếu dịch vụ ngoài, dùng adapter mô phỏng rõ ràng có contract và hướng dẫn chuyển sang cấu hình thật. Hãy bắt đầu thực hiện ngay các phần không bị chặn.
```

Khi tiếp tục phiên mới:

```text
Đọc lại master prompt, docs/implementation-progress.md, docs/traceability.csv và docs/decisions.md. Đối chiếu code/test thực tế với tiến độ, tiếp tục hạng mục chưa hoàn thành theo thứ tự phụ thuộc. Không tạo lại dự án hoặc bỏ các module đã làm. Kết thúc phiên bằng tiến độ và bằng chứng kiểm thử cập nhật.
```

## 2. Vai trò, mục tiêu và nguyên tắc thực thi

Bạn là kỹ sư triển khai chịu trách nhiệm từ kiến trúc, CSDL, backend, frontend đến kiểm thử, cấu hình chạy và bàn giao. Xây dựng ứng dụng nghiệp vụ có thể cài đặt, thao tác, lưu dữ liệu thật vào MySQL và tái chạy kiểm thử; quy mô lớn thể hiện bằng phạm vi đầy đủ, cấu trúc module và tính nhất quán, không bằng số lượng file hoặc chia microservices không cần thiết.

- Không kết thúc ở scaffold, dashboard tĩnh, mock CRUD, nút không có xử lý hoặc TODO nghiệp vụ.
- Mỗi UC phải có luồng chính, luồng thay thế, ngoại lệ đã nêu; quyền truy cập; dữ liệu; kiểm thử và đường dẫn bằng chứng.
- Các UC ưu tiên thấp được làm sau, không tự loại khỏi sản phẩm đích. Giữ nhãn ưu tiên gốc trong truy vết.
- Không tuyên bố ứng dụng đủ điều kiện khai thác hàng không thực tế. Đây là ứng dụng nghiệp vụ triển khai được với AI hỗ trợ và IoT mô phỏng; tích hợp và quy trình vận hành thật cần xác minh riêng.
- Không xóa hoặc reset database có dữ liệu người dùng. Migration phá hủy cần phương án chuyển đổi và backup. Seed demo chỉ chạy môi trường local/test hoặc khi được cho phép rõ ràng.
- Khi vướng credentials, thiết bị, mạng hoặc quyết định nghiệp vụ, ghi Blocked cụ thể, hoàn thành phần độc lập còn lại; không giả lập kết quả thành công của hệ thống thật.

## 3. Nguồn chuẩn và giải quyết khác biệt

| Nguồn | Vai trò |
| --- | --- |
| UseCase_va_DacTa (2).docx | Phạm vi nghiệp vụ, actor, 48 UC, BR/NFR được tham chiếu |
| Đề xuất công nghệ - ERD.docx | Stack công nghệ bắt buộc, 20 thực thể nền, giữ chỗ 15 phút |
| Bao_cao_kien_truc_tong_the_QL_Hang_Khong_Thong_Minh (1).docx | Phân lớp MVC + Modular Monolith, ranh giới tích hợp, AI/IoT và phụ lục Release 1 |
| Bao_cao_Activity_Sequence_6_module_dau (1).docx | Thứ tự tương tác và các bước kiểm tra M01–M06 |
| Unit_Test_Plan_Template.docx | Baseline Unit Test và mẫu ghi kết quả thực thi |
| Acceptance_Test_Plan_Template.docx | Baseline UAT và mẫu bằng chứng nghiệm thu |

Quyết định áp dụng:

1. Dùng PHP 8.x + Laravel, MySQL, Blade + Bootstrap + JavaScript. React/Vue/Angular và PostgreSQL trong phần kiến trúc tổng quát chỉ là lựa chọn tham khảo, không thay thế stack đã chốt.
2. “Module 1” trong kế hoạch Release 1 không đồng nghĩa M01. Phạm vi yêu cầu lần này là toàn bộ M01–M16, bao gồm M05 bảo dưỡng, không dừng ở Release 1.
3. ERD 20 thực thể là baseline, không phải giới hạn số bảng. Bổ sung bảng cần thiết cho mọi UC, nêu lý do và quan hệ trong `docs/database-gap-analysis.md`.
4. Các mẫu test chứa baseline tuần 1 và phần bổ sung tuần 2. Giữ nguồn/phiên bản bằng namespace để tránh trùng ID; không ghi đè UAT-02 tuần 1 bằng UAT-02 tuần 2.
5. DOCX UAT tuần 1 có các ô bị lệch/thiếu: chuẩn hóa cột Input/Expected/Status dựa trên ý nghĩa, ghi rõ đây là diễn giải kiểm thử, không tự nhận là nguyên văn.
6. Nhiều BR/NFR chỉ được tham chiếu mã, chưa có đầy đủ danh mục định nghĩa và ngưỡng. Không bịa nguyên văn BR-xxx/NFR-xxx. Lập `docs/rules-register.md` với mã, UC nguồn, nội dung biết được, phần thiếu, giả định cấu hình và test. Các ngưỡng bổ sung trong prompt là đề xuất kỹ thuật, không phải trích từ tài liệu.
7. Một số Exception Flow để trống không có nghĩa được bỏ validation/auth/error handling. Gắn các kiểm thử bổ sung nhãn `DERIVED`.
8. Giữ cả trường mô tả và quan hệ bị giản lược trong hình ERD: FlightSchedule, country, passenger_id, thời gian thực tế, người ký kỹ thuật… Chuẩn hóa tên trường bằng data dictionary; không tạo hai trường đồng nghĩa chỉ vì tên khác ở sơ đồ và phần văn bản.

## 4. Stack và cấu trúc triển khai

### 4.1 Stack bắt buộc

- PHP 8.x tương thích với Laravel được chọn; dùng Composer, Eloquent, Migration, Seeder, Form Request, Gates/Policies, Queue, Scheduler.
- Blade layout/component, Bootstrap, JavaScript; Chart.js hoặc thư viện tương đương được kiểm tra tương thích; QR generator và scanner. Vite nếu dùng chỉ đóng gói asset, không biến dự án thành SPA khác stack.
- MySQL/InnoDB, utf8mb4; môi trường dev ưu tiên Windows/XAMPP theo nguồn, có thêm Docker Compose tái lập. Xác minh engine thực tế vì gói local có thể cung cấp MariaDB; không ghi MySQL nếu chạy engine khác.
- Unit Test: PHPUnit hoặc Pest tương thích Laravel. Feature/integration chạy MySQL riêng. UAT browser có thể dùng Playwright như công cụ QA, không phải stack ứng dụng thay thế.
- AI có adapter; service Python riêng là lựa chọn được kiến trúc cho phép. Nếu chọn phải khóa dependency và có profile chạy. IoT dùng ESP32/Wokwi, DHT22/BMP280/Rain Sensor theo baseline test; MQTT/HTTP qua adapter.
- Không bắt buộc Redis để khởi động: database queue/cache được phép cho local. Nếu chọn Redis ở profile tải cao, phải khai báo và kiểm thử rõ.
- Đọc lockfile/runtime trước, kiểm tra tài liệu chính thức khi cài dependencies và chốt phiên bản vào lockfile/README. Không dùng nhãn “latest” không khóa phiên bản. Không nâng major một repo đang chạy mà chưa đánh giá tương thích.

### 4.2 Cấu trúc đề xuất

```text
app/
  Modules/
    Foundation/ FlightPlanning/ FlightOperations/ Fleet/
    Maintenance/ Crew/ Passenger/ Booking/ Checkin/
    Baggage/ GroundService/ Finance/ CustomerCare/
    Analytics/ IoT/ Administration/
  Shared/{Contracts,Exceptions,Security,Audit,Clock,Money}/
  Integrations/{Payment,Airport,Baggage,MRO,Crew,ERP,Notification,Identity,AI,IoT}/
resources/views/{layouts,components,passenger,staff,operations,admin}/
resources/{js,css}/
routes/{web.php,api.php,console.php}/
database/{migrations,factories,seeders}/
tests/{Unit,Feature,Integration,Acceptance,Fixtures}/
iot-simulator/{wokwi,scenarios}/
ai-service/
docker/
docs/{source,adr,testing,runbooks}/
```

Trong mỗi module: Controllers, Requests, Services/Actions, Repositories, Models, DTOs, Policies, Jobs, Events, Listeners, Enums theo nhu cầu thực. Không tạo lớp rỗng chỉ để đủ cây thư mục.

Luồng chuẩn: Request → Auth/Policy/FormRequest → Controller → Service → Repository/Eloquent → MySQL. Controller mỏng; nghiệp vụ không nằm ở Blade/JavaScript. Service điều phối liên module qua contract rõ ràng; không gọi chéo Controller. Migrations/FK thống nhất trên CSDL tập trung. External I/O không nằm trong transaction giữ khóa dài.

## 5. Ma trận phân hệ và sản phẩm phải có

| Module | UC | Chức năng triển khai bắt buộc | Điểm nghiệm thu quan trọng |
| --- | --- | --- | --- |
| M01 Foundation | 01–03 | Sân bay, tài nguyên, nhập/xuất dữ liệu chủ, kiểm tra chất lượng, duyệt/phiên bản | IATA/ICAO duy nhất; timezone; import lỗi không công bố |
| M02 FlightPlanning | 04–06 | Route, mùa lịch/lặp, nháp/duyệt/công bố, kiểm tra aircraft/gate/turnaround | Không công bố khi còn xung đột hoặc thiếu dữ liệu kiểm tra |
| M03 FlightOperations | 07–09 | Tạo chuyến từ lịch, sự kiện, mốc dự kiến/thực tế, cảnh báo, xử lý gián đoạn | Đổi tàu/cửa/chuyến kiểm tra lại nguồn lực và ghi lý do |
| M04 Fleet | 10–11 | Tàu bay, dòng/cấu hình ghế, khả dụng, giờ bay, chu kỳ | Nhận lặp sự kiện không cộng giờ/chu kỳ hai lần |
| M05 Maintenance | 12–14 | Kế hoạch theo ngày/giờ/chu kỳ, work order, vật tư, bằng chứng, release | Quá hạn chặn khai thác; thiếu ký xác nhận không đóng lệnh |
| M06 Crew | 15–17 | Hồ sơ, năng định, giấy phép, đào tạo, nghỉ/dự bị, phân công/duyệt/thay thế | Kiểm tra bằng cấp, lịch, thời gian nhiệm vụ/nghỉ cả lúc duyệt |
| M07 Passenger | 18–19 | Hồ sơ, consent, liên hệ, yêu cầu hỗ trợ và chuyển bộ phận | Chỉ truy cập hồ sơ thuộc phạm vi được cấp |
| M08 Booking | 20–23 | Tìm hành trình, giá/tồn chỗ, giữ chỗ, dịch vụ, thanh toán/vé, đổi/hủy/hoàn | Khóa tồn chỗ; hold 15 phút; callback/refund idempotent |
| M09 Checkin | 24–27 | Điều kiện check-in, seat map, hành lý ký gửi, QR cấp/thu hồi/cấp lại, boarding/no-show/offload | Ghế duy nhất; QR đúng chuyến/thời hạn; chống quét lặp |
| M10 Baggage | 28–30 | Tag/chặng, sự kiện/custody, tra cứu, hồ sơ thất lạc/chậm/hỏng/sai chuyến, giao trả | Timeline có nguồn/thời gian; sự kiện trễ không làm lùi trạng thái |
| M11 GroundService | 31–33 | Task, đơn vị, SLA, bắt đầu/kết thúc, bằng chứng, ngoại lệ, turnaround | Không đóng chuyến còn mốc bắt buộc nếu chưa có ngoại lệ được duyệt |
| M12 Finance | 34–36 | Thu/phí/thuế/giảm giá/hoàn, đối soát, chênh lệch, điều chỉnh duyệt, báo cáo/ERP | Tổng tiền chính xác, có nguồn giao dịch; không ghi sổ trùng |
| M13 CustomerCare | 37–39 | Thông báo đa kênh/fallback, khiếu nại/bồi thường, lịch sử tương tác | Trạng thái gửi thật của adapter; che thông tin nhạy cảm |
| M14 Analytics | 40–42 | KPI theo role/filter, dự báo/khuyến nghị, giải thích/model version/giám sát/rollback | KPI tính DB, AI có metadata, không tự phê duyệt |
| M15 IoT | 43–44 | Thiết bị, kịch bản bình thường/bất thường, ingest, dedupe, trễ/sai thứ tự/offline | Wokwi → adapter → DB → cảnh báo/dashboard có bằng chứng |
| M16 Administration | 45–48 | User/role/permission/scope/delegation, audit, tích hợp, cấu hình/cảnh báo hiệu lực | Default deny; ủy quyền hết hạn; secret được bảo vệ; audit truy vết |

Không đồng nhất “có một màn hình module” với “đã hoàn thành mọi UC của module”. Phụ lục A là acceptance contract chi tiết của từng UC.

## 6. Actor, RBAC và bảo mật

Seed 12 vai trò đúng nguồn: Quản trị hệ thống, Quản trị dữ liệu, Điều hành khai thác, Quản lý đội bay, Kỹ thuật bảo dưỡng, Điều hành tổ bay, Nhân viên sân bay, Dịch vụ mặt đất, Bán vé và chăm sóc khách hàng, Tài chính kế toán, An toàn và an ninh, Hành khách. External actor (Payment Gateway, BHS…) là service identity riêng, không tài khoản nhân viên.

- Tạo `docs/permissions-matrix.csv`: role, UC, action, read/write/approve/export, data_scope. Mặc định deny; mỗi người có thể nhiều role. Quản trị tài khoản không tự động được ký release hoặc duyệt tài chính.
- Scope theo sân bay, đơn vị, chuyến được giao, hồ sơ của mình; kiểm tra server-side cả tải file/export/API. Hành khách chỉ tạo/xem yêu cầu của mình, không được thực hiện tác vụ nhân viên chỉ vì cùng tham gia UC.
- Login/logout, đổi mật khẩu, reset bằng kênh local kiểm chứng; hash password chuẩn Laravel; session regeneration, CSRF, throttling. Token riêng cho ingest/API, có khả năng revoke; scope không dùng role client gửi lên.
- Temporary delegation có người cấp, người nhận, phạm vi, bắt đầu/kết thúc; kiểm tra hiệu lực mỗi lần gọi; audit cấp/thu hồi.
- Mask dữ liệu cá nhân ở bảng, log, export theo quyền. Không đưa passport, password, secret, raw QR vào log. Secret không vào Git, `.env.example` chỉ placeholder.
- Upload giới hạn loại/kích thước, tên do server sinh, lưu private và download qua policy. Chặn CSV formula injection ở export; validate import theo từng dòng.
- Audit append-only ở tầng ứng dụng: actor/service, action, object, UTC timestamp, request/correlation ID, outcome, before/after đã loại secret, reason/approver khi có. Không cung cấp API sửa/xóa audit cho vai trò thường.

## 7. CSDL, giao dịch và tính toàn vẹn

Giữ 20 thực thể nền và thuộc tính gốc ở phụ lục B. Bổ sung tối thiểu các nhóm dưới đây; tên bảng là đề xuất triển khai, không phải ERD nguyên văn:

| Nhóm | Bảng/quan hệ bổ sung |
| --- | --- |
| Nền/lịch | airport_resources, resource_allocations, master_data_versions, import_batches/import_rows, schedule_versions/approvals, flight_events, disruption_cases/decisions |
| Đội bay/tổ bay | aircraft_seats/configuration_versions, aircraft_usage_events, crew_qualifications/licenses/training, crew_availability, crew_assignment_versions/approvals/acknowledgements |
| Bảo dưỡng | maintenance_plans/due_items, work_order_tasks/materials/evidence, technical_release_history |
| Booking | fare_products/rules, flight_seat_inventory, holds/hold_items, tickets, ancillary_services, payments/payment_events, refunds, booking_changes |
| Hành khách | consents, assistance_requests; liên kết khách đặt với nhiều hành khách, không ép mọi passenger có account |
| Check-in/hành lý | boarding_events, token_issue_history, baggage_segments, baggage_incidents/return_records |
| Mặt đất/tài chính | ground_units/tasks/evidence/exceptions, financial_entries, reconciliation_batches/items/discrepancies, adjustment_approvals |
| CSKH/AI/IoT | notification_deliveries/templates, complaints/compensation_requests, interactions, model_versions/predictions/evaluations, devices/telemetry/rejected_events/alerts |
| Quản trị | permissions/role_permissions, data_scopes/delegations, audit_logs, integration_configs/deliveries, outbox_events, idempotency_keys, configuration_versions |

Data dictionary phải có kiểu, null/default, PK/FK/unique/index, ý nghĩa, UC nguồn và quy tắc xóa. Dùng UUID phù hợp nguồn, không ép mọi bảng cùng kiểu nếu không cần. Money dùng DECIMAL hoặc minor unit + currency, tuyệt đối không float. Trọng lượng/giờ bay dùng decimal đủ độ chính xác. Tất cả thời gian nghiệp vụ lưu UTC; giữ timezone IANA của sân bay và giờ nguồn khi ingest; chuyển giờ hiển thị đúng địa phương.

### 7.1 Ràng buộc quan trọng

- Unique IATA/ICAO, tail_number, crew_code, order_code, ticket_number, tag_code; FK bảo vệ quan hệ. Route origin khác destination.
- Aircraft/crew/resource scheduling kiểm tra interval overlap và turnaround. Dùng khóa trên bản ghi tài nguyên để serialize kiểm tra + lưu; truy vấn “không thấy trùng rồi insert” không đủ chống race.
- Inventory unique `(flight_id, seat_number)`; check-in unique `(flight_id, passenger_id)` và ghế được gán một lần. Với giữ chỗ theo hạng thay vì ghế, vẫn phải có counter khóa và bound không âm/không vượt capacity.
- Tạo booking nhiều chặng: lock theo thứ tự ổn định, validate toàn bộ, commit nguyên khối hoặc rollback. Server tính giá, phí, thuế, dịch vụ; không tin `total` từ browser.
- Hold có `expires_at`, mặc định 15 phút; job giải phóng idempotent, thời điểm hết hạn kiểm tra ngay cả khi scheduler trễ. Payment và expiry tranh chấp phải khóa cùng booking/inventory và có test.
- Booking đã hết hạn nhận payment thành công muộn: không bán ghế đã cấp người khác; ghi payment vào đối soát/hoàn hoặc xử lý theo chính sách được ghi nhận. Không bỏ callback hoặc tự phát vé sai tồn chỗ.
- Callback validate chữ ký, merchant, order, amount, currency; unique provider transaction/event ID. Retry không ghi tiền/phát vé hai lần. Unknown/pending không coi là success.
- Refund không vượt số còn được hoàn; chống refund trùng và refund đồng thời. Bút toán correction tham chiếu bản gốc, không sửa mất dấu lịch sử.
- Flight completion update usage một lần theo event ID; trạng thái aircraft unavailable khi overdue; chỉ release hợp lệ mới khôi phục, vẫn phải kiểm tra các lệnh chặn khác.
- Boarding consume token nguyên tử. Cấp lại revoke token cũ và tăng phiên bản; chỉ một token active trên check-in, có lịch sử. QR là opaque token ngẫu nhiên hoặc signed token, DB lưu hash; không chứa PII dạng rõ. Nếu chọn opaque token, ghi ADR thay cho yêu cầu kiểm chữ ký token, vẫn bảo đảm xác thực bằng hash an toàn.
- WorkOrder → TechnicalRelease tại thời điểm chưa ký là 1 → 0..1 bản hiện hành; lưu các lần ký/thu hồi trong history. Không tạo chứng chỉ giả để ép quan hệ 1:1 trước khi hoàn tất.
- Outbox ghi trong cùng transaction nghiệp vụ; worker gửi ngoài transaction, retry backoff, giới hạn lần, dead-letter/failed_jobs, thao tác retry có audit.

### 7.2 Vòng đời đề xuất cần chốt trong ADR

| Đối tượng | Trạng thái/điều kiện |
| --- | --- |
| Lịch | Draft → PendingApproval → Approved → Published; thay đổi tạo version, revalidate trước publish |
| Chuyến | Scheduled → CheckinOpen → Boarding → Departed → Arrived → Closed; Cancelled chỉ khi hợp lệ; delay là thuộc tính/sự kiện riêng |
| Booking | Held → PendingPayment → Confirmed; Held/PendingPayment → Expired hoặc Cancelled theo rule; đổi/hoàn có workflow riêng |
| Payment | Pending → Succeeded/Failed/Unknown; đối soát giải quyết Unknown, không cập nhật ngược do event cũ |
| Maintenance | Planned → InProgress → AwaitingSignoff → Completed; technical release là quyết định riêng |
| GroundTask | Assigned → InProgress → Completed; Blocked/Exception cần reason và phê duyệt nếu miễn mốc bắt buộc |
| Complaint | Open → Assigned → InProgress → Resolved → Closed; bồi thường có approval riêng |

Lập bảng permitted transitions, actor, precondition, side effects, audit và tests. Các trạng thái trên là thiết kế đề xuất, không coi là danh mục nguyên văn nguồn.

## 8. UI, API và nghiệp vụ xuyên suốt

- Bốn vùng giao diện: Passenger Portal; Airport Staff; Operations; Administration. Menu theo quyền, nhưng policy server mới là kiểm soát thực.
- Màn hình danh sách có search/filter/sort/pagination; biểu mẫu có validation; detail có ID/tên đối tượng rõ, lịch sử và hành động theo trạng thái. Loading/empty/error/success đều có phản hồi.
- Sidebar, breadcrumb, badge trạng thái, thông báo thao tác, xác nhận tác vụ nhạy cảm; responsive tablet/desktop; label bàn phím/accessible contrast; giao diện tiếng Việt thống nhất.
- Lịch bay và tài nguyên có bảng/timeline; seat map phản ánh dữ liệu thật; QR hiển thị/in được, scanner có camera và phương án nhập token khi không có camera; dashboard không hardcode KPI.
- Hành khách tự xem đơn, vé, check-in, hành lý, hỗ trợ/khiếu nại. Nhân viên thao tác trong phạm vi đơn vị/chuyến. Nhập tiền hoặc approver không được bỏ qua xác nhận backend.
- REST `/api/v1` cho tích hợp/async, web routes cho Blade; không cần biến mọi render Blade thành API. Dùng tên miền nghiệp vụ nhất quán.
- Tạo OpenAPI cho mọi API và `docs/web-routes.md` cho UI: auth/scope, params/schema, success/error, paging/filter, idempotency và examples.
- JSON lỗi gồm code, message, field errors, correlation ID; phân biệt 401/403/404/409/422/429/5xx. Không trả stack trace cho client.
- Các command nghiệp vụ phải rõ: schedule submit/approve/publish/check-conflicts; crew approve/replace; booking hold/pay/change/refund; checkin/boarding; work-order signoff/release; reconciliation/approve-adjustment. Không dùng generic CRUD để vượt workflow.

## 9. AI, IoT và hệ thống bên ngoài

### 9.1 AI thực thi được và trung thực

Tối thiểu có dự báo chậm chuyến và phát hiện telemetry bất thường để bao phủ UT-AI-01/02; UC-41 triển khai lựa chọn các bài toán và ghi phạm vi từng model: delay, demand, maintenance, resource recommendation, anomalies theo dữ liệu sẵn có. Các bài toán chưa có dữ liệu thật có baseline mô phỏng chạy được, gắn nhãn rõ; không trả số ngẫu nhiên rồi gọi là mô hình huấn luyện.

- Registry: model/task/version/status/approved_by/approved_at/artifact checksum/feature schema/data provenance/metrics. Chỉ model approved được phục vụ dự báo chính thức.
- Input delay: flightId, scheduledDepartureUtc, weather features, traffic features, rotation features, feature timestamps. Validate missing/stale/invalid inputs.
- Output: predictionId, task, modelVersion, predictedAt, probabilityDelay [0..1], expectedDelayMinutes >=0, confidence hoặc thông tin không có confidence, factors/explanation, dataQuality, mode `trained`/`simulation`/`rule_based`.
- Anomaly: temperature/humidity/pressure/rain, timestamp; output anomalyFlag, score có định nghĩa miền, reasons, modelVersion, thời điểm. Không biến cảnh báo thành lệnh điều khiển thiết bị.
- Nếu huấn luyện: dữ liệu synthetic phải ghi rõ; tách train/validation/test theo thời gian, tránh leakage, so baseline, lưu seed/metrics. Nếu chưa có ground truth thì drift/quality hiển thị “chưa đủ dữ liệu”, không bịa metric.
- Timeout hoặc model hỏng: dịch vụ cốt lõi vẫn hoạt động; UI báo unavailable/fallback và provenance. Human-in-the-loop: nhân viên accept/reject recommendation có lý do, không tự đổi lịch/giải phóng tàu/hoàn tiền.

### 9.2 IoT mô phỏng

Bàn giao `sketch.ino`, `diagram.json`, danh sách thư viện, sơ đồ pin và README Wokwi. Xác minh khả năng hỗ trợ cảm biến/part trong Wokwi khi triển khai; nếu thiếu BMP280/rain part thì dùng nguồn giá trị mô phỏng có nhãn rõ và giữ schema, ghi limitation, không tuyên bố đã đo cảm biến không tồn tại.

Payload contract đề xuất:

```json
{
  "schemaVersion": 1,
  "eventId": "unique-event-id",
  "deviceId": "SIM-ESP32-01",
  "eventType": "environment.telemetry",
  "eventTime": "2026-10-18T00:00:00Z",
  "sequence": 42,
  "value": {"temperatureC": 28.5, "humidityPct": 65, "pressureHpa": 1010.2, "rainRaw": 120},
  "simulated": true
}
```

`receivedTime` do server gán; `eventTime` giữ thời gian nguồn. Định nghĩa đơn vị và phạm vi rainRaw trong schema, không tự gọi raw là mm mưa. Authentication device, rate-limit, schema validation; dedupe unique device/event; lưu late events nhưng không ghi đè latest bằng event cũ. sequence reset cần boot/session ID hoặc policy rõ. Có rejected events, offline watchdog, threshold config/hysteresis và alert acknowledge.

Scenario tái lập: normal, vượt ngưỡng, thiếu field, out-of-range, duplicate, late, out-of-order, disconnect/reconnect, burst, restart sequence. CLI replay giúp chạy CI nhưng không thay bằng chứng Wokwi trong UAT-15. Wokwi/cloud broker không truy cập được thì ghi Blocked và hướng dẫn tunnel/broker cấu hình; không hardcode localhost như địa chỉ truy cập từ Wokwi.

### 9.3 Adapter tích hợp

Payment, notification, airport events, baggage, MRO, HRM, ERP, identity có interface, mapping, timeout/retry/idempotency và contract test. Local simulator cho phép chạy độc lập không credential trả phí. Email có mail catcher; SMS/push mô phỏng có lịch sử delivery và failure/fallback. ERP có xuất file đúng schema. SSO là adapter khi chưa có IdP, không nhận đã đồng bộ thật.

Phân biệt `local-simulation`, `sandbox`, `live` trong UI/config/docs. Callback simulator không được tắt kiểm chữ ký ở đường chạy live. Việc gửi tin ra ngoài, giao dịch tiền thật và public deploy chỉ thực hiện khi người dùng cho phép cụ thể.

## 10. Kiểm thử: đáp ứng cả hai mẫu nguồn

“Đủ 2 test” được hiểu là đủ hai loại tài liệu Unit Test và Acceptance Test/UAT, không phải chỉ viết hai test case. Bao phủ baseline tuần 1 + tuần 2 và mở rộng đến đủ 48 UC.

### 10.1 Quy ước và ma trận truy vết

Tạo `docs/traceability.csv` với cột:

```text
module_id,uc_id,source_priority,actor,br_refs,nfr_refs,implementation_paths,ui_route,api_operation,db_entities,unit_test_ids,integration_test_ids,uat_test_ids,status,evidence_path,open_issue
```

Một UC có nhiều dòng khi cần. Mọi UC-01…UC-48 đều có ít nhất happy-path UAT và các alternative/exception nguồn; tất cả thao tác có test denied/ownership phù hợp. Định danh test: `UT-W1-*`, `UT-W2-*`, `UAT-W1-*`, `UAT-W2-*` giữ nguyên ID gốc phía sau; mở rộng `UAT-UC01-01`, `IT-BOOK-RACE-01`… Không sửa ID gốc thành nghĩa khác.

### 10.2 Baseline Unit Test phải có

| Nguồn/ID | Nội dung bắt buộc |
| --- | --- |
| W1 UT-LOGIN-01 | Đăng nhập đúng trả session/token và role |
| W1 UT-FLIGHT-01/02 | Tạo flight hợp lệ; từ chối arrival <= departure |
| W1 UT-BOOK-01 | Booking đủ passenger + flight tạo ID |
| W2 UT-AUTH-01/02 | Login hợp lệ; role thiếu quyền bị 403 và không đổi DB |
| W2 UT-AIRPORT-01 | Định danh sân bay duy nhất |
| W2 UT-ROUTE-01 | Origin != destination, FK hợp lệ |
| W2 UT-FLIGHT-01/02/03 | Flight hợp lệ, giờ không hợp lệ, tàu bay xung đột BR-005 |
| W2 UT-CREW-01 | Không phân công crew thiếu khả dụng/năng định |
| W2 UT-BOOK-01/02 | Booking passenger/flight/seat; giải phóng hold hết hạn BR-011 |
| W2 UT-TICKET-01 | QR/token liên kết đúng vé; phân biệt vé và boarding pass sau check-in |
| W2 UT-CHECKIN-01 | Điều kiện check-in hợp lệ |
| W2 UT-BAG-01 | Tag duy nhất, đúng hành trình |
| W2 UT-MAINT-01/02 | Đến hạn sinh cảnh báo; overdue chặn khai thác BR-007 |
| W2 UT-DASH-01 | Filter thời gian trả đúng dữ liệu/KPI |
| W2 UT-AI-01/02 | Delay input/output đúng schema; anomaly dữ liệu cảm biến |
| W2 UT-IOT-01 | DHT22/BMP280/Rain sinh JSON đúng schema |
| W2 UT-AUDIT-01 | Audit actor/time/action/before/after theo quyền bảo vệ dữ liệu |

Nguồn có 4 hàng baseline W1 và 20 hàng W2. Dùng test data provider nếu cùng logic nhưng report phải truy vết đủ các hàng nguồn. Một số baseline gọi “unit” nhưng cần HTTP/DB/thiết bị: viết unit cho domain và feature/integration bổ trợ, ghi loại thực tế; không biến mọi test thành unit giả bằng mock.

### 10.3 Baseline UAT và khoảng trống phải bù

| ID tuần 2 | Phạm vi UC/mục tiêu |
| --- | --- |
| UAT-01 | Auth + UC-45: đúng quyền, sai quyền bị từ chối |
| UAT-02 | UC-01–03: sân bay và định danh; mở rộng resource/master data |
| UAT-03 | UC-04–09: route/schedule/flight/xung đột/gián đoạn |
| UAT-04 | UC-10–11: đội bay, khả dụng, usage |
| UAT-05 | UC-15–17: tổ bay, điều kiện, duyệt/thay thế |
| UAT-06 | UC-18–19: hồ sơ đúng quyền, consent/hỗ trợ |
| UAT-07 | UC-20–23: tìm chuyến, hold, payment/đổi/hủy/hoàn |
| UAT-08 | UC-22/26: phát vé và QR hợp lệ theo giai đoạn |
| UAT-09 | UC-24–27: check-in, baggage intake, QR boarding |
| UAT-10 | UC-28–30: tag/timeline/bất thường/giao trả |
| UAT-11 | UC-31–33: task/tiến độ/ngoại lệ quay đầu |
| UAT-12 | UC-12–14: lịch/work order/overdue/release |
| UAT-13 | UC-40: KPI/filter/role |
| UAT-14 | UC-41–42: dự báo, giải thích, model governance, người quyết định |
| UAT-15 | UC-43–44: Wokwi telemetry → tiếp nhận → cảnh báo |

Giữ riêng 4 case W1: đăng nhập/quyền; chuyến/lịch; hành khách-booking-vé-QR; AI/IoT dashboard. Bộ tuần 2 không đủ đại diện tường minh mọi UC: bổ sung UAT riêng cho UC-34/35/36 (tài chính), UC-37/38/39 (CSKH), UC-46/47/48 (audit/tích hợp/cấu hình), cùng các nhánh còn thiếu ở từng nhóm trên. Không chỉ chạy 15 happy path rồi tuyên bố đủ 48 UC.

### 10.4 Kiểm thử rủi ro bắt buộc bổ sung

1. Hai tiến trình đặt ghế cuối đồng thời: đúng một booking hợp lệ, không âm tồn chỗ; test trên MySQL thật bằng kết nối riêng.
2. Callback payment lặp/đảo thứ tự/sai chữ ký/sai amount; hold expiry chạy cùng payment; không phát vé/ghi sổ hai lần.
3. Hai scanner quét cùng QR: một lần boarding; token revoked/expired/wrong flight bị từ chối. Reissue vô hiệu mã cũ.
4. Hai người publish schedule hoặc gán aircraft/crew/resource cùng lúc: không lọt xung đột. Test biên turnaround và qua ngày/múi giờ.
5. Crew giấy phép hết hạn trong thời gian nhiệm vụ, nghỉ không đủ; phê duyệt/replacement kiểm lại dữ liệu mới.
6. Maintenance overdue, chưa signoff, còn blocker khác: không release/gán khai thác; flight event lặp không tăng usage lần hai.
7. Passenger A truy cập booking/passport/baggage/file của B; nhân viên sân bay ngoài scope; delegation hết hạn: từ chối không rò dữ liệu.
8. Hoàn tiền đồng thời/trùng/vượt hạn; đối soát chênh số tiền/không xác định giao dịch; điều chỉnh cần quyền duyệt.
9. IoT duplicate/late/out-of-order/disconnect/schema invalid/burst; AI unavailable hoặc thiếu feature không làm sập CRUD cốt lõi.
10. Queue retry và restart sau commit: không mất outbox, không gửi trùng nghiệp vụ; job lỗi có cảnh báo và hướng dẫn retry.
11. Import duplicate/invalid FK/mixed good-bad rows; export có ô bắt đầu =,+,-,@ được bảo vệ.
12. Backup/restore trên database test độc lập, đối chiếu số lượng và quan hệ; không chỉ kiểm có file backup.

### 10.5 Mẫu test case và ghi kết quả

Mỗi case: ID, source ID/week, UC/BR, scope/actor, objective, preconditions, fixture/input, numbered steps, expected result, actual result, status, build/commit, environment, tester, execution date/time, duration, bug ID, evidence path.

Trạng thái ban đầu `Not Run`; khi chạy mới ghi `Passed`/`Failed`; `Blocked` và `Skipped` phải có lý do. Không dùng Passed cho test chỉ viết code hoặc mock integration chưa kết nối. UAT tự động đạt không thay chữ ký nghiệm thu người phụ trách; approval để Pending cho tới khi có xác nhận thật.

Đầu ra: `docs/testing/unit-test-plan.md`, `acceptance-test-plan.md`, `test-cases.csv`, `test-execution-results.csv`, JUnit XML, coverage, browser traces/screenshots không chứa secret, `defects.md`, `release-test-report.md`.

Gate đề xuất (không phải ngưỡng gốc DOCX): 100% baseline đã thực thi đạt trong profile khai báo; 100% UC có traceability và acceptance coverage; 0 lỗi critical/high chưa giải quyết; unit coverage domain/service trọng yếu >=85% line và >=75% branch nếu công cụ hỗ trợ; test concurrency/invariants bắt buộc dù coverage đạt. Nếu gate chưa đạt ghi rõ chưa nghiệm thu, không hạ gate để làm xanh CI.

## 11. Dữ liệu mẫu, hiệu năng và vận hành

### 11.1 Seed tái lập

Demo seeder idempotent: 12 vai trò và account local; tối thiểu 5 sân bay/3 timezone, 10 routes, 3 aircraft models/10 aircraft, 30 crew có valid/expired/unavailable, 30 flights nhiều trạng thái, 50 passenger, 50 booking gồm held/expired/paid/refunded; task/maintenance/finance/complaint/telemetry bình thường và lỗi. Tách fixture test nhỏ với data stress. Đồng hồ test cố định; demo dùng mốc tương đối với ngày chạy để không hết hạn ngay khi tải dự án.

Không dùng dữ liệu cá nhân thật; tài khoản demo chỉ local, password nhận qua env hoặc sinh và hướng dẫn lấy an toàn; không tự bật tài khoản demo production. Seed DB không được thay thế logic CRUD.

### 11.2 Mục tiêu hiệu năng đề xuất

Ghi rõ cấu hình máy, dataset, concurrent users, warm/cold cache và script load test. Profile tham khảo: 100.000 flight, 100.000 booking, 500.000 telemetry; 50 người dùng đồng thời; p95 API danh sách <1 giây, tìm chuyến <2 giây, ingest <500 ms ở 20 event/s, loại trừ độ trễ dịch vụ ngoài và đo riêng. Đây là mục tiêu cần đo/tinh chỉnh theo môi trường, không phải tuyên bố hiệu năng đã đạt.

Pagination mặc định 25, giới hạn 100; allowlist filter/sort; composite indexes theo query thật; eager loading tránh N+1; batch import/export/telemetry, retention policy. Cache KPI có TTL/invalidation; không dùng cache làm nguồn quyết định tồn chỗ. Lưu slow query và plan khi có điểm nghẽn.

### 11.3 Khởi chạy và bàn giao

- `.env.example` cho DB, queue, mail, integrations, AI, broker; không secret. README Windows/XAMPP và Docker Linux/macOS, versions/extensions/ports rõ.
- Composer install, cấu hình env/key, migrate, demo seed an toàn, npm install/ci + build, queue worker và scheduler; các lệnh phải khớp repo thực tế và được chạy thử.
- `docker compose up --build` khởi động web/app, MySQL, worker, scheduler; profiles AI/IoT/mail/mock. Health/readiness kiểm DB/queue; không dùng lệnh migrate:fresh trong startup.
- Script setup/test bằng PowerShell và shell hoặc command portable. Document PHP executable/extension/Composer trên Windows, không mặc định Linux command chạy PowerShell.
- CI cài dependencies khóa phiên bản, lint/static analysis, unit, MySQL feature/integration, browser acceptance, asset build; artifacts test xuất ngay cả khi fail. Test DB tách biệt và kiểm tra env trước migration.
- Logs có correlation ID; metrics lỗi, latency, queue lag, failed jobs, payment unknown, telemetry lag; alert có owner/ack. Backup DB + uploads với runbook restore đã thử.
- RPO/RTO là mục tiêu đề xuất cần thống nhất và đo, không bịa SLA; ví dụ dev staging RPO 24h/RTO 4h chỉ khi team chấp nhận. Có rollback code/migration forward-fix và hướng dẫn ứng phó queue chết/DB mất kết nối.
- Hướng dẫn chạy demo end-to-end: quản trị dữ liệu → schedule publish → aircraft/crew hợp lệ → booking/payment → ticket → check-in/bag/QR → boarding → ground task → hoàn thành flight → usage/maintenance → finance/KPI. Demo riêng refund, complaint, AI và IoT.

## 12. Kế hoạch triển khai và cách không bỏ sót

| Giai đoạn | Phạm vi | Gate hoàn thành |
| --- | --- | --- |
| G0 | Kiểm kê repo/nguồn; map 48 UC, BR/NFR, ADR, schema gap, test mapping | Traceability đủ, assumptions rõ; không phá code cũ |
| G1 | Runtime, auth/RBAC/scope, audit, module skeleton, DB, CI, config | Login/403/ownership/audit và clean setup chạy |
| G2 | M01/M02/M04/M06; M03 nền; M05 liên quan khả dụng | Sân bay → lịch → tàu/tổ bay → publish/flight, conflict tests |
| G3 | M07/M08/M09/M10 | Booking-payment-ticket-checkin-QR-baggage end-to-end và race tests |
| G4 | Hoàn thiện M03/M05/M11/M12/M13 | Gián đoạn, maintenance, ground, finance, CSKH thật |
| G5 | M14/M15/M16 còn lại | KPI/AI/Wokwi/integration/config/governance |
| G6 | Hardening, regression toàn UC, tải, restore, đóng gói | Đủ bằng chứng baseline+expanded tests, README tái lập |

Mỗi giai đoạn triển khai theo lát cắt dọc: migration/model → policy/validation → service/transaction → route/UI → test → demo → cập nhật traceability. Không làm toàn bộ UI tĩnh trước rồi mới backend. Bất cứ dependency phải làm sớm thì điều chỉnh thứ tự và ghi lý do, không loại scope.

Cuối mỗi phiên cập nhật `implementation-progress.md`: commit, phần hoàn thành, phần dở, tests đã chạy và kết quả, blockers, quyết định, bước tiếp theo, lệnh tái lập. Đầu phiên sau xác minh code với nhật ký. Không coi toàn bộ giai đoạn hoàn thành vì một test xanh.

## 13. Định nghĩa hoàn thành và nội dung báo cáo Codex

Chỉ đánh dấu dự án “đã triển khai đầy đủ theo phạm vi” khi:

- [ ] 16 module/48 UC có implementation và kiểm thử truy vết; mọi luồng nguồn được xử lý.
- [ ] Stack đúng, MySQL migration/seed chạy từ database sạch, cài đặt theo README được xác minh.
- [ ] UI dùng backend/DB thật; không còn nút giả hoặc logic hardcode thay nghiệp vụ.
- [ ] 12 roles và scope có positive/negative tests; audit/PII/secret được kiểm soát.
- [ ] Transaction, locking, idempotency, expiry, QR replay và workflow approval được chứng minh bằng test.
- [ ] Hai baseline W1/W2 của Unit/UAT được giữ và mở rộng; kết quả có build/time/evidence thật.
- [ ] Tích hợp mô phỏng được ghi nhãn, contract/routing sang sandbox/live được tài liệu hóa; phần chưa thử thật được liệt kê.
- [ ] AI/IoT đáp ứng baseline, Wokwi có bằng chứng hoặc ghi blocker rõ; không nhận nghiệm thu toàn bộ khi blocker bắt buộc còn tồn tại.
- [ ] Build/test/CI/restore/health/worker/scheduler và demo end-to-end có lệnh/bằng chứng.
- [ ] Có ERD cập nhật, data dictionary, API docs, user guide theo role, administrator runbook, test reports và release notes.

Báo cáo cuối: chức năng hoàn thành theo UC; thay đổi kiến trúc; cách cài/chạy; phiên bản; tests đã chạy/đạt/lỗi/chưa chạy; đường dẫn bằng chứng; mô phỏng so với tích hợp thật; hạn chế còn lại. Không nói “production-ready” chỉ dựa vào prompt hoặc bộ seed. Nếu chưa hoàn thành thì nêu chính xác phần còn thiếu và tiếp tục ở phiên sau bằng nhật ký.

## 14. Phụ lục A — Đặc tả nguồn của đủ 48 use case

Nội dung sau trích xuất từ các đoạn/bảng của UseCase_va_DacTa (2).docx. Giữ actor, priority, basic/alternative/exception flow, mã BR/NFR. Các hình UML nhúng không được tái tạo trong bản Markdown; khi có DOCX đối chiếu hình cùng nội dung đặc tả. Ô trống được giữ trống, không hiểu là đã có yêu cầu chi tiết.

HỌC VIỆN CÔNG NGHỆ BƯU CHÍNH VIỄN THÔNG
MÔN: QUẢN LÝ DỰ ÁN PHẦN MỀM
BỘ TÀI LIỆU QUẢN LÝ
USECASE VÀ ĐẶC TẢ
Dự án: Phần mềm Quản lý Hàng không Thông minh
Tích hợp AI, QR, IoT mô phỏng và phân tích dữ liệu
1. Mô hình Use Case UML
1.1. Xác định các actor của hệ thống
Quản trị hệ thống: là người chịu trách nhiệm quản lý tài khoản người dùng, phân quyền, cấu hình hệ thống, giám sát tích hợp, nhật ký hệ thống và các chức năng kỹ thuật phục vụ vận hành.
Quản trị dữ liệu: là người chịu trách nhiệm quản lý dữ liệu nền, dữ liệu sân bay, đường bay, dữ liệu chủ, chất lượng dữ liệu và các cấu hình liên quan đến dữ liệu phân tích.
Điều hành khai thác: là bộ phận trực tiếp quản lý lịch bay, chuyến bay khai thác, trạng thái chuyến bay, các sự kiện bất thường và hoạt động điều phối trong quá trình khai thác.
Quản lý đội bay: chịu trách nhiệm quản lý thông tin tàu bay, trạng thái khai thác, khả năng sử dụng và các chỉ số liên quan đến giờ bay, chu kỳ bay của đội tàu bay.
Kỹ thuật bảo dưỡng: chịu trách nhiệm lập kế hoạch bảo dưỡng, quản lý lệnh bảo dưỡng, theo dõi hạn bảo dưỡng, ghi nhận kết quả kỹ thuật và quyết định trạng thái khả dụng của tàu bay.
Điều hành tổ bay: chịu trách nhiệm quản lý hồ sơ tổ bay, năng định, giấy phép, lịch khả dụng, phân công, phê duyệt và thay thế nhân sự tổ bay khi cần thiết.
Nhân viên sân bay: thực hiện các nghiệp vụ trực tiếp tại sân bay như làm thủ tục, tiếp nhận hành lý ký gửi, phát hành thẻ lên tàu bay, kiểm soát hành khách lên tàu bay và xử lý hành lý.
Dịch vụ mặt đất: chịu trách nhiệm thực hiện các nhiệm vụ phục vụ mặt đất, cập nhật tiến độ công việc và phối hợp các hoạt động quay đầu tàu bay.
Bán vé và chăm sóc khách hàng: thực hiện các nghiệp vụ liên quan đến tìm kiếm hành trình, đặt chỗ, thay đổi đơn hàng, hỗ trợ hành khách, xử lý khiếu nại và gửi thông báo tới khách hàng.
Tài chính kế toán: chịu trách nhiệm ghi nhận giao dịch tài chính, đối soát, xử lý chênh lệch, hoàn tiền, điều chỉnh và lập báo cáo tài chính nghiệp vụ.
An toàn và an ninh: chịu trách nhiệm giám sát hoạt động kiểm toán, truy vết các thao tác quan trọng và kiểm tra việc tuân thủ các yêu cầu về an toàn, an ninh và kiểm soát truy cập.
Hành khách: là người trực tiếp sử dụng các dịch vụ của hệ thống như tìm kiếm chuyến bay, đặt chỗ, thanh toán, làm thủ tục, theo dõi hành lý và gửi yêu cầu hỗ trợ hoặc khiếu nại.
1.2. Các Use Case chính của từng actor

**Actor**: Các Use Case chính | Phạm vi trách nhiệm


**Quản trị hệ thống**: Mô phỏng thiết bị và dữ liệu đo; Xử lý dữ liệu thiết bị bất thường; Quản lý người dùng và phân quyền; Quản lý nhật ký kiểm toán; Quản lý tích hợp hệ thống; Quản lý cấu hình và cảnh báo | Quản trị kỹ thuật, cấu hình và vận hành hệ thống


**Quản trị dữ liệu**: Quản lý sân bay; Quản lý tài nguyên sân bay; Quản lý dữ liệu chủ; Quản lý đường bay; Xem bảng điều khiển và chỉ số KPI; Dự báo và khuyến nghị; Giải thích và giám sát mô hình; Quản lý cấu hình dữ liệu | Quản lý dữ liệu nền, chất lượng dữ liệu và dữ liệu phục vụ phân tích


**Điều hành khai thác**: Lập và công bố lịch bay; Kiểm tra xung đột lịch bay; Quản lý chuyến bay khai thác; Theo dõi trạng thái và cảnh báo chuyến bay; Xử lý gián đoạn khai thác; Tạo và phân công nhiệm vụ mặt đất; Theo dõi tiến độ quay đầu; Xem bảng điều khiển và chỉ số KPI | Quản lý lịch bay, trạng thái chuyến và điều phối hoạt động khai thác


**Quản lý đội bay**: Quản lý tàu bay; Theo dõi sử dụng tàu bay; Xem bảng điều khiển và chỉ số KPI | Theo dõi hồ sơ, trạng thái và mức độ sử dụng tàu bay


**Kỹ thuật bảo dưỡng**: Quản lý lịch bảo dưỡng; Quản lý lệnh bảo dưỡng; Cảnh báo và giải phóng kỹ thuật; Dự báo và khuyến nghị | Quản lý kế hoạch, thực hiện và kiểm soát bảo dưỡng tàu bay


**Điều hành tổ bay**: Quản lý hồ sơ và khả dụng tổ bay; Phân công tổ bay; Phê duyệt và thay thế tổ bay | Quản lý nhân sự tổ bay và bảo đảm phân công đúng điều kiện


**Nhân viên sân bay**: Làm thủ tục và chọn chỗ; Tiếp nhận hành lý ký gửi; Phát hành thẻ lên tàu bay bằng mã QR; Kiểm soát lên tàu bay; Tạo thẻ hành lý; Theo dõi hành trình hành lý; Xử lý hành lý bất thường | Thực hiện các nghiệp vụ phục vụ hành khách tại sân bay


**Dịch vụ mặt đất**: Tạo và phân công nhiệm vụ; Thực hiện nhiệm vụ mặt đất; Theo dõi tiến độ quay đầu | Thực hiện và cập nhật tiến độ các nhiệm vụ phục vụ mặt đất


**Bán vé và chăm sóc khách hàng**: Quản lý hồ sơ hành khách; Quản lý nhu cầu hỗ trợ; Tìm kiếm hành trình; Quản lý đặt chỗ và đơn hàng; Đổi, hủy và hoàn tiền; Gửi thông báo đa kênh; Xử lý khiếu nại và bồi thường; Tra cứu lịch sử tương tác | Hỗ trợ hành khách trong quá trình mua và sử dụng dịch vụ


**Tài chính kế toán**: Ghi nhận giao dịch tài chính; Đối soát giao dịch; Điều chỉnh, báo cáo và xuất dữ liệu; Xem bảng điều khiển và chỉ số KPI | Quản lý doanh thu, hoàn tiền, chênh lệch và đối soát


**An toàn và an ninh**: Quản lý nhật ký kiểm toán | Theo dõi truy vết, kiểm soát thao tác nhạy cảm và tuân thủ


**Hành khách**: Quản lý hồ sơ hành khách; Quản lý nhu cầu hỗ trợ; Tìm kiếm hành trình; Quản lý đặt chỗ và đơn hàng; Thanh toán và phát hành; Đổi, hủy và hoàn tiền; Làm thủ tục và chọn chỗ; Phát hành thẻ lên tàu bay bằng mã QR; Theo dõi hành trình hành lý; Xử lý hành lý bất thường; Gửi thông báo đa kênh; Xử lý khiếu nại và bồi thường | Sử dụng dịch vụ từ giai đoạn tìm chuyến đến sau khi hoàn thành hành trình

A: Sơ đồ tổng quan 
B: Dữ liệu nền và sân bay
C : Đường bay và lịch bay
D : Điều hành chuyến bay
E : Đội tàu bay
F : Bảo dưỡng
G : Tổ bay
H: Hành khách và khách hang
I: Đặt chỗ và đơn hang
J: Làm thủ tục và lên tàu bay
K: Hành lý
L: Dịch vụ mặt đất
M: Tài chính nghiệp vụ
N: Thông báo và chăm sóc khách hàng


O: Trí tuệ nhân tạo và phân tích dữ liệu
P: Mô phỏng Internet vạn vật
Q: Quản trị và tích hợp
Bản đặc tả usecase
### M01 - DỮ LIỆU NỀN VÀ SÂN BAY
#### UC-01 - Quản lý sân bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-01


**Use Case Name**: Quản lý sân bay


**Use Case Description**: Cho phép Quản trị dữ liệu quản lý thông tin sân bay gồm mã, quốc gia, thành phố, tọa độ, múi giờ và giờ hoạt động.


**Actor**: Quản trị dữ liệu


**Priority**: Must Have


**Trigger**: Actor chọn chức năng Quản lý sân bay.


**Pre-Condition**: Actor đã đăng nhập và có quyền quản lý dữ liệu nền.


**Post-Condition**: Thông tin sân bay hợp lệ được lưu vào hệ thống.


**Basic Flow**: 1. Actor mở danh sách sân bay.

2. Hệ thống hiển thị dữ liệu hiện có.
3. Actor chọn thêm mới hoặc chỉnh sửa.
4. Actor nhập thông tin sân bay.
5. Hệ thống kiểm tra dữ liệu.
6. Actor xác nhận lưu.
7. Hệ thống cập nhật dữ liệu.

**Alternative Flow**: Actor thay đổi trạng thái hoặc thông tin của sân bay hiện có.


**Exception Flow**: E1. Thiếu dữ liệu bắt buộc → yêu cầu nhập lại.

E2. Mã sân bay bị trùng → từ chối lưu.
E3. Không đủ quyền → từ chối thao tác.

**Business Rule**: BR-001: Đối tượng nghiệp vụ phải có định danh duy nhất.

BR-002: Thời gian phải được quản lý đúng múi giờ.

**Non-Functional**: NFR-002: Đảm bảo hiệu năng.

NFR-005: Kiểm soát truy cập.
NFR-007: Ghi nhật ký thay đổi.
#### UC-02 - Quản lý tài nguyên sân bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-02


**Use Case Name**: Quản lý tài nguyên sân bay


**Use Case Description**: Quản lý nhà ga, cửa ra tàu bay, vị trí đỗ, băng chuyền, quầy và trạng thái khả dụng.


**Actor**: Quản trị dữ liệu


**Priority**: Must Have


**Trigger**: Actor chọn quản lý tài nguyên của một sân bay.


**Pre-Condition**: Sân bay đã tồn tại.


**Post-Condition**: Tài nguyên được lưu và liên kết đúng với sân bay.


**Basic Flow**: 1. Actor chọn sân bay.

2. Hệ thống hiển thị tài nguyên.
3. Actor thêm hoặc sửa tài nguyên.
4. Nhập thông tin tài nguyên.
5. Chọn trạng thái khả dụng.
6. Hệ thống kiểm tra.
7. Lưu dữ liệu.

**Alternative Flow**: Actor chuyển một tài nguyên sang trạng thái tạm ngừng sử dụng.


**Exception Flow**: 


**Business Rule**: BR-001: Dữ liệu phải có định danh duy nhất.


**Non-Functional**: NFR-005; NFR-007; NFR-015 về bảo mật, nhật ký và chất lượng dữ liệu.

#### UC-03 - Quản lý dữ liệu chủ

**Thuộc tính**: Nội dung


**Use Case ID**: UC-03


**Use Case Name**: Quản lý dữ liệu chủ


**Use Case Description**: Cho phép nhập, xuất, phê duyệt, phiên bản hóa và kiểm tra chất lượng dữ liệu chủ.


**Actor**: Quản trị dữ liệu


**Priority**: Must Have


**Trigger**: Actor nhập hoặc thay đổi dữ liệu chủ.


**Pre-Condition**: Actor có quyền quản lý dữ liệu.


**Post-Condition**: Dữ liệu hợp lệ được phê duyệt và công bố.


**Basic Flow**: 1. Actor chọn dữ liệu cần quản lý.

2. Nhập hoặc chỉnh sửa dữ liệu.
3. Hệ thống kiểm tra mã trùng, dữ liệu thiếu và quan hệ không hợp lệ.
4. Hiển thị kết quả kiểm tra.
5. Actor sửa nếu cần.
6. Gửi phê duyệt.
7. Hệ thống lưu phiên bản.

**Alternative Flow**: Actor xuất dữ liệu để kiểm tra hoặc sử dụng ngoài hệ thống.


**Exception Flow**: E1. Tệp nhập sai định dạng → từ chối.

E2. Dữ liệu không đạt chất lượng → không cho công bố.

**Business Rule**: BR-001; BR-002.


**Non-Functional**: NFR-007; NFR-015.

### M02 - ĐƯỜNG BAY VÀ LỊCH BAY
#### UC-04 - Quản lý đường bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-04


**Use Case Name**: Quản lý đường bay


**Use Case Description**: Quản lý điểm đi, điểm đến, khoảng cách, thời gian chuẩn, hạn chế và mùa khai thác của đường bay.


**Actor**: Quản trị dữ liệu


**Priority**: Must Have


**Trigger**: Actor chọn Quản lý đường bay.


**Pre-Condition**: Sân bay đi và sân bay đến tồn tại.


**Post-Condition**: Đường bay hợp lệ được lưu.


**Basic Flow**: 1. Mở danh sách đường bay.

2. Chọn thêm/sửa.
3. Chọn sân bay đi và đến.
4. Nhập thông tin khai thác.
5. Hệ thống kiểm tra.
6. Lưu.

**Alternative Flow**: Cập nhật hạn chế hoặc mùa khai thác.


**Exception Flow**: 


**Business Rule**: BR-001; BR-002.


**Non-Functional**: NFR-002; NFR-015.

#### UC-05 - Lập và công bố lịch bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-05


**Use Case Name**: Lập và công bố lịch bay


**Use Case Description**: Tạo lịch bay một lần hoặc lặp theo mùa, quản lý phiên bản, phê duyệt và công bố.


**Actor**: Điều hành khai thác


**Priority**: Must Have


**Trigger**: Actor tạo một lịch khai thác mới.


**Pre-Condition**: Đường bay và dữ liệu nền đã tồn tại.


**Post-Condition**: Lịch được lưu ở trạng thái nháp, chờ duyệt, đã duyệt hoặc đã công bố.


**Basic Flow**: 1. Actor tạo lịch.

2. Chọn đường bay, ngày, giờ và tần suất.
3. Hệ thống kiểm tra dữ liệu.
4. Actor kiểm tra xung đột.
5. Gửi phê duyệt.
6. Người có thẩm quyền phê duyệt.
7. Hệ thống công bố lịch.

**Alternative Flow**: Actor tạo phiên bản mới từ lịch đã công bố.


**Exception Flow**: E1. Thiếu thông tin bắt buộc → không công bố.

E2. Có xung đột chưa xử lý → giữ trạng thái chờ.

**Business Rule**: BR-003; BR-004.


**Non-Functional**: NFR-002; NFR-007; NFR-012.

#### UC-06 - Kiểm tra xung đột lịch bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-06


**Use Case Name**: Kiểm tra xung đột lịch bay


**Use Case Description**: Kiểm tra tàu bay, sân bay, cửa ra tàu bay và thời gian quay đầu trước khi công bố lịch.


**Actor**: Điều hành khai thác


**Priority**: Must Have


**Trigger**: Actor yêu cầu kiểm tra một phương án lịch bay.


**Pre-Condition**: Phương án lịch bay đã được tạo.


**Post-Condition**: Hệ thống trả về kết quả hợp lệ hoặc danh sách xung đột.


**Basic Flow**: 1. Nhận lịch cần kiểm tra.

2. Kiểm tra tàu bay.
3. Kiểm tra tài nguyên sân bay.
4. Kiểm tra thời gian quay đầu.
5. Tổng hợp xung đột.
6. Hiển thị cho actor.

**Alternative Flow**: Không có xung đột → cho phép tiếp tục phê duyệt.


**Exception Flow**: E1. Thiếu dữ liệu nguồn lực → cảnh báo không thể kiểm tra đầy đủ.


**Business Rule**: BR-005.


**Non-Functional**: NFR-002; NFR-015.

### M03 - ĐIỀU HÀNH CHUYẾN BAY
#### UC-07 - Quản lý chuyến bay khai thác

**Thuộc tính**: Nội dung


**Use Case ID**: UC-07


**Use Case Name**: Quản lý chuyến bay khai thác


**Use Case Description**: Khởi tạo chuyến bay từ lịch đã công bố và quản lý trạng thái vòng đời của chuyến.


**Actor**: Điều hành khai thác


**Priority**: Must Have


**Trigger**: Lịch bay đến thời điểm khai thác.


**Pre-Condition**: Lịch bay đã được công bố.


**Post-Condition**: Chuyến bay khai thác được tạo và theo dõi.


**Basic Flow**: 1. Hệ thống đọc lịch công bố.

2. Khởi tạo chuyến.
3. Gắn dữ liệu liên quan.
4. Điều hành kiểm tra.
5. Lưu trạng thái chuyến.

**Alternative Flow**: Actor cập nhật thông tin chuyến khi có thay đổi.


**Exception Flow**: 


**Business Rule**: BR-003; BR-004; BR-005.


**Non-Functional**: NFR-001; NFR-002; NFR-007.

#### UC-08 - Theo dõi trạng thái và cảnh báo chuyến bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-08


**Use Case Name**: Theo dõi trạng thái và cảnh báo chuyến bay


**Use Case Description**: Theo dõi thời gian kế hoạch, dự kiến, thực tế và phát hiện các bất thường.


**Actor**: Điều hành khai thác


**Priority**: Must Have


**Trigger**: Hệ thống nhận sự kiện mới của chuyến bay.


**Pre-Condition**: Chuyến bay đã tồn tại.


**Post-Condition**: Trạng thái và cảnh báo được cập nhật.


**Basic Flow**: 1. Nhận sự kiện.

2. Xác định chuyến.
3. Cập nhật mốc thời gian.
4. So sánh kế hoạch với thực tế.
5. Kiểm tra điều kiện cảnh báo.
6. Hiển thị cảnh báo.

**Alternative Flow**: 


**Exception Flow**: E1. Không xác định được chuyến → ghi lỗi tích hợp.


**Business Rule**: BR-002; BR-020.


**Non-Functional**: NFR-001; NFR-002; NFR-009.

#### UC-09 - Xử lý gián đoạn khai thác

**Thuộc tính**: Nội dung


**Use Case ID**: UC-09


**Use Case Name**: Xử lý gián đoạn khai thác


**Use Case Description**: Xử lý chậm, hủy, đổi tàu bay, đổi cửa hoặc thiếu nguồn lực.


**Actor**: Điều hành khai thác


**Priority**: Should Have


**Trigger**: Phát hiện bất thường cần can thiệp.


**Pre-Condition**: Chuyến bay và sự kiện bất thường đã tồn tại.


**Post-Condition**: Phương án xử lý được ghi nhận và các dữ liệu liên quan được cập nhật.


**Basic Flow**: 1. Actor mở cảnh báo.

2. Hệ thống xác định đối tượng bị ảnh hưởng.
3. Hiển thị thông tin liên quan.
4. Actor xây dựng phương án.
5. Chọn phương án xử lý.
6. Cập nhật dữ liệu.
7. Lưu lý do quyết định.

**Alternative Flow**: Hệ thống cung cấp gợi ý nhưng actor quyết định.


**Exception Flow**: 


**Business Rule**: BR-004; BR-005; BR-025.


**Non-Functional**: NFR-007; NFR-009.

### M04 - ĐỘI TÀU BAY
#### UC-10 - Quản lý tàu bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-10


**Use Case Name**: Quản lý tàu bay


**Use Case Description**: Quản lý số đăng ký, loại tàu bay, cấu hình ghế và trạng thái khai thác.


**Actor**: Quản lý đội bay


**Priority**: Must Have


**Trigger**: Actor truy cập danh sách tàu bay.


**Pre-Condition**: Actor có quyền quản lý đội bay.


**Post-Condition**: Hồ sơ tàu bay được cập nhật.


**Basic Flow**: 1. Xem danh sách.

2. Chọn thêm/sửa.
3. Nhập thông tin.
4. Chọn trạng thái.
5. Kiểm tra.
6. Lưu.

**Alternative Flow**: Actor thay đổi cấu hình hoặc trạng thái.


**Exception Flow**: E1. Số đăng ký bị trùng → từ chối lưu.


**Business Rule**: BR-001; BR-006.


**Non-Functional**: NFR-005; NFR-007.

#### UC-11 - Theo dõi sử dụng tàu bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-11


**Use Case Name**: Theo dõi sử dụng tàu bay


**Use Case Description**: Ghi nhận giờ bay, chu kỳ và các chỉ số sử dụng từ chuyến bay.


**Actor**: Quản lý đội bay


**Priority**: Must Have


**Trigger**: Chuyến bay hoàn thành hoặc có số liệu sử dụng mới.


**Pre-Condition**: Tàu bay tồn tại.


**Post-Condition**: Giờ bay và chu kỳ được cập nhật.


**Basic Flow**: 1. Nhận số liệu.

2. Xác định tàu bay.
3. Cập nhật giờ bay.
4. Cập nhật chu kỳ.
5. Kiểm tra ngưỡng.
6. Lưu.

**Alternative Flow**: Actor tra cứu lịch sử sử dụng.


**Exception Flow**: 


**Business Rule**: BR-006; BR-007.


**Non-Functional**: NFR-004; NFR-007; NFR-015.

### M05 - BẢO DƯỠNG
#### UC-12 - Quản lý lịch bảo dưỡng

**Thuộc tính**: Nội dung


**Use Case ID**: UC-12


**Use Case Name**: Quản lý lịch bảo dưỡng


**Use Case Description**: Lập và theo dõi lịch bảo dưỡng theo ngày, giờ bay, chu kỳ hoặc điều kiện.


**Actor**: Kỹ thuật bảo dưỡng


**Priority**: Must Have


**Trigger**: Có hạng mục bảo dưỡng cần lập kế hoạch.


**Pre-Condition**: Tàu bay tồn tại.


**Post-Condition**: Lịch bảo dưỡng được lưu và theo dõi.


**Basic Flow**: 1. Chọn tàu bay.

2. Xem số liệu sử dụng.
3. Chọn hạng mục bảo dưỡng.
4. Xác định hạn.
5. Kiểm tra xung đột.
6. Lưu lịch.

**Alternative Flow**: Điều chỉnh lịch khi dữ liệu khai thác thay đổi.


**Exception Flow**: 


**Business Rule**: BR-006; BR-007.


**Non-Functional**: NFR-007; NFR-009.

#### UC-13 - Quản lý lệnh bảo dưỡng

**Thuộc tính**: Nội dung


**Use Case ID**: UC-13


**Use Case Name**: Quản lý lệnh bảo dưỡng


**Use Case Description**: Tạo lệnh bảo dưỡng, phân công nhân lực, vật tư, vị trí và ghi nhận kết quả thực hiện.


**Actor**: Kỹ thuật bảo dưỡng


**Priority**: Must Have


**Trigger**: Có hạng mục bảo dưỡng đến thời điểm thực hiện.


**Pre-Condition**: Có kế hoạch bảo dưỡng hợp lệ.


**Post-Condition**: Kết quả bảo dưỡng và bằng chứng được lưu.


**Basic Flow**: 1. Tạo lệnh.

2. Chọn hạng mục.
3. Phân công nhân lực.
4. Chọn vật tư/vị trí.
5. Thực hiện.
6. Ghi kết quả.
7. Xác nhận kỹ thuật.

**Alternative Flow**: Công việc chưa hoàn tất → giữ trạng thái đang thực hiện.


**Exception Flow**: E1. Thiếu vật tư → cảnh báo.

E2. Thiếu xác nhận → không đóng lệnh.

**Business Rule**: BR-007; BR-010.


**Non-Functional**: NFR-005; NFR-007; NFR-018.

#### UC-14 - Cảnh báo và giải phóng kỹ thuật

**Thuộc tính**: Nội dung


**Use Case ID**: UC-14


**Use Case Name**: Cảnh báo và giải phóng kỹ thuật


**Use Case Description**: Cảnh báo công việc bảo dưỡng và quyết định trạng thái khả dụng của tàu bay.


**Actor**: Kỹ thuật bảo dưỡng


**Priority**: Must Have


**Trigger**: Công việc sắp đến hạn, quá hạn hoặc đã hoàn thành.


**Pre-Condition**: Có hồ sơ bảo dưỡng.


**Post-Condition**: Trạng thái kỹ thuật tàu bay được cập nhật.


**Basic Flow**: 1. Hệ thống kiểm tra hạn.

2. Tạo cảnh báo.
3. Kỹ thuật xem hồ sơ.
4. Kiểm tra kết quả.
5. Xác nhận kỹ thuật.
6. Cập nhật trạng thái tàu bay.

**Alternative Flow**: Tàu bay tiếp tục ở trạng thái không khả dụng.


**Exception Flow**: 


**Business Rule**: BR-006; BR-007; BR-010.


**Non-Functional**: NFR-007; NFR-009.

### M06 - TỔ BAY
#### UC-15 - Quản lý hồ sơ và khả dụng tổ bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-15


**Use Case Name**: Quản lý hồ sơ và khả dụng tổ bay


**Use Case Description**: Quản lý vị trí, năng định, giấy phép, đào tạo, lịch khả dụng, nghỉ phép và dự bị.


**Actor**: Điều hành tổ bay


**Priority**: Must Have


**Trigger**: Actor cần cập nhật hồ sơ nhân sự tổ bay.


**Pre-Condition**: Có hồ sơ nhân sự.


**Post-Condition**: Hồ sơ và trạng thái khả dụng được cập nhật.


**Basic Flow**: 1. Chọn nhân sự.

2. Xem hồ sơ.
3. Cập nhật năng định/giấy phép.
4. Cập nhật lịch khả dụng.
5. Kiểm tra.
6. Lưu.

**Alternative Flow**: Ghi nhận nghỉ phép hoặc dự bị.


**Exception Flow**: E1. Dữ liệu năng định không hợp lệ → không lưu.


**Business Rule**: BR-008; BR-009.


**Non-Functional**: NFR-005; NFR-007.

#### UC-16 - Phân công tổ bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-16


**Use Case Name**: Phân công tổ bay


**Use Case Description**: Tạo phương án phân công tổ bay và kiểm tra các ràng buộc nghiệp vụ.


**Actor**: Điều hành tổ bay


**Priority**: Must Have


**Trigger**: Có chuyến bay cần tổ bay.


**Pre-Condition**: Chuyến bay và nhân sự tồn tại.


**Post-Condition**: Phương án phân công hợp lệ được tạo.


**Basic Flow**: 1. Chọn chuyến.

2. Xác định nhu cầu vị trí.
3. Xem nhân sự khả dụng.
4. Chọn nhân sự.
5. Kiểm tra năng định, giấy phép, thời gian nhiệm vụ và nghỉ ngơi.
6. Lưu phương án.

**Alternative Flow**: Hệ thống đề xuất phương án phân công.


**Exception Flow**: E1. Không đủ nhân sự hợp lệ → cảnh báo.

E2. Vi phạm quy tắc → không chấp nhận.

**Business Rule**: BR-008; BR-009.


**Non-Functional**: NFR-002; NFR-007.

#### UC-17 - Phê duyệt và thay thế tổ bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-17


**Use Case Name**: Phê duyệt và thay thế tổ bay


**Use Case Description**: Phê duyệt, thông báo, xác nhận và thay thế nhân sự tổ bay khi có thay đổi.


**Actor**: Điều hành tổ bay


**Priority**: Must Have


**Trigger**: Phương án phân công chờ phê duyệt hoặc phát sinh thay đổi.


**Pre-Condition**: Đã có phương án phân công.


**Post-Condition**: Phân công chính thức được cập nhật và lưu lịch sử.


**Basic Flow**: 1. Xem phương án.

2. Kiểm tra quy tắc.
3. Phê duyệt.
4. Gửi thông báo.
5. Ghi nhận xác nhận.
6. Thay thế nếu cần.
7. Lưu lịch sử.

**Alternative Flow**: Sử dụng nhân sự dự bị.


**Exception Flow**: E1. Người thay thế không đạt điều kiện → từ chối.


**Business Rule**: BR-008; BR-009; BR-010.


**Non-Functional**: NFR-007; NFR-018.

### M07 - HÀNH KHÁCH VÀ KHÁCH HÀNG
#### UC-18 - Quản lý hồ sơ hành khách

**Thuộc tính**: Nội dung


**Use Case ID**: UC-18


**Use Case Name**: Quản lý hồ sơ hành khách


**Use Case Description**: Tạo và cập nhật hồ sơ hành khách, thông tin liên hệ và sự đồng ý xử lý dữ liệu.


**Actor**: Hành khách; Bán vé và chăm sóc khách hàng


**Priority**: Must Have


**Trigger**: Tạo mới hoặc cập nhật hồ sơ hành khách.


**Pre-Condition**: Người dùng có quyền phù hợp.


**Post-Condition**: Hồ sơ được lưu.


**Basic Flow**: 1. Nhập thông tin.

2. Hệ thống kiểm tra dữ liệu.
3. Ghi nhận sự đồng ý xử lý dữ liệu.
4. Xác nhận.
5. Lưu hồ sơ.

**Alternative Flow**: Hành khách tự cập nhật thông tin được phép.


**Exception Flow**: 


**Business Rule**: BR-022; BR-023.


**Non-Functional**: NFR-005; NFR-006; NFR-007.

#### UC-19 - Quản lý nhu cầu hỗ trợ

**Thuộc tính**: Nội dung


**Use Case ID**: UC-19


**Use Case Name**: Quản lý nhu cầu hỗ trợ


**Use Case Description**: Ghi nhận và quản lý nhu cầu hỗ trợ đặc biệt của hành khách.


**Actor**: Hành khách; Bán vé và chăm sóc khách hàng


**Priority**: Should Have


**Trigger**: Hành khách yêu cầu hỗ trợ.


**Pre-Condition**: Có hồ sơ hành khách hoặc đặt chỗ.


**Post-Condition**: Nhu cầu hỗ trợ được ghi nhận.


**Basic Flow**: 1. Chọn hành khách.

2. Chọn loại hỗ trợ.
3. Nhập thông tin.
4. Kiểm tra quyền.
5. Lưu.
6. Chuyển đến bộ phận liên quan.

**Alternative Flow**: Cập nhật hoặc hủy yêu cầu.


**Exception Flow**: 


**Business Rule**: BR-022; BR-023.


**Non-Functional**: NFR-005; NFR-006; NFR-011.

### M08 - ĐẶT CHỖ VÀ ĐƠN HÀNG
#### UC-20 - Tìm kiếm hành trình

**Thuộc tính**: Nội dung


**Use Case ID**: UC-20


**Use Case Name**: Tìm kiếm hành trình


**Use Case Description**: Tìm chuyến, giá, chỗ và điều kiện áp dụng.


**Actor**: Hành khách; Bán vé và chăm sóc khách hàng


**Priority**: Must Have


**Trigger**: Người dùng nhập yêu cầu chuyến đi.


**Pre-Condition**: Có lịch bay và dữ liệu giá.


**Post-Condition**: Các phương án phù hợp được hiển thị.


**Basic Flow**: 1. Nhập điểm đi, điểm đến, ngày.

2. Hệ thống tìm chuyến.
3. Kiểm tra giá và chỗ.
4. Áp dụng điều kiện.
5. Hiển thị kết quả.

**Alternative Flow**: Người dùng thay đổi bộ lọc.


**Exception Flow**: 


**Business Rule**: BR-011.


**Non-Functional**: NFR-002; NFR-003; NFR-012.

#### UC-21 - Quản lý đặt chỗ và đơn hàng

**Thuộc tính**: Nội dung


**Use Case ID**: UC-21


**Use Case Name**: Quản lý đặt chỗ và đơn hàng


**Use Case Description**: Tạo, giữ, xác nhận và hủy đặt chỗ; quản lý hành khách, chặng, chỗ và dịch vụ bổ sung.


**Actor**: Hành khách; Bán vé và chăm sóc khách hàng


**Priority**: Must Have


**Trigger**: Người dùng chọn hành trình.


**Pre-Condition**: Hành trình còn chỗ.


**Post-Condition**: Đơn hàng được tạo.


**Basic Flow**: 1. Chọn hành trình.

2. Hệ thống giữ chỗ.
3. Nhập hành khách.
4. Chọn dịch vụ.
5. Tính tổng tiền.
6. Lưu đơn hàng.

**Alternative Flow**: Hủy giữ chỗ trước khi thanh toán.


**Exception Flow**: 


**Business Rule**: BR-011.


**Non-Functional**: NFR-002; NFR-004; NFR-007.

#### UC-22 - Thanh toán và phát hành

**Thuộc tính**: Nội dung


**Use Case ID**: UC-22


**Use Case Name**: Thanh toán và phát hành


**Use Case Description**: Thực hiện thanh toán và phát hành vé hoặc quyền sử dụng dịch vụ.


**Actor**: Hành khách; Cổng thanh toán


**Priority**: Must Have


**Trigger**: Đơn hàng sẵn sàng thanh toán.


**Pre-Condition**: Đơn hàng còn hiệu lực.


**Post-Condition**: Thanh toán và vé được cập nhật.


**Basic Flow**: 1. Xác nhận số tiền.

2. Gửi giao dịch tới cổng thanh toán.
3. Nhận kết quả.
4. Kiểm tra giao dịch trùng.
5. Cập nhật đơn hàng.
6. Phát hành vé/xác nhận.

**Alternative Flow**: Giao dịch chưa xác định → chuyển chờ đối soát.


**Exception Flow**: 


**Business Rule**: BR-012.


**Non-Functional**: NFR-004; NFR-005; NFR-007.

#### UC-23 - Đổi, hủy và hoàn tiền

**Thuộc tính**: Nội dung


**Use Case ID**: UC-23


**Use Case Name**: Đổi, hủy và hoàn tiền


**Use Case Description**: Xử lý thay đổi hành trình, hủy và hoàn tiền theo chính sách.


**Actor**: Hành khách; Bán vé và chăm sóc khách hàng; Cổng thanh toán


**Priority**: Must Have


**Trigger**: Người dùng yêu cầu thay đổi đơn hàng.


**Pre-Condition**: Đơn hàng tồn tại.


**Post-Condition**: Đơn hàng và giao dịch liên quan được cập nhật.


**Basic Flow**: 1. Mở đơn hàng.

2. Chọn yêu cầu.
3. Kiểm tra điều kiện.
4. Tính phí/chênh lệch.
5. Xác nhận.
6. Thực hiện giao dịch.
7. Cập nhật đơn hàng.

**Alternative Flow**: Chuyển yêu cầu sang người có quyền cao hơn.


**Exception Flow**: E1. Không đủ điều kiện đổi/hoàn → từ chối.


**Business Rule**: BR-013; BR-021.


**Non-Functional**: NFR-004; NFR-005; NFR-007.

### M09 - LÀM THỦ TỤC VÀ LÊN TÀU BAY
#### UC-24 - Làm thủ tục và chọn chỗ

**Thuộc tính**: Nội dung


**Use Case ID**: UC-24


**Use Case Name**: Làm thủ tục và chọn chỗ


**Use Case Description**: Kiểm tra điều kiện làm thủ tục và cho phép chọn hoặc đổi chỗ.


**Actor**: Hành khách; Nhân viên sân bay


**Priority**: Must Have


**Trigger**: Hành khách thực hiện thủ tục chuyến bay.


**Pre-Condition**: Có đặt chỗ hợp lệ.


**Post-Condition**: Hành khách hoàn thành thủ tục và có chỗ ngồi.


**Basic Flow**: 1. Xác định hành khách.

2. Kiểm tra điều kiện.
3. Hiển thị sơ đồ ghế.
4. Chọn ghế.
5. Kiểm tra chính sách.
6. Xác nhận.

**Alternative Flow**: Hệ thống tự gán ghế.


**Exception Flow**: 


**Business Rule**: BR-022.


**Non-Functional**: NFR-002; NFR-004; NFR-005.

#### UC-25 - Tiếp nhận hành lý ký gửi

**Thuộc tính**: Nội dung


**Use Case ID**: UC-25


**Use Case Name**: Tiếp nhận hành lý ký gửi


**Use Case Description**: Ghi nhận hành lý ký gửi của hành khách trong quá trình làm thủ tục.


**Actor**: Nhân viên sân bay


**Priority**: Must Have


**Trigger**: Hành khách có hành lý ký gửi.


**Pre-Condition**: Hành khách có chuyến bay hợp lệ.


**Post-Condition**: Hành lý được ghi nhận.


**Basic Flow**: 1. Mở hồ sơ hành khách.

2. Nhập số kiện và trọng lượng.
3. Kiểm tra điều kiện.
4. Xác nhận hành lý.
5. Lưu.

**Alternative Flow**: Hành lý có yêu cầu xử lý đặc biệt.


**Exception Flow**: E1. Không liên kết được với hành khách → không tiếp nhận.


**Business Rule**: BR-016.


**Non-Functional**: NFR-002; NFR-007.

#### UC-26 - Phát hành thẻ lên tàu bay bằng mã QR

**Thuộc tính**: Nội dung


**Use Case ID**: UC-26


**Use Case Name**: Phát hành thẻ lên tàu bay bằng mã QR


**Use Case Description**: Tạo, thu hồi hoặc cấp lại mã QR của thẻ lên tàu bay.


**Actor**: Hành khách; Nhân viên sân bay


**Priority**: Must Have


**Trigger**: Hành khách hoàn thành thủ tục.


**Pre-Condition**: Hành khách đủ điều kiện lên tàu bay.


**Post-Condition**: Thẻ lên tàu bay có mã QR hợp lệ được tạo.


**Basic Flow**: 1. Nhận dữ liệu chuyến.

2. Sinh mã nhận diện.
3. Gắn chuyến và thời hạn.
4. Lưu trạng thái.
5. Cấp thẻ cho hành khách.

**Alternative Flow**: Thu hồi mã cũ và cấp lại mã mới.


**Exception Flow**: E1. Không tạo được mã → báo lỗi.


**Business Rule**: BR-014.


**Non-Functional**: NFR-005; NFR-006; NFR-007.

#### UC-27 - Kiểm soát lên tàu bay

**Thuộc tính**: Nội dung


**Use Case ID**: UC-27


**Use Case Name**: Kiểm soát lên tàu bay


**Use Case Description**: Quét mã QR tại cửa và ghi nhận trạng thái lên tàu bay.


**Actor**: Nhân viên sân bay


**Priority**: Must Have


**Trigger**: Mã QR được quét tại cửa.


**Pre-Condition**: Chuyến đang trong thời gian lên tàu bay.


**Post-Condition**: Trạng thái hành khách được cập nhật.


**Basic Flow**: 1. Quét QR.

2. Xác thực mã.
3. Kiểm tra chuyến và thời hạn.
4. Kiểm tra trạng thái sử dụng.
5. Ghi nhận hành khách đã lên tàu.
6. Đồng bộ danh sách.

**Alternative Flow**: Ghi nhận hành khách không lên tàu hoặc bị đưa khỏi chuyến.


**Exception Flow**: E1. Mã hết hạn/sai chuyến → từ chối.

E2. Mã đã sử dụng → cảnh báo.

**Business Rule**: BR-014; BR-015.


**Non-Functional**: NFR-001; NFR-002; NFR-004.

### M10 - HÀNH LÝ
#### UC-28 - Tạo thẻ hành lý

**Thuộc tính**: Nội dung


**Use Case ID**: UC-28


**Use Case Name**: Tạo thẻ hành lý


**Use Case Description**: Tạo định danh hành lý liên kết với hành khách, chuyến và chặng.


**Actor**: Nhân viên sân bay


**Priority**: Must Have


**Trigger**: Hành lý ký gửi được tiếp nhận.


**Pre-Condition**: Hành khách có hành trình hợp lệ.


**Post-Condition**: Thẻ hành lý duy nhất được tạo.


**Basic Flow**: 1. Nhận thông tin hành lý.

2. Sinh mã thẻ.
3. Liên kết hành khách.
4. Liên kết chuyến/chặng.
5. Lưu.
6. In thẻ.

**Alternative Flow**: Hành trình gồm nhiều chặng.


**Exception Flow**: E1. Không xác định được chuyến → không tạo thẻ.


**Business Rule**: BR-001; BR-016.


**Non-Functional**: NFR-004; NFR-007.

#### UC-29 - Theo dõi hành trình hành lý

**Thuộc tính**: Nội dung


**Use Case ID**: UC-29


**Use Case Name**: Theo dõi hành trình hành lý


**Use Case Description**: Ghi nhận các điểm quét, trạng thái và đơn vị chịu trách nhiệm đối với hành lý.


**Actor**: Nhân viên sân bay; Hành khách; Hệ thống xử lý hành lý


**Priority**: Must Have


**Trigger**: Hành lý phát sinh một sự kiện xử lý.


**Pre-Condition**: Thẻ hành lý tồn tại.


**Post-Condition**: Hành trình hành lý được cập nhật.


**Basic Flow**: 1. Nhận mã hành lý.

2. Xác định điểm xử lý.
3. Ghi thời gian.
4. Ghi nguồn sự kiện.
5. Cập nhật trạng thái.
6. Kiểm tra bất thường.

**Alternative Flow**: Hành khách tra cứu trạng thái bằng mã an toàn.


**Exception Flow**: 


**Business Rule**: BR-016; BR-017.


**Non-Functional**: NFR-002; NFR-004; NFR-009.

#### UC-30 - Xử lý hành lý bất thường

**Thuộc tính**: Nội dung


**Use Case ID**: UC-30


**Use Case Name**: Xử lý hành lý bất thường


**Use Case Description**: Xử lý hành lý thất lạc, chậm, hư hỏng, sai chuyến hoặc thiếu sự kiện.


**Actor**: Nhân viên sân bay; Hành khách


**Priority**: Must Have


**Trigger**: Phát hiện sự cố hoặc hành khách báo vấn đề.


**Pre-Condition**: Hành lý có mã nhận diện.


**Post-Condition**: Hồ sơ bất thường được tạo và theo dõi.


**Basic Flow**: 1. Tra cứu hành lý.

2. Xem lịch sử.
3. Xác định loại sự cố.
4. Tạo hồ sơ.
5. Theo dõi xử lý.
6. Giao trả nếu tìm thấy.
7. Đóng hồ sơ.

**Alternative Flow**: Chuyển sang quy trình an ninh nếu cần.


**Exception Flow**: 


**Business Rule**: BR-016; BR-017; BR-018.


**Non-Functional**: NFR-005; NFR-007; NFR-009.

### M11 - DỊCH VỤ MẶT ĐẤT
#### UC-31 - Tạo và phân công nhiệm vụ

**Thuộc tính**: Nội dung


**Use Case ID**: UC-31


**Use Case Name**: Tạo và phân công nhiệm vụ


**Use Case Description**: Tạo các nhiệm vụ quay đầu và phân công cho đơn vị thực hiện.


**Actor**: Điều hành khai thác; Dịch vụ mặt đất


**Priority**: Must Have


**Trigger**: Chuyến bay cần chuẩn bị dịch vụ mặt đất.


**Pre-Condition**: Chuyến bay tồn tại.


**Post-Condition**: Nhiệm vụ có đơn vị chịu trách nhiệm và thời hạn.


**Basic Flow**: 1. Chọn chuyến.

2. Tạo danh sách nhiệm vụ.
3. Kiểm tra danh sách.
4. Phân công đơn vị.
5. Đặt thời hạn.
6. Lưu.

**Alternative Flow**: Thêm nhiệm vụ phát sinh.


**Exception Flow**: E1. Không có đơn vị phù hợp → cảnh báo.


**Business Rule**: BR-019.


**Non-Functional**: NFR-002; NFR-007.

#### UC-32 - Thực hiện nhiệm vụ mặt đất

**Thuộc tính**: Nội dung


**Use Case ID**: UC-32


**Use Case Name**: Thực hiện nhiệm vụ mặt đất


**Use Case Description**: Ghi nhận bắt đầu, hoàn thành, bằng chứng và ngoại lệ của nhiệm vụ.


**Actor**: Dịch vụ mặt đất


**Priority**: Must Have


**Trigger**: Đơn vị bắt đầu nhiệm vụ.


**Pre-Condition**: Nhiệm vụ đã được phân công.


**Post-Condition**: Kết quả thực hiện được ghi nhận.


**Basic Flow**: 1. Mở nhiệm vụ.

2. Xác nhận bắt đầu.
3. Thực hiện.
4. Ghi bằng chứng.
5. Xác nhận hoàn thành.

**Alternative Flow**: Ghi nhận ngoại lệ hoặc chậm.


**Exception Flow**: 


**Business Rule**: BR-019.


**Non-Functional**: NFR-005; NFR-007.

#### UC-33 - Theo dõi tiến độ quay đầu

**Thuộc tính**: Nội dung


**Use Case ID**: UC-33


**Use Case Name**: Theo dõi tiến độ quay đầu


**Use Case Description**: Theo dõi tiến độ nhiệm vụ, thời hạn và cảnh báo ảnh hưởng giờ khởi hành.


**Actor**: Điều hành khai thác; Dịch vụ mặt đất


**Priority**: Must Have


**Trigger**: Chuyến bay đang thực hiện các nhiệm vụ quay đầu.


**Pre-Condition**: Có danh sách nhiệm vụ.


**Post-Condition**: Tiến độ và cảnh báo được cập nhật.


**Basic Flow**: 1. Tổng hợp trạng thái.

2. Hiển thị tiến độ.
3. So sánh với thời hạn.
4. Phát hiện nhiệm vụ trễ.
5. Tạo cảnh báo.
6. Theo dõi đến hoàn tất.

**Alternative Flow**: Đánh giá chất lượng sau khi hoàn thành.


**Exception Flow**: E1. Còn mốc bắt buộc → không đóng chuyến khi chưa có ngoại lệ được duyệt.


**Business Rule**: BR-019; BR-020.


**Non-Functional**: NFR-002; NFR-009.

### M12 - TÀI CHÍNH NGHIỆP VỤ
#### UC-34 - Ghi nhận giao dịch tài chính

**Thuộc tính**: Nội dung


**Use Case ID**: UC-34


**Use Case Name**: Ghi nhận giao dịch tài chính


**Use Case Description**: Ghi nhận khoản thu, phí, thuế, giảm giá, hoàn tiền và điều chỉnh.


**Actor**: Tài chính kế toán


**Priority**: Must Have


**Trigger**: Phát sinh giao dịch.


**Pre-Condition**: Có giao dịch nguồn hợp lệ.


**Post-Condition**: Dữ liệu tài chính được ghi nhận.


**Basic Flow**: 1. Nhận giao dịch.

2. Xác định loại khoản.
3. Ghi số tiền.
4. Liên kết nguồn.
5. Lưu.

**Alternative Flow**: Ghi nhận hoàn tiền hoặc điều chỉnh.


**Exception Flow**: 


**Business Rule**: BR-021.


**Non-Functional**: NFR-004; NFR-007; NFR-015.

#### UC-35 - Đối soát giao dịch

**Thuộc tính**: Nội dung


**Use Case ID**: UC-35


**Use Case Name**: Đối soát giao dịch


**Use Case Description**: Đối chiếu giao dịch nội bộ với cổng thanh toán, ngân hàng hoặc đối tác.


**Actor**: Tài chính kế toán; Cổng thanh toán


**Priority**: Must Have


**Trigger**: Đến kỳ đối soát hoặc phát sinh giao dịch chưa xác định.


**Pre-Condition**: Có dữ liệu hai phía.


**Post-Condition**: Giao dịch được đánh dấu khớp hoặc tạo hồ sơ chênh lệch.


**Basic Flow**: 1. Nhận dữ liệu.

2. Ghép giao dịch.
3. So sánh số tiền và trạng thái.
4. Đánh dấu khớp.
5. Tạo hồ sơ chênh lệch nếu có.

**Alternative Flow**: Tự động đóng giao dịch khớp hoàn toàn.


**Exception Flow**: E1. Không xác định giao dịch → chuyển xử lý.


**Business Rule**: BR-012; BR-021.


**Non-Functional**: NFR-004; NFR-007; NFR-015.

#### UC-36 - Điều chỉnh, báo cáo và xuất dữ liệu

**Thuộc tính**: Nội dung


**Use Case ID**: UC-36


**Use Case Name**: Điều chỉnh, báo cáo và xuất dữ liệu


**Use Case Description**: Phê duyệt điều chỉnh, lập báo cáo doanh thu và xuất dữ liệu sang hệ thống tài chính.


**Actor**: Tài chính kế toán


**Priority**: Must Have / Should Have


**Trigger**: Có yêu cầu điều chỉnh hoặc đến kỳ báo cáo.


**Pre-Condition**: Có dữ liệu tài chính.


**Post-Condition**: Điều chỉnh hoặc báo cáo được hoàn tất.


**Basic Flow**: 1. Chọn dữ liệu.

2. Kiểm tra giao dịch.
3. Kiểm tra quyền phê duyệt.
4. Xử lý điều chỉnh.
5. Tạo báo cáo.
6. Xuất dữ liệu sang ERP.

**Alternative Flow**: Báo cáo theo chuyến, đường bay, kênh hoặc kỳ.


**Exception Flow**: 


**Business Rule**: BR-013; BR-021.


**Non-Functional**: NFR-005; NFR-007; NFR-009.

### M13 - THÔNG BÁO VÀ CHĂM SÓC KHÁCH HÀNG
#### UC-37 - Gửi thông báo đa kênh

**Thuộc tính**: Nội dung


**Use Case ID**: UC-37


**Use Case Name**: Gửi thông báo đa kênh


**Use Case Description**: Gửi thông báo giao dịch hoặc chuyến bay theo kênh và ngôn ngữ phù hợp.


**Actor**: Bán vé và chăm sóc khách hàng; Hành khách


**Priority**: Should Have


**Trigger**: Có sự kiện cần thông báo.


**Pre-Condition**: Có thông tin liên hệ phù hợp.


**Post-Condition**: Trạng thái gửi thông báo được ghi nhận.


**Basic Flow**: 1. Nhận sự kiện.

2. Xác định người nhận.
3. Chọn mẫu.
4. Chọn kênh.
5. Gửi.
6. Lưu trạng thái.

**Alternative Flow**: Chuyển sang kênh khác khi kênh chính lỗi.


**Exception Flow**: E1. Không có thông tin liên hệ → ghi không gửi được.


**Business Rule**: BR-022; BR-023.


**Non-Functional**: NFR-006; NFR-009; NFR-012.

#### UC-38 - Xử lý khiếu nại và bồi thường

**Thuộc tính**: Nội dung


**Use Case ID**: UC-38


**Use Case Name**: Xử lý khiếu nại và bồi thường


**Use Case Description**: Tiếp nhận, phân loại, xử lý và theo dõi khiếu nại của hành khách.


**Actor**: Hành khách; Bán vé và chăm sóc khách hàng


**Priority**: Should Have


**Trigger**: Hành khách gửi khiếu nại.


**Pre-Condition**: Có thông tin hành khách.


**Post-Condition**: Hồ sơ khiếu nại được tạo và theo dõi.


**Basic Flow**: 1. Tiếp nhận yêu cầu.

2. Tạo hồ sơ.
3. Liên kết giao dịch/chuyến.
4. Phân loại.
5. Xử lý.
6. Cập nhật kết quả.
7. Thông báo hành khách.

**Alternative Flow**: Chuyển yêu cầu bồi thường sang người có thẩm quyền.


**Exception Flow**: 


**Business Rule**: BR-022.


**Non-Functional**: NFR-005; NFR-006; NFR-007.

#### UC-39 - Tra cứu lịch sử tương tác

**Thuộc tính**: Nội dung


**Use Case ID**: UC-39


**Use Case Name**: Tra cứu lịch sử tương tác


**Use Case Description**: Cho phép nhân viên chăm sóc khách hàng tra cứu lịch sử tương tác được phép.


**Actor**: Bán vé và chăm sóc khách hàng


**Priority**: Should Have


**Trigger**: Actor mở hồ sơ hành khách.


**Pre-Condition**: Actor có quyền truy cập.


**Post-Condition**: Lịch sử tương tác được hiển thị.


**Basic Flow**: 1. Tìm hành khách.

2. Kiểm tra quyền.
3. Tổng hợp tương tác.
4. Che dữ liệu không cần thiết.
5. Hiển thị theo thời gian.

**Alternative Flow**: Lọc theo loại tương tác.


**Exception Flow**: E1. Không đủ quyền → từ chối.


**Business Rule**: BR-022; BR-023.


**Non-Functional**: NFR-005; NFR-006; NFR-007.

### M14 - TRÍ TUỆ NHÂN TẠO VÀ PHÂN TÍCH DỮ LIỆU
#### UC-40 - Xem bảng điều khiển và chỉ số KPI

**Thuộc tính**: Nội dung


**Use Case ID**: UC-40


**Use Case Name**: Xem bảng điều khiển và chỉ số KPI


**Use Case Description**: Hiển thị dữ liệu và KPI phù hợp theo vai trò.


**Actor**: Quản trị dữ liệu và các vai trò được phân quyền


**Priority**: Must Have


**Trigger**: Người dùng mở bảng điều khiển.


**Pre-Condition**: Có quyền truy cập và dữ liệu nguồn.


**Post-Condition**: KPI phù hợp được hiển thị.


**Basic Flow**: 1. Xác định vai trò.

2. Lấy dữ liệu.
3. Áp dụng bộ lọc.
4. Tính chỉ số.
5. Hiển thị kết quả.

**Alternative Flow**: Thay đổi khoảng thời gian hoặc bộ lọc.


**Exception Flow**: 


**Business Rule**: BR-022.


**Non-Functional**: NFR-002; NFR-003; NFR-009; NFR-015.

#### UC-41 - Dự báo và khuyến nghị

**Thuộc tính**: Nội dung


**Use Case ID**: UC-41


**Use Case Name**: Dự báo và khuyến nghị


**Use Case Description**: Cung cấp dự báo chậm chuyến, nhu cầu, bảo dưỡng, phân bổ nguồn lực hoặc bất thường.


**Actor**: Điều hành khai thác; Kỹ thuật bảo dưỡng; Tài chính kế toán; Bán vé và chăm sóc khách hàng


**Priority**: Could Have


**Trigger**: Người dùng yêu cầu dự báo hoặc mô hình được chạy theo lịch.


**Pre-Condition**: Mô hình được phê duyệt và có dữ liệu đầu vào.


**Post-Condition**: Kết quả dự báo được tạo để hỗ trợ người dùng.


**Basic Flow**: 1. Chọn bài toán.

2. Lấy dữ liệu.
3. Chọn phiên bản mô hình.
4. Thực hiện dự báo.
5. Hiển thị kết quả.
6. Người có trách nhiệm xem xét.

**Alternative Flow**: Người dùng không sử dụng khuyến nghị.


**Exception Flow**: E1. Dữ liệu không đạt chất lượng → cảnh báo hoặc không chạy.


**Business Rule**: BR-024; BR-025.


**Non-Functional**: NFR-015; NFR-016.

#### UC-42 - Giải thích và giám sát mô hình

**Thuộc tính**: Nội dung


**Use Case ID**: UC-42


**Use Case Name**: Giải thích và giám sát mô hình


**Use Case Description**: Hiển thị độ tin cậy, thời điểm, phiên bản mô hình và theo dõi chất lượng mô hình.


**Actor**: Quản trị dữ liệu


**Priority**: Must Have


**Trigger**: Có kết quả AI hoặc cần kiểm tra mô hình.


**Pre-Condition**: Mô hình tồn tại.


**Post-Condition**: Thông tin giải thích và trạng thái mô hình được hiển thị.


**Basic Flow**: 1. Chọn mô hình/kết quả.

2. Hiển thị phiên bản.
3. Hiển thị thời điểm chạy.
4. Hiển thị độ tin cậy.
5. Theo dõi độ lệch và chất lượng.
6. Ghi nhận kết quả giám sát.

**Alternative Flow**: Khôi phục phiên bản mô hình trước theo quy trình được duyệt.


**Exception Flow**: E1. Thiếu thông tin mô hình → đánh dấu kết quả không đủ căn cứ.


**Business Rule**: BR-024; BR-025.


**Non-Functional**: NFR-007; NFR-016.

### M15 - MÔ PHỎNG INTERNET VẠN VẬT
#### UC-43 - Mô phỏng thiết bị và dữ liệu đo

**Thuộc tính**: Nội dung


**Use Case ID**: UC-43


**Use Case Name**: Mô phỏng thiết bị và dữ liệu đo


**Use Case Description**: Mô phỏng thiết bị và luồng dữ liệu để kiểm thử nghiệp vụ.


**Actor**: Quản trị hệ thống


**Priority**: Should Have


**Trigger**: Actor khởi chạy kịch bản mô phỏng.


**Pre-Condition**: Thiết bị và kịch bản đã được cấu hình.


**Post-Condition**: Dữ liệu mô phỏng được sinh và tiếp nhận.


**Basic Flow**: 1. Chọn thiết bị.

2. Chọn kịch bản.
3. Khởi chạy.
4. Sinh sự kiện.
5. Gửi dữ liệu.
6. Hệ thống tiếp nhận và lưu.

**Alternative Flow**: Chạy đồng thời nhiều thiết bị.


**Exception Flow**: 


**Business Rule**: IoT chỉ mô phỏng/nhận sự kiện, không điều khiển thiết bị hàng không thật.


**Non-Functional**: NFR-004; NFR-009; NFR-017.

#### UC-44 - Xử lý dữ liệu thiết bị bất thường

**Thuộc tính**: Nội dung


**Use Case ID**: UC-44


**Use Case Name**: Xử lý dữ liệu thiết bị bất thường


**Use Case Description**: Phát hiện dữ liệu trùng, đến trễ, sai thứ tự, mất kết nối hoặc vượt ngưỡng.


**Actor**: Quản trị hệ thống; Quản trị dữ liệu


**Priority**: Should Have


**Trigger**: Dữ liệu bất thường được nhận.


**Pre-Condition**: Luồng dữ liệu thiết bị đang hoạt động.


**Post-Condition**: Bất thường được ghi nhận và cảnh báo.


**Basic Flow**: 1. Nhận dữ liệu.

2. Kiểm tra mã sự kiện.
3. Kiểm tra thời gian/thứ tự.
4. Kiểm tra giá trị.
5. Phân loại bất thường.
6. Phát cảnh báo.

**Alternative Flow**: Dữ liệu đến trễ nhưng hợp lệ → vẫn lưu với thời gian nguồn.


**Exception Flow**: E1. Thiếu trường bắt buộc → đưa vào danh sách dữ liệu lỗi.


**Business Rule**: Không điều khiển thiết bị thật.


**Non-Functional**: NFR-009; NFR-015; NFR-017.

### M16 - QUẢN TRỊ VÀ TÍCH HỢP
#### UC-45 - Quản lý người dùng và phân quyền

**Thuộc tính**: Nội dung


**Use Case ID**: UC-45


**Use Case Name**: Quản lý người dùng và phân quyền


**Use Case Description**: Quản lý tài khoản, vai trò, quyền, phạm vi dữ liệu và ủy quyền tạm thời.


**Actor**: Quản trị hệ thống


**Priority**: Must Have


**Trigger**: Actor tạo hoặc thay đổi tài khoản/quyền.


**Pre-Condition**: Actor có quyền quản trị.


**Post-Condition**: Tài khoản và quyền được cập nhật.


**Basic Flow**: 1. Chọn người dùng.

2. Xem quyền hiện tại.
3. Gán vai trò.
4. Chọn phạm vi dữ liệu.
5. Xác nhận.
6. Lưu.
7. Ghi nhật ký.

**Alternative Flow**: Đồng bộ thông tin từ nhà cung cấp định danh.


**Exception Flow**: 


**Business Rule**: BR-022; BR-023.


**Non-Functional**: NFR-005; NFR-006; NFR-007.

#### UC-46 - Quản lý nhật ký kiểm toán

**Thuộc tính**: Nội dung


**Use Case ID**: UC-46


**Use Case Name**: Quản lý nhật ký kiểm toán


**Use Case Description**: Ghi và tra cứu đăng nhập, truy cập dữ liệu nhạy cảm, thay đổi cấu hình và phê duyệt.


**Actor**: Quản trị hệ thống; An toàn và an ninh


**Priority**: Must Have


**Trigger**: Có sự kiện cần ghi nhật ký hoặc có nhu cầu tra cứu.


**Pre-Condition**: Cơ chế ghi nhật ký hoạt động.


**Post-Condition**: Sự kiện được lưu và có thể truy vết.


**Basic Flow**: 1. Nhận sự kiện.

2. Ghi thời gian.
3. Ghi người thực hiện.
4. Ghi hành động và kết quả.
5. Lưu an toàn.
6. Người có quyền tra cứu.

**Alternative Flow**: Xuất dữ liệu phục vụ kiểm tra.


**Exception Flow**: E1. Không đủ quyền → từ chối tra cứu.


**Business Rule**: BR-004; BR-010; BR-021; BR-022 tùy loại sự kiện.


**Non-Functional**: NFR-007; NFR-018.

#### UC-47 - Quản lý tích hợp hệ thống

**Thuộc tính**: Nội dung


**Use Case ID**: UC-47


**Use Case Name**: Quản lý tích hợp hệ thống


**Use Case Description**: Quản lý API, hàng đợi và trao đổi tệp với các hệ thống bên ngoài.


**Actor**: Quản trị hệ thống


**Priority**: Must Have


**Trigger**: Có nhu cầu cấu hình hoặc giám sát tích hợp.


**Pre-Condition**: Hệ thống đối tác và giao diện trao đổi đã được xác định.


**Post-Condition**: Luồng tích hợp hoạt động và được giám sát.


**Basic Flow**: 1. Chọn tích hợp.

2. Cấu hình giao diện.
3. Cấu hình xác thực.
4. Kiểm tra kết nối.
5. Kích hoạt.
6. Theo dõi trạng thái.
7. Ghi lỗi nếu có.

**Alternative Flow**: Trao đổi tệp thay vì API.


**Exception Flow**: 


**Business Rule**: Giao dịch quan trọng phải có khả năng truy vết và chống xử lý trùng.


**Non-Functional**: NFR-004; NFR-005; NFR-008; NFR-009; NFR-015.

#### UC-48 - Quản lý cấu hình và cảnh báo

**Thuộc tính**: Nội dung


**Use Case ID**: UC-48


**Use Case Name**: Quản lý cấu hình và cảnh báo


**Use Case Description**: Cấu hình danh mục, ngưỡng, mẫu thông báo, quy tắc và kênh cảnh báo.


**Actor**: Quản trị hệ thống; Quản trị dữ liệu


**Priority**: Must Have / Should Have


**Trigger**: Actor cần thay đổi cấu hình.


**Pre-Condition**: Actor có quyền đối với loại cấu hình.


**Post-Condition**: Cấu hình mới được lưu và ghi nhật ký.


**Basic Flow**: 1. Chọn nhóm cấu hình.

2. Xem giá trị hiện tại.
3. Thay đổi giá trị.
4. Kiểm tra hợp lệ.
5. Xác nhận.
6. Lưu.
7. Ghi lịch sử.

**Alternative Flow**: Thiết lập thời điểm bắt đầu có hiệu lực.


**Exception Flow**: E1. Giá trị không hợp lệ → không lưu.

E2. Không đủ quyền → từ chối.

**Business Rule**: Mọi thay đổi quan trọng phải có khả năng truy vết.


**Non-Functional**: NFR-005; NFR-007; NFR-009; NFR-014.


## 15. Phụ lục B — Công nghệ và ERD nguồn

Văn bản gốc trích xuất, bao gồm danh mục 20 thực thể và mã Mermaid có trong nguồn. Các diễn giải ở phần triển khai phải được phân biệt với baseline này.

```text
1. TÌM HIỂU CÔNG NGHỆ VÀ NGÔN NGỮ ĐỀ XUẤT
Dựa trên định hướng kiến trúc đã thống nhất trong tài liệu phân tích nghiệp vụ của nhóm, hệ thống lựa chọn kiến trúc tập trung với các công nghệ đề xuất chính:
Thành phần | Phương án
Frontend | HTML/CSS/JavaScript + Bootstrap hoặc Blade template
Backend | PHP 8.x + Laravel
CSDL | MySQL - mô hình tập trung
1.1. Lý do lựa chọn Frontend (HTML/CSS/JavaScript + Bootstrap hoặc Blade template)
Tối ưu hóa tốc độ phát triển (Rapid Development): Thư viện Bootstrap cung cấp sẵn hệ thống Grid Responsive chuẩn và các thành phần giao diện hoàn chỉnh (Navbar, Modal, Table, Form, Cards, Badges) giúp thành viên phụ trách Front-End (Phan Vinh) nhanh chóng xây dựng các màn hình quản trị phức tạp (Dashboard chỉ số, Danh sách chuyến bay, Bảng điều hành) mà không mất nhiều thời gian căn chỉnh CSS thủ công.
Tích hợp liền mạch qua Blade Template Engine: Việc sử dụng Blade Template của Laravel cho phép tái sử dụng layout (thông qua @extends, @include, @component), đổ dữ liệu trực tiếp từ Controller ra View mà không cần cấu hình phức tạp về CORS, State Management hay Client-side Routing như các thư viện SPA (React/Vue).
Trực quan, dễ tiếp cận và ổn định: Phù hợp với năng lực kỹ thuật của nhóm và tiến độ bàn giao sản phẩm theo từng tuần (Sprint 7 ngày). Giảm thiểu rủi ro lệch pha tích hợp giữa Front-End và Back-End.
Dễ dàng mở rộng thư viện ngoài: Thuận tiện nhúng các thư viện JavaScript trực quan hóa dữ liệu như Chart.js / ApexCharts cho phân hệ Báo cáo KPI (M14) và thư viện HTML5 QR Code Scanner phục vụ kiểm soát vé tại cửa ra máy bay (M09).
1.2. Lý do lựa chọn Backend (PHP 8.x + Laravel Framework)
Kiến trúc MVC chuẩn mực & Đầy đủ tính năng tích hợp sẵn (Batteries-Included): Laravel cung cấp sẵn toàn bộ các thành phần nền tảng thiết yếu của hệ thống hàng không: Hệ thống xác thực đăng nhập (Authentication), phân quyền theo vai trò RBAC (Gates & Policies), xác thực dữ liệu đầu vào (Form Request Validation), mã hóa bảo mật, Session và Task Scheduling. Đội ngũ Back-End (Toàn) có thể tập trung 100% vào logic nghiệp vụ điều hành bay thay vì phải tự xây dựng các tiện ích cơ bản.
Sức mạnh vượt trội của Eloquent ORM: Quản lý và thao tác với các mối quan hệ thực thể quan hệ (1-1, 1-N, N-N kèm bảng trung gian Pivot) cực kỳ tự nhiên, dễ đọc. Hỗ trợ cơ chế Eager Loading (with()) giúp tối ưu truy vấn, tránh hoàn toàn lỗi N+1 Query khi tải danh sách chuyến bay kèm thông tin sân bay, tàu bay và phi hành đoàn.
Hệ thống Migration & Database Seeder bằng code: Toàn bộ cấu trúc 20 bảng CSDL và dữ liệu danh mục ban đầu (Sân bay, Tuyến bay, Dòng máy bay) được quản lý phiên bản (version control) bằng code. Tất cả thành viên trong nhóm và QA (Hải Đạt) chỉ cần chạy lệnh php artisan migrate --seed là có ngay môi trường CSDL đồng nhất để kiểm thử.
Cải tiến hiệu năng mạnh mẽ trên PHP 8.x: Tối ưu hóa tốc độ nhờ trình biên dịch JIT (Just-In-Time), tính năng Union Types, Constructor Promotion, Attributes giúp mã nguồn Back-End ngắn gọn, sáng sủa và an toàn về kiểu dữ liệu.
1.3. Lý do lựa chọn Cơ sở dữ liệu (MySQL - mô hình tập trung)
Bảo đảm trọn vẹn chuẩn giao dịch ACID (qua Storage Engine InnoDB): Nghiệp vụ hàng không đòi hỏi độ chính xác tuyệt đối trong các thao tác: Giữ chỗ (Holding lock trong 15 phút theo quy tắc BR-011), trừ tồn chỗ ghế ngồi, thanh toán và phát hành vé. MySQL hỗ trợ cơ chế khóa cấp dòng (Row-Level Locking) và Transaction cô lập, ngăn chặn hoàn toàn tình trạng bán vượt quá số ghế (Overbooking).
Ràng buộc toàn vẹn dữ liệu cứng (Foreign Key Constraints): Mối quan hệ giữa Sân bay, Đường bay, Tàu bay, Chuyến bay, Tổ bay và Lịch bảo dưỡng được bảo vệ nghiêm ngặt ngay tại tầng CSDL. Không thể xảy ra tình trạng dữ liệu mồ côi (ví dụ: máy bay đang bay bị xóa nhầm, hoặc chuyến bay thiếu sân bay đến/đi theo quy tắc BR-003).
Phù hợp với định hướng "Mô hình tập trung - Baseline đề xuất": Ở giai đoạn MVP hiện tại, mô hình CSDL tập trung giúp đơn giản hóa việc thiết lập hạ tầng, sao lưu dự phòng (backup), đồng bộ dữ liệu dễ dàng, tránh sự phức tạp và chi phí vận hành không cần thiết của các hệ thống phân tán.
Thân thiện và phổ biến: MySQL là CSDL quan hệ tiêu chuẩn, vận hành mượt mà trên môi trường Local (XAMPP/Docker), giúp bạn Toàn và bạn Đạt (QA) dễ dàng quản trị, kiểm tra dữ liệu qua phpMyAdmin, MySQL Workbench hoặc DBeaver.
2. THỰC THỂ CSDL
User: Tài khoản người dùng/nhân viên (id, username, email, password_hash, full_name, status).
Role: Vai trò phân quyền hệ thống (id, role_code, role_name, description).
UserRole (Bảng trung gian N-N): Phân quyền tài khoản (user_id, role_id).
Airport: Sân bay (iata_code, icao_code, name, city, country, timezone).
Route: Đường bay (id, origin_airport_code, dest_airport_code, distance_km, standard_duration_minutes).
AircraftModel: Dòng máy bay (id, model_code, manufacturer, seat_capacity, turnaround_time_standard).
Aircraft: Tàu bay thực tế (tail_number, model_id, manufacture_year, total_flight_hours, operational_status).
FlightSchedule: Lịch bay kế hoạch (id, flight_number, route_id, model_id, departure_time_utc, arrival_time_utc, days_of_week).
OperationalFlight (Flight): Chuyến bay khai thác thực tế (id, schedule_id, flight_number, flight_date, route_id, aircraft_tail, scheduled_departure_utc, scheduled_arrival_utc, actual_departure_utc, actual_arrival_utc, flight_status).
CrewMember: Nhân sự tổ bay (id, user_id, crew_code, rank, base_airport_code).
FlightCrewAssignment (Bảng trung gian N-N): Phân công tổ bay cho chuyến bay (id, flight_id, crew_id, assigned_position, duty_start_utc, duty_end_utc).
PassengerProfile: Hồ sơ hành khách (id, user_id, id_card_passport, full_name, date_of_birth, gender, phone, email).
OrderBooking: Đơn đặt chỗ PNR (id, order_code, customer_user_id, total_amount, order_status, hold_expires_at).
OrderItem: Mục chi tiết trong đơn (id, order_id, flight_id, passenger_id, item_type, amount).
CheckInRecord: Thủ tục check-in và chọn chỗ (id, flight_id, passenger_id, seat_number, checkin_time_utc).
BoardingPassToken: Thẻ lên tàu bay mã QR (id, checkin_id, token_hash, valid_until, is_used, boarded_at_utc).
BaggageTag: Hành lý ký gửi (tag_code, flight_id, passenger_id, weight_kg, current_status).
BaggageTrackingEvent: Điểm quét hành trình hành lý (id, tag_code, event_type, scan_time_utc, scan_location, custody_unit).
WorkOrder: Lệnh bảo dưỡng kỹ thuật (id, work_order_number, aircraft_tail, check_type, lead_engineer_id, start_time, end_time, passed_safety).
TechnicalRelease: Chứng chỉ giải phóng kỹ thuật bay (id, work_order_id, aircraft_tail, inspector_id, is_cleared_for_flight, release_time_utc).
3. CÁC MỐI QUAN HỆ CHÍNH TRONG CSDL MYSQL
Mối quan hệ 1 - 1 (Một - Một):
User - CrewMember: Mỗi nhân sự tổ bay liên kết định danh với đúng 1 tài khoản User.
CheckInRecord - BoardingPassToken: Mỗi lần check-in thành công sinh ra duy nhất 1 thẻ lên tàu bay mã QR bảo mật.
WorkOrder - TechnicalRelease: Mỗi lệnh bảo dưỡng khi hoàn tất có 1 chứng chỉ ký duyệt giải phóng kỹ thuật.
Mối quan hệ 1 - N (Một - Nhiều):
Airport - Route: Một sân bay là điểm xuất phát hoặc điểm đến của nhiều đường bay.
Route - OperationalFlight: Một đường bay có nhiều chuyến bay khai thác.
AircraftModel - Aircraft: Một dòng tàu bay có nhiều máy bay trong đội bay.
Aircraft - OperationalFlight: Một máy bay vận hành nhiều chuyến bay ở các khung giờ khác nhau.
Aircraft - WorkOrder: Một máy bay trải qua nhiều đợt bảo dưỡng.
User - OrderBooking: Một khách hàng có thể đặt nhiều đơn hàng.
OrderBooking - OrderItem: Một đơn đặt chỗ bao gồm nhiều mục vé và dịch vụ.
OperationalFlight - CheckInRecord: Một chuyến bay có nhiều hành khách làm thủ tục.
OperationalFlight - BaggageTag: Một chuyến bay chuyên chở nhiều kiện hành lý ký gửi.
BaggageTag - BaggageTrackingEvent: Một kiện hành lý có nhiều sự kiện quét điểm theo hành trình.
Mối quan hệ N - N (Nhiều - Nhiều):
User - Role: Một người dùng có nhiều vai trò và một vai trò có nhiều người dùng. Giải quyết bằng bảng trung gian UserRole.
OperationalFlight - CrewMember: Một chuyến bay cần nhiều thành viên tổ bay và một thành viên tổ bay tham gia nhiều chuyến bay. Giải quyết bằng bảng trung gian FlightCrewAssignment.
4. SƠ ĐỒ MÔ HÌNH
4.1. Sơ đồ ERD
erDiagram
    %% ==========================================
    %% 1. CỤM QUẢN TRỊ
    %% ==========================================
    USER ||--o{ USER_ROLE : "has"
    ROLE ||--o{ USER_ROLE : "grants"
    USER ||--o| CREW_MEMBER : "profiles"
    USER ||--o{ ORDER_BOOKING : "places"

    %% ==========================================
    %% 2. CỤM KHAI THÁC BAY
    %% ==========================================
    AIRPORT ||--o{ ROUTE : "originates_or_terminates"
    ROUTE ||--o{ OPERATIONAL_FLIGHT : "schedules"
    AIRCRAFT_MODEL ||--o{ AIRCRAFT : "classifies"
    AIRCRAFT ||--o{ OPERATIONAL_FLIGHT : "operates"

    %% ==========================================
    %% 3. CỤM TỔ BAY
    %% ==========================================
    OPERATIONAL_FLIGHT ||--o{ FLIGHT_CREW_ASSIGNMENT : "requires"
    CREW_MEMBER ||--o{ FLIGHT_CREW_ASSIGNMENT : "assigned_to"

    %% ==========================================
    %% 4. CỤM ĐẶT CHỖ VÀ HÀNH KHÁCH
    %% ==========================================
    ORDER_BOOKING ||--o{ ORDER_ITEM : "contains"
    PASSENGER_PROFILE ||--o{ ORDER_ITEM : "travels_as"
    OPERATIONAL_FLIGHT ||--o{ ORDER_ITEM : "applies_to"

    %% ==========================================
    %% 5. CỤM THỦ TỤC VÀ HÀNH LÝ
    %% ==========================================
    OPERATIONAL_FLIGHT ||--o{ CHECKIN_RECORD : "registers"
    PASSENGER_PROFILE ||--o{ CHECKIN_RECORD : "checks_in"
    CHECKIN_RECORD ||--|| BOARDING_PASS_TOKEN : "issues_qr"
    OPERATIONAL_FLIGHT ||--o{ BAGGAGE_TAG : "carries"
    BAGGAGE_TAG ||--o{ BAGGAGE_TRACKING_EVENT : "tracks"

    %% ==========================================
    %% 6. CỤM BẢO DƯỠNG
    %% ==========================================
    AIRCRAFT ||--o{ WORK_ORDER : "maintains"
    WORK_ORDER ||--|| TECHNICAL_RELEASE : "certifies"

    %% ==========================================
    %% ĐỊNH NGHĨA THỰC THỂ: [TÊN THUỘC TÍNH] [KIỂU DỮ LIỆU] [KHÓA]
    %% ==========================================

    USER {
        id uuid PK
        username string
        email string
        password_hash string
        full_name string
        status string
    }

    ROLE {
        id int PK
        role_code string
        role_name string
        description string
    }

    USER_ROLE {
        user_id uuid FK
        role_id int FK
    }

    AIRPORT {
        iata_code string PK
        icao_code string
        name string
        city string
        timezone string
    }

    ROUTE {
        id int PK
        origin_airport_code string FK
        dest_airport_code string FK
        distance_km int
        flight_duration_minutes int
    }

    AIRCRAFT_MODEL {
        id int PK
        model_code string
        manufacturer string
        seat_capacity int
        turnaround_time_minutes int
    }

    AIRCRAFT {
        tail_number string PK
        model_id int FK
        manufacture_year int
        total_flight_hours int
        operational_status string
    }

    OPERATIONAL_FLIGHT {
        id uuid PK
        flight_number string
        flight_date date
        route_id int FK
        aircraft_tail string FK
        scheduled_dep_utc timestamp
        scheduled_arr_utc timestamp
        flight_status string
    }

    CREW_MEMBER {
        id uuid PK
        user_id uuid FK
        crew_code string
        rank string
        base_airport_code string
    }

    FLIGHT_CREW_ASSIGNMENT {
        id uuid PK
        flight_id uuid FK
        crew_id uuid FK
        assigned_position string
        duty_start_utc timestamp
        duty_end_utc timestamp
    }

    ORDER_BOOKING {
        id uuid PK
        order_code string
        customer_user_id uuid FK
        total_amount decimal
        order_status string
        hold_expires_at timestamp
    }

    PASSENGER_PROFILE {
        id uuid PK
        id_card_passport string
        full_name string
        dob date
        gender string
        phone string
    }

    ORDER_ITEM {
        id uuid PK
        order_id uuid FK
        flight_id uuid FK
        passenger_id uuid FK
        item_type string
        amount decimal
    }

    CHECKIN_RECORD {
        id uuid PK
        flight_id uuid FK
        passenger_id uuid FK
        seat_number string
        checkin_time_utc timestamp
    }

    BOARDING_PASS_TOKEN {
        id uuid PK
        checkin_id uuid FK
        token_hash string
        is_used boolean
        valid_until timestamp
        boarded_at_utc timestamp
    }

    BAGGAGE_TAG {
        tag_code string PK
        flight_id uuid FK
        weight_kg float
        status string
    }

    BAGGAGE_TRACKING_EVENT {
        id bigint PK
        tag_code string FK
        event_type string
        scan_time_utc timestamp
        location string
        custody_unit string
    }

    WORK_ORDER {
        id uuid PK
        work_order_number string
        aircraft_tail string FK
        check_type string
        start_time timestamp
        end_time timestamp
        passed_safety boolean
    }

    TECHNICAL_RELEASE {
        id uuid PK
        work_order_id uuid FK
        aircraft_tail string FK
        release_time_utc timestamp
        is_cleared_for_flight boolean
    }

 
4.2. Phân cụm liên kết
Cụm Quản trị: User liên kết N-N với Role qua UserRole, User liên kết 1-1 với CrewMember, User liên kết 1-N với OrderBooking.
Cụm Khai thác bay: Airport liên kết 1-N với Route, Route liên kết 1-N với OperationalFlight, AircraftModel liên kết 1-N với Aircraft, Aircraft liên kết 1-N với OperationalFlight.
Cụm Tổ bay: OperationalFlight liên kết N-N với CrewMember qua FlightCrewAssignment.
Cụm Đặt chỗ và Hành khách: OrderBooking liên kết 1-N với OrderItem, PassengerProfile liên kết 1-N với OrderItem, OperationalFlight liên kết 1-N với OrderItem.
Cụm Thủ tục và Hành lý: OperationalFlight và PassengerProfile liên kết 1-N với CheckInRecord, CheckInRecord liên kết 1-1 với BoardingPassToken. OperationalFlight liên kết 1-N với BaggageTag, BaggageTag liên kết 1-N với BaggageTrackingEvent.
Cụm Bảo dưỡng: Aircraft liên kết 1-N với WorkOrder, WorkOrder liên kết 1-1 với TechnicalRelease.
```

## 16. Phụ lục C — Hai mẫu kiểm thử nguồn

### C.1 Unit Test

```text
HỌC VIỆN CÔNG NGHỆ BƯU CHÍNH VIỄN THÔNG
MÔN: QUẢN LÝ DỰ ÁN PHẦN MỀM
UNIT TEST - KẾ HOẠCH & MẪU TEST CASE
Người phụ trách tài liệu: Bùi Văn Đạt - B23DCCN126
Dự án: Phần mềm Quản lý Hàng không Thông minh
Nhóm 05 - Tuần 1 (17/09/2026 - 24/09/2026)
1. Nguyên tắc
Các test case dưới đây là baseline/mẫu tuần 1. Trạng thái mặc định là Not Run vì chưa có build ổn định để thực thi. Không được đổi sang Passed/Failed nếu chưa chạy test thực tế.
2. Mẫu test case
ID | Phạm vi/Actor | Kịch bản | Input/Bước chính | Expected Result | Status
UT-LOGIN-01 | AuthService | Đăng nhập đúng tài khoản | User hợp lệ | Trả token/session và role | Not Run
UT-FLIGHT-01 | FlightService | Tạo chuyến với dữ liệu hợp lệ | Route/aircraft hợp lệ | Lưu chuyến thành công | Not Run
UT-FLIGHT-02 | FlightService | Từ chối giờ đến <= giờ đi | Thời gian không hợp lệ | Validation error | Not Run
UT-BOOK-01 | BookingService | Tạo booking đủ thông tin | Passenger + flight | Booking ID được tạo | Not Run
3. Mẫu ghi kết quả khi thực thi
Build/Version | Ngày chạy | Tester | Actual Result | Status | Bug ID nếu fail


1. Nguyên tắc
Test case được thiết kế từ Use Case, Business Rule và mô hình dữ liệu hiện có. Tất cả để Not Run vì chưa có build/source thực thi trong bộ Drive được đối chiếu.
ID | Phạm vi | Kịch bản | Input/Bước chính | Expected Result | Status
UT-AUTH-01 | Auth/RBAC | Đăng nhập hợp lệ | Tài khoản hợp lệ | Tạo session/token và trả đúng role | Not Run
UT-AUTH-02 | Auth/RBAC | Từ chối role không đủ quyền | Nhân viên gọi chức năng quản trị | Access denied/403; không thay đổi dữ liệu | Not Run
UT-AIRPORT-01 | Airport | Tạo sân bay có định danh duy nhất | IATA/ICAO hợp lệ | Lưu sân bay thành công | Not Run
UT-ROUTE-01 | Route | Tạo đường bay với 2 sân bay hợp lệ | Origin != Destination | Lưu route, FK hợp lệ | Not Run
UT-FLIGHT-01 | Flight | Tạo chuyến hợp lệ | Route, aircraft, giờ đi/đến | Lưu chuyến thành công | Not Run
UT-FLIGHT-02 | Flight | Từ chối giờ đến <= giờ đi | Arrival <= Departure | Validation error | Not Run
UT-FLIGHT-03 | Flight | Kiểm tra xung đột tàu bay | Hai chuyến chồng thời gian | Phát hiện xung đột theo BR-005 | Not Run
UT-CREW-01 | Crew | Từ chối tổ bay không đủ điều kiện | Crew thiếu khả dụng/năng định | Không cho phân công | Not Run
UT-BOOK-01 | Booking | Tạo booking đủ dữ liệu | Passenger + Flight + Seat | Tạo Booking ID | Not Run
UT-BOOK-02 | Booking | Giải phóng giữ chỗ hết hạn | Hold quá timeout | Ghế được trả về tồn chỗ theo BR-011 | Not Run
UT-TICKET-01 | Ticket/QR | Sinh QR cho vé hợp lệ | Ticket hợp lệ | QR/token liên kết đúng vé | Not Run
UT-CHECKIN-01 | Check-in | Kiểm tra điều kiện check-in | Flight + Passenger hợp lệ | Cho phép check-in khi đủ điều kiện | Not Run
UT-BAG-01 | Baggage | Tạo thẻ hành lý | Passenger + Flight + Bag | Tag duy nhất, liên kết đúng hành trình | Not Run
UT-MAINT-01 | Maintenance | Tính/cảnh báo bảo dưỡng đến hạn | Aircraft + chu kỳ | Sinh cảnh báo đến hạn | Not Run
UT-MAINT-02 | Maintenance | Chặn khai thác khi bảo dưỡng quá hạn | Maintenance overdue | Aircraft unavailable theo BR-007 | Not Run
UT-DASH-01 | Dashboard | Lọc KPI theo thời gian | Khoảng ngày hợp lệ | Trả đúng tập dữ liệu/filter | Not Run
UT-AI-01 | AI Delay | Nhận bộ feature chuyến bay | Weather/traffic/rotation features | Trả probability/expected delay theo schema | Not Run
UT-AI-02 | AI Anomaly | Phát hiện mẫu cảm biến bất thường | Temp/Humidity/Pressure/Rain | Trả anomaly flag/score | Not Run
UT-IOT-01 | IoT | Đọc dữ liệu cảm biến mô phỏng | DHT22/BMP280/Rain Sensor | Sinh payload JSON đúng schema | Not Run
UT-AUDIT-01 | Audit | Ghi log thay đổi quan trọng | Actor + action + before/after | Audit log có actor/time/action | Not Run
2. Phần chưa điền
Actual Result, Build/Version, ngày chạy, thời gian chạy và Bug ID chưa điền vì chưa có build/source để thực thi. Khi developer bàn giao module, QA bổ sung kết quả thật thay vì suy đoán.
```

### C.2 Acceptance Test/UAT

Các hàng tuần 1 có ô trống/lệch được giữ nguyên để đối chiếu; sử dụng quy tắc chuẩn hóa ở mục 3 khi tạo test case thực thi.

```text
HỌC VIỆN CÔNG NGHỆ BƯU CHÍNH VIỄN THÔNG
MÔN: QUẢN LÝ DỰ ÁN PHẦN MỀM
ACCEPTANCE TEST / UAT - KẾ HOẠCH & MẪU
Người phụ trách tài liệu: Bùi Văn Đạt - B23DCCN126
Dự án: Phần mềm Quản lý Hàng không Thông minh
Nhóm 05 - Tuần 1 (17/09/2026 - 24/09/2026)
1. Nguyên tắc
Các test case dưới đây là baseline/mẫu tuần 1. Trạng thái mặc định là Not Run vì chưa có build ổn định để thực thi. Không được đổi sang Passed/Failed nếu chưa chạy test thực tế.
2. Mẫu test case
ID | Phạm vi/Actor | Kịch bản | Input/Bước chính | Expected Result | Status
UAT-01 | PM/nhóm | Đăng nhập và phân quyền | Đúng chức năng theo role | Not Run | 
UAT-02 | PM/nhóm | Quản lý chuyến/lịch bay | CRUD + trạng thái hoạt động | Not Run | 
UAT-03 | PM/nhóm | Hành khách/booking/vé/QR | Luồng demo hoàn chỉnh | Not Run | 
UAT-04 | PM/nhóm | AI/IoT mô phỏng | Có cảnh báo và dashboard | Not Run | 
3. Mẫu ghi kết quả khi thực thi
Build/Version | Ngày chạy | Tester | Actual Result | Status | Bug ID nếu fail


1. Nguyên tắc
Các tiêu chí nghiệm thu được suy ra trực tiếp từ scope, Use Case và Business Analysis. Chưa thể ký nghiệm thu ở tuần 2 vì chưa có Release Candidate.
ID | Yêu cầu nghiệm thu | Tiêu chí chấp nhận | Status
UAT-01 | Đăng nhập & phân quyền | Quản lý/Nhân viên chỉ sử dụng chức năng được cấp | Not Run
UAT-02 | Quản lý sân bay | Thêm/sửa/xem dữ liệu sân bay và định danh duy nhất | Not Run
UAT-03 | Đường bay & lịch/chuyến bay | Tạo route/flight và kiểm tra xung đột cơ bản | Not Run
UAT-04 | Đội tàu bay | Theo dõi trạng thái/khả dụng tàu bay | Not Run
UAT-05 | Tổ bay | Phân công theo khả dụng/điều kiện | Not Run
UAT-06 | Hành khách | Quản lý hồ sơ hành khách đúng quyền | Not Run
UAT-07 | Booking | Tìm chuyến và tạo booking/giữ ghế | Not Run
UAT-08 | Vé & QR | Phát hành vé/QR mô phỏng từ booking | Not Run
UAT-09 | Check-in/Boarding | Check-in và quét QR cập nhật trạng thái | Not Run
UAT-10 | Hành lý | Tạo tag và theo dõi hành trình hành lý | Not Run
UAT-11 | Dịch vụ mặt đất | Theo dõi nhiệm vụ và tiến độ quay đầu | Not Run
UAT-12 | Bảo dưỡng | Theo dõi lịch/work order và cảnh báo quá hạn | Not Run
UAT-13 | Dashboard | Hiển thị KPI và bộ lọc dữ liệu | Not Run
UAT-14 | AI | Có dự đoán/cảnh báo hỗ trợ quyết định; không tự thay thế người có thẩm quyền | Not Run
UAT-15 | IoT mô phỏng | Wokwi phát telemetry; hệ thống nhận và cảnh báo | Not Run
```

## 17. Phụ lục D — Thứ tự xử lý M01–M06 từ báo cáo Activity/Sequence

Nội dung mô tả trích từ báo cáo; không bao gồm ảnh sơ đồ nhúng.

```text
BÁO CÁO SƠ ĐỒ ACTIVITY VÀ SEQUENCE
HỆ THỐNG QUẢN LÝ HÀNG KHÔNG THÔNG MINH
Phạm vi: 6 module đầu tiên (M01 – M06)
1. Phạm vi trình bày
Báo cáo này chỉ trình bày sơ đồ Activity và Sequence cho sáu module đầu tiên của hệ thống. Nội dung nghiệp vụ trong hai tài liệu đã cung cấp chỉ được dùng làm cơ sở để xác định luồng xử lý, actor và các bước trao đổi; báo cáo không chép lại các phần phân tích nghiệp vụ hoặc đặc tả Use Case đã có.
Mỗi module gồm hình sơ đồ, phần mô tả ngắn theo cách trình bày của báo cáo sinh viên và mã PlantUML tương ứng. Các sơ đồ tập trung vào luồng chính cùng một số nhánh ngoại lệ quan trọng để vừa bám sát đặc tả vừa tránh làm hình quá rối.
1. M01 - Dữ liệu nền và sân bay
Module M01 quản lý các dữ liệu nền gồm sân bay, tài nguyên sân bay và dữ liệu chủ. Luồng xử lý tập trung vào thao tác thêm/sửa, kiểm tra tính hợp lệ, lưu dữ liệu và ghi nhận phiên bản đối với dữ liệu chủ. Nội dung sơ đồ được xây dựng trong phạm vi UC-01 đến UC-03.
1.1. Activity Diagram
Hình 1.1. Sơ đồ Activity của M01 - Dữ liệu nền và sân bay
Sơ đồ Activity của M01 mô tả quá trình quản trị dữ liệu nền. Người quản trị chọn nhóm dữ liệu cần thao tác, nhập hoặc chỉnh sửa thông tin, sau đó hệ thống kiểm tra quyền, dữ liệu bắt buộc, mã trùng và các quan hệ liên quan. Nếu dữ liệu hợp lệ thì hệ thống lưu dữ liệu; riêng dữ liệu chủ có thêm bước gửi phê duyệt và lưu phiên bản. Cách mô tả này giúp thấy rõ các trường hợp dữ liệu sai hoặc người dùng không đủ quyền sẽ bị dừng trước khi ghi vào hệ thống.
1.2. Sequence Diagram
Hình 1.2. Sơ đồ Sequence của M01 - Dữ liệu nền và sân bay
Sơ đồ Sequence thể hiện quá trình trao đổi giữa Quản trị dữ liệu, giao diện M01, dịch vụ dữ liệu nền, bộ kiểm tra và cơ sở dữ liệu. Sau khi người dùng gửi dữ liệu, dịch vụ nghiệp vụ chuyển sang bộ kiểm tra để đối chiếu trước khi lưu. Nếu phát hiện lỗi, hệ thống trả thông báo về giao diện; nếu hợp lệ, dữ liệu được cập nhật và trả kết quả thành công.
2. M02 - Đường bay và lịch bay
Module M02 gồm quản lý đường bay, lập và công bố lịch bay, đồng thời kiểm tra xung đột trước khi công bố. Sơ đồ thể hiện rõ việc lịch chỉ được chuyển sang bước phê duyệt khi dữ liệu đầy đủ và các xung đột chính đã được xử lý. Nội dung sơ đồ được xây dựng trong phạm vi UC-04 đến UC-06.
2.1. Activity Diagram
Hình 2.1. Sơ đồ Activity của M02 - Đường bay và lịch bay
Ở M02, luồng nghiệp vụ được chia thành phần quản lý đường bay và phần lập lịch bay. Đường bay phải hợp lệ trước khi được dùng để tạo lịch. Khi lập lịch, hệ thống tiếp tục kiểm tra dữ liệu nguồn lực và xung đột về tàu bay, tài nguyên sân bay hoặc thời gian quay đầu. Chỉ khi không còn xung đột thì lịch mới được chuyển sang bước phê duyệt và công bố.
2.2. Sequence Diagram
Hình 2.2. Sơ đồ Sequence của M02 - Đường bay và lịch bay
Sơ đồ Sequence cho thấy dữ liệu đường bay được kiểm tra trước, sau đó Điều hành khai thác tạo phương án lịch bay. Dịch vụ lịch bay gọi bộ kiểm tra xung đột để lấy thông tin nguồn lực từ cơ sở dữ liệu. Nếu có lỗi, lịch được giữ ở trạng thái chờ; nếu hợp lệ, người điều hành xác nhận công bố và hệ thống lưu phiên bản lịch.
3. M03 - Điều hành chuyến bay
Module M03 theo dõi chuyến bay từ khi được khởi tạo từ lịch đã công bố cho tới khi cập nhật trạng thái và xử lý bất thường. Khi phát hiện gián đoạn, hệ thống hỗ trợ tổng hợp thông tin, còn phương án xử lý cuối cùng do điều hành khai thác lựa chọn. Nội dung sơ đồ được xây dựng trong phạm vi UC-07 đến UC-09.
3.1. Activity Diagram
Hình 3.1. Sơ đồ Activity của M03 - Điều hành chuyến bay
Sơ đồ Activity của M03 bắt đầu từ lịch bay đã công bố. Hệ thống khởi tạo chuyến bay, tiếp nhận các sự kiện mới và cập nhật các mốc thời gian. Khi phát hiện bất thường, hệ thống hiển thị cảnh báo để Điều hành khai thác lựa chọn phương án xử lý. Trước khi áp dụng, phương án tiếp tục được kiểm tra ràng buộc để tránh cập nhật một phương án không hợp lệ.
3.2. Sequence Diagram
Hình 3.2. Sơ đồ Sequence của M03 - Điều hành chuyến bay
Trong Sequence Diagram, lịch bay và hệ thống AODB/FIDS là nguồn cung cấp dữ liệu đầu vào. Dịch vụ điều hành chuyến bay ghi nhận sự kiện, so sánh kế hoạch với thực tế và gọi bộ kiểm tra cảnh báo. Với trường hợp có bất thường, quyết định xử lý vẫn do Điều hành khai thác thực hiện, sau đó hệ thống kiểm tra và lưu lại dữ liệu cùng lý do xử lý.
4. M04 - Đội tàu bay
Module M04 quản lý hồ sơ tàu bay và theo dõi mức độ sử dụng theo giờ bay, chu kỳ. Dữ liệu sử dụng được kiểm tra trùng trước khi cộng dồn và được đối chiếu với các ngưỡng để phục vụ việc theo dõi khả dụng và bảo dưỡng. Nội dung sơ đồ được xây dựng trong phạm vi UC-10 đến UC-11.
4.1. Activity Diagram
Hình 4.1. Sơ đồ Activity của M04 - Đội tàu bay
M04 có hai luồng chính là quản lý hồ sơ tàu bay và theo dõi sử dụng tàu bay. Khi cập nhật hồ sơ, số đăng ký và dữ liệu tàu bay được kiểm tra trước khi lưu. Với dữ liệu giờ bay và chu kỳ, hệ thống phải kiểm tra bản ghi trùng để tránh cộng lặp, đồng thời kiểm tra ngưỡng sử dụng nhằm phục vụ theo dõi tình trạng khai thác và bảo dưỡng.
4.2. Sequence Diagram
Hình 4.2. Sơ đồ Sequence của M04 - Đội tàu bay
Sơ đồ Sequence mô tả Quản lý đội bay thao tác qua giao diện M04 để xem hoặc cập nhật hồ sơ. Dữ liệu sử dụng từ chuyến bay được gửi vào dịch vụ đội tàu bay và được đối chiếu với cơ sở dữ liệu. Nếu là bản ghi mới, hệ thống cập nhật giờ bay, chu kỳ và kết quả kiểm tra ngưỡng; nếu trùng thì không cộng lại số liệu.
5. M05 - Bảo dưỡng
Module M05 bao quát việc lập lịch bảo dưỡng, quản lý lệnh bảo dưỡng và giải phóng kỹ thuật. Tàu bay chỉ được cập nhật sang trạng thái khả dụng khi công việc đã hoàn thành, có xác nhận kỹ thuật và không vi phạm điều kiện quá hạn. Nội dung sơ đồ được xây dựng trong phạm vi UC-12 đến UC-14.
5.1. Activity Diagram
Hình 5.1. Sơ đồ Activity của M05 - Bảo dưỡng
Sơ đồ Activity của M05 mô tả quy trình từ lập lịch bảo dưỡng đến khi giải phóng kỹ thuật. Trước tiên, kỹ thuật kiểm tra số liệu sử dụng, xác định hạng mục đến hạn và lập lịch. Khi thực hiện bảo dưỡng, hệ thống theo dõi nhân lực, vật tư, kết quả và xác nhận kỹ thuật. Tàu bay chỉ chuyển sang trạng thái khả dụng khi công việc hoàn tất, không quá hạn và có đủ phê duyệt cần thiết.
5.2. Sequence Diagram
Hình 5.2. Sơ đồ Sequence của M05 - Bảo dưỡng
Trong Sequence Diagram, Kỹ thuật bảo dưỡng làm việc qua giao diện M05, còn dịch vụ bảo dưỡng xử lý dữ liệu với cơ sở dữ liệu và bộ kiểm tra quy tắc. Các bước kiểm tra xung đột kế hoạch, nguồn lực, vật tư và điều kiện giải phóng được thực hiện trước khi cập nhật trạng thái cuối cùng của tàu bay.
6. M06 - Tổ bay
Module M06 quản lý hồ sơ, khả dụng và phân công tổ bay. Trước khi lưu hoặc phê duyệt phương án, hệ thống phải kiểm tra năng định, giấy phép, thời gian nhiệm vụ, nghỉ ngơi và các xung đột liên quan; khi có thay đổi thì việc thay thế tiếp tục được kiểm tra lại và lưu lịch sử. Nội dung sơ đồ được xây dựng trong phạm vi UC-15 đến UC-17.
6.1. Activity Diagram
Hình 6.1. Sơ đồ Activity của M06 - Tổ bay
M06 tập trung vào hồ sơ và phân công tổ bay. Hệ thống kiểm tra năng định, giấy phép, đào tạo và lịch khả dụng trước khi lưu hồ sơ. Khi phân công cho chuyến bay, các điều kiện về vị trí, thời gian nhiệm vụ, thời gian nghỉ và xung đột được kiểm tra. Nếu phát sinh thay thế, nhân sự dự bị cũng phải được kiểm tra lại trước khi cập nhật phân công chính thức.
6.2. Sequence Diagram
Hình 6.2. Sơ đồ Sequence của M06 - Tổ bay
Sơ đồ Sequence cho thấy Điều hành tổ bay gửi thông tin qua giao diện M06, dịch vụ tổ bay lấy hồ sơ và lịch khả dụng từ cơ sở dữ liệu rồi gọi bộ kiểm tra quy tắc. Với phương án hợp lệ, hệ thống lưu phân công, gửi thông báo cho thành viên tổ bay, nhận xác nhận và lưu lịch sử. Trường hợp thay thế cũng đi qua bước kiểm tra điều kiện trước khi cập nhật.
```

## 18. Phụ lục E — Kiến trúc tổng thể và phụ lục Release 1

Giữ nguồn tham khảo dưới đây để không bỏ mất ranh giới tích hợp và thông tin phiên bản. Khi lựa chọn công nghệ/phạm vi, áp dụng quyết định ưu tiên ở mục 3: Laravel/Blade/Bootstrap/MySQL và toàn bộ 16 phân hệ.

```text
HỌC VIỆN CÔNG NGHỆ BƯU CHÍNH VIỄN THÔNG
MÔN: QUẢN LÝ DỰ ÁN PHẦN MỀM
LẬP SƠ ĐỒ KIẾN TRÚC TỔNG THỂ CHO PHẠM VI NHÓM
Đề tài: Xây dựng Phần mềm Quản lý Hàng không Thông minh
Tích hợp AI, QR, IoT mô phỏng và phân tích dữ liệu
1. MỤC TIÊU VÀ CĂN CỨ THIẾT KẾ
Báo cáo này thực hiện yêu cầu lập sơ đồ kiến trúc tổng thể cho phạm vi nhóm, xác định ranh giới giữa Frontend - Backend - Cơ sở dữ liệu - AI/IoT, đồng thời đề xuất mô hình kiến trúc làm cơ sở xây dựng bộ khung code cho hệ thống Quản lý Hàng không Thông minh.
Căn cứ phân tích nghiệp vụ, hệ thống cần hỗ trợ quản lý sân bay, đường bay, lịch bay, điều hành chuyến bay, đội tàu bay, bảo dưỡng, tổ bay, hành khách, đặt chỗ, thanh toán, check-in, boarding bằng QR, hành lý, dịch vụ mặt đất, tài chính nghiệp vụ, dashboard, AI hỗ trợ, IoT mô phỏng, quản trị và tích hợp API. Các thành phần AI và IoT chỉ đóng vai trò hỗ trợ; quyết định nghiệp vụ quan trọng vẫn thuộc về người có thẩm quyền.
1.1. Mục tiêu kiến trúc
Tách rõ trách nhiệm giữa giao diện, xử lý nghiệp vụ, lưu trữ dữ liệu và các dịch vụ AI/IoT.
Cho phép nhóm phát triển các phân hệ song song nhưng vẫn sử dụng chung quy ước dữ liệu và API.
Đảm bảo các luồng nghiệp vụ cốt lõi như đặt chỗ, thanh toán, boarding và đối soát không bị xử lý trùng.
Dễ bảo trì, dễ kiểm thử, có thể mở rộng khi số sân bay, chuyến bay, hành khách, hành lý và thiết bị tăng.
Tạo cơ sở để sau này tách một số module thành dịch vụ độc lập nếu quy mô dự án tăng.
1.2. Nguyên tắc thiết kế
Nguyên tắc | Áp dụng trong hệ thống
Phân tách trách nhiệm | Frontend chỉ hiển thị và gửi yêu cầu; Backend xử lý nghiệp vụ; CSDL chịu trách nhiệm lưu trữ; AI/IoT là dịch vụ hỗ trợ.
Module hóa | Mỗi miền nghiệp vụ như Flight, Crew, Booking, Baggage, Maintenance được tổ chức thành module rõ ràng.
API-first | Frontend, AI, IoT và hệ thống bên ngoài giao tiếp thông qua REST API, webhook, event hoặc MQTT tùy loại.
Security by design | Áp dụng xác thực, RBAC, audit log, mã hóa và nguyên tắc quyền tối thiểu ngay từ kiến trúc.
Human-in-the-loop | Kết quả AI không tự ghi đè dữ liệu nguồn và các khuyến nghị nhạy cảm phải được người dùng xác nhận.
2. PHẠM VI KIẾN TRÚC HỆ THỐNG
Phạm vi kiến trúc bám theo các phân hệ nghiệp vụ đã xác định trong tài liệu phân tích. Hệ thống được xem như một nền tảng quản lý thống nhất, có một nguồn dữ liệu dùng chung cho chuyến bay và các nguồn lực liên quan, đồng thời cung cấp cơ chế tích hợp với hệ thống ngoài.
Mã | Phân hệ | Nội dung chính
M01 | Dữ liệu nền và sân bay | Sân bay, nhà ga, cửa, vị trí đỗ, múi giờ, mã tham chiếu
M02 | Đường bay và lịch bay | Mạng đường bay, mùa lịch, slot, tần suất, công bố lịch
M03 | Điều hành chuyến bay | Trạng thái, mốc thời gian, phân bổ nguồn lực, bất thường
M04 | Đội tàu bay | Hồ sơ, cấu hình, khả dụng, giờ bay, chu kỳ
M05 | Bảo dưỡng | Kế hoạch, hạn, work order, vật tư, giải phóng kỹ thuật
M06 | Tổ bay | Hồ sơ, năng định, khả dụng, phân công, thay thế
M07 | Hành khách và khách hàng | Hồ sơ, liên hệ, nhu cầu hỗ trợ, consent
M08 | Đặt chỗ và đơn hàng | Tìm kiếm, giữ chỗ, giá, dịch vụ, thanh toán, hoàn đổi
M09 | Check-in và boarding | Chọn chỗ, QR, boarding, no-show
M10 | Hành lý | Tag, quét, hành trình, custody, bất thường, trả hành lý
M11 | Dịch vụ mặt đất | Nhiệm vụ, nhà cung cấp, SLA, mốc quay đầu
M12 | Tài chính nghiệp vụ | Doanh thu, phí, hoàn tiền, đối soát, chứng từ
M13 | Thông báo và chăm sóc | Thông báo đa kênh, khiếu nại, lịch sử tương tác
M14 | AI và phân tích dữ liệu | Dự báo, cảnh báo, khuyến nghị, dashboard
M15 | IoT mô phỏng | Thiết bị, telemetry, kịch bản, sự kiện, cảnh báo
M16 | Quản trị và tích hợp | Người dùng, quyền, cấu hình, audit, API, giám sát
3. KIẾN TRÚC TỔNG THỂ ĐỀ XUẤT
Phương án được đề xuất là kiến trúc phân lớp kết hợp MVC ở tầng ứng dụng web và Modular Monolith ở Backend. Cách tiếp cận này phù hợp với phạm vi đồ án vì vừa bảo đảm cấu trúc rõ ràng, vừa tránh độ phức tạp vận hành của microservices ở giai đoạn đầu.
Hình 1. Sơ đồ kiến trúc tổng thể hệ thống Quản lý Hàng không Thông minh
3.1. Luồng tương tác tổng quát
Người dùng thao tác trên giao diện Web/Portal hoặc thiết bị nghiệp vụ có giao diện tương ứng.
Frontend gửi request qua HTTPS đến Backend API.
Controller tiếp nhận request, kiểm tra dữ liệu đầu vào và chuyển xử lý cho Service.
Service áp dụng quy tắc nghiệp vụ, gọi Repository/DAO để truy xuất hoặc cập nhật CSDL.
Khi cần AI hoặc IoT, Backend gọi dịch vụ chuyên biệt qua REST API, event hoặc MQTT.
Kết quả được Backend chuẩn hóa và trả về Frontend; các thay đổi quan trọng được ghi audit log.
4. RANH GIỚI FRONTEND - BACKEND - CSDL - AI/IoT
Thành phần | Trách nhiệm chính | Không được thực hiện
Frontend | Hiển thị giao diện; nhập dữ liệu; kiểm tra định dạng cơ bản; gọi API; hiển thị dashboard, QR, trạng thái và cảnh báo. | Không truy cập CSDL trực tiếp; không tự quyết định quy tắc nghiệp vụ quan trọng.
Backend | Xác thực, phân quyền, xử lý nghiệp vụ, điều phối module, kiểm tra quy tắc, tích hợp hệ thống ngoài, audit. | Không phụ thuộc giao diện cụ thể; không để Controller chứa toàn bộ business logic.
Cơ sở dữ liệu | Lưu dữ liệu nghiệp vụ, trạng thái, lịch sử, log, cấu hình; đảm bảo khóa, ràng buộc và truy vết. | Không chứa logic giao diện; không để ứng dụng ngoài truy cập trực tiếp nếu chưa qua lớp tích hợp.
AI Service | Dự báo, chấm điểm rủi ro, gợi ý phương án, giải thích kết quả, quản lý phiên bản mô hình. | Không tự ghi đè dữ liệu nguồn hoặc tự phê duyệt quyết định an toàn/tài chính.
IoT Simulator/Broker | Phát và nhận telemetry mô phỏng, kiểm thử mất kết nối, trễ, sai thứ tự, dữ liệu bất thường. | Không điều khiển thiết bị hàng không thật trong phạm vi bài tập.
Hệ thống bên ngoài | Cung cấp/nhận dữ liệu đặt chỗ, sân bay, hành lý, kỹ thuật, thanh toán, ERP, CRM, Identity. | Không được xem là nguồn dữ liệu nội bộ nếu chưa xác định vai trò nguồn chuẩn và cơ chế đối soát.
4.1. Frontend
Frontend được tổ chức theo các nhóm giao diện nghiệp vụ: giao diện hành khách, giao diện nhân viên sân bay, giao diện điều hành và quản trị, cùng các màn hình dashboard. Frontend có thể dùng một công nghệ web hiện đại như React, Vue hoặc Angular; lựa chọn cụ thể không ảnh hưởng đến ranh giới kiến trúc miễn là giao tiếp với Backend thông qua API.
4.2. Backend
Backend là trung tâm nghiệp vụ của hệ thống. Mỗi module có Controller, Service và Repository riêng nhưng dùng chung cơ chế xác thực, phân quyền, logging, exception handling và cấu hình. Các nghiệp vụ liên miền được điều phối tại Service/Application Layer thay vì để các Controller gọi trực tiếp CSDL.
4.3. Cơ sở dữ liệu
CSDL nghiệp vụ chính nên sử dụng hệ quản trị quan hệ như PostgreSQL hoặc MySQL vì phần lớn dữ liệu có quan hệ chặt chẽ và cần giao dịch. Các bảng sự kiện hoặc telemetry có thể tách riêng theo nhu cầu hiệu năng, nhưng trong phạm vi nhóm vẫn có thể đặt cùng một hệ CSDL với schema riêng để đơn giản hóa triển khai.
4.4. AI và IoT
AI và IoT được tách thành dịch vụ hỗ trợ. AI nhận dữ liệu đã chuẩn hóa từ Backend hoặc Data Layer, trả về xác suất, điểm rủi ro hoặc khuyến nghị. IoT Simulator phát telemetry có eventId, deviceId, eventType, eventTime, receivedTime, value và sequence để kiểm thử khả năng xử lý trùng, sự kiện đến trễ và sai thứ tự.
5. MÔ HÌNH KIẾN TRÚC MVC KẾT HỢP MODULAR MONOLITH
MVC được dùng để phân tách giao diện và điều khiển luồng yêu cầu, trong khi Modular Monolith tổ chức Backend thành các module nghiệp vụ có ranh giới rõ ràng. Mô hình này phù hợp với đội sinh viên vì dễ triển khai, dễ debug và vẫn tạo nền tảng để mở rộng sau này.
Thành phần MVC | Ánh xạ trong hệ thống | Ví dụ
View | Frontend/Presentation | Màn hình lịch bay, dashboard, đặt chỗ, check-in, quản lý hành lý.
Controller | API Controller | FlightController, BookingController, BaggageController, MaintenanceController.
Model | Domain Entity + DTO + Service + Repository | Flight, Aircraft, Passenger, Booking, Baggage, Payment, CrewAssignment.
5.1. Vì sao không chọn microservices ngay từ đầu
Phạm vi nghiệp vụ lớn nhưng nhóm phát triển và môi trường triển khai của đồ án có giới hạn.
Microservices làm tăng số lượng service, cấu hình mạng, cơ chế retry, tracing và bài toán nhất quán dữ liệu.
Modular Monolith vẫn cho phép tách module độc lập về code, dependency và dữ liệu logic.
Khi có nhu cầu mở rộng, các module tải cao như Notification, AI, IoT hoặc Payment Integration có thể tách ra trước.
5.2. Quy tắc phụ thuộc giữa các lớp
Lớp | Được phép gọi | Không nên gọi trực tiếp
Controller | Service, Validator, Auth Context | Repository/CSDL
Service | Repository, Domain Service, Integration Client | UI
Repository | ORM/Data Source | Controller
Integration Client | API ngoài, MQTT, webhook | Giao diện người dùng
AI/IoT Adapter | AI Service, IoT Broker | CSDL nghiệp vụ nếu chưa qua Backend
6. KIẾN TRÚC DỮ LIỆU
Các đối tượng Chuyến bay, Đơn hàng/Hành khách, Tàu bay, Tổ bay, Hành lý và Nhiệm vụ khai thác là các miền dữ liệu trung tâm. Mỗi thay đổi quan trọng cần ghi nhận thời gian, nguồn phát sinh, người thực hiện và trạng thái để phục vụ audit, dashboard và AI.
Nhóm thực thể | Vai trò
Airport / Route | Dữ liệu nền sân bay và đường bay
FlightSchedule / OperationalFlight | Lịch công bố và chuyến bay thực tế
Aircraft / Maintenance | Tàu bay, giờ bay, chu kỳ, kế hoạch và work order
CrewMember / CrewAssignment | Hồ sơ tổ bay và phân công
Passenger / OrderBooking | Hành khách, hành trình, giá, dịch vụ và trạng thái đơn
Payment | Thanh toán, hoàn tiền, trạng thái đối soát
BoardingPass / SeatCheckin | Ghế, check-in, QR token và trạng thái sử dụng
Baggage / BaggageEvent | Tag hành lý, hành trình, điểm quét, custody
GroundTask / Incident | Nhiệm vụ mặt đất và sự cố/ngoại lệ
IoTDeviceEvent | Telemetry thiết bị mô phỏng
FinancialEntry | Dòng tài chính nghiệp vụ và trạng thái đối soát
AuditLog | Đăng nhập, thay đổi dữ liệu, phê duyệt và truy cập nhạy cảm
6.1. Quy tắc dữ liệu quan trọng
Sử dụng định danh duy nhất cho sân bay, đường bay, chuyến bay, tàu bay, nhân sự, đơn hàng và hành lý.
Lưu thời gian nguồn và thời gian chuẩn để đối chiếu xuyên múi giờ.
Áp dụng idempotency cho đặt chỗ, thanh toán, boarding và các luồng callback/webhook.
Tách dữ liệu cá nhân, dữ liệu bí mật và dữ liệu công khai theo mức kiểm soát truy cập.
Audit log phải ghi chủ thể, hành động, thời điểm và kết quả; các thay đổi phê duyệt phải có lịch sử.
7. KIẾN TRÚC AI, QR VÀ IOT MÔ PHỎNG
7.1. AI Service
AI được xác định là công cụ hỗ trợ dự báo và gợi ý, không thay thế quyết định của nhân viên. Backend cung cấp dữ liệu đầu vào cho AI Service và nhận kết quả kèm độ tin cậy, phiên bản mô hình và thời điểm dự báo.
Bài toán AI | Đầu ra kiến trúc | Điểm kiểm soát
Dự báo chậm chuyến | Xác suất chậm và yếu tố chính | Điều hành quyết định
Dự báo nhu cầu | Tải dự kiến theo đường bay | Hoạch định xem xét
Cảnh báo hành lý rủi ro | Danh sách hành lý cần can thiệp | Nhân viên xác nhận
Bảo dưỡng dự báo | Rủi ro hoặc thời điểm cần kiểm tra | Kỹ thuật phê duyệt
Tối ưu phân bổ nguồn lực | Phương án xếp lịch | Điều hành lựa chọn
Phát hiện bất thường tài chính | Giao dịch đáng chú ý | Tài chính điều tra
7.2. QR
QR được dùng như token hoặc mã định danh an toàn thay vì chứa dữ liệu cá nhân nhạy cảm ở dạng rõ. Thẻ lên tàu bay cần có token, chuyến/chặng, phiên bản và thời hạn; Backend kiểm tra chữ ký, trạng thái sử dụng, thu hồi và chống quét lặp.
7.3. IoT mô phỏng
Thiết bị mô phỏng phát sự kiện telemetry qua MQTT hoặc event. Backend hoặc IoT Adapter phải xử lý được mất kết nối, retry, bản ghi trùng, dữ liệu đến trễ và sai thứ tự. Trong phạm vi bài tập, hệ thống không điều khiển thiết bị hàng không thật.
8. KIẾN TRÚC TÍCH HỢP
Hệ thống không hoạt động độc lập mà cần tích hợp với các hệ thống đặt chỗ, sân bay, hành lý, kỹ thuật, thanh toán, tài chính, chăm sóc khách hàng và định danh. Kiến trúc nên sử dụng lớp Integration Adapter để tách giao thức bên ngoài khỏi logic nghiệp vụ nội bộ.
Hệ thống ngoài | Dữ liệu chính | Cơ chế đề xuất
PSS / GDS / DCS | Lịch, tồn chỗ, đặt chỗ, check-in, boarding | API hoặc message
AODB / FIDS | Tài nguyên sân bay, trạng thái chuyến | API hoặc event
BHS / BRS | Sự kiện và đối chiếu hành lý | Event hoặc message
MRO / hệ kỹ thuật | Tàu bay, hạn, work order, giải phóng | API hoặc file
Crew Management / HRM | Hồ sơ, lịch, năng định, phân công | API
Payment Gateway | Thanh toán, hoàn tiền, callback, đối soát | API + webhook
ERP Finance | Doanh thu, công nợ, bút toán | API hoặc file
CRM / Notification | Khách hàng, email, SMS, push | API
Identity Provider | SSO, MFA, tài khoản, nhóm | OIDC / SAML / SCIM
IoT Broker | Telemetry và trạng thái thiết bị | MQTT hoặc event
Data Platform | Sự kiện, dữ liệu phân tích, mô hình AI | Stream / ETL / ELT
9. YÊU CẦU PHI CHỨC NĂNG ẢNH HƯỞNG ĐẾN KIẾN TRÚC
Nhóm | Yêu cầu kiến trúc
Tính sẵn sàng | Có kế hoạch failover, bảo trì và phân loại chức năng trọng yếu.
Hiệu năng | API phổ biến cần phản hồi trong ngưỡng thỏa thuận ở tải bình thường và cao điểm.
Mở rộng | Thiết kế theo module, hỗ trợ mở rộng theo sân bay, chuyến bay, hành khách, hành lý và người dùng.
Nhất quán | Idempotency và cơ chế đối chiếu cho booking, payment, boarding và reconciliation.
Bảo mật | Xác thực mạnh, RBAC, mã hóa, quản lý secret, audit và phân quyền tối thiểu.
Phục hồi | Xác định RTO/RPO, backup và kiểm thử khôi phục.
Giám sát | Theo dõi ứng dụng, tích hợp, hàng đợi, dữ liệu và mô hình AI; cảnh báo có người phụ trách.
Bảo trì | Mô-đun hóa, tài liệu hóa, cấu hình hóa và kiểm thử hồi quy.
AI | Versioning mô hình, theo dõi drift, baseline, rollback và human-in-the-loop.
IoT | Chịu được mất kết nối, gửi lặp, trễ, sai thứ tự và dữ liệu vượt ngưỡng.
10. BỘ KHUNG CODE ĐỀ XUẤT
Bộ khung code nên phản ánh trực tiếp ranh giới kiến trúc để mỗi thành viên có thể phát triển module mà không phá vỡ cấu trúc chung. Ví dụ cấu trúc thư mục như sau:
smart-airport/
├── frontend/
│   ├── passenger-web/
│   └── admin-web/
├── backend/
│   ├── common/
│   │   ├── security/
│   │   ├── exception/
│   │   └── config/
│   ├── modules/
│   │   ├── airport/
│   │   ├── flight/
│   │   ├── aircraft/
│   │   ├── maintenance/
│   │   ├── crew/
│   │   ├── passenger/
│   │   ├── booking/
│   │   ├── checkin/
│   │   ├── baggage/
│   │   ├── groundservice/
│   │   ├── finance/
│   │   └── reporting/
│   └── integrations/
│       ├── payment/
│       ├── identity/
│       └── external-airport/
├── ai-service/
│   ├── delay-prediction/
│   ├── demand-forecast/
│   └── predictive-maintenance/
├── iot-simulator/
│   ├── devices/
│   ├── telemetry/
│   └── mqtt/
└── database/
    ├── migrations/
    ├── schema/
    └── seed/
10.1. Cấu trúc bên trong một module Backend
flight/
├── controller/
│   └── FlightController
├── service/
│   └── FlightService
├── repository/
│   └── FlightRepository
├── model/
│   └── Flight
├── dto/
│   ├── FlightRequest
│   └── FlightResponse
├── mapper/
└── validator/
10.2. Quy ước luồng xử lý
Frontend -> Controller -> Service -> Repository -> CSDL.
Backend -> Integration Adapter -> hệ thống ngoài.
Backend -> AI Client -> AI Service -> kết quả dự báo -> Backend -> người dùng xác nhận.
IoT Simulator -> MQTT/Event -> IoT Adapter -> kiểm tra trùng/thứ tự -> lưu sự kiện -> cảnh báo/dashboard.
11. PHƯƠNG ÁN TRIỂN KHAI GIAI ĐOẠN ĐỒ ÁN
Ở giai đoạn đồ án, nhóm có thể triển khai tối giản theo mô hình 4 thành phần: Frontend, Backend, Database và AI/IoT Service. Các thành phần chạy bằng container hoặc tiến trình riêng trên cùng máy chủ phát triển. Khi cần trình diễn tích hợp, có thể dùng MQTT broker và mock server cho các hệ thống ngoài.
Thành phần | Gợi ý triển khai
Frontend | Web app chạy qua Nginx/Vite dev server hoặc tương đương.
Backend | Một ứng dụng Modular Monolith, cung cấp REST API và WebSocket khi cần cập nhật gần thời gian thực.
Database | PostgreSQL/MySQL, migration quản lý bằng công cụ của framework.
AI Service | Service Python độc lập, expose REST API; có thể dùng mô hình giả lập nếu chưa huấn luyện mô hình thật.
IoT | MQTT broker + simulator phát telemetry theo kịch bản.
External systems | Mock API/webhook để kiểm thử payment, identity, AODB/FIDS, BHS/BRS.
12. KẾT LUẬN
Kiến trúc được đề xuất đáp ứng yêu cầu tách rõ Frontend - Backend - CSDL - AI/IoT, đồng thời phù hợp với phạm vi lớn nhưng nguồn lực triển khai ở mức đồ án. MVC giúp chuẩn hóa luồng giao diện - điều khiển - mô hình, còn Modular Monolith giúp chia hệ thống thành các module nghiệp vụ độc lập tương đối mà không tạo ra gánh nặng vận hành của microservices.
Phương án này cũng phù hợp với các yêu cầu về bảo mật, audit, tính nhất quán, tích hợp, AI có kiểm soát và IoT mô phỏng trong tài liệu phân tích nghiệp vụ. Đây có thể được sử dụng trực tiếp làm cơ sở thống nhất cấu trúc repository, phân chia công việc và bắt đầu xây dựng bộ khung code của nhóm.
PHỤ LỤC ĐỒNG BỘ TUẦN 3 - BỔ SUNG NGÀY 13/10/2026
Dữ liệu cần chủ tài liệu xác minh: ngày họp số 3 <nhập thông tin>; kết quả thực thi/bằng chứng Release 1 <nhập thông tin>; người duyệt và ngày review <nhập thông tin>. Chữ đỏ đánh dấu phần bổ sung/điều chỉnh đến tuần 3.
Quy ước: Toàn bộ phần chữ đỏ là nội dung bổ sung/đề xuất sau bản gốc; các nội dung trước đó được giữ nguyên để đối chiếu. Nguồn: bản thảo họp lần 3, bảng phân công ngày 29/09 và tài liệu tuần 1-2 trong kho Nhóm 05. Không khẳng định code/test đã hoàn thành khi chưa có bằng chứng.
Mốc lịch: tuần 3 được dùng làm nhãn cập nhật sau họp lần 3; ngày họp chính thức chưa được xác minh từ biên bản đã ký. Mốc 01/10, 08/10, 10/10 là mốc kế hoạch cần đối chiếu kết quả thực tế.
PHỤ LỤC KIẾN TRÚC THỰC THI RELEASE 1
BE: PHP 8.x + Laravel; CSDL: MySQL chạy với XAMPP; FE: Bootstrap + JavaScript; công cụ hỗ trợ: Codex/Claude. Các phiên bản cụ thể và cấu hình đã chạy phải được xác nhận bằng README hoặc repo.
Gói triển khai Module 1 (quản lý cốt lõi): sân bay, đường bay, máy bay, chuyến bay, lịch bay và tổ bay/đội bay. Không đồng nhất nhãn “Module 1” của kế hoạch nhóm với mã M01 của kiến trúc tổng thể.
Mô hình tham chiếu cho nhóm: Browser (Bootstrap/JS) → Laravel HTTP API/Controllers → Model/Service → MySQL (XAMPP). AI/IoT không là điều kiện chạy Module 1.
API contract cần liệt kê base URL, endpoint, auth/RBAC, mã lỗi, paging/filter, tên trường nhất quán; schema migration và `.env.example` không chứa secret.
Mapping M01=Airport; M02=Route/Schedule; M03=Flight; M04=Aircraft; M06=Crew; M05=Maintenance nằm ngoài module thực thi được giao.
```
