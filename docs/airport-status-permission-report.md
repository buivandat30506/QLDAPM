# Trạng thái sân bay và quyền Giám đốc

**Cập nhật mới nhất 10/11/2026:** bốn nhãn sân bay dùng Normal/High/Overload/Maintenance; demo v2 phân bố mục tiêu 15/4/2/1 cho 22 sân bay và có nhận biết nguồn Demo theo yêu cầu mới. Quyền Giám đốc giữ nguyên. Xem [báo cáo hiện hành](airport-operational-status-reset-report.md); phân bố v1 và các cách trình bày nguồn ở phần lịch sử bên dưới đã được thay thế.

Ngày 09/11/2026. Phạm vi: trạng thái cấp sân bay trong M01, dữ liệu mô phỏng local/test và quyền cập nhật có phạm vi. Không thay đổi quy tắc khai thác M02/M03/M18, không kích hoạt Multi-role WIP, không commit/push.

## 1. Kết quả khảo sát và quyết định kiến trúc

Danh sách role thực trong `Access::ROLES`: passenger, airport_agent, finance, admin, data_admin, operations, ops_manager, fleet_manager, maintenance, crew_scheduler, ground_agent, customer_service, analyst, integration_admin, auditor. Không có Giám đốc sân bay/hệ thống. Runtime dùng `users.role`, có ngoại lệ admin cho quyền nhân viên. `UserRole` và migration `add_user_roles` là phần Multi-role đang phát triển; lượt này giữ nguyên, không dùng làm căn cứ tự cấp quyền.

Vì admin kỹ thuật không mặc định là Giám đốc, endpoint trạng thái **không dùng `Access::allows(...,'data_admin')` để cho phép ghi** nữa. Tạo quyền nghiệp vụ riêng `airport.operational_status.update`, biểu diễn bằng bảng bổ nhiệm `mysql.airport_status_authorities`:

| Trường | Ý nghĩa |
|---|---|
| user_id | FK users trên cùng node mysql/db2 |
| position | airport_director: Giám đốc sân bay; system_director: Giám đốc cấp hệ thống |
| airport_scope | Mã sân bay được giao; `*` chỉ được cấp chủ đích cho system_director |
| active | Bổ nhiệm còn hiệu lực hay đã thu hồi |
| reason | Căn cứ bổ nhiệm/thu hồi, bắt buộc |
| created_at / updated_at | Thời điểm ghi nhận |

Unique (user_id,airport_scope). Một người có thể có nhiều phạm vi cụ thể; đây là bổ nhiệm có giới hạn cho đúng quyền trạng thái, **không phải triển khai Multi-role cho cả hệ thống**. Sân bay nằm ở node ops nên không tạo FK xuyên node; service kiểm tra mã sân bay tồn tại.

Không tự thêm primary role, đổi role người dùng, hoặc cấp quyền cho admin/QA theo tên tài khoản. Chưa có giao diện quản trị bổ nhiệm; quản trị viên có quyền truy cập console triển khai dùng lệnh dưới đây theo quyết định tổ chức. Chưa tự bổ nhiệm tài khoản nào trong database làm việc vì người dùng chưa chỉ định danh tính/phạm vi người được bổ nhiệm. Các bổ nhiệm dùng để kiểm thử nằm hoàn toàn trong database cô lập.

## 2. Ma trận quyền và phạm vi

| Người dùng | Xem danh sách/chi tiết sân bay | Sửa trạng thái toàn sân bay | Sửa hạ tầng/hồ sơ chung |
|---|---|---|---|
| data_admin | Theo quyền M01 hiện có | Không, trừ khi có bổ nhiệm đúng phạm vi | Theo quyền cũ, không cấp thêm |
| admin kỹ thuật | Theo quyền đọc M01 hiện có | **Không tự có**; cần bổ nhiệm như mọi người khác | Giữ chính sách cũ ở các luồng khác |
| Nhân viên thuộc nhóm Access::SCOPED | Chỉ sân bay gắn với tài khoản; xem trạng thái, không có form sửa | Không nếu chưa được bổ nhiệm | Không vì được đọc trạng thái mà được sửa M01 |
| airport_director được bổ nhiệm | Phạm vi sân bay được giao, cộng quyền đọc nhân viên đã có | Chỉ mã sân bay có bổ nhiệm active | Không tự cấp quyền sửa hạ tầng/hồ sơ |
| system_director được bổ nhiệm | Các mã được giao hoặc toàn bộ nếu có `*` rõ ràng | Tương ứng đúng phạm vi active | Không tự cấp các quyền khác |
| Role khác chưa có quyền đọc/phạm vi hoặc passenger | Không mở quyền M01 tùy tiện | Không | Không đổi |

