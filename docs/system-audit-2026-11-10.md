# Báo cáo kiểm tra hệ thống Airline — 10/11/2026

## 1. Kết luận

Các luồng cốt lõi hoạt động trong môi trường local và bộ kiểm thử backend đạt **178/178 test, 2.267 assertion** sau khi sửa hai lỗi đã tái hiện. Hệ thống vẫn còn các vấn đề cần xử lý về phạm vi sân bay, quan hệ dữ liệu hành khách, transaction và dependency. Kết quả này chưa đủ để kết luận sẵn sàng đưa vào vận hành thật.

Phạm vi đợt kiểm tra gồm cấu trúc ứng dụng, phân quyền, booking/reservation, thanh toán mô phỏng, check-in/boarding, khai thác, hành lý, hỗ trợ, worker/outbox, cơ sở dữ liệu, frontend, dependency và khả năng khởi chạy trên Windows. Các kết quả bên dưới là kiểm tra thực hiện trong đợt này; tài liệu nghiệm thu cũ không được dùng thay cho kết quả chạy test.

## 2. Môi trường và cách kiểm tra

| Thành phần | Môi trường thực tế |
| --- | --- |
| Hệ điều hành | Windows, PowerShell |
| Backend | Laravel 12.70.2 |
| PHP thực thi | PHP 8.4.27 tại `../.runtime/php/php.exe` |
| PHP mặc định trong PATH | 8.2.12; không đáp ứng platform requirement của bộ dependency hiện tại |
| Cơ sở dữ liệu | Ba MySQL 8.0.46 độc lập; readiness trả ba server UUID khác nhau |
| Trình duyệt | Chrome trên Windows, Playwright, 1 worker |
| Ứng dụng local | `http://127.0.0.1:8000` |
| Kiểm thử E2E | Web riêng ở cổng 18081, schema và tài khoản `airline_test` trên ba node |
| Tích hợp thanh toán | Mock VNPay/MoMo/chuyển khoản; không thu tiền thật |
| Email | SMTP Mailpit local |

Không reset hoặc seed cơ sở dữ liệu ứng dụng chính. Các bài PHPUnit và E2E chạy nối tiếp vì dùng chung các schema test. Những kiểm tra ghi dữ liệu bổ sung chỉ được chạy khi guard xác nhận cả tên DB và tài khoản đều là `airline_test`.

## 3. Kết quả kiểm tra

| Hạng mục | Kết quả | Ý nghĩa / giới hạn |
| --- | --- | --- |
| Cú pháp PHP | 310 file đạt | Không gồm việc chứng minh tính đúng nghiệp vụ |
| Cú pháp JavaScript | Đạt | Kiểm tra source và asset phục vụ ứng dụng |
| `composer validate --strict --no-check-publish` | Đạt | Cấu trúc manifest/lock hợp lệ |
| `composer audit` | **Không đạt: 7 advisory / 4 package** | Chi tiết tại mục 6 |
| `npm audit`, gồm dev dependencies | 0 lỗ hổng được công bố | Theo dữ liệu audit tại thời điểm kiểm tra |
| `npm run assets` | Đạt | Đồng bộ frontend sang `backend/public` |
| `artisan view:cache` | Đạt | Blade biên dịch thành công |
| Migration DB chính | 22 migration đã áp dụng | Không thực hiện migrate fresh trên DB chính |
| PHPUnit toàn bộ | **178 passed / 2.267 assertion**, 294,33 giây | Sau sửa RBAC; trước sửa có 176 passed và 2 failed |
| PHPUnit hồi quy RBAC | Hai ca lỗi ban đầu đã đạt | Ownership/role và phạm vi workspace/mutation |
| Playwright toàn bộ | **26 passed / 4 failed**, 7,7 phút | 30 test trong 11 spec; cần tách lỗi fixture/rate limit khỏi lỗi nghiệp vụ |
| Playwright chạy lại bốn ca lỗi | **4/4 đạt khi chạy riêng** | Home 2,8s; lập lịch 10,6s; tạo nhân viên 6,2s; đổi tàu 11,7s; reset cache DB test giữa các lần chạy |
| Readiness local | `status: ok` | Ba node riêng biệt có kết nối; web và worker khởi động được |
| Compose YAML | Parse được | Chưa chạy Docker Compose vì máy thiếu Docker |
| `git diff --check` | Đạt | Có cảnh báo chuẩn hóa CRLF/LF, không có lỗi whitespace |

