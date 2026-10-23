# Rà soát ngôn ngữ giao diện — 23/10/2026

## Phạm vi và cách đối chiếu

Đọc route, controller, model/service và các view đang được gọi để xác định nghĩa trước khi sửa. Chỉ biên tập chữ trong Blade và tham chiếu chữ trong kiểm thử; không sửa Backend, cấu hình, database, CSS hoặc mã xử lý JavaScript. Script thêm dòng quyền nằm trong Blade giữ nguyên, chỉ đổi chữ trên nút trong mẫu HTML. Không commit/push.

Đầu lượt này đã có 34 file khác HEAD từ các đợt trước. Giữ nguyên các thay đổi đó. Số liệu 21/11/34 trong `docs/ui-layout-review.md` thuộc đợt bố cục trước, không phải số file của lượt ngôn ngữ. Bản chụp đầu lượt ngôn ngữ và Git status nằm trong `storage/app/language-review/`; đối chiếu với bản chụp này, không dùng toàn bộ diff với HEAD để nhận là thay đổi mới.

Đã khảo sát các trang đang dùng: Tổng quan, sân bay/tài nguyên, dữ liệu chủ/CSV, đường bay/lịch bay, chuyến bay khai thác, máy bay/cấu hình/giờ bay, nhân sự/phân công phi hành đoàn, bảo dưỡng, tài khoản/quyền/nhật ký, hồ sơ hành khách/hỗ trợ và đăng nhập/khôi phục mật khẩu. `resources/js/app.js` không có chữ cần đổi trong ngữ cảnh hiện hành. Các view cũ `admin/{airports,aircrafts,crew,flights,routes}/index.blade.php` và `welcome.blade.php` không được route hiện hành gọi, giữ nguyên để tránh sửa giao diện lưu trữ không sử dụng.

## Bảng thuật ngữ trước và sau

Các mã trong cột “trước” vẫn giữ nguyên trong dữ liệu, giá trị option và điều kiện. Bảng dưới mô tả nhãn hiển thị.

