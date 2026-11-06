# Data dictionary — các lát cắt đã triển khai

Tên trường cũ và mapping baseline nằm trong database-gap-analysis.md. Thời gian lưu UTC. Migration nguồn: `database/migrations/2026_10_18_000001_add_access_control_and_audit_context.php`. Không ghi bảng dự kiến như đã triển khai.

| Bảng/cột | Kiểu, null/default | Ràng buộc, xóa | Ý nghĩa / UC |
| --- | --- | --- | --- |
| permissions.id/code | bigint PK; varchar80 not null | code unique | Quyền theo hành động, UC-45 |
| permissions.uc_refs/action | varchar100/20 not null | Được seed theo config/access.php | Truy vết nguồn; read/write/approve/export |
| role_permissions.role_id/permission_id | bigint not null | PK composite; FK roles/permissions cascade | Vai trò có quyền; không ADMIN bypass |
| user_data_scopes.id | bigint PK | auto increment | Grant phạm vi riêng mỗi role/user |
| user_data_scopes.user_id/role_id | UUID/bigint not null | FK users/roles cascade; unique(user,role,type,id) | Người dùng nhiều vai trò; thu hồi có hiệu lực ngay |
| user_data_scopes.scope_type/scope_id | varchar20/80; id default * | application enum global/self/airport/flight; app validate object | Quyền toàn hệ thống hoặc đối tượng cụ thể; collection core cũ chỉ global |
| delegations.id | bigint PK | auto increment | Ủy quyền tạm thời UC-45 |
| delegations.grantor_id/grantee_id/created_by | UUID not null | FK users restrict | Người có quyền gốc, người nhận, người quản trị thao tác |
| delegations.permission_id | bigint not null | FK permissions restrict | Chỉ quyền người cấp đang có trực tiếp |
| delegations.scope_type/scope_id | varchar20/80 not null | global/airport/flight; không dây chuyền | Giới hạn phạm vi ủy quyền |
| delegations.starts_at/ends_at/revoked_at | datetime UTC; revoked nullable | index(grantee,permission,ends_at) | Hiệu lực [starts_at,ends_at), thu hồi ngay |
| delegations.reason | text not null | max500 tại app | Lý do; không nhập secret/PII |
| delegations.created_at/updated_at | timestamp | Laravel timestamps | Lịch sử cấp/thu hồi kèm audit append-only |
| audit_logs.correlation_id | UUID nullable | index | Server request ID; log cũ có thể null, UC-46 |
| audit_logs.outcome | varchar20 not null default SUCCESS | SUCCESS/DENIED tại app | Kết quả; trước/sau đã redact; không cung cấp API sửa/xóa audit |

Không đổi PK/kiểu bảng cũ ở phiên này. Chưa hoàn tất dictionary cho toàn bộ bảng hiện hữu và các module G2–G6. Root test MySQL cô lập chỉ chứa dữ liệu tổng hợp, không là cấu hình triển khai thật.

## Bổ sung lát cắt UC-18/19

| Bảng/cột | Kiểu / ràng buộc | Ý nghĩa |
| --- | --- | --- |
| passenger_profiles.created_by | UUID nullable FK users restrict | Chủ hồ sơ khách đi cùng không có account; user_id có giá trị luôn ưu tiên ownership của người được liên kết |
| passenger_consents.id/passenger_id/actor_id | bigint PK; UUID FK profile/users restrict | Lịch sử consent append-only, UC-18 |
| passenger_consents.purpose/policy_version | varchar30; SERVICE/MARKETING; local-policy-v1 | Lựa chọn mẫu cho local; không coi là chính sách pháp lý đã duyệt |
| passenger_consents.granted/recorded_at_utc | bool not null; datetime UTC | Ghi nhận cấp/rút, index(passenger,purpose,id); không ghi đè lịch sử |
| assistance_requests.id/passenger_id/flight_id/created_by | UUID PK, FK profile/flight/users restrict; flight nullable | Hồ sơ hỗ trợ chung hoặc gắn chuyến có booking hợp lệ, UC-19 |
| assistance_requests.category | varchar30 enum tại app | WHEELCHAIR/MEDICAL/UNACCOMPANIED_MINOR/OTHER |
| assistance_requests.details/resolution | text encrypted cast; resolution nullable | Dữ liệu hỗ trợ mã hóa bằng APP_KEY; không đưa nội dung vào audit |
| assistance_requests.department/status | varchar30/20 | CUSTOMER_CARE/AIRPORT_SUPPORT; ROUTED→CANCELLED hoặc COMPLETED; update/route khi chưa kết thúc |
| assistance_requests.created_at/updated_at | timestamp UTC | Queue nội bộ có filter/pagination; index(department,status,created_at) |

