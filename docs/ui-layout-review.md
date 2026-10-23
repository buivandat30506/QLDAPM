# Bàn giao bố cục giao diện — kết quả rà soát cuối 23/10/2026

Giữ nguyên file Tổng quan và các quy tắc CSS đã có của trang đó; CSS mới loại trừ trang Tổng quan. Không sửa backend, route, dữ liệu ứng dụng thật, JavaScript nghiệp vụ hoặc quyền. Theo yêu cầu rà soát cuối, đã cập nhật tham chiếu nhãn/menu và bước mở phần thu gọn trong acceptance, giữ nguyên các kiểm tra nghiệp vụ/quyền; bổ sung kiểm tra hồi quy validation. Không commit/push.

## Đối chiếu 21 file, 11 file hiển thị và trạng thái Git

Con số **21** trong báo cáo trước là danh sách file của đợt mở rộng, gồm cả file mới và tài liệu. Trước đợt mở rộng đã có hai file thay đổi từ phần Tổng quan: `resources/css/app.css` và `resources/views/admin/dashboard.blade.php`. `app.css` thuộc cả hai đợt nên tổng hợp là **2 + 21 − 1 = 22 file khác HEAD**.

Đầu lượt rà soát cuối, Git thực tế có **20 file tracked đã sửa và 2 file untracked**, không phải 11. Bằng chứng lưu tại `storage/app/system-layout-review/final-audit/git-before.txt`; cả 21 file trong danh sách trước đều có trên đĩa. Không có danh sách hoặc snapshot của bảng IDE đếm 11 file nên chưa xác định được bộ lọc/phạm vi của bảng đó; không thể khẳng định 11 là toàn bộ Git diff hoặc kết luận có file bị mất. `git diff --stat` mặc định cũng không tính hai file untracked.

Sau rà soát cuối có **34 file khác HEAD: 30 tracked sửa, 4 untracked**. Lượt này chạm 17 file, trong đó 5 đã nằm trong 22 file trước và 12 file bổ sung vào diff: **22 + 17 − 5 = 34**. Script, database và evidence trong storage bị Git bỏ qua nên không nằm trong các số đếm trên.

### File đã thay đổi trước lượt rà soát cuối

- Phần Tổng quan: `resources/css/app.css`, `resources/views/admin/dashboard.blade.php`.
- Đợt mở rộng: đầy đủ 21 file ở mục “Danh sách lịch sử đợt mở rộng” bên dưới; có `app.css` giao nhau với phần Tổng quan. Đây là thay đổi đã có khi bắt đầu yêu cầu hiện tại; không reset/restore hoặc loại bỏ chúng.

### 17 file sửa/thêm trong lượt rà soát cuối

| File | Thay đổi lượt này |
| --- | --- |
| `resources/css/operations-layout.css` | Vùng bấm mở form cao tối thiểu 44px, có nền/marker rõ hơn |
| `resources/views/foundation/airports.blade.php` | Mở form thêm khi có lỗi |
| `resources/views/foundation/master-index.blade.php` | Mở form tạo dữ liệu chủ khi có lỗi |
| `resources/views/foundation/airport-show.blade.php` | Mở phần sửa tài nguyên khi có lỗi; giữ cơ chế mở collapse cha đã có |
| `resources/views/planning/routes.blade.php` | Mở form thêm đường bay khi có lỗi |
| `resources/views/planning/schedules.blade.php` | Mở form thêm lịch khi có lỗi |
| `resources/views/planning/schedule-show.blade.php` | Mở form sửa/hủy lịch khi có lỗi |
| `resources/views/admin/workflows.blade.php` | Mở các phần nhập workflow khi có lỗi |
| `resources/views/admin/partials/edit-form.blade.php` | Mở form sửa dùng chung khi có lỗi |
| `tests/Acceptance/crew.spec.js` | Nhãn hiện hành, mở sidebar/nhóm tổ bay; giữ assertions trạng thái/lịch sử/403 |
| `tests/Acceptance/fleet.spec.js` | Nhãn hiện hành, mở các disclosure; xác định heading cấp 1; giữ phiên bản/ghế/API/quyền/mobile |
| `tests/Acceptance/foundation.spec.js` | Thao tác mở form tài nguyên; kiểm tra form mã trùng tự mở và giữ old input |
| `tests/Acceptance/maintenance.spec.js` | Đi qua menu hiện hành; nhãn máy bay/vị trí/đường về danh sách; giữ toàn bộ luồng ký/duyệt/giải phóng |
| `tests/Acceptance/planning.spec.js` | Menu và nhãn hiện hành, kiểm tra URL đích; giữ bản công bố, recurrence, DRAFT và 403 |
| `tests/Acceptance/ui.js` (mới) | Helper mở sidebar/details và xác nhận trạng thái mở |
| `tests/Acceptance/disclosures.spec.js` (mới) | Bốn ca hồi quy validation, old input, mobile và collapse lồng nhau |
| `docs/ui-layout-review.md` | Báo cáo cuối, phân loại lỗi và bằng chứng |