| Nhóm / vị trí | Trước | Sau | Lý do |
| --- | --- | --- | --- |
| Chung, đồng hồ | UTC không giải thích | Giờ phối hợp quốc tế (UTC), có chú thích cạnh đồng hồ | Làm rõ mốc giờ dùng trong hệ thống |
| Tổng quan, bảng chuyến | Theo thời gian thực | Bảng chuyến bay trong ngày | Bảng hiện tại không tự cập nhật dữ liệu chuyến bay |
| Tổng quan, khu vực sân bay | Tổng quan theo từng sân bay | Sân bay trong bảng chuyến bay | Không diễn đạt thành thống kê tất cả sân bay |
| Tổng quan, công việc | Chưa có công việc cần xử lý | Chưa có danh sách công việc hiển thị tại đây | Khu vực tĩnh không đủ căn cứ kết luận không có việc cần làm |
| Sân bay, mã và múi giờ | IATA / ICAO / múi giờ | Giữ tên mã, giải thích 3/4 chữ cái và mã múi giờ IANA | Có hướng dẫn ngắn tại biểu mẫu, giữ mã chuyên ngành |
| Tài nguyên GATE | Cửa / cửa khởi hành | Cổng ra máy bay | Phân biệt với vị trí đỗ STAND |
| Tài nguyên OCCUPIED | Đang bận | Đang sử dụng | Diễn đạt trạng thái tài nguyên rõ hơn |
| Tài nguyên MAINTENANCE | Mã hoặc nhãn chưa nhất quán | Tạm ngừng / bảo trì | Dùng cho hạ tầng sân bay, không gọi là bảo dưỡng máy bay |
| Dữ liệu chủ, trạng thái | DRAFT / PENDING / APPROVED / PUBLISHED | Nháp / Chờ duyệt / Đã duyệt / Đã công bố | Giữ cùng cách gọi với lịch bay |
| Nhập CSV, trạng thái | READY / INVALID / COMMITTED | Sẵn sàng nhập / Có lỗi cần sửa / Đã nhập | Phân biệt kiểm tra tệp với nhập bản nháp |
| Dữ liệu chủ, nút lưu | Lưu thay đổi | Lưu dữ liệu chủ | Nêu đối tượng được lưu |
| Dữ liệu chủ, trường JSON | Thuộc tính JSON | Thuộc tính dữ liệu (JSON) | Giải thích định dạng, không đổi tên trường payload |
| Hướng dẫn dữ liệu chủ | Nhắc JSON và tên thuộc tính kỹ thuật | Giải thích name, timezone, demo, cabin_code, minor_units và giới hạn sẵn có | Người nhập biết từng thuộc tính nhận gì; không đổi quy tắc |
| Lô nhập | SHA-256 chưa giải thích | Mã kiểm tra tính toàn vẹn (SHA-256) | Giữ thuật ngữ cần cho đối chiếu tệp |
| Đường bay | Tuyến đường bay | Đường bay | Thống nhất một tên; giữ mã và route |
| Lập lịch | Lịch trình bay | Lịch bay | Phân biệt bản lịch với chuyến bay khai thác |
| Lập lịch, thao tác | Thêm lịch trình / Chi tiết lịch trình | Thêm lịch bay / Chi tiết lịch bay | Đồng nhất tiêu đề, phần mở form và trang chi tiết |
| Lập lịch, thời gian | UTC / DST / ngày cộng thêm | Giải thích giờ UTC, giờ mùa hè (DST), số ngày cộng vào ngày đến | Làm rõ cách nhập hiện có, không quy đổi giờ bằng công thức mới |
| Chuyến bay, trạng thái | Theo lịch / Lên lịch / Chậm / Đã khởi hành | Đã lên lịch / Chậm chuyến / Đã cất cánh | Thống nhất với nhãn chuyên ngành hiện có trong cấu hình |
| Chuyến bay, BOARDING | Đang lên máy bay | Đang đón khách | Đồng nhất cách gọi trong bảng, bộ lọc, form và Tổng quan |
| Chuyến bay, nguồn | DEMO / AODB / FIDS | Mô phỏng (DEMO) / Cơ sở dữ liệu khai thác sân bay (AODB) / Hệ thống thông tin chuyến bay (FIDS) | Giữ mã nguồn và giải thích khi cần chọn/xem nguồn |
| Chuyến bay, lịch sử sự kiện | sequence / legacy | Số thứ tự / Dữ liệu cũ | Giảm thuật ngữ triển khai xuất hiện trực tiếp |
| Chuyến bay, xử lý sự cố | turnaround / local | Thời gian quay đầu / mô phỏng | Tên nghiệp vụ và phạm vi mô phỏng rõ ràng |
| Chuyến bay, check-in | Check-in / theo cửa sổ / Lưu check-in | Làm thủ tục (check-in) / theo khung giờ / Lưu chế độ làm thủ tục | Giải thích thuật ngữ và đúng đối tượng thao tác |
| Chuyến bay, hàng đợi | Outbox thông báo / Gửi lại outbox | Hàng đợi gửi thông báo (mô phỏng) / Gửi lại thông báo | Nêu công việc thay cho tên kỹ thuật |
| Chuyến bay, xử lý nhiều chuyến | Xem trước lô điều hành / áp dụng toàn bộ lô | Xem trước xử lý nhiều chuyến bay / áp dụng cho tất cả chuyến bay | Làm rõ phạm vi xác nhận |
| Máy bay, cấu hình | Lịch sử cấu hình bất biến | Lịch sử cấu hình được lưu nguyên trạng | Giữ ý nghĩa phiên bản không bị ghi đè |
| Máy bay, hạng khoang | ECONOMY / BUSINESS… | Phổ thông / Thương gia… | Nhãn tiếng Việt, giữ mã cấu hình ghế |
| Máy bay, bộ lọc | Lọc | Lọc máy bay | Nêu danh sách được lọc |
| Tổng quan, trạng thái máy bay | Khai thác | Khả dụng | Đồng nhất nhãn OPERATIONAL với hồ sơ và danh sách máy bay |
| Phi hành đoàn | Năng lực, cơ sở và khả dụng | Năng lực, cơ sở và thời gian có thể nhận nhiệm vụ | Làm rõ nghĩa thời gian khả dụng |
| Phi hành đoàn, danh sách | Giấy phép / đào tạo hết hạn | Ngày hết hạn giấy phép / đào tạo | Cột chứa ngày, không phải kết luận tất cả đã hết hạn |
| Bảo dưỡng | Cơ sở hạn | Căn cứ xác định hạn | Nhãn rõ nghĩa, dùng đúng dữ liệu trigger_type |
| Bảo dưỡng, hướng dẫn vật tư | Trừ nguyên tử | Kiểm tra và xuất kho trong cùng một lần xử lý | Bỏ thuật ngữ lập trình, giữ mô tả cơ chế đã có |
| Bảo dưỡng, không có kết quả | Chưa có kế hoạch trong phạm vi tìm kiếm | Không có kế hoạch phù hợp với bộ lọc trong phạm vi được cấp | Không khẳng định toàn hệ thống chưa có kế hoạch |
| Tài khoản | ACTIVE / INACTIVE / SUSPENDED | Hoạt động / Ngừng hoạt động / Tạm khóa | Nhãn danh sách đồng nhất với biểu mẫu sửa |
| Quản lý quyền | Bỏ | Bỏ dòng quyền | Nêu rõ tác động lên dòng vai trò/phạm vi |
| Nhật ký | Before / After (đã che nhạy cảm) | Dữ liệu trước / sau thay đổi (đã che thông tin nhạy cảm) | Diễn đạt tiếng Việt đầy đủ |
| Nhật ký, mã đã xác định | LOGIN_SUCCEEDED / SUCCESS… | Đăng nhập thành công (LOGIN_SUCCEEDED) / Thành công (SUCCESS)… | Có nhãn dễ hiểu, giữ mã để lọc và truy vết |
| Hành khách | consent | Sự đồng ý xử lý dữ liệu | Giữ nguyên trường consent, giá trị và lựa chọn rút lại |
| Khôi phục mật khẩu | local / mô phỏng kỹ thuật | Chế độ mô phỏng và Yêu cầu khôi phục mật khẩu | Nêu rõ thao tác và phạm vi hiện có |
| Trang luồng nghiệp vụ | M01–M06 / demo / trạng thái thô | Tên luồng nghiệp vụ / mô phỏng / nhãn tiếng Việt | Bỏ tiền tố nội bộ khỏi tiêu đề, giữ nguyên module và điều hướng |