## Bổ sung M01 / UC-01–03

Migrations `2026_10_18_000003_complete_foundation_workflows` và `000004_add_airport_coordinate_checks`. Không thay thế bảng ERD nền. Không tạo tọa độ giả cho bản ghi cũ.

| Bảng/cột | Kiểu, null/default | Ràng buộc / xóa / index | Ý nghĩa |
| --- | --- | --- | --- |
| airports.icao_code | varchar4 nullable | unique mới; migration từ chối dữ liệu trùng, không tự sửa | Định danh ICAO, chuẩn hóa uppercase/trim ở service |
| airports.latitude/longitude | decimal(10,7) nullable, default null | MySQL CHECK giới hạn [-90,90]/[-180,180] và cả hai null hoặc cùng có giá trị | Tọa độ sân bay, UC-01; SQLite không thay bằng chứng CHECK MySQL |
| airport_resources.resource_type/status | varchar30/20 không đổi schema cũ | Unique(airport,type,code) giữ nguyên; app bổ sung STAND/CHECKIN_COUNTER | Đủ nhà ga/cửa/vị trí đỗ/băng chuyền/quầy; RUNWAY legacy giữ lại, UC-02 |
| domain_locks.name | varchar80 PK not null | Hàng master-data dùng lockForUpdate trong mọi mutation dữ liệu chủ | Mutex serialize chất lượng/duplicate/import/version; không có dữ liệu KPI mô phỏng |
| master_data_records.revision | unsigned int not null default0 | Tăng mỗi sửa/chuyển trạng thái; app kiểm expected_revision, lỗi409 khi stale | Khác version: revision là lần sửa; version chỉ tăng khi công bố |
| master_data_records.submitted_by/approved_by/published_by | UUID nullable | FK users restrict; actor lấy từ session, null với fixture/CLI lịch sử | Trách nhiệm gửi/duyệt/công bố, UC-03 |
| master_data_records.submitted_at/approved_at/published_at | datetime nullable, UTC | Không backfill actor/thời gian không biết của bản ghi cũ | Mốc phê duyệt; audit lưu mọi chuyển trạng thái |
| master_data_records.category/status | varchar50/20 không đổi | Index(category,status) mới; unique(category,code) cũ giữ nguyên | Schema v1 CABIN/SERVICE/CURRENCY; code/category bất biến sau tạo |
| entity_versions.snapshot/version | JSON / unsigned int không đổi | Unique(type,id,version); app append-only, không có endpoint sửa/xóa | Bản PUBLISHED bất biến; API published đọc phiên bản gần nhất khi head đang sửa |
| master_import_batches.id | UUID PK not null | Không có API delete lô | Lô preview/import CSV, UC-03 |
| master_import_batches.created_by | UUID not null | FK users restrict | DATA_ADMIN/global hoặc delegation tương ứng, không tin browser actor |
| master_import_batches.filename/sha256 | varchar180 / char64 not null | filename basename giới hạn; SHA256 của bytes tệp gốc kể cả BOM | Chỉ metadata, không public upload/raw file |
| master_import_batches.status | varchar20 not null | App enum INVALID/READY/COMMITTED; index(status,created_at) | READY chưa ghi danh mục; COMMITTED retry không nhập lại |
| master_import_batches.row_count/error_count | unsigned int not null | error_count là số bản ghi có lỗi, không phải tổng số field lỗi | Báo cáo chất lượng |
| master_import_batches.committed_at | datetime UTC nullable | Có giá trị khi commit thành công | Nhập tất cả bản ghi thành DRAFT, chưa tự công bố |
| master_import_batches.created_at/updated_at | timestamp UTC | Laravel timestamps | Thời gian preview/revalidation/commit |
| master_import_rows.id/batch_id | bigint PK / UUID not null | FK batch cascade (dòng staging thuộc lô); unique(batch,line); không API delete | Ngoại lệ ownership cho staging, không cascade business/audit |
| master_import_rows.line | unsigned int not null | Thứ tự record CSV từ2 gồm header, không phải số dòng vật lý multiline | Trỏ lỗi về bản ghi nguồn |
| master_import_rows.input/errors | JSON not null | input redact secret keys; errors là map field→messages | Metadata nhập và lỗi từng record; unknown schema không công bố |
| master_import_rows.record_id | unsigned bigint nullable | FK master_data_records restrict | Null trước commit hoặc lô lỗi; liên kết bản nháp khi thành công |