`dashboard.blade.php` không đổi so với snapshot trước đợt mở rộng. `app.css` không sửa thêm trong lượt rà soát cuối. Các điều kiện mới `$errors->any()` chỉ điều khiển trạng thái mở form, không thay thế điều kiện quyền hay validation. Khi chưa có error bag riêng cho từng form/dòng, mở các phần nhập liên quan trên trang là lựa chọn bảo thủ để không giấu trường lỗi; không suy đoán thêm quy tắc nghiệp vụ.

## Phân tích từng acceptance từng thất bại

Báo cáo trước đã quy các điểm dừng về nhãn/disclosure quá rộng. Rà soát này phân biệt lỗi tìm locator với lỗi giao diện thật: các form thêm sân bay/đường bay/lịch/dữ liệu chủ thiếu tự mở khi validation thất bại là thiếu sót giao diện đã tồn tại trước đợt mở rộng và bị lượt trước bỏ sót. Đã sửa, không quy lỗi đó cho test. Các disclosure cố ý đóng khi chưa có lỗi vẫn được giữ; test phải mở bằng thao tác người dùng.

| Ca / chức năng | Mong đợi | Thực tế trước sửa/test cập nhật | Nguyên nhân và xử lý | Bằng chứng cuối |
| --- | --- | --- | --- | --- |
| UAT-UC15: hồ sơ/năng định, nghỉ phép, dự bị, hủy khoảng | Tạo/sửa hồ sơ, ghi và hủy khoảng có lịch sử; mobile không tràn | Timeout ở `Năng định dòng tàu`; sau đó test còn dùng hai tên nút cũ | Nhãn đã cũ: view dùng `Năng định dòng máy bay`, `Lưu hồ sơ phi hành đoàn` ngay trong snapshot đầu đợt. Không đổi trường/route/quyền; cập nhật locator | Đạt; `crew.spec.js`, `final-audit/acceptance.json`, ảnh `uc15-crew-calendar-mobile.png` |
| UAT-UC16-17: phân công, duyệt, xác nhận, thay thế | APPROVED/CONFIRMED/REPLACED đúng số lượng, giữ lý do/lịch sử; data admin bị 403 | Không tìm được menu `Phương án tổ bay`; nút duyệt cũng dùng tên cũ | Nhãn/menu và trạng thái sidebar/nhóm thu gọn đã khác test. Mở menu như người dùng, dùng `Phân công phi hành đoàn` và `Kiểm tra và duyệt phân công`; không bỏ bước duyệt/xác nhận | Đạt trọn luồng, gồm kiểm tra 403 và ba sự kiện xác nhận; ảnh `uc16-17-approved-replacement.png` |
| UAT-UC10: máy bay và phiên bản cấu hình ghế | Hai cấu hình bất biến, ghế khóa/mở đúng, sửa trạng thái lưu DB, đúng API/quyền/mobile | Không có menu `Đội tàu bay`; test bỏ qua bước mở cấu hình/lịch sử/hồ sơ. Lượt giữa gặp locator heading trùng topbar/h1 | Nhãn và đường thao tác đã cũ; các form đóng mặc định là hành vi có trước. Đã tăng độ rõ vùng mở form; helper bấm mở. Locator heading được giới hạn cấp 1, không bỏ kiểm tra heading. Cơ chế tự mở khi có lỗi của fleet đã có và được giữ | Đạt toàn bộ assertions ghế 4/3 rồi 4/4, versions `[2,1]`, API, trạng thái và mobile; ảnh `uc10-fleet-mobile.png` |
| UAT-UC01-02: sân bay, vị trí đỗ và quyền | Mã trùng báo lỗi, thấy/sửa được dữ liệu nhập lại; thêm/sửa tài nguyên; ops không được ghi | Test dừng khi select nằm trong phần thêm tài nguyên đang đóng. Đồng thời form thêm sân bay sau lỗi mã trùng thực sự vẫn đóng | Hai vấn đề riêng: test thiếu bước bấm `Thêm cơ sở hạ tầng` (đã đóng từ snapshot trước); **lỗi UI validation** ở form sân bay được sửa bằng server-render `open`. Phần sửa tài nguyên cũng bổ sung mở khi lỗi | Đạt cả mã trùng/old input, tài nguyên, không có nút ghi và HTTP 403; thêm ca UI-VALIDATION collapse cha/con; ảnh `uc01-02-airport.png` |
| UAT-UC12-14: toàn luồng bảo dưỡng | Lập/sửa kế hoạch, thiếu vật tư giữ chờ, bổ sung kho, kết quả/tài liệu/chữ ký, phê duyệt và giải phóng đúng lịch sử | Không có menu `Bảo dưỡng kỹ thuật`; nhãn máy bay/vị trí và link về danh sách cũng cũ | Đối chiếu view/menu và giữ nguyên route/action/quyền; cập nhật thao tác đi qua menu hiện hành và tên nhãn. Không bỏ kiểm tra thiếu vật tư, chốt khi hạng mục chưa đạt, tải tài liệu hay ký/duyệt/giải phóng | Đạt toàn bộ luồng, gồm cảnh báo chưa hoàn tất và lịch sử `tech_demo`; ảnh `uc12-14-maintenance-mobile.png` |
| UAT-UC05-06: lịch lặp, kiểm tra/công bố, bản sửa đổi | Ba lần khai thác đúng recurrence; bản cũ còn PUBLISHED khi sửa, rồi SUPERSEDED sau công bố v2 | Không có link `Lập và công bố lịch`; một số nhãn/form và heading cũ | Cập nhật sang menu `Quản lý chuyến bay`, xác nhận URL `/admin/schedules`; cập nhật nhãn hiện hành. Không phục hồi submenu cũ, không thay logic công bố. Bổ sung tự mở form lịch khi validation lỗi | Đạt recurrence, phiên bản, bản cũ/mới và mobile; ảnh `uc05-06-published-v2.png`, `uc05-mobile.png` |
| UAT-UC06-EX: thiếu nguồn lực và quyền data admin | Không gửi duyệt lịch thiếu máy bay/cửa/hiệu lực; vẫn DRAFT; data admin bị 403 | UI báo `Thiếu máy bay, cửa hoặc khoảng hiệu lực...`, test đợi `Thiếu tàu bay` | Khác cách gọi, không khác quy tắc. Giữ assertion thông báo thiếu máy bay, DRAFT, nút gửi duyệt vắng và API 403 | Đạt; `planning.spec.js`, kết quả JSON cuối |