Lưu nháp, gửi duyệt, phê duyệt, công bố, tạo bản sửa đổi và áp dụng phương án vẫn là các hành động riêng. Không đổi chúng thành “Lưu” chung. Các hàng giờ kế hoạch, dự kiến và thực tế giữ riêng, giữ dữ liệu gốc. Bảo dưỡng máy bay và bảo trì tài nguyên sân bay dùng từ khác nhau theo đối tượng.

## Những phần giữ nguyên hoặc cần làm rõ

| Nội dung | Căn cứ / xử lý trong lượt này | Đề xuất tiếp theo |
| --- | --- | --- |
| `gate_number` của chuyến bay | `FlightRecovery` có thể gán resource_code của cả GATE và STAND; không đủ dữ liệu ở mọi bảng để gọi chắc chắn là cổng. Đổi nhãn tổng quát thành “Tài nguyên khởi hành”; ô chọn phương án có sẵn resource_type nên hiển thị rõ loại cổng/vị trí đỗ | Nếu cần nhãn riêng ở bảng chuyến bay, xác nhận quy ước nghiệp vụ và dữ liệu quan hệ trước; không sửa Backend trong lượt này |
| Mã quyền, vai trò, UUID, entity_type và hành động nhật ký chưa có ánh xạ xác nhận | Giữ mã định danh; thêm nhãn/hướng dẫn ở vị trí đủ căn cứ. Những hành động đã xác định có nhãn và vẫn giữ mã trong ngoặc | Cần danh mục chính thức mô tả quyền và loại đối tượng; không đoán từ mã |
| Mã giao thức `flight-event.v1`, phiên bản quy tắc và `local-policy-v1` | Là mã hồ sơ/chính sách, giữ nguyên; giải thích ý nghĩa xung quanh mã | Chủ nghiệp vụ xác nhận tên chính thức nếu cần tên hiển thị riêng |
| JSON lưu trong lịch sử/chi tiết phương án | Giữ khóa và toàn bộ nội dung, không đổi cấu trúc hoặc giả làm trường giao diện đã Việt hóa | Cần thiết kế riêng cho màn hình tra cứu nếu muốn thay trình bày dữ liệu kỹ thuật |
| Mã hành động trong lịch sử chuyến bay ngoài bảng nhãn đã xác nhận | Giữ nguyên mã, không dịch suy đoán các mã cũ hoặc mã động | Thống nhất danh mục nhãn đầy đủ với chủ nghiệp vụ |
| Tên người dùng, tên vai trò và nội dung do người dùng nhập | Là dữ liệu, giữ nguyên, kể cả tên demo bằng tiếng Anh | Không thay dữ liệu lưu trữ trong đợt biên tập chữ |

