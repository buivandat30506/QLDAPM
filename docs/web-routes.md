# Web routes — phạm vi hiện đã triển khai

Blade + Bootstrap 5.3.8 / JavaScript, Vite bundle local. Backend quản lý validation/CSRF/quyền, không dựa vào ẩn menu.

| Route | Method / auth | Hành vi |
| --- | --- | --- |
| /login | GET/POST public; throttle10/phút | Đăng nhập ACTIVE, regenerate session, audit; sai thông tin trả validation |
| /logout | POST auth, CSRF | Xóa phiên, regenerate CSRF |
| /account | GET auth ACTIVE | Tài khoản và form đổi mật khẩu |
| /account/password | POST auth ACTIVE, throttle5/phút, CSRF | Kiểm mật khẩu hiện tại, mật khẩu mới >=12 chữ+số; thu hồi API token và logout |
| /forgot-password | GET/POST public, throttle5/phút | Broker + local-simulation private mailbox; không trả secret qua HTTP |
| /reset-password/{token} | GET public | Form dùng token broker, không chứa PII trong QR |
| /reset-password | POST public, throttle5/phút, CSRF | Token single use/expiry; revoke tokens/sessions |
| /administration/users | GET/POST users.manage global | Search username, pagination25; tạo account chưa có quyền; email/username unique |
| /administration/users/{user} | GET users.manage | Form role/scope và history delegation |
| /administration/users/{id} | PUT users.manage, CSRF | Atomic roles+scopes; invalid scope rollback; không tự khóa/xóa quyền admin đang thao tác |
| /administration/delegations | POST users.manage, CSRF | Grantor trực tiếp có quyền; start/end UTC; không ủy quyền dây chuyền |
| /administration/delegations/{id}/revoke | POST users.manage, CSRF | Idempotent revoke, audit |
| /administration/audit | GET audit.read global | Lọc entity_type/action/actor/correlation/from/to UTC; 25 dòng/trang; redact before/after |
| /administration/audit/export | GET audit.export global | CSV cùng filter, streaming, chống formula injection, ghi audit export |
| /admin/{dashboard,airports,routes,aircrafts,flights,crew,workflows} | auth + CoreAccess | Luồng cũ được giữ; mỗi action Gate; collection legacy cần global. Nhiều UC vẫn Partial. |

Lỗi web: auth redirect login, thiếu quyền403, đối tượng không có404, validation redirect + lỗi trường. API tương ứng dùng 401/403/404/409/422/429 và correlation ID. API passenger đã auth+scope nhưng payment direct-confirm và scanner hash cũ trả409 cho đến khi G3 có contract an toàn. Không hiển thị nút giả cho các luồng chưa có.

## Lát cắt hành khách UC-18/19

Tất cả route sau auth + ACTIVE + CSRF cho mutation; kiểm scope/ownership ở service, đối tượng ngoài phạm vi trả404. Passenger list có search/pagination25 và chỉ hiện giấy tờ đã che.

| Route | Method | Hành vi / quyền |
| --- | --- | --- |
| /portal/profiles | GET/POST | passenger.read/write; tạo self hoặc guest, server gán owner; consent bắt buộc lúc tạo |
| /portal/profiles/{id} | GET/PUT | Đọc/sửa đúng owner hoặc SALES global được cấp; blank document giữ giá trị cũ; unique document, validate birth/contact |
| /portal/profiles/{id}/consents | POST | Ghi lịch sử purpose/granted/version/UTC/actor; không ghi đè lịch sử |
| /portal/assistance | GET/POST | assistance.read/write; filter status/department; tạo hỗ trợ chung hoặc gắn flight có booking hợp lệ; handoff vào queue nội bộ |
| /portal/assistance/{id} | GET/POST | Read đúng scope; update/cancel cho owner; route/complete cho SALES hoặc AIRPORT_STAFF đúng scope; trạng thái cuối không sửa lại |

Details/resolution hỗ trợ encrypted, không ghi nội dung vào audit. Không gọi department nội bộ là xác nhận từ provider ngoài. Policy consent mẫu còn chờ duyệt nghiệp vụ.
# Bổ sung M01 trong phiên G2

| Route | Quyền / hành vi |
| --- | --- |
| GET /admin/airports, GET /admin/airports/{iataCode} | airport.read; lọc collection theo global/airport, object gate trước validation |
| POST /admin/airports | airport.write/global; IATA/ICAO unique, timezone/tọa độ validated |
| PUT/DELETE /admin/airports/{iataCode} | airport.write của sân bay; xóa chỉ khi không còn liên kết |
| POST /admin/airports/{iataCode}/resources | airport.write của sân bay; 6 loại gồm STAND/CHECKIN_COUNTER |
| PUT/DELETE /admin/airports/resources/{resourceId} | Kiểm airport chủ object; type/owner không thay bằng request; xóa có guard lịch/chuyến |
| GET /admin/master-data, /{id} | master.read/global; filter/pagination/quality/history/snapshot |
| POST /admin/master-data, PUT /admin/master-data/{id} | master.write/global; DRAFT update có expected_revision |
| POST /admin/master-data/{id}/{action} | submit/revise: master.write; approve/publish/reject: master.approve; CSRF/state/revision/reason |
| GET /admin/master-data/template | master.read; CSV template category,code,payload |
| POST /admin/master-data/imports | master.write + read; multipart file, preview chưa nhập |
| GET /admin/master-data/imports/{batch} | master.read; chi tiết lô, lỗi từng record, pagination25 |
| POST /admin/master-data/imports/{batch}/commit | master.write + read; atomic/revalidate/idempotent COMMITTED |
| GET /admin/master-data/export | master.read + master.export; CSV report theo filter, formula protection |
| GET /health/ready | Public status-only, 200/503, no-store; CLI app:health có component health |