Bằng chứng đối chiếu trước sửa: `storage/app/system-layout-review/views/` (snapshot Blade), `historical/acceptance-original.json` (7 đạt/7 thất bại), `historical/original-browser-results/*/error-context.md` (locator và cây giao diện tại điểm dừng). Bằng chứng lượt cuối: `final-audit/acceptance.json`, `summary.json`, `browser.txt`, `evidence/`. Những lần chạy giữa không đạt được giữ ở `final-audit/interim-browser.txt`; không dùng chúng làm bằng chứng xanh.

### Những ca còn chưa kiểm chứng trên MySQL

| Ca | Mong đợi | Thực tế và nguyên nhân | Bằng chứng |
| --- | --- | --- | --- |
| `FoundationWorkflowTest::test_mysql_constraints_reject_invalid_coordinates_even_without_service_validation` | MySQL từ chối latitude 91, longitude 181 hoặc cặp tọa độ không đầy đủ khi insert SQL trực tiếp; mã CHECK 3819; không lưu CHK | **Skipped / chưa kiểm chứng**. Lượt này chạy SQLite; không suy ra CHECK của MySQL từ validation PHP | Nhánh `markTestSkipped` trong test và `final-audit/php.txt` |
| `FlightPlanningTest::test_mysql_check_constraints_reject_invalid_dates_and_arrival_offset` | MySQL từ chối mùa khai thác thiếu ngày kết thúc và `arrival_day_offset=3` qua SQL trực tiếp; mã 3819 | **Skipped / chưa kiểm chứng**. Không khởi tạo hay sửa dữ liệu MySQL thật để chạy thử | Nhánh `markTestSkipped` và `final-audit/php.txt` |