Như vậy cả 30 kịch bản E2E đều đã có lần chạy thành công trong đợt kiểm tra, nhưng **chưa có một bản chạy chung đạt 30/30**. Không coi việc chạy lại riêng là bộ E2E toàn bộ đã ổn định. Bốn ca được chạy lại lần lượt là `home.spec.js:4`, `operations.spec.js:4`, `portals.spec.js:3`, `resource-change.spec.js:3`.

## 4. Hai lỗi đã sửa

### F01 — Cao: admin tự động có quyền nghiệp vụ ngoài ma trận RBAC

- Vị trí: `backend/app/Modules/Shared/Access.php:14`.
- Trước sửa: `Access::allows()` trả true cho admin khi danh sách quyền có bất kỳ role nào khác passenger. Admin do đó vượt giới hạn tại service/middleware dùng helper này.
- Trái với `docs/rbac-matrix.md:3` và `:21`: admin quản trị tài khoản/phạm vi, không tự có quyền tài chính hoặc điều hành.
- Sửa: kiểm tra role chính xác bằng `in_array(..., true)`, loại bỏ nhánh wildcard của admin.
- Bằng chứng: `JourneyTest::test_ownership_and_role_boundaries` và `OperationsWorkflowTest::test_roles_and_airport_scope_are_checked_for_workspace_and_mutations` thất bại trước sửa và đạt sau sửa; toàn bộ PHPUnit đạt 178 test.
- Tác động chủ đích: tài khoản admin chỉ được thực hiện các hành động khai báo rõ role admin.

### F02 — Cao: khóa nút submit làm mất quyết định gửi lên server

- Vị trí: `frontend/app.js:431`, đồng bộ tại `backend/public/app.js`; nút mock tại `backend/resources/views/mock-payment.blade.php:3`.
- Trước sửa: listener submit vô hiệu hóa ngay nút vừa nhấn. Trình duyệt loại nút disabled khỏi payload, làm mất cặp `name=value` như `result=approve` hoặc `approve=1`.
- Tái hiện: thanh toán mock báo thiếu `result`; các luồng phê duyệt dùng giá trị nút cũng lỗi khi chạy trên trình duyệt.
- Sửa: lưu cặp name/value của nút vào hidden input trước khi disable; khai báo rõ `type="submit"` cho hai nút mock.
- Kiểm chứng sau sửa: `reservations.spec.js` đạt 4/4 và `business.spec.js` đạt 5/5. Các ca này đã được chạy lại trên trình duyệt thực.

## 5. Phát hiện còn mở, theo ưu tiên

Mức độ dưới đây là ưu tiên kỹ thuật của đợt rà soát, không phải điểm CVSS. Phát hiện từ đọc mã và kết quả gọi trực tiếp service/controller được phân biệt với kiểm thử qua HTTP.

Kiểm tra bổ sung trên DB test đã tái hiện các kết quả sau. Mọi ghi dữ liệu của probe được rollback trên cả ba node; không tạo dữ liệu thử ở DB chính.

| Tình huống gọi trực tiếp | Kết quả quan sát |
| --- | --- |
| Airport agent HAN xác minh một passenger UUID ngẫu nhiên | Tạo record VERIFIED thành công |
| Passenger gửi assistance cho reservation của mình nhưng passenger UUID ngẫu nhiên | Tạo record OPEN thành công |
| Passenger gửi reservation của mình kèm key đã có ở reservation của người khác | Nhận nguyên record ancillary của reservation khác |
| Passenger gửi ancillary với amount bằng 0 | Giá 0 được lưu ở record PENDING |
| Operations HAN tạo season rule trên route có origin khác HAN | Tạo record DRAFT thành công |
| Operations HAN đọc dữ liệu controller M2 | Có booking với flight.origin khác HAN |
| Kiểm tra ánh xạ transaction của integration_connection bằng reflection | Kết quả mysql; handler thực tế dùng ops |

Các probe trên chứng minh lỗi kiểm tra dữ liệu/quyền tại service và read model; chưa phải bản tái hiện tấn công hoàn chỉnh qua HTTP. Với M2, kết quả phụ thuộc dữ liệu có booking ngoài HAN; dữ liệu E2E trong đợt này có trường hợp đó.

### O01 — Cao: ancillary nhận giá từ client và replay key không gắn chủ sở hữu