Tiền tệ CURRENCY.minor_units chỉ là danh mục; booking/payment vẫn cần money exact và adapter ở G3. Không nhận CURRENCY CRUD là đã xử lý tiền. Runtime heartbeat dùng bảng cache/jobs sẵn có; không thêm thực thể nghiệp vụ hoặc hardcode KPI.

## M02 — Bổ sung lập lịch và truy vết phiên bản

| Trường | Kiểu / ràng buộc | Ý nghĩa |
| --- | --- | --- |
| routes.operating_from/operating_to | date nullable cặp; MySQL CHECK ngày kết thúc >= bắt đầu | Mùa theo ngày sân bay đi; null cả cặp là không giới hạn |
| routes.allowed_model_ids/blocked_weekdays | JSON nullable, app kiểm model tồn tại/ngày1–7 | Rỗng không giới hạn dòng/không chặn ngày; ghi chú không thành luật tự động |
| routes.restriction_notes/revision | text nullable / unsigned int default1 | Nội dung hạn chế và optimistic revision |
| flight_schedules.series_id | UUID nullable indexed, service tự đặt root UUID | Chuỗi các bản sửa; legacy được backfill theo ID sẵn có |
| flight_schedules.supersedes_id | UUID nullable, self-FK restrict | Bản công bố được đề nghị thay thế; không xóa bản cũ |
| flight_schedules.open_series_id | UUID nullable UNIQUE | Chỉ một draft/pending/approved trong chuỗi; công bố/hủy giải phóng khóa này |
| flight_schedules.revision/version | unsigned int / unsigned int | revision tăng khi sửa/chuyển trạng thái; version tăng theo chuỗi khi công bố |
| flight_schedules.season_code | varchar30 nullable | Nhãn mùa do người lập nhập, không giả định mã mùa chuẩn ngoài |
| flight_schedules.excluded_dates/extra_dates | JSON nullable, max366 phần tử/loại | YYYY-MM-DD theo ngày nguồn, trong hiệu lực, không trùng giữa hai loại |
| flight_schedules.source_timezone | varchar64 not null defaultUTC | IANA nguồn cho cặp giờ địa phương; UTC đối với clock legacy |
| flight_schedules.departure_time_local/arrival_time_local | time nullable cặp, app validation | Cả hai dùng cùng source_timezone, quy đổi theo ngày; không ghi đè clock nguồn |
| flight_schedules.arrival_day_offset | unsigned tinyint default0; MySQL CHECK<=2 | Ngày đến nguồn cộng0–2 ngày; local arrival phải sau departure |
| flight_schedules.created_by/submitted_by/approved_by/published_by | UUID nullable, FK users restrict | Actor từ session, null nếu dữ liệu khởi tạo/legacy không xác định; không bịa người ký |
| flight_schedules.submitted_at/approved_at/published_at | datetime UTC nullable | Mốc trách nhiệm của từng phiên bản |
| flight_schedules.change_reason | text nullable; revise yêu cầu5–1000ký tự | Lý do mở phiên bản mới; audit giữ lý do trả sửa/hủy riêng |
| flight_schedule_checks.id/schedule_id | bigint PK / UUID FK restrict | Báo cáo kiểm tra thuộc một phương án, không API sửa/xóa |
| flight_schedule_checks.revision/checked_by/created_at | unsigned int / UUID FK users nullable / datetime UTC | Phiên bản input và người/thời điểm kiểm tra |
| flight_schedule_checks.is_valid/occurrence_count/issues/impacts | boolean / unsigned int / JSON / JSON | Lỗi theo ngày/field; impacted flight ID/số hiệu/giờ UTC/requires_resolution |
| operational_flights.service_date | date nullable | Ngày khai thác theo nguồn khi tạo flight mới; flight_date vẫn ngày UTC, không sửa dữ liệu cũ |
| domain_locks.name=flight-planning | varchar PK, SELECT FOR UPDATE | Mutex chung lấy trong transaction trước consistent reads; xem ADR-027 |