Không có acceptance browser bị bỏ qua trong lượt cuối. Các phạm vi chưa kiểm chứng khác: Safari/Firefox, toàn bộ tổ hợp quyền/trạng thái, modal legacy không còn route hiện hành và trang xem trước lô điều hành qua trình duyệt. Kiểm tra GET/responsive không được coi là kiểm chứng nghiệp vụ đầy đủ cho các phạm vi này.

## Phạm vi và kết quả

Các trang dùng chung nhận cách trình bày bảng, bộ lọc, biểu mẫu, phân trang và trạng thái rỗng qua lớp `workspace-page`. Các trang đã có bố cục riêng được kế thừa thay vì viết lại. Portal giữ nguyên menu và quyền hiện có; chỉ đổi cách trình bày nền, thẻ, bảng và thanh điều hướng.

| Nhóm / các trang đang tồn tại | Đã chỉnh | Đã kiểm tra | Hạn chế |
| --- | --- | --- | --- |
| Sân bay: danh sách; chi tiết, sửa hồ sơ, tài nguyên | Nhóm tra cứu/kết quả; bảng cuộn riêng; tự mở form lỗi | GET responsive; acceptance tạo/sửa/mã trùng/tài nguyên/quyền; regression collapse lồng nhau | CHECK MySQL chưa kiểm chứng |
| Dữ liệu chủ: danh sách, thêm, chi tiết/sửa, kiểm tra lô CSV | Đồng bộ bộ lọc, bảng, JSON dài, nhóm nút, phân trang và disclosure | GET danh sách/chi tiết/lô; acceptance nhập CSV, duyệt/công bố/bản sửa đổi, CSV lỗi và xuất; PHP foundation | Không duyệt mọi tổ hợp schema và quyền bằng trình duyệt |
| Đường bay: danh sách, tạo, chi tiết/sửa | Kế thừa bố cục có sẵn; đồng bộ bảng/bộ lọc/biểu mẫu | GET và responsive; acceptance tạo/sửa/tìm/xóa; PHP planning | Không đổi quy tắc mùa và hạn chế |
| Lịch: danh sách, tạo, chi tiết/sửa, duyệt/công bố, hủy | Nhóm trường; làm rõ luồng lập/công bố; tự mở form lỗi | Acceptance recurrence/công bố/v2/thiếu nguồn lực/403; regression lỗi thêm lịch; PHP planning | CHECK MySQL chưa kiểm chứng; không thử mọi nhánh hủy bằng browser |
| Khai thác: danh sách, tạo, chi tiết, phương án, xem trước lô | Tra cứu/danh sách trước thẻ tham chiếu; cảnh báo trước nhập trạng thái; nhãn/trường căn hai cột khi đủ rộng; bảng riêng | GET danh sách/chi tiết, responsive; PHP operations cho trạng thái, sự kiện, phương án, check-in, đóng chuyến, outbox và scope | Xem trước lô chỉ rà mã và nhận CSS chung; chưa mở bằng trình duyệt. Không kiểm chứng mọi trạng thái sự kiện |
| Máy bay: danh sách/tạo, chi tiết/sửa, cấu hình ghế, lịch sử/sổ sử dụng | Trạng thái khai thác trước hồ sơ; phân cấp sơ đồ ghế; đồng bộ bảng/disclosure | Hai acceptance fleet đạt, gồm cấu hình v1/v2, API, quyền và mobile; PHP fleet | Không thử mọi tổ hợp cấu hình |
| Tổ bay: danh sách/tạo, chi tiết/sửa, lịch khả dụng, danh sách/phương án phân công | Nhóm trường; phân công hiện tại trước gợi ý | Hai acceptance crew đạt, gồm duyệt/xác nhận/thay thế/lịch sử/403; PHP crew | Không thử mọi tổ hợp chính sách và scope |
| Bảo dưỡng: danh sách, lập/sửa kế hoạch, hồ sơ/lệnh, vật tư/tài liệu/chữ ký | Tiêu đề tồn kho sát bảng; biểu mẫu/lịch sử/vật tư đồng bộ | Acceptance trọn luồng chờ vật tư, ký, duyệt, tải tài liệu và giải phóng đạt; PHP maintenance | Browser dùng SQLite, không chứng minh concurrency MySQL |
| Luồng nghiệp vụ M01–M06 | Bọc bảng còn thiếu vùng cuộn; trường theo cột thích ứng; giữ toàn bộ thao tác và điều kiện cũ | GET responsive; PHP diagram | Các truy vấn Blade đã tồn tại được giữ nguyên; chưa thao tác mọi nhánh demo qua UI |
| Quản trị: tài khoản, sửa quyền/ủy quyền, audit | Đồng bộ portal; bảng ủy quyền có vùng cuộn; nhóm từng dòng scope; bộ lọc/nút/xuất/phân trang | GET responsive; thêm/bỏ dòng scope bằng JS; hai acceptance tài khoản/quyền và audit/CSV/CSRF; PHP access | Không kiểm tra mọi tổ hợp ủy quyền bằng UI |
| Hành khách: danh sách/tạo/sửa hồ sơ/consent; danh sách/tạo/chi tiết hỗ trợ | Đồng bộ portal và biểu mẫu; bảng consent cuộn riêng | GET cả danh sách và chi tiết ở 3 kích thước; acceptance hồ sơ/consent/hỗ trợ/sửa/hủy; PHP passenger | Không kiểm tra toàn bộ tổ hợp quyền hỗ trợ |
| Tài khoản: đăng nhập, quên/đặt lại/đổi mật khẩu | Thẻ đăng nhập rõ ràng; giới hạn độ rộng biểu mẫu mật khẩu; giữ nhãn, lỗi và dữ liệu nhập lại | Đăng nhập/đăng xuất, đăng nhập lỗi và old input; responsive trang khôi phục/đặt lại; PHP access cho mật khẩu/reset | Không gửi yêu cầu khôi phục hoặc đổi mật khẩu tài khoản thật |
| Tổng quan | Giữ nguyên nội dung và bố cục của đợt trước | GET ở 1440/390/320 px; so sánh file với bản đầu đợt này | Menu dùng chung có thay đổi ưu tiên theo yêu cầu bên dưới |