Thông báo do Backend tạo chưa sửa vì chưa có cơ chế dịch riêng ở giao diện:

| Nguồn | Nội dung còn gặp / đề xuất |
| --- | --- |
| `AuthController` | Thông báo khôi phục có “Local: hộp thư mô phỏng riêng tư”; đề xuất “Chế độ mô phỏng: hướng dẫn được lưu trong hộp thư mô phỏng riêng tư”. Thông báo đổi mật khẩu có “thu hồi token”; đề xuất giải thích là thu hồi mã truy cập API trong lần sửa Backend được cho phép |
| `MasterDataQuality`, `ValidationMessages` và validation trong controller/service | Lỗi có thể nhắc category/payload/cabin_code hoặc tên trường kỹ thuật. Giữ nguyên lỗi và quy tắc; đã thêm hướng dẫn định dạng ngay tại form. Đề xuất nhãn trường và bản dịch thông báo tại tầng Backend sau |
| `FlightEventService`, `FlightOutbox`, `FlightOperations::impact()` | Nội dung sự kiện, lỗi gửi thông báo và mô tả ảnh hưởng trả về dưới dạng chuỗi; không thay chữ bằng phép thay từ hàng loạt trong Blade |
| `MaintenanceReadiness` và dữ liệu cảnh báo bảo dưỡng | Có thông báo “Ngưỡng hiển thị local, không phải chuẩn kỹ thuật”; đề xuất “Ngưỡng cảnh báo dùng cho mô phỏng, không phải tiêu chuẩn kỹ thuật” |
| Flash thành công/lỗi từ các controller khác | Giữ nguyên; không sửa Backend hoặc làm mất nội dung cảnh báo để đồng nhất chữ |

Không thêm phép thay từ bằng JavaScript/CSS. Các phép `str_replace` đã có ở layout để đổi “tàu bay” trong flash vẫn giữ nguyên, không mở rộng sang thuật ngữ khác.

## File sửa trong lượt ngôn ngữ

40 file, gồm 34 view (33 sửa và 1 partial mới), 5 file kiểm thử và báo cáo này. Danh sách được lấy bằng cách so sánh với snapshot đầu lượt; các file bố cục khác đang hiện trên Git giữ nguyên.

- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/workflows.blade.php`
- `resources/views/administration/audit.blade.php`
- `resources/views/administration/user-edit.blade.php`
- `resources/views/administration/users.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/crew/fields.blade.php`
- `resources/views/crew/index.blade.php`
- `resources/views/crew/plan.blade.php`
- `resources/views/crew/show.blade.php`
- `resources/views/fleet/index.blade.php`
- `resources/views/fleet/show.blade.php`
- `resources/views/foundation/airport-fields.blade.php`
- `resources/views/foundation/airport-show.blade.php`
- `resources/views/foundation/import-show.blade.php`
- `resources/views/foundation/master-index.blade.php`
- `resources/views/foundation/master-schema.blade.php`
- `resources/views/foundation/master-show.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/maintenance/index.blade.php`
- `resources/views/maintenance/show.blade.php`
- `resources/views/operations/bulk.blade.php`
- `resources/views/operations/index.blade.php`
- `resources/views/operations/show.blade.php`
- `resources/views/operations/status.blade.php`
- `resources/views/partials/ui-label.blade.php` — mới, chỉ ánh xạ mã sang nhãn hiển thị, có fallback mã gốc; không có truy vấn hoặc quy tắc nghiệp vụ.
- `resources/views/passenger/assistance.blade.php`
- `resources/views/passenger/profile-edit.blade.php`
- `resources/views/passenger/profiles.blade.php`
- `resources/views/planning/route-show.blade.php`
- `resources/views/planning/routes.blade.php`
- `resources/views/planning/schedule-fields.blade.php`
- `resources/views/planning/schedule-show.blade.php`
- `resources/views/planning/schedules.blade.php`
- `tests/Acceptance/disclosures.spec.js`
- `tests/Acceptance/fleet.spec.js`
- `tests/Acceptance/foundation.spec.js`
- `tests/Acceptance/planning.spec.js`
- `tests/Feature/FleetManagementTest.php`
- `docs/ui-language-review.md` — mới.

Kiểm thử chỉ đổi chuỗi tham chiếu nhãn đã sửa: trạng thái lô nhập, trường JSON, nút lưu dữ liệu chủ, lịch bay, cổng ra máy bay, bộ lọc máy bay và tiêu đề lịch sử cấu hình. Giữ số ca, điều kiện quyền, kiểm tra giá trị/mã, dữ liệu lưu, lỗi, lịch sử và thao tác.

## Kiểm chứng và bằng chứng

- `npm.cmd run build`: đạt.
- PHP, 10 nhóm liên quan: 125 đạt, 2 bỏ qua có điều kiện, 1.251 assertions. Hai ca kiểm tra CHECK constraints của MySQL (tọa độ sân bay; ngày và số ngày cộng vào ngày đến) **chưa kiểm chứng** trên MySQL. Không kết nối hoặc thay đổi dữ liệu MySQL thật.
- Đối chiếu 56 view với snapshot đầu lượt: giữ nguyên các thuộc tính form/input/select/textarea/button (ngoại trừ chữ hướng dẫn/nhãn truy cập được phép), giữ nguyên điều kiện `@can`, `@if`, `@elseif`, `@unless`, `@auth`, `@guest`. Các option trước đây lấy giá trị ngầm từ chữ được thêm value bằng đúng mã cũ trước khi dịch nhãn.
- Kiểm tra trình duyệt bổ sung: 29 URL, 87 lượt ở chiều rộng 1.440/390/320 px, không tràn toàn trang và không có lỗi JavaScript. Đã xem ảnh dữ liệu chủ, lịch bay và sửa quyền ở 320 px. Nút “Bỏ dòng quyền” không bị cắt chữ ở cả ba kích thước; thêm/xóa dòng bằng mẫu HTML/JavaScript vẫn đúng. Kiểm tra thực tế bộ lọc trạng thái gửi `DRAFT`, nguồn gửi `DEMO` dù nhãn đã Việt hóa.
- Acceptance: cả 18 ca đã có kết quả đạt sau kiểm tra lại. Lượt tổng có ghi trace đạt 17/18; ca bảo dưỡng chạy riêng trên SQLite mới đạt trong 58,4 giây khi cho thời gian 120 giây, không ghi trace. Tăng thời gian chỉ qua tham số lệnh chạy; không sửa file kiểm thử bảo dưỡng/hành khách, không bỏ bước hay giảm assertion. Đây không phải kết quả một lượt tổng 18/18.
- Kiểm tra sau các chữ chỉnh cuối ở Tổng quan/chế độ làm thủ tục: 3 trang, 9 lượt ở cùng ba kích thước, HTTP 200, không lỗi JavaScript hoặc tràn trang; nhãn “Đóng làm thủ tục” vẫn có value `CLOSED`. `last-copy-check.json` lưu kết quả.

| Ca từng dừng | Mong đợi / thực tế khi dừng | Phân loại và bằng chứng cuối |
| --- | --- | --- |
| Bảo dưỡng, lần đầu | Sau duyệt phải hiện form bắt đầu; ảnh trang vẫn “Chưa duyệt nguồn lực hiện tại”, sau 60 giây không tìm được form bắt đầu | Chưa xác định nguyên nhân lần đầu; không coi là nhãn cũ hoặc khẳng định lỗi test. Không sửa nhãn các form duyệt/bắt đầu, điều kiện quyền hoặc Backend. `first-browser-results/maintenance-*/error-context.md`, `acceptance-first.json` |
| Hồ sơ hành khách, lần đầu | Đăng nhập phải tới trang tài khoản và có liên kết hồ sơ; ảnh vẫn là trang đăng nhập, chờ liên kết quá hạn | Chưa xác định nguyên nhân lần đầu, chưa đủ bằng chứng quy cho menu. Lần chạy tổng kế tiếp đạt toàn bộ tạo/sửa hồ sơ, sự đồng ý xử lý dữ liệu, hỗ trợ và hủy, không sửa test hoặc chức năng. `first-browser-results/passenger-*/error-context.md`, `acceptance-trace.json` |
| Bảo dưỡng, lần ghi trace | Hoàn tất cả luồng và kiểm tra mobile; đã giải phóng, nhưng quá 60 giây ở bước tải lại trang | Trace xác nhận đã ghi nhận giải phóng, không phải lỗi tìm nhãn. `trace-browser-results/maintenance-*/trace.zip`, `browser-final.txt` |
| Bảo dưỡng, chạy riêng lần cuối | Tạo/sửa kế hoạch, duyệt, thiếu vật tư, nhập thêm, bắt đầu, chặn ký khi chưa đạt, ghi kết quả, tải tài liệu, ký/duyệt/giải phóng, lịch sử, mobile và ID không trùng phải đạt | Đạt toàn bộ, 58,4 giây, dùng SQLite mới; `maintenance-retest.txt`, `runtime/acceptance.json`, `evidence/uc12-14-maintenance-mobile.png` |

Giới hạn: hai điểm dừng lần đầu chưa có nguyên nhân được xác minh; các lần chạy sau không tái hiện. Chưa thử trên MySQL, chưa thử mọi tổ hợp quyền/dữ liệu hoặc mọi trình duyệt. Các luồng khôi phục mật khẩu không gửi thư thật trong lượt này. Không suy diễn kết quả SQLite thành xác nhận ràng buộc MySQL.

Bằng chứng: `storage/app/language-review/{git-before.txt,manifest.json,php.txt,probe.json,probe.txt,acceptance-first.json,acceptance-trace.json,browser.txt,browser-final.txt,maintenance-retest.txt}`; ảnh và error-context nằm cùng thư mục và các thư mục evidence/runtime. Snapshot `views/`, `js/`, `acceptance/` chỉ phục vụ đối chiếu lượt này, không đưa vào mã ứng dụng. `runtime/prepare.php`, `serve.ps1`, `playwright.config.mjs`, `probe.mjs`, `inventory.mjs` dùng để tái kiểm tra trên SQLite mới; không dùng cơ sở dữ liệu thật. Muốn tái chạy acceptance, dừng server kiểm thử, chạy prepare.php tạo SQLite mới, chạy serve.ps1 rồi `npx.cmd playwright test --config storage/app/language-review/runtime/playwright.config.mjs --timeout 120000`. Không dùng lại fixture đã bị các ca tạo dữ liệu sửa đổi.

Đã dừng server do lượt này tạo, xóa ba file SQLite riêng cùng con trỏ database.json và các script biên tập một lần. Danh sách xóa nằm trong `cleanup.txt`, chỉ bên trong `storage/app/language-review/`; không dọn hoặc sửa bằng chứng của đợt bố cục trong `storage/app/system-layout-review/`. Giữ snapshot, ảnh, báo cáo JSON, trace của lần quá hạn và các helper tái kiểm tra. `verified-results.json` đối chiếu 18 mã ca duy nhất với kết quả đạt gần nhất, không che kết quả thất bại ban đầu.

## Lưu ý khi chia sẻ qua Git

Các đường dẫn `storage/app/language-review/` trong báo cáo là bằng chứng local, bị Git bỏ qua và không có trong bản clone mới. Không đưa snapshot, profile, dữ liệu thử, ảnh, trace hoặc báo cáo chạy tự động vào commit. Các kiểm thử cần chia sẻ nằm trong `tests/Acceptance/` và `tests/Feature/`; helper `tests/Acceptance/ui.js` đi kèm các ca dùng nó. Để tái chạy trên MySQL test riêng, dùng `php scripts/prepare-browser.php` và `npm.cmd run test:acceptance -- --timeout 120000` theo mục Kiểm thử trong README; đây là quy trình tái kiểm tra, không phải bằng chứng MySQL đã đạt trong đợt này.

Trước commit bàn giao, đã chạy lại build và 10 nhóm PHP trên mã nguồn cuối: **125 đạt, 2 skipped, 1.251 assertions**. Hai ca MySQL vẫn chưa kiểm chứng. Rà soát 53 file dự kiến chia sẻ (gồm các đợt bố cục, ngôn ngữ và cập nhật README); đối chiếu 39 view tracked với HEAD xác nhận thuộc tính xử lý biểu mẫu và điều kiện quyền giữ nguyên, ngoại trừ chủ đích bổ sung tự mở phần nhập khi có lỗi. Không chạy lại acceptance trong lượt commit; kết quả trình duyệt là các lượt được ghi phía trên. Bằng chứng kiểm tra trước commit lưu local tại `pre-push-php.txt` và `pre-push-audit.json`.