PUBLISHED/SUPERSEDED/CANCELLED là trạng thái vòng đời lịch; không thay vòng đời operational flight. entity_versions giữ snapshot công bố cùng impacts; published actor/reason có trong snapshot. Constraints MySQL có test engine riêng; SQLite không làm bằng chứng cho CHECK/locking.

## M04/M06 — migration 000006/000007

| Entity/field | Kiểu/ràng buộc | Ý nghĩa |
| --- | --- | --- |
| aircrafts.base_airport_code/revision | nullable FK airports restrict / unsigned int | Cơ sở phục vụ airport scope; null legacy đòi global; revision chống ghi đè form cũ |
| aircrafts.opening_flight_hours/opening_flight_cycles/opening_reason | decimal10,2 / uint / varchar500 nullable | Số dư tiếp nhận tách ledger; migration giữ tổng và ghi nguồn chuyển tiếp |
| aircrafts.current_configuration_id | nullable FK aircraft_configurations restrict | Pointer bản công bố hiện hành; không sửa nội dung bản cũ |
| aircraft_configurations | bigint PK, tail FK restrict, unique(tail,version), JSON sections/seats | Cấu hình nhập theo hồ sơ vật lý; có label,row,column,cabin,enabled; name/reason/created_by/created_at; seat_count/sellable_capacity |
| operational_flights.aircraft_configuration_id/seat_capacity_snapshot | nullable FK restrict / uint nullable | Bản cấu hình và capacity của chuyến, không đổi ngầm khi cấu hình tàu thay đổi |
| aircraft_usage_records.source/external_id | varchar40/100, unique nullable pair | Nguồn record đầu; unique flight_id hiện có chống cộng cùng chuyến nhiều lần |
| aircraft_usage_records.occurred_at/received_at/recorded_by | UTC timestamps nullable / FK users nullable restrict | Provenance; legacy chưa xác định giữ null, không bịa mốc/actor |
| aircraft_usage_receipts | bigint PK, unique(source,external_id), usage_record_id FK restrict, received_at | Alias sự kiện được giữ cả khi cùng flight đã có ledger; ID không được tái dùng cho flight khác |
| aircraft_usage_alerts | usage_record_id unique FK restrict, tail FK, level, hours/cycles since maintenance, created_at | DUE_SOON/OVERDUE từ facts; giữ lịch sử, không đại diện trạng thái hiện tại sau bảo dưỡng |
| crew_members.revision | uint default1 | Khóa lạc quan cho profile/calendar UI |
| crew_availability_windows | crew FK restrict; type; starts/ends UTC; reason; created_by/at | LEAVE/UNAVAILABLE chặn duty overlap; STANDBY chỉ ưu tiên nếu còn hợp lệ |
| crew_availability_windows.cancelled_at/by/cancel_reason | nullable timestamp/UUID FK/varchar500 | Hủy giữ bản ghi và nguồn trách nhiệm |
| crew_rule_versions | code PK, rules JSON, provenance, approval_status, created_at | local-crew-v1 bất biến ở application, LOCAL_ONLY; numeric policy chưa được chuyên gia duyệt |
| flight_crew_assignments.revision/approved_by/confirmed_by/confirmation_mode/rule_version_code | uint; nullable users FK; SELF/OPERATOR; rule FK restrict | Actor và rule được dùng khi quyết định; đổi điều kiện bỏ approval/ack cũ |
| crew_assignment_events | assignment FK restrict; action; snapshot JSON; actor; reason; created_at UTC | Snapshot append-only các lần assign/approve/confirm/replace/invalidate; không serialize relation cá nhân |