Mọi web mutation có session auth/ACTIVE/CSRF, ngoại trừ health GET public. API tương ứng ở [foundation.openapi.yaml](api/foundation.openapi.yaml). Form có feedback submit; lỗi stale trả409 cần tải lại, lỗi dữ liệu trả form error. Scope unit chưa được triển khai, không coi airport là unit.

## M02 — Đường bay và lịch bay

| Route | Quyền / hành vi |
| --- | --- |
| GET /admin/routes | route.read; lọc search/status/sort, pagination, airport scope theo sân bay đi |
| POST /admin/routes | route.write theo sân bay đi; sân bay/định danh/mùa/hạn chế hợp lệ |
| GET /admin/routes/{id} | route.read object; chi tiết, form theo quyền, lịch sử |
| PUT /admin/routes/{id} | route.write object; kiểm quyền trước validation, expected_revision chống stale |
| DELETE /admin/routes/{id} | route.write; chỉ khi chưa có lịch/chuyến; audit trước xóa |
| GET /admin/schedules | schedule.read; search/status/season_code/sort, pagination, airport scope theo sân bay đi |
| POST /admin/schedules | schedule.write; lưu DRAFT, không nhận actor/version/series từ browser |
| GET /admin/schedules/{id} | schedule.read object; checks/impacts/versions/actors/history |
| PUT /admin/schedules/{id} | schedule.write object; chỉ DRAFT, UI gửi expected_revision |
| POST /admin/schedules/{id}/check | schedule.write; lưu báo cáo tổng hợp, trạng thái không đổi |
| POST /admin/schedules/{id}/submit | schedule.write; DRAFT→PENDING khi kiểm lại hợp lệ |
| POST /admin/schedules/{id}/approve hoặc publish | schedule.approve; kiểm lại ở commit, ghi actor/time/snapshot |
| POST /admin/schedules/{id}/revise | schedule.write; reason bắt buộc, UUID draft mới, bản công bố giữ hiệu lực |
| POST /admin/schedules/{id}/reject hoặc cancel | schedule.approve; reason bắt buộc; hủy lịch không tự hủy flight/booking |

API bearer: [flight-planning.openapi.yaml](api/flight-planning.openapi.yaml). Legacy /admin/workflows vẫn dùng cùng ScheduleService; màn hình M02 riêng có filter/chi tiết/checks/phiên bản. Các phần M03–M06 trong legacy workflows vẫn đang hoàn thiện.

## M04/M06

M04 `/admin/aircrafts` hỗ trợ GET danh sách phân trang và POST tạo; GET/PUT/DELETE `/admin/aircrafts/{tailNumber}` cho chi tiết/sửa/xóa hồ sơ chưa sử dụng; POST `/{tailNumber}/configurations` tạo bản công bố bất biến. POST `/admin/aircraft-models` yêu cầu global aircraft.write. Scope tàu theo base airport; lịch sử sử dụng/đối chiếu/alerts nằm trên chi tiết.

M06 GET/POST `/admin/crew`, GET/PUT `/admin/crew/{id}` quản lý hồ sơ; POST `/admin/crew/{id}/availability` ghi lịch và POST `/availability/{window}/cancel` hủy có lý do. GET `/admin/crew-plans` tìm chuyến; GET `/admin/crew-plans/{flightId}` xem/gợi ý/thay thế; POST `/assign` và `/approve` cho phương án; POST `/admin/crew-assignments/{id}/{confirm|replace|remove}` ghi nhận xác nhận/thay thế/hủy. Scope profile theo cơ sở, plan theo sân bay đi/flight, assign đòi cả hai; service kiểm lại dữ liệu trong transaction.

Các form có CSRF, thông báo lỗi/thành công, trạng thái đang gửi và dữ liệu DB thật. Giới hạn local và điều kiện chưa xác nhận được ghi rõ trên UI. Hướng dẫn [fleet-crew](user-guides/fleet-crew.md), contract bearer [fleet-crew.openapi.yaml](api/fleet-crew.openapi.yaml). Thành viên tài khoản liên kết xem thông báo nội bộ/xác nhận tại `/admin/workflows`; OPERATOR acknowledgement không nhận là SELF.