Nhóm SCOPED hiện hữu gồm airport_agent, operations, ops_manager, ground_agent, integration_admin. Quyền đọc trạng thái chỉ bổ sung vào danh sách/chi tiết sân bay và link tương ứng; phần cơ sở hạ tầng vẫn dùng quyền M01 cũ. Không biến mọi nhân viên thành data_admin. Người có primary role khác nhưng được bổ nhiệm hợp lệ được đọc đúng các sân bay để thực hiện nhiệm vụ Giám đốc.

`AirportController::status` kiểm tra bổ nhiệm ở Backend trước validation, rồi khóa bản ghi người dùng và kiểm tra lại trước transaction ghi trên ops. Luồng bổ nhiệm cũng khóa người dùng, tránh một yêu cầu cập nhật dùng quyền vừa bị thu hồi đồng thời. Không tạo transaction phân tán hoặc FK xuyên node; ghi trạng thái và audit của nó cùng transaction ops. Cần tránh mở đường ghi SQL trực tiếp ngoài các service đã kiểm soát.

## 3. Cách bổ nhiệm/thu hồi có chủ đích

Ví dụ dưới đây là cú pháp sử dụng; **thay ID bằng người đã được tổ chức chỉ định**, không chạy cho một tài khoản bất kỳ chỉ vì có role admin:

```sh
php artisan airline:airport-status-authority USER_ID airport_director --airport=HAN --reason="Quyết định bổ nhiệm và phạm vi quản lý HAN"
php artisan airline:airport-status-authority USER_ID system_director --airport=HAN --airport=SGN --reason="Phạm vi quản lý được giao"
php artisan airline:airport-status-authority USER_ID system_director --all-airports --reason="Quyết định quản lý toàn hệ thống"
php artisan airline:airport-status-authority USER_ID airport_director --airport=HAN --revoke --reason="Quyết định thu hồi phạm vi HAN"
```

Thu hồi `*` không tự thu hồi các phạm vi cụ thể còn active; phải thu hồi từng phạm vi nếu quyết định yêu cầu như vậy. Command từ chối airport_director phạm vi `*`, role passenger/không hợp lệ, tài khoản disabled và sân bay không tồn tại. Audit mysql `airport.status.authority` ghi người được bổ nhiệm, vị trí/phạm vi/hiệu lực trước–sau; căn cứ lưu trên bản ghi bổ nhiệm. Actor console là NULL, không giả một người dùng web đã thực hiện. Console hiện không có khái niệm danh tính quản trị OS gắn vào users; cần quy trình vận hành lưu quyết định/nhật ký console nếu triển khai thật.

Đây không phải endpoint web tự cấp quyền và không có nút nâng quyền cho người đăng nhập. Vai trò/quyền khác không được sửa theo lệnh này.

## 4. Dữ liệu trạng thái và nguồn

Giữ các cột cũ: operational_status, operational_status_note, operational_status_updated_by, operational_status_updated_at và audit ops. Giữ đúng bốn mã NORMAL/HIGH/OVERLOAD/MAINTENANCE; NULL vẫn là chưa xác định.

Migration mới `2026_11_09_020000_add_airport_status_authorities_and_sources.php` thêm ba cột nullable:

- `operational_status_source`: SIMULATION hoặc MANUAL; NULL giữ nghĩa nguồn cũ chưa được gắn nhãn.
- `operational_status_simulation_key`: định danh bộ dữ liệu `airport-status-local-v1`.
- `operational_status_simulation_fingerprint`: SHA-256 để xác định dữ liệu mô phỏng còn nguyên vẹn khi dọn.

Không sửa `active`, flights, allocations, tài nguyên hoặc trạng thái toàn sân bay từ lịch M18/condition hạ tầng. Không có thuật toán tính ngưỡng HIGH/OVERLOAD. Không xóa ghi chú/người/thời điểm của trạng thái thủ công cũ để tạo mô phỏng.

## 5. Seed mô phỏng an toàn và có thể chạy lại

Command không tự chạy trong migration, seeder demo, lịch cron hoặc khi mở trang:

```sh
php artisan airline:simulate-airport-statuses
php artisan airline:simulate-airport-statuses --apply
php artisan airline:simulate-airport-statuses --clear
php artisan airline:simulate-airport-statuses --clear --apply
```

