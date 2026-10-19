# Defects

| ID | Mức | Hiện trạng ban đầu | Trạng thái |
| --- | --- | --- | --- |
| SEC-001 | High | API passenger công khai, PNR/tag/passenger ID truy cập chéo | Đã bảo vệ; MySQL AccessFoundationTest / PassengerProfileTest kiểm auth/ownership/airport; file ownership chờ module upload |
| SEC-002 | High | ADMIN bypass quyền duyệt kỹ thuật/tài chính | Đã bỏ bypass; tests admin-only bị403 technical release; vai trò tài chính đích chưa triển khai |
| SEC-003 | High | Booking updateOrCreate theo passport có thể ghi đè người khác | Đã thêm ownership guard; regression overwrite legacy booking còn cần mở rộng ở G3 |
| BOOK-001 | High | Client gửi seat_price/baggage_price; float money | Đã loại giá client khỏi Form Request/service, cộng minor units; local-v1/VND provenance trên đơn. FleetManagementTest focused SQLite PASS (14 assertions), MySQL migration/test chưa chạy; fare engine thật chưa có, giữ High/Open đến khi tariff được duyệt và MySQL integration đạt. |
| OPS-VIEW-001 | Medium | Trang chi tiết chuyến lỗi PHP khi render do Blade directives `@endcan`/`@endif` sát nhau | Đã tách directive. FlightOperationsTest scope detail PASS trong regression SQLite; cần lặp lại trong browser acceptance. |
| BOOK-002 | High | confirm trực tiếp không callback ký, expiry update rollback khi throw | G3 Pending |
| QR-001 | High | Token hash DB dùng làm bearer; thiếu wrong-flight/revoke/reissue/no-show | G3 Pending |
| AUDIT-001 | Medium | Log before/after không redact, thiếu correlation/outcome | Đã bổ sung redact/correlation/outcome; tests MySQL/Chrome đạt; retention và lịch sử log cũ chưa xử lý đầy đủ |
| SEED-001 | Medium | Demo password cố định; chưa chặn production | Chặn ngoài local/testing; local mới dùng random; password cố định chỉ fixture testing; chưa đủ demo dataset mục11.1 |
| MYSQL-JSON-001 | High | Native JSON làm callback event gửi lại bị coi là khác payload | Đã sửa canonical comparison, Unit + MySQL event replay đạt; giữ log lỗi ban đầu |
| BOOK-RACE-001 | High | Đọc customer trước lock tạo snapshot cũ, bán hai chỗ khi capacity1 | Đã sửa thứ tự lock; IT-BOOK-RACE-01 quan sát hai waiter và một booking. G2 đã thêm master/aircraft/gate/crew races; payment/expiry/QR/refund vẫn chưa chạy. |
| FDN-ICAO-001 | High | ICAO chưa có unique DB, timezone/tọa độ thiếu kiểm tra | Đã bổ sung service/Form Request/unique/CHECK; FoundationWorkflowTest chạy MySQL và browser M01. Hồ sơ cũ thiếu tọa độ giữ null; không tự sửa ICAO trùng. |
| FDN-RESOURCE-001 | Medium | Thiếu vị trí đỗ/quầy và UI sửa trạng thái tài nguyên | Đã bổ sung types, scoped backend, UI edit/suspend/filter/history; feature và browser thực chạy. |
| SETUP-KEY-001 | High | composer setup gọi key:generate vô điều kiện, có thể mất khả năng giải mã dữ liệu cũ | Đã dùng ensure-app-key; existing-key path chạy, hash .env không đổi. Nhánh missing-key DB cũ/DB mới còn cần mở rộng test; không xoay key người dùng. |
| DEMO-LOGIN-001 | Medium | Hướng dẫn lấy mật khẩu trực tiếp từ JSON dễ sao chép cả escape, gây login thất bại | Đã thêm helper decode/copy và sửa hướng dẫn. Chrome tái hiện lỗi serialized và login thật cả4account; helper contract mock đạt, clipboard thật Not Run, xác nhận người dùng Pending. Evidence 2026-10-18-login-fix/report.md. |
| PLAN-UI-001 | Medium | ID ô lọc mùa trùng ID ô nhập mã mùa; label có thể trỏ vào filter nên mã mùa đầu tiên không được lưu. Lượt browser đầu chưa kiểm tra giá trị này | Đã tách season_filter, thêm kiểm tra ID không trùng và giá trị sau reload; browser lượt cuối đạt. Giữ initial-verified/season_code null và các bản chạy trước, không nhận lượt cũ đã chứng minh lưu mã mùa. |
| PLAN-MOBILE-001 | Medium | Navbar vượt chiều rộng390px | Đã wrap các nhóm navbar; assertion scrollWidth và screenshot mobile thực chạy đạt. Failure ở browser-development-02, không bỏ assertion. |
| PLAN-NAV-001 | Low | Biểu tượng làm tên truy cập link lập lịch không khớp tên hiển thị trong kiểm tra trợ năng; link thực tế có sẵn | Thêm aria-label và aria-hidden cho icon. Browser fixture và smoke demo_ops tại8000 đã bấm được link theo accessible name chính xác. Không phải thiếu route/link. |
| FLEET-SCOPE-001 | High | Trong lúc mở rộng airport scope, route legacy `aircrafts/{tail}/usage` đi trước route mới, không áp dụng filter/authorization theo tham số tailNumber | Bỏ route trùng; MySQL FleetManagementTest kiểm collection/object/history/write ngoài scope bị403 và ngày lọc sai422. development-01 giữ failure; chỉ lượt cuối đúng build là bằng chứng hiện hành. |
| CREW-MIGRATION-001 | Medium | Tên index mặc định calendar vượt giới hạn64ký tự của MySQL; SQLite không phát hiện | Đặt tên crew_availability_lookup. Lỗi chỉ trên DB test riêng, giữ crew-development-01 và crew-migration-diagnostic. Migrations bổ sung trên demo đã chạy sau sửa, không reset dữ liệu. |
| CREW-SUGGEST-001 | Medium | Preview gợi ý lại người đã được phân công vào cùng chuyến | Bỏ nhân sự đang có assignment còn hiệu lực; giữ revalidation của assignment hiện có. crew-development-02 có failure, development-03 đạt; kiểm replacement chỉ exclude assignment cũ cùng flight/vị trí. |
| FLEET-HOURS-API-001 | Low | SQLite trả DECIMAL hours thành số, MySQL trả chuỗi; response không đồng nhất | Chuẩn hóa hours decimal string2digits khi nhận/đọc ledger. Lượt SQLite phát hiện lỗi không được ghi Passed; full SQLite sau sửa đã chạy và lưu XML. |
| BOOK-RACE-FIXTURE-002 | Medium | Fixture race cũ giảm aircraft model capacity sau khi flight đã chốt capacity riêng; không còn mô tả chuyến một ghế | Build6bf97… thất bại138tests/1failure, lưu initial-regression và ledger Failed. Fixture mới tạo cấu hình vật lý một ghế rồi chốt trên flight; giữ hai MySQL waiter, một created/một rejected và đúng một seat item, không hạ assertion. Focused recheck thực chạy đạt; full regression build mới phải hoàn tất trước promotion. |