Các view cũ trong `admin/airports`, `admin/routes`, `admin/aircrafts`, `admin/flights`, `admin/crew` và `welcome` không phải trang được các route web hiện tại trả về. Đã rà soát tham chiếu, không khôi phục hoặc thay thế các trang hiện hành bằng các view này. Modal cũ chỉ nhận giới hạn chiều cao/vùng cuộn chung; chưa kiểm thử kích hoạt qua trình duyệt vì không thuộc các luồng route hiện hành.

## Điều hướng

Mã đầu đợt ưu tiên `admin.flights` trong mục Quản lý chuyến bay. Theo yêu cầu đính kèm, đã ưu tiên `admin.schedules` nếu có quyền xem lịch. Giữ nhánh dự phòng chuyến khai thác/đường bay khi không có quyền xem lịch. Giữ điều kiện hiển thị, trạng thái active, các module và submenu hiện có; không khôi phục submenu cũ. Liên kết tuyến đường bay trong trang lịch được giữ nguyên.

## Kiểm chứng

- `npm.cmd run build`: đạt; dùng npm.cmd do execution policy hiện tại của PowerShell.
- 10 nhóm PHP test liên quan chạy lại cùng một lượt: **125 đạt, 2 skipped, 1.251 assertions**. Hai ca MySQL được liệt kê riêng phía trên, không tính đạt.
- Acceptance cuối: **18 đạt, 0 failed, 0 skipped, 0 flaky** (14 ca cũ cập nhật tham chiếu + 4 ca validation mới). Không xóa assertion nghiệp vụ, kiểm tra quyền, phiên bản, lịch sử, ghế, CSRF hay mobile để đạt kết quả.
- Crawler chạy lại: **28 URL, 84 lượt ở 1440/390/320 px**, không tràn ngang toàn trang; thêm/bỏ dòng scope bằng JavaScript đạt. Trang bảo dưỡng chi tiết có trong lượt này.
- 56 template: crawler đối chiếu các biểu thức dữ liệu/khối PHP và thuộc tính form/control với snapshot, giữ nguyên. Các điều kiện mở form khi có lỗi được bổ sung có chủ đích; không thay điều kiện quyền, include hay CSRF. File Tổng quan giống snapshot.
- Dùng SQLite thử nghiệm mới, migrations/seeders sẵn có; không chỉnh `.env` hoặc MySQL thật. Không suy ra độ đúng của CHECK/locking MySQL từ SQLite.
- `git diff --check` đạt; không có diff trong `app`, `routes`, `database`, `config`. Script/fixture/evidence trong storage bị Git bỏ qua.