- Vị trí: `backend/app/Modules/Shared/UseCaseService.php:203–205`.
- `amount` chỉ được kiểm tra là số nguyên không âm rồi ghi trực tiếp; hành khách có thể gửi giá 0 cho yêu cầu dịch vụ.
- `booking_id` chỉ kiểm tra định dạng UUID, không kiểm tra booking tồn tại hoặc thuộc reservation đang yêu cầu.
- Sau kiểm tra quyền trên reservation đầu vào, code tìm `idempotency_key` trên toàn bảng và trả nguyên record cũ. Không so owner, reservation hoặc payload. Khi biết key của giao dịch khác, người gọi có thể nhận record thuộc reservation khác dù gửi reservation của mình.
- Airport agent cũng chưa được kiểm tra sân bay tại handler này.
- Cần tính giá từ catalog phía server, xác minh quan hệ booking/reservation, giới hạn quyền sân bay và ràng buộc key theo actor/nghiệp vụ; trả 409 khi cùng key nhưng payload khác. Trạng thái đang là PENDING, chưa chứng minh rằng giá 0 đã gây thất thoát thanh toán thật.

### O02 — Cao: các thao tác bổ sung bỏ sót kiểm tra sân bay

- `UseCaseService.php:104–116` (`seasonRule`): có role operations/ops_manager nhưng không có kiểm tra origin của route hoặc bản ghi cũ thuộc sân bay được gán.
- `UseCaseService.php:208–224` (`baggageIncident`, `baggageIncidentResolve`): airport_agent chỉ qua kiểm tra role, không kiểm tra sân bay chuyến bay gắn với hành lý.
- `UseCaseService.php:246–253` (`documentVerify`, `assistance`): thiếu kiểm tra phạm vi sân bay của nhân viên.
- Đây là các nhánh đi qua `WorkspaceController::dispatch`, không chỉ là hàm không được sử dụng. Role check tại controller không thay thế object/scope check tại service.
- Cần dùng chung bộ kiểm tra booking → flight → origin và chặn tài khoản scoped khi không có sân bay hợp lệ. Khi cập nhật bản ghi, kiểm tra cả quan hệ hiện tại và quan hệ mới.

### O03 — Cao: dashboard M2 để operations đọc dữ liệu ngoài sân bay

- Vị trí: `backend/app/Http/Controllers/ModuleController.php:16–20`, `:110–164`.
- M2 cho role operations truy cập nhưng query chỉ giới hạn passenger và airport_agent. Operations do đó nhận booking/reservation/hành lý trên nhiều sân bay.
- Đường trả JSON dùng nguyên các row booking/reservation, nên sai scope có thể làm lộ thông tin hành khách trong các row đó.
- Các điều kiện tại `:78`, `:119` và `:166` còn yêu cầu `airport_code` có giá trị mới áp scope: tài khoản scoped thiếu mã sân bay có thể rơi về query không giới hạn. Quy trình cấp role hiện có kiểm tra sân bay, nhưng read path vẫn nên từ chối dữ liệu không hợp lệ.
- Cần áp dụng scope thống nhất cho cả HTML/JSON và chọn tường minh các trường cần trả. Chưa kết luận admin hoặc customer_service phải có cùng giới hạn với operations; cần bám ma trận quyền của từng role.

### O04 — Trung bình: đánh dấu giấy tờ VERIFIED cho UUID không tồn tại

- Vị trí: `backend/app/Modules/Shared/UseCaseService.php:246–253`.
- `documentVerify` chỉ kiểm tra định dạng `reservation_passenger_id`, không kiểm tra người đó tồn tại hay thuộc booking nào.
- `assistance` kiểm tra quyền trên reservation khi caller là passenger, nhưng không kiểm tra `passenger_id` tồn tại và thuộc reservation đó; `assigned_to` cũng chỉ kiểm tra kiểu integer.
- Migration `backend/database/migrations/2026_11_06_010000_usecase_workflows.php:105–126` tạo index UUID cho các quan hệ này, không có foreign key để chặn ở DB.
- Cần kiểm tra tồn tại, quan hệ và phạm vi trong cùng transaction. FK nội bộ cùng DB nên được cân nhắc sau khi rà dữ liệu đã có; liên kết qua các node cần validation và cơ chế đối soát phù hợp.

### O05 — Trung bình: transaction của integration_connection mở trên sai DB