Không có `--apply` chỉ xem trước. Cả seed và clear đều kiểm tra APP_ENV local/testing và host ops là node cục bộ được cho phép; production bị từ chối. Lệnh dành cho CLI có chủ đích, không có nút seed trên web.

Bộ v1 có mapping cố định theo **mã sân bay**, không phụ thuộc thứ tự sort hoặc ngẫu nhiên. Không đổi kết quả của sân bay cũ khi thêm mã mới:

| Trạng thái mô phỏng | Mã sân bay trong kế hoạch cố định |
|---|---|
| NORMAL | BMV, DIN, HUI, THD, VCL, VII |
| HIGH | CAH, DLI, PQC, TBB, VCS, VKG |
| OVERLOAD | CXR, HAN, PXU, UIH, VDH |
| MAINTENANCE | DAD, HPH, SGN, VCA, VDO |

Đây là phân bố trình bày, **không phải đánh giá thực tế các sân bay này**. Mã không có trong mapping bị bỏ qua. Mã sân bay chỉ được seed khi toàn bộ trường trạng thái/ghi chú/người/thời điểm/nguồn/key/fingerprint đều NULL. Bất kỳ dấu hiệu đã ghi nhận trước đó đều được bảo toàn, kể cả trạng thái NULL nhưng có ghi chú hoặc người/thời điểm cập nhật.

Khi seed: note ghi rõ “Dữ liệu mô phỏng phục vụ kiểm thử và trình bày; không phải thông tin vận hành thực tế.”, source SIMULATION, actor NULL, có timestamp; audit ops `airport.operational_status.simulated` lưu trước–sau. Chạy lại không ghi đè hoặc đổi timestamp/fingerprint của dữ liệu đã seed.

## 6. Chuyển sang dữ liệu thật và dọn mô phỏng

Giám đốc có quyền mở chi tiết, chọn trạng thái, nhập lý do, tích xác nhận rồi submit. Request chưa xác nhận bị từ chối cả ở HTML và Backend (`confirmed=accepted`). Khi ghi: source MANUAL, lưu người/thời điểm/lý do, xóa key/fingerprint mô phỏng và audit `airport.operational_status.updated` giữ snapshot trước–sau. Không thay đổi trạng thái khi chỉ đổi dropdown, mở trang hoặc chưa submit.

Clear chỉ chọn source SIMULATION và đúng key v1, sau đó so fingerprint với toàn bộ bộ trường trạng thái được seed. Nếu đã có thay đổi ngoài luồng khiến fingerprint không khớp, báo skipped, không xóa. Trạng thái được Giám đốc xác nhận đã thành MANUAL nên không thuộc diện dọn. Với dữ liệu mô phỏng còn nguyên, clear đưa các trường trạng thái về NULL, có audit `airport.operational_status.simulation.cleared`; không đổi `active` hoặc hồ sơ sân bay.

Trước vận hành thật: backup; chạy clear dry-run, rà soát eligible/skipped, chạy clear --apply cho dữ liệu mô phỏng còn nguyên; rà soát từng skipped; Giám đốc xác nhận dữ liệu thật theo phạm vi. Không chạy lại seed local trên môi trường thật. UI luôn gắn nhãn “Dữ liệu mô phỏng” cạnh badge và giải thích nguồn; không dùng tên người giả cho actor console.

## 7. Giao diện và audit

NORMAL xanh lá, HIGH vàng/cam, OVERLOAD đỏ, MAINTENANCE tím; có chấm tròn, chữ và chú giải. Trạng thái mô phỏng có nhãn riêng, tooltip và cảnh báo trong chi tiết. Tìm kiếm, phân trang SQL và liên kết chi tiết giữ nguyên.

Người đọc không thấy form trạng thái; Giám đốc thấy form theo đúng sân bay. Quyền sửa hồ sơ chung và duyệt master data được giữ tách biệt; có quyền trạng thái không được sửa name/city/timezone hoặc hạ tầng. Lịch sử thể hiện nguồn công cụ mô phỏng hoặc người cập nhật thật; thông báo thành công/thất bại dùng luồng feedback/validation hiện hữu.

## 8. Backup, migration và kiểm thử

Snapshot trước thay đổi: `backend/storage/app/private/status-before.json`. Backup đầy đủ ba node: `backups/20261109T153211Z-18453/{db1,db2,db3}.sql.gz`, đã kiểm tra gzip và restore SQL thật vào schema mới `status_verify_20261109` trên cả ba node. Kiểm tra migration lên/xuống/lên giữ nguyên hash các bảng legacy. Chỉ migration mới được chọn theo --path; không chạy Multi-role/analytics hoặc migrate:fresh/db:wipe.