## Danh sách lịch sử đợt mở rộng (21 file, trước lượt rà soát cuối)

1. `resources/css/app.css` — thêm import; giữ CSS Tổng quan từ đợt trước.
2. `resources/css/operations-layout.css` — CSS mới cho giao diện vận hành/portal/xác thực.
3. `resources/views/layouts/admin.blade.php`
4. `resources/views/layouts/portal.blade.php`
5. `resources/views/foundation/airports.blade.php`
6. `resources/views/planning/schedules.blade.php`
7. `resources/views/planning/schedule-fields.blade.php`
8. `resources/views/operations/index.blade.php`
9. `resources/views/operations/show.blade.php`
10. `resources/views/fleet/show.blade.php`
11. `resources/views/crew/fields.blade.php`
12. `resources/views/crew/plan.blade.php`
13. `resources/views/maintenance/index.blade.php`
14. `resources/views/admin/workflows.blade.php`
15. `resources/views/administration/user-edit.blade.php`
16. `resources/views/passenger/profile-edit.blade.php`
17. `resources/views/auth/login.blade.php`
18. `resources/views/auth/account.blade.php`
19. `resources/views/auth/forgot-password.blade.php`
20. `resources/views/auth/reset-password.blade.php`
21. `docs/ui-layout-review.md`