- Vị trí: `backend/app/Modules/Shared/UseCaseService.php:40–52` và `:188–190`.
- `connectionFor()` không liệt kê `integration_connection`, nên `execute()` mở transaction trên mysql.
- Handler thực tế ghi `integration_connections`, gọi `lockForUpdate()` và ghi audit trên ops. Các thao tác ops không nằm trong transaction mysql đó; lỗi audit sau ghi có thể để lại thay đổi đã commit, và row lock không được giữ theo transaction dự kiến.
- Cần ánh xạ action này sang ops và bổ sung ca rollback thực sự khi thao tác thứ hai lỗi. Đây là kết luận từ đường gọi và connection trong mã; chưa fault-inject lỗi production.

### O06 — Trung bình: luồng gửi notification EMAIL/SMS chưa có consumer rõ ràng

- Vị trí: `backend/app/Modules/Shared/UseCaseService.php:231–233`, `:410–417`.
- `notification_send` tạo in-app notification, ghi delivery EMAIL/SMS ở PENDING rồi trả về. Không enqueue job hoặc phát outbox tại handler.
- Tìm tham chiếu `notification_deliveries` trong application chỉ thấy đường tạo và cập nhật trạng thái thủ công. Worker `backend/routes/console.php:25` xử lý hết hạn booking, outbox và SLA hỗ trợ; không thấy consumer đọc bảng này.
- Nếu đây chỉ là sổ ghi nhận gửi qua hệ thống ngoài thì cần thể hiện rõ hợp đồng tích hợp; nếu người dùng mong hệ thống tự gửi thì đây là khoảng trống chức năng cần bổ sung job/provider/retry.
- Email thanh toán và quên mật khẩu đi qua luồng khác và đã có E2E đạt; không suy rộng phát hiện này thành mọi email đều hỏng.

### O07 — Thấp: KPI hành lý chỉ tính 12 booking gần nhất

- Vị trí: `backend/app/Http/Controllers/ModuleController.php:124`, `:139–142`.
- Với passenger, query baggage lấy ID từ danh sách booking đã `limit(12)`. KPI hành lý vì vậy thiếu hành lý của booking cũ hơn.
- Cần tính KPI trên toàn bộ booking được phép xem, chỉ áp limit cho danh sách hiển thị. Ngoài ra `countQuery()` đang nuốt mọi exception và trả 0 (`:202`), có thể che lỗi DB thành số liệu kinh doanh hợp lệ.

### O08 — Thấp: bất tiện và lỗi khi chạy công cụ mặc định trên Windows

- `scripts/configure.py:27`: gọi `os.getuid()`/`os.getgid()`, không có trên Windows; đã tái hiện `AttributeError`.
- `playwright.config.js:6`: mặc định `/usr/bin/chromium`; Windows cần đặt `CHROMIUM_PATH`.
- PHP trong PATH là 8.2, cần dùng runtime 8.4 đã có để chạy bộ dependency hiện tại.
- Một số thông báo trong `UseCaseService.php`, ví dụ `:220`, `:392`, `:404`, `:415`, chứa dấu `?` ngay trong source. Cần sửa từng chuỗi tiếng Việt; không đánh đồng với lỗi hiển thị UTF-8 của console PowerShell.

### O09 — Trung bình: bộ E2E chưa cô lập rate limit giữa các kịch bản

- Vị trí: `backend/routes/web.php:36`, `tests/e2e/helpers.js:11`, các spec đăng nhập nhiều role.
- Ứng dụng giới hạn login 5 lần/phút theo IP. Các browser context mới vẫn dùng chung IP, còn helper không kiểm tra login đã thành công trước khi tiếp tục.
- Trong bản chạy toàn bộ: home dừng ở 429; operations và resource-change dừng ở trang login sau khi chuyển trang; portals nhận 429. Chưa thể gộp mọi failure này thành lỗi nghiệp vụ riêng lẻ.
- `business.spec.js` đã có clear cache test trước từng scenario, các spec khác chưa áp dụng nhất quán.
- Cần cô lập limiter trong fixture test và xác nhận response login sớm; không tăng hoặc tắt giới hạn đăng nhập của ứng dụng để làm test đạt. Các lần chạy lại bên dưới reset cache của DB test bằng script có guard.
- Kết quả chẩn đoán: cả bốn ca lỗi đạt khi chạy riêng với cache test được reset, không thay đổi logic đăng nhập hoặc giới hạn của ứng dụng.

## 6. Dependency security audit