Down migration được thử trên bản khôi phục chưa có dữ liệu provenance/bổ nhiệm mới. Nó từ chối rollback nếu đã có source trạng thái hoặc bản ghi bổ nhiệm, để tránh mất dữ liệu mới. Không chạy rollback trên database làm việc sau khi seed; khi chuyển mô hình cần migration dữ liệu được rà soát, không xóa cột mới tùy ý.

`airline_test` dùng PHPUnit có guard database/user; bản khôi phục dùng browser, account đúng vai trò và sân bay TST riêng. Không dùng quyền QA ngoại lệ để né chính sách; admin kỹ thuật cũng bị từ chối ghi trong browser/test khi không có bổ nhiệm.

Đã áp dụng riêng migration mới và seed có chủ đích trên database **airline cục bộ lúc 23:44:36 ngày 09/11/2026 (UTC+7)**. Kết quả: 22 trạng thái mô phỏng — NORMAL 6, HIGH 6, OVERLOAD 5, MAINTENANCE 5. Trước khi seed có 0 sân bay đã ghi nhận thủ công; các trường hợp cần bảo vệ thủ công trước/sau seed được kiểm thử trên database riêng. Chạy lại không đổi bản ghi; clear dry-run nhận diện đủ 22 bản mô phỏng còn nguyên, không thực hiện clear trên dữ liệu trình bày đang dùng.

**Không có bổ nhiệm mới trên database làm việc** (`airport_status_authorities` có 0 dòng). Không thay đổi role/quyền có sẵn, người dùng, active, 644 hạ tầng, 987 chuyến hoặc 2.918 phân bổ. Hash của 17 bảng dữ liệu legacy và nội dung audit cũ trên ba node khớp trước/sau; chỉ các trường trạng thái/nguồn và audit mô phỏng mới được ghi theo yêu cầu.

Kiểm thử đạt **62 tests, 704 assertions**; sau điều chỉnh quyền đọc theo sân bay, chạy lại bộ liên quan đạt **28 tests, 366 assertions**. Browser Chrome/Playwright đạt 13 trang × 6 độ rộng, không pageerror JavaScript hoặc tràn ngang trang, có kiểm tra direct API 403 cho data_admin/admin chưa bổ nhiệm, 403 ngoài phạm vi, 422 thiếu xác nhận và submit form Giám đốc chuyển SIMULATION→MANUAL.

Các chức năng đã kiểm tra gồm: từ chối production; dry-run; đủ bốn trạng thái; replay không đổi; bảo vệ thủ công trước/sau seed; clear và fingerprint; role/scope/thu hồi; reason/confirmation/audit; trạng thái không ảnh hưởng flights/active; tương thích đề nghị master data cũ. Chưa kiểm thử production, nhân sự/bổ nhiệm thật hoặc khả năng chịu tải lớn.

## 9. Giới hạn và nội dung cần BA/quản trị xác nhận

- Chưa có mapping giữa bổ nhiệm Giám đốc với chức danh/đơn vị trong hệ thống nhân sự. Chưa có UI bổ nhiệm hoặc approval workflow cho bổ nhiệm; CLI yêu cầu quy trình quyết định có trách nhiệm.
- Chưa bổ nhiệm người dùng thật tự động. Người dùng cần chỉ định ID, cấp chức vụ và phạm vi để quản trị vận hành cấp đúng người. Đây là dữ liệu tổ chức thiếu, không phải lý do cấp mặc định cho admin/QA.
- Chính sách admin của các phân hệ khác không thay đổi; quyền trạng thái là ngoại lệ kiểm soát riêng. Những bất nhất test admin của lượt trước không được giải quyết bằng cách mở rộng hay thu hẹp toàn bộ RBAC.
- Khi Multi-role hoàn thiện, cần adapter/migration được rà soát để đưa quyền này vào mô hình chung, giữ bổ nhiệm và audit; không đọc bảng WIP chưa tích hợp như quyền đã có.
- Môi trường production/database dùng chung không được áp dụng hoặc kiểm thử trong lượt này. Không coi dữ liệu mô phỏng là đo tải, thông báo khai thác hoặc điều kiện an toàn.

## 10. File thay đổi và nghiệm thu

Danh sách đầy đủ file của lượt này được tập hợp trong [báo cáo trạng thái/vị trí hạ tầng](infrastructure-status-location-report.md), cùng kết quả tests/browser và kiểm tra bảo toàn dữ liệu.