Mức là phân loại kỹ thuật của phiên này, không phải kết luận kiểm toán khai thác. Không hạ severity để qua gate.

Checkpoint G2 đã chạy thêm master import/publication và aircraft/gate/crew races. Những ca này không đóng các lỗi BOOK-001/002/QR-001 hoặc thay kiểm thử payment/QR/refund. Báo cáo G1/các câu mô tả chưa chạy ở dòng lịch sử giữ để đối chiếu; kết quả hiện hành ở release-test-report.md.


## Rà soát M05 trong phiên 2026-10-18

| ID | Mức | Phát hiện | Sửa và bằng chứng |
| --- | --- | --- | --- |
| MAINT-LEGACY-001 | High | Text evidence/checkbox cho phép ký/release và mọi task reset mốc sử dụng | API cũ409 cho hồ sơ chưa kiểm chứng; review/adopt giữ snapshot, tasks/file/actor mới; chỉ full inspection explicit mới reset. DiagramWorkflowTest được tăng yêu cầu file/signature, không hạ assertion. Focused MySQL và Chrome M05 đã chạy; full build cuối xem release report. |
| MAINT-AIRBORNE-001 | High | Start chỉ kiểm scheduled windows, có thể bắt đầu khi DEPARTED đã qua giờ đến dự kiến | Chặn DEPARTED độc lập timeline; test_airborne_aircraft_cannot_start_maintenance_even_after_scheduled_arrival, focused MySQL và SQLite cuối đạt. |
| MAINT-PLAN-001 | High | Safety gate chỉ đọc legacy orders/usage tổng, chưa chặn theo plan giờ/cycles/condition | Đồng bộ plan readiness tại commit, projected duration/+1cycle và actual usage→alert; race submit-vs-modern-plan kiểm2waiters, một commit/một reject. Không lấy scheduler làm safety gate. |
| MAINT-DELETE-001 | Medium | Aircraft có plan chưa có order có thể đi đến FK error khi xóa, và đổi base làm mất nguồn lực/scope | Service trả422, giữ base/history; test_fleet_cannot_move_or_delete_aircraft_with_an_open_plan đã chạy. |

Development errors không bị che: development-01 có4test errors do fixture GD thiếu; thay fixture bằng PNG bytes hợp lệ, giữ9cases/120assertions khi chạy lại. development-04 browser strict-selector lỗi2alerts; chọn đúng alert nội dung, giữ assertion nguồn. pre-safety-review giữ full regression152Passed trước các hardening mới; không dùng thay kết quả build cuối. High BOOK-001/BOOK-002/QR-001 vẫn mở.


| ID bổ sung | Mức | Phát hiện / xử lý | Bằng chứng |
| --- | --- | --- | --- |
| MAINT-DEPARTURE-001 | High | resource_id null bỏ qua plan gate khi khởi hành; đã đưa Readiness trực tiếp vào khởi hành, đọc cả legacy orders và không tin cờ OPERATIONAL cũ | test_departure_checks_overdue_maintenance_even_without_resource_or_current_status_flag,2datasets MySQL đã chạy; full report build cuối |
| MAINT-AUTHORIZATION-001 | High | BA9.7 yêu cầu duyệt trước làm, khác duyệt kết quả; bổ sung actor/time/authorize và invalidate khi đổi nguồn lực, legacy logged-in start409 phải review/adopt | test_work_requires_explicit_prior_approval_with_actor_and_revision; test_resource_adjustment_is_versioned_before_stock_issue_and_frozen_after_start; Chrome M05 có thao tác duyệt trước start |

MySQL build801893 bị chủ động ngắt khi bổ sung yêu cầu BA9.7; interruption JSON/exit-1 giữ nguyên, không là Passed. Đây không phải lỗi test được bỏ để tạo xanh. Sau bổ sung vẫn giữ toàn bộ159case ở full suite, không bỏ/skip thêm ca MySQL để qua gate.