Tổng sử dụng phải bằng opening + SUM(ledger); chênh lệch được báo chứ không tự sửa. Cấu hình/check-in chưa có physical map giữ trạng thái chặn. FK mới restrict delete; rollback migration cần xử lý phụ thuộc theo thứ tự, không dùng rollback để xóa lịch sử nghiệp vụ trên DB có dữ liệu.


## M05 — kế hoạch, thực hiện và giải phóng

Migration additive `2026_10_18_000008_complete_maintenance_workflows`; [ERD](erd-maintenance.md), [hướng dẫn](user-guides/maintenance.md), [API](api/maintenance.openapi.yaml).

| Bảng | Trường và ràng buộc chính |
| --- | --- |
| maintenance_plans | id bigint; plan_code unique50; aircraft_tail FK; task200; trigger_type DATE/HOURS/CYCLES/CONDITION; starts/ends/due UTC; due_hours decimal12,2 / due_cycles unsigned nullable theo loại; condition/condition_active; tasks JSON checklist; resets_usage_basis boolean explicit; revision unsigned; status ACTIVE/RESOLVED; reason, created_by FK user, timestamps |
| maintenance_orders | Giữ nguyên mọi trường cũ; thêm plan_id unique nullable FK, technician_id FK user, location_id FK airport_resources, technical_confirmed_by FK user, revision. plan_id null nghĩa hồ sơ tương thích chưa được chứng thực, không tự tạo chữ ký/file. Actor approval/release nằm trong event tương ứng |
| maintenance_materials | id; airport_code FK; code50/name200/unit30; quantity unsigned integer; unique airport/code. Đơn vị nguyên do kỹ thuật khai báo |
| maintenance_order_materials | order_id/material_id FK; quantity integer>0; consumed boolean; unique order/material. Chỉ điều chỉnh trước xuất, giữ snapshot trước/sau |
| maintenance_stock_moves | material_id/order_id(nullable) FK; reference100; delta signed integer; reason; actor_id FK; created_at UTC. unique material/reference; ORDER-id dành cho xuất kho một lần |
| maintenance_order_tasks | order_id FK; description200; result text; passed; actor_id FK; recorded_at UTC. Các thay đổi kết quả lưu thêm snapshot event |
| maintenance_evidence | order_id FK; path private do server sinh; MIME thực; bytes; SHA256; actor_id FK; created_at UTC. path không xuất API/detail/audit; download có scope và no-store |
| maintenance_events | plan_id/order_id FK; action40; snapshot JSON plan/order/tasks/materials; reason; actor_id FK; created_at UTC. Không route sửa/xóa; giữ nguồn gốc legacy và mọi trách nhiệm |
| maintenance_alerts | plan_id FK; kind DUE/DUE_SOON/MATERIAL_SHORTAGE; message; observed_at/resolved_at UTC; unique plan/kind để refresh không nhân bản cảnh báo |

FK mới restrict delete. Lock dùng chung trước đọc tại commit. Cảnh báo có thể trễ tối đa chu kỳ scheduler nhưng gate đọc đồng bộ. Ngưỡng hiển thị24h/usage tổng local chưa được chuyên môn duyệt. Lệnh quá hạn không tự release; quy trình ngoại lệ phải được phê duyệt ngoài quyền suy diễn của ứng dụng. Không dùng giá/tiền float cho vật tư (M05 không hạch toán tiền; tài chính M12 còn triển khai).


M05 BA9.7: migration000009 thêm maintenance_orders.authorized_by FK users (restrict, nullable cho lịch sử chưa xác nhận) và authorized_at UTC. Đây là duyệt trước thực hiện; approved_by/approved_at là duyệt kết quả sau ký kỹ thuật. Assign nguồn lực/định mức làm mất authorization nhưng snapshot/event cũ vẫn giữ người/time. Actor/time không backfill từ created_by hoặc checkbox cũ. Baseline WorkOrder ánh xạ maintenance_orders; TechnicalRelease ánh xạ event RELEASE cùng snapshot order/plan/tasks/materials, một lần hợp lệ nhờ trạng thái terminal/revision/PlanningLock; không tự phát sinh chứng chỉ PKI.