`resources/views/admin/dashboard.blade.php` vẫn hiện trong Git diff từ phần Tổng quan; các lượt sau không chỉnh file đó. Danh sách 17 file của lượt rà soát cuối ở đầu báo cáo. Hợp của hai danh sách và file Tổng quan là đầy đủ 34 file hiện khác HEAD; không tính trùng các file xuất hiện ở nhiều lượt.

## Storage: giữ gì, đã dọn gì, cách tái kiểm tra

| Nhóm file | Quyết định và lý do |
| --- | --- |
| `views/`, `app.before.css` | Giữ snapshot trước đợt mở rộng để đối chiếu nhãn, disclosure, biểu thức và CSS; không nạp chúng vào ứng dụng |
| `historical/acceptance-original.json`, `historical/original-browser-results/`, `evidence/` | Giữ kết quả 7/14 ban đầu, bảy error-context và ảnh cũ để truy nguồn từng điểm dừng |
| `final-audit/acceptance.json`, `summary.json`, `browser.txt`, `php.txt`, `evidence/` | Giữ kết quả cuối, tên ca/trạng thái/thời lượng, PHP skipped và ảnh kiểm chứng |
| `final-audit/git-before.txt`, `files-before.json`, `cleanup.json`, `interim-browser.txt` | Giữ inventory và lịch sử dọn/chạy giữa; phân biệt trước/sau, không che lần chạy thất bại |
| `final-audit/prepare.php`, `serve.ps1`, `playwright.config.mjs`, `crawl.mjs`, `README.md` | Giữ công cụ tạo SQLite mới và tái chạy acceptance/crawler, không phụ thuộc database dùng thật |
| `crawl.json`, `interactions.json`, `focused-php.txt` và ảnh crawler mới | Giữ kết quả các kiểm tra bổ sung và responsive; báo cáo cũ được nhận diện bằng tên/thời điểm riêng |
| `edit.mjs`, `final-audit/update.mjs` | Đã xóa: script chỉnh mã một lần, không cần và có rủi ro chạy lại |
| `interactions.mjs`, `infrastructure.mjs`, `invariants.mjs` cũ | Đã xóa: script thao tác tạm/trùng, assertion cũ không còn khớp các điều kiện UI validation mới; các kiểm tra cần giữ đã nằm trong acceptance/crawler |
| `prepare.php`, `playwright.config.mjs` ở gốc review, thư mục lồng `storage/` | Đã xóa sau khi chép báo cáo/error-context vào historical: cấu hình cũ sinh đường dẫn lặp, không dùng tái chạy |
| `frontend-review.sqlite`, database/profile SQLite của các lượt audit đã xong, ảnh root tạm cũ | Đã dọn: fixture có thể tạo lại; ảnh thay thế đã có trong evidence. Chỉ xóa tài nguyên do agent tạo, sau khi xác minh đường dẫn thuộc đúng thư mục review và dừng server |

Xem `storage/app/system-layout-review/README.md` để tái chạy. `prepare.php` luôn tạo file SQLite tên mới bằng chế độ tạo độc quyền, không ghi đè fixture hay cấu hình ứng dụng. Các script/ảnh/evidence này là tài nguyên local bị Git bỏ qua, không tự có trong bản clone mới; các test hồi quy cần giữ đã đặt ở `tests/Acceptance/`.

## Đề xuất chưa thực hiện

- Chuyển truy vấn có sẵn trong Blade luồng demo về controller/service: cần backend.
- Bổ sung dữ liệu hoặc nghiệp vụ cho các vùng đang ghi “Chưa có dữ liệu/chức năng”, các lịch/nhân viên/phương tiện mặt đất: cần backend và xác nhận nghiệp vụ.
- Chạy lại acceptance và hai ca CHECK trên MySQL test riêng khi có môi trường phù hợp. Đợt này đã được cho phép cập nhật tham chiếu acceptance, nhưng chưa có bằng chứng MySQL; không kết luận nghiệm thu toàn hệ thống từ kết quả SQLite.