`composer audit --format=json` trả exit code 1, không có package abandoned. Các phiên bản sau lấy từ lockfile; mốc vá lấy từ affected ranges do Composer trả về ngày kiểm tra.

| Package | Đang khóa | Số advisory | Mốc vá trong nhánh hiện tại |
| --- | --- | --- | --- |
| symfony/http-foundation | 7.4.22 | 4 | 7.4.23 |
| symfony/http-kernel | 7.4.22 | 1 | 7.4.23 |
| symfony/routing | 7.4.22 | 1 | 7.4.23 |
| symfony/yaml | 8.1.10 | 1 | 8.1.11 |

Tham chiếu advisory:

1. [CVE-2026-118514](https://symfony.com/cve-2026-118514): HEAD request và quy tắc access_control của Symfony.
2. [GHSA-3h27-hjv7-fqjx](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx): bypass path rule qua script name.
3. [GHSA-xg2q-4rrq-657q](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q): trusted proxy private_ranges và các dạng địa chỉ IP.
4. [GHSA-5mx8-mwrp-5693](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693): URL sinh ra có thể dùng host từ client.
5. [CVE-2026-123339 / GHSA-wg5q-m2ww-f3c9](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9): forwarded headers trong inline fragments.
6. [CVE-2026-118516](https://symfony.com/cve-2026-118516): compiled route host placeholder có thể khớp đầu path.
7. [CVE-2026-123792 / GHSA-hwfg-qwvh-2wfx](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx): độ phức tạp parser YAML gây giữ process.

Composer không cung cấp severity cho các entry này. Chưa chứng minh tất cả đều khai thác được trong Laravel/cấu hình triển khai của dự án; vẫn nên cập nhật bản vá tương thích rồi chạy lại PHPUnit và E2E. Đợt này chưa thay dependency/lockfile.

## 7. Giới hạn và khoảng trống kiểm chứng

- Chưa giao dịch VNPay/MoMo thật, đối soát ngân hàng thật hoặc gửi SMS/SMTP production.
- Chưa load/stress test, kiểm tra số người đặt vé đồng thời ở tải production hoặc đo SLA định lượng.
- Chưa diễn tập backup/restore, mất node, failover, network partition hoặc disaster recovery.
- Thiếu Docker/Bash trên máy nên chưa chạy nguyên bộ Compose, shell backup/restore và topology container. Compose nhắm MySQL 8.4; đợt này chạy MySQL 8.0.46.
- Readiness/worker khởi động được không chứng minh toàn bộ hàng đợi không thể kẹt hoặc mọi retry đều đúng.
- Test hiện có cho `UseCaseBackendWorkflowTest` gồm bốn ca rộng, chưa đủ phủ các tình huống từ chối quyền/quan hệ dữ liệu nêu trên. Việc 178 test đạt không phủ định các phát hiện còn mở.
- Không thực hiện penetration test hạ tầng công khai, rà soát chứng thư/TLS production, hoặc UAT của nhân sự nghiệp vụ.

## 8. Thứ tự xử lý đề xuất

1. Xử lý O01–O04: scope, ownership, quan hệ dữ liệu và giá server; thêm test từ chối truy cập giữa hai sân bay/hai hành khách.
2. Vá các dependency Symfony rồi chạy lại hồi quy; không dùng tùy chọn bỏ qua security audit làm cách khắc phục.
3. Sửa transaction O05, kiểm thử rollback và cạnh tranh cập nhật trên đúng node.
4. Xác định rõ hợp đồng gửi EMAIL/SMS O06; triển khai consumer hoặc ghi rõ chế độ ghi nhận qua hệ thống ngoài.
5. Sửa KPI, các thông báo hỏng và công cụ Windows; ổn định E2E khi chạy nối tiếp nhiều lần đăng nhập.
6. Trước vận hành thật: chạy môi trường Docker mục tiêu, UAT, thanh toán sandbox của nhà cung cấp, kiểm thử tải và diễn tập phục hồi dữ liệu.

## 9. Thay đổi của đợt kiểm tra

- `backend/app/Modules/Shared/Access.php`: sửa RBAC.
- `frontend/app.js` và `backend/public/app.js`: bảo toàn giá trị submitter.
- `backend/resources/views/mock-payment.blade.php`: khai báo loại nút submit.
- Báo cáo này: kết quả và danh sách khắc phục; chưa commit hoặc publish.
