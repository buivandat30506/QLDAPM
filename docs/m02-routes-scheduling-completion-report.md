# M02 — Đường bay và lịch bay

Ngày thực hiện: 10/11/2026. Phạm vi: M02-F01 đến F05, trên ứng dụng Laravel đang chạy tại `http://localhost:8000`.

## 1. Căn cứ và hiện trạng trước sửa

Đã đọc yêu cầu đính kèm, `docs/docx-coverage-audit.md`, luồng `catalog?flow=routes`, `scheduling?flow=planning`, WorkspaceController, WorkspaceFlow/StaffNavigation, OperationsService, MasterDataService, UseCaseService, FlightChangeService, logic công bố, outbox/M17, kiểm tra cổng/M18, migration và dữ liệu thật trên node ops.

Không tìm thấy DOCX gốc hay Sitemap trong repository; đường dẫn DOCX được báo cáo cũ nhắc đến không truy cập được ở máy hiện tại. Không coi những mô tả trong báo cáo cũ là bằng chứng thay cho source và database hiện tại. Cụ thể: báo cáo cũ nói thiếu thực thể lịch mùa, nhưng database thực tế **đã có `flight_seasons`**; lần này mở rộng thực thể đó.

| Use case | Backend trước sửa | Frontend trước sửa | Database trước sửa |
|---|---|---|---|
| F01 | Tạo tuyến, kiểm tra trùng; đề nghị sửa và duyệt độc lập đã có | Bảng và form dùng chung workspace, thiếu trang chi tiết riêng | `routes`, `master_changes`, audit đã có |
| F02 | Có `season_rule` lưu metadata; `season` tạo chuyến theo chuỗi thứ, hai luồng chưa nối đầy đủ | Không có quy trình kế hoạch → xem trước → phát sinh rõ ràng | `flight_seasons` có hiệu lực, thứ, giờ, giá, phiên bản; thiếu số hiệu/cổng và liên kết chuyến |
| F03 | Tạo chuyến DRAFT và tạo cả đợt trong transaction; chưa có nguồn định kỳ bền vững | Form tạo lẻ/tạo kỳ đứng cạnh các form duyệt | `flights` có version, creator, giá và trạng thái; thiếu ánh xạ ngày kế hoạch → chuyến |
| F04 | Công bố đã khóa tàu bay, kiểm tra khả dụng, trùng lịch, 30 phút quay đầu, phương án giữ tàu | Thiếu màn hình kiểm tra theo phạm vi và kết quả từng xung đột | Dùng `flights`, `aircraft`, `change_reservations`; không cần bảng xung đột giả |
| F05 | Gửi duyệt; phê duyệt độc lập và công bố chung một transaction; bulk atomic; audit/outbox; M03 xử lý thay đổi đã công bố | Bốn form đơn/đợt gần trùng nhau; thiếu chi tiết nguồn và lịch sử sửa nháp | Có audit/version/published_at/approved_by; chưa có đề nghị sửa nháp với ảnh trước/sau riêng |

Kiểm kê đầu và cuối: 44 đường bay, 0 kế hoạch định kỳ, 987 chuyến bay, 33 tàu bay, 5.922 phân công tổ bay, 2.918 lịch sử dụng hạ tầng, 22 sân bay, 782 hạ tầng chuẩn, 647 tài nguyên legacy, 5 master changes, 1.993 audit. Các dữ liệu/ID/cột cũ được so sánh bằng SHA-256 và giữ nguyên. Không tạo kế hoạch hoặc chuyến giả trong database đang dùng.

## 2. Kết quả theo Use Case

| Use case | Backend sau sửa | Frontend sau sửa | Database sau sửa |
|---|---|---|---|
| F01 | RouteManagementController tái sử dụng OperationsService và MasterDataService; tìm theo ID/mã/tên sân bay, lọc, kiểm tra đề nghị không đổi | Danh sách 12 dòng/trang → trang chi tiết → form sửa đóng gọn, điền sẵn; hàng đợi chờ duyệt riêng, diff và người thực hiện | Giữ bảng và quy trình duyệt có sẵn; không đổi ID |
| F02 | SchedulePlanning lưu kế hoạch độc lập, hiệu lực dài, kiểm tra version/sân bay/đường bay/cổng; giữ giới hạn phát sinh 31 ngày bao gồm hai đầu | Checkbox Thứ Hai–Chủ nhật; chọn tuyến, tàu, hiệu lực, giờ địa phương, thời lượng, giá, cổng; chi tiết và sửa kế hoạch | Bổ sung nullable `number`, `gate` vào `flight_seasons`; kế hoạch cũ vẫn đọc được |
| F03 | Preview không ghi; generate kiểm tra lại trong transaction, khóa kế hoạch/sân bay/tàu; chạy lại bỏ qua occurrence; vẫn gọi hành vi tạo DRAFT hiện có | Xem trước từng ngày, giờ đi/đến theo sân bay, cảnh báo, chuyến đã có và xác nhận riêng | `flight_season_occurrences`: unique kế hoạch/ngày, unique flight, phiên bản và snapshot nguồn |
| F04 | ScheduleConflicts đọc dữ liệu thật; trùng nháp/chờ duyệt là cảnh báo, trùng lịch khai thác/giữ tàu là lỗi chặn; submit và publish đều kiểm tra lại | Chủ động chạy theo bộ lọc; kết quả có chuyến, tàu, lịch liên quan, khoảng giờ, nguyên nhân và link xử lý; chưa chạy không báo an toàn | Không lưu kết quả lỗi thời làm nguồn quyết định |
| F05 | Luồng batch chung cho một/nhiều chuyến; kiểm tra trước, xác nhận, kết quả từng chuyến, replay bỏ qua chuyến đã xử lý; đề nghị sửa nháp có version, ảnh trước/sau và duyệt độc lập | Danh sách → chi tiết → kiểm tra/xác nhận; phân biệt lập lịch, công bố, khai thác; lịch sử có người và thời gian; form sửa thu gọn | `flight_schedule_changes`: flight/source_version, before/after, reason, creator/approver, trạng thái, timestamps; duyệt sửa tăng version và trả về DRAFT |

## 3. Ranh giới nghiệp vụ và quyền

- Giữ nguyên nhóm sidebar “Quản lý chuyến bay”, chỉ hai submenu “Đường bay” và “Lập và công bố lịch bay”. Sửa active state cho trang chi tiết. Bốn tab nằm trong nội dung trang.
- F01 giữ `data_admin` và admin theo Access hiện có. Theo tác nhân F02/F03 người dùng cung cấp, thêm `data_admin` vào chức năng lập kế hoạch và tạo chuyến M02; không sửa Access chung, multi-role, hay cấp quyền công bố cho data_admin.
- Giữ `operations` gửi duyệt, `ops_manager` phê duyệt/công bố, scope sân bay đi; quyền admin trung tâm hiện có vẫn giữ. Quyền quản lý metadata kế hoạch của operations/ops_manager trước đây được giữ.
- Phê duyệt và công bố hiện là **một hành vi nguyên tử** của backend. UI nói rõ việc này; không giả tạo một trạng thái “đã duyệt chờ công bố” mới.
- Công bố kiểm tra lại tổ bay, năng định/giấy phép, khả dụng, nghỉ 12 giờ, cổng/M18, giới hạn quay đầu 30 phút và giữ tàu theo quy tắc cũ. Không đặt thêm chuẩn hàng không.
- Sửa ở M02 chỉ áp dụng cho lịch chưa công bố, DRAFT/PENDING. Người duyệt sửa phải khác người đề nghị. Phiên bản hoặc snapshot nguồn thay đổi thì từ chối. Sau duyệt sửa phải gửi duyệt lịch lại. Lịch có phân bổ hạ tầng bị chặn sửa theo đường tắt này.
- Lịch đã công bố tiếp tục qua điều hành gián đoạn M03 và quy trình đổi tàu/tổ bay hiện có. Không viết trực tiếp vào trạng thái khai thác hoặc sửa các saga M03/M04/M18.
- API metadata legacy không được ghi đè kế hoạch mới đầy đủ hoặc kế hoạch đã phát sinh để vượt kiểm tra version/nguồn. Luồng legacy khác vẫn tồn tại để tương thích.
- Giữ `sale_price` VND: giá được truyền nguyên vào chuyến và sự kiện công bố, tiếp tục tạo inventory M17 qua outbox hiện có.

## 4. Thời gian, an toàn dữ liệu và migration

Thời gian chuyến lưu UTC. Nhập giờ đi theo timezone sân bay đi, giờ đến theo timezone sân bay đến từ M01. Preview định kỳ tính giờ đến từ thời lượng và chuyển sang timezone đích. Khoảng ngày lọc dùng sân bay/đường bay đã chọn hoặc sân bay phụ trách; tra cứu toàn hệ thống không chọn sân bay thì ghi rõ UTC. Giờ địa phương bị thiếu hoặc lặp do DST bị từ chối, không tự chọn một thời điểm mơ hồ.

Migration: `backend/database/migrations/2026_11_10_040000_complete_schedule_planning.php`. Đã áp dụng trước vào `airline_test`, sau đó vào database vận hành local. Chỉ thêm hai cột nullable và hai bảng, không cập nhật dữ liệu cũ. `down()` từ chối nếu có dữ liệu nguồn/lịch sử nhằm tránh mất dấu vết.

Trước migration đã lưu SQL snapshot, source snapshot và kiểm kê private trong `backend/storage/app/private/m02-before*`; dữ liệu private không được đưa vào báo cáo hay commit. Đối chiếu sau migration chọn đúng các cột/ID cũ để không coi cột mới là sửa dữ liệu.

Kế hoạch có chuyến đã phát sinh giữ nguyên quy luật; chỉ cho phép lưu trữ/ngừng phát sinh. Muốn đổi quy luật phải tạo kế hoạch mới. Lịch sử nguồn của chuyến không bị thay bằng nội dung kế hoạch hiện tại. Các chuyến cũ không có liên kết nguồn không được tự gán nguồn hoặc dựng lịch sử.

## 5. Giao diện

- Route list ngắn, có tìm kiếm và lọc, mã sân bay kèm tên, trạng thái và link chi tiết.
- Planning có bốn tab: Lịch bay định kỳ, Danh sách chuyến bay, Kiểm tra xung đột, Phê duyệt và công bố. Tab xử lý chỉ hiện theo quyền. Chuyển tab giữ bộ lọc phù hợp, bỏ page và cờ đã kiểm tra.
- Chọn chuyến trực tiếp trong bảng để xử lý; không cần tìm chuyến trong dropdown dài. Một chuyến dùng cùng màn hình kết quả/xác nhận như nhiều chuyến.
- Lịch sử không in JSON thô; hiện trường đổi, trước/sau, người và thời điểm. Form sửa chỉ mở khi cần.
- Bảng có vùng cuộn ngang riêng ở màn hình nhỏ; form chuyển một cột. Không ngắt chữ giữa từ. Phân trang riêng bằng tiếng Việt, tránh SVG phóng lớn do giao diện Tailwind không khớp CSS Bootstrap.
- Link bỏ qua nội dung chỉ hiện với `:focus-visible`, vẫn dùng được bằng bàn phím. Không thay cấu trúc sidebar.
- Cờ chạy kiểm tra và hành động batch là trường form riêng; không phụ thuộc giá trị nút submit bị cơ chế chống bấm lặp của ứng dụng vô hiệu hóa.

## 6. Kiểm thử và bằng chứng

Kết quả: **73 tests / 1.010 assertions đều đạt**, bao gồm M02PlanningTest (8 bài), OperationsWorkflowTest, SidebarWorkflowTest, FlightResourceChangeTest, CabinConfigurationTest, InfrastructureManagementTest, AirportProfileWorkflowTest, ResourceActionDisclosureTest và UseCaseBackendWorkflowTest. Thời gian 7 phút 39 giây trên Docker local. Sau rà soát cuối, sửa cả giới hạn lệch một ngày ở action `season` legacy (32 ngày tính cả hai đầu → từ chối); chạy lại bài recurring/batch: **1 test / 13 assertions đạt**. Các test ghi dữ liệu chỉ chạy bằng tài khoản/database `airline_test`; TestCase kiểm tra tên database và username trước khi reset dữ liệu test.

Chrome HTTP: **10 màn hình × 6 độ rộng đều đạt**, không lỗi JavaScript, không tràn ngang toàn trang. Đã bấm thật nút kiểm tra xung đột và nút kiểm tra đợt trước xác nhận; mở form bằng Enter, chọn nhiều chuyến và kiểm tra skip-link. Node syntax check và `git diff --check` đạt. Ảnh được chụp sau khi kết thúc transition responsive để không lưu trạng thái sidebar đang trượt.

`M02PlanningTest` bao phủ tìm/thêm/trùng đường bay, đề nghị sửa và duyệt độc lập; kế hoạch dài và cửa sổ 31/32 ngày; checkbox, timezone/DST; preview/generate; nguồn/version và chạy lại; cảnh báo nháp, xung đột thật, kiểm tra lại khi gửi/công bố; batch atomic, M17/M18 và replay; sửa nháp có diff/version, từ chối nguồn cũ, tự duyệt và sửa lịch đã công bố; phân quyền/scope; nhập giờ địa phương và lỗi HTML giữ dữ liệu.

`scripts/check-m02-planning.cjs` kiểm tra Chrome thật qua HTTP localhost: 10 trang/màn hình M02 ở 1920, 1440, 1024, 768, 390, 320 px; HTTP status, không tràn toàn trang, chọn cổng theo sân bay đi, mở form bằng bàn phím, checkbox chọn nhiều, submit kiểm tra batch không ghi, chạy kiểm tra xung đột thật và skip-link. Screenshot/JSON kết quả nằm trong storage private. Không dùng script này để phát sinh/công bố chuyến thật.

Các test hồi quy được điều chỉnh cho những thay đổi có chủ ý: trang M02 mới thay form workspace cũ; gửi duyệt bây giờ từ chối máy bay không khả dụng/đang giữ; giới hạn 31 ngày tính cả hai đầu cũng áp dụng cho action legacy. Kiểm tra công bố vẫn dùng dữ liệu có thể thay đổi sau thời điểm gửi. Toàn bộ ID, số lượng và hash dữ liệu ops cũ được kiểm tra lại sau lượt trình duyệt và đều giữ nguyên.

## 7. Tệp thay đổi trong lần này

- Backend mới: `RouteManagementController.php`, `SchedulePlanningController.php`, `SchedulePlanning.php`, `ScheduleConflicts.php`, `SchedulingDisplay.php`.
- Backend tích hợp: `WorkspaceController.php`, `OperationsService.php`, `UseCaseService.php`, `StaffNavigation.php`, `WorkspaceFlow.php`, `routes/web.php`, xử lý lỗi HTML M02 trong `bootstrap/app.php`.
- Database: migration `2026_11_10_040000_complete_schedule_planning.php`.
- Views: `backend/resources/views/m02/` gồm layout, route list/form/detail, planning, plan form/detail, generation preview, flight form/detail, batch review, field partials, pagination.
- Assets: `frontend/m02.css`, `frontend/m02.js` và bản phục vụ tương ứng trong `backend/public/`; bổ sung CSS skip-link vào app.css nguồn/bản public.
- Tests: `M02PlanningTest.php`; cập nhật kỳ vọng có chủ ý trong OperationsWorkflowTest, SidebarWorkflowTest, FlightResourceChangeTest; script trình duyệt mới.
- Báo cáo này. Không commit/push, không reset/stash các thay đổi có sẵn trong workspace.

## 8. Giới hạn và BA cần xác nhận

1. Chưa có đặc tả mã mùa IATA, phiên bản lịch mùa công nghiệp, tự động điều chỉnh hàng loạt các chuyến đã phát sinh hoặc quy trình hủy/thu hồi đề nghị sửa. Không tự tạo các quy tắc này; hiện lưu kế hoạch, lưu trữ và tạo kế hoạch mới.
2. Chưa tách “duyệt” và “công bố” thành hai transaction/trạng thái riêng vì backend hiện có và yêu cầu chưa xác định quyền/điều kiện trung gian. Giao diện diễn đạt đúng hành vi đang thực hiện.
3. Kiểm tra xung đột độc lập tập trung vào tàu bay và ràng buộc đã có; tổ bay/cổng được kiểm tra bắt buộc khi xác nhận công bố. Lượt xem trước không phải chứng nhận đủ điều kiện công bố.
4. Mỗi lượt kiểm tra độc lập tối đa 100 chuyến và công khai số đã kiểm tra/tổng số; cần thu hẹp bộ lọc để kiểm tra phần còn lại. Batch tối đa 31 chuyến, trang danh sách 12 dòng.
5. Database vận hành chưa có kế hoạch định kỳ nên UI hiển thị trạng thái rỗng thật. Tạo kế hoạch/phát sinh/công bố/sửa và lỗi nghiệp vụ được kiểm tra bằng dữ liệu test riêng, không bổ sung dữ liệu demo vào hệ thống đang dùng.
6. Chuyến legacy thiếu timestamp công bố hoặc nguồn định kỳ hiển thị “chưa ghi nhận” tương ứng; không suy đoán người duyệt, giờ công bố hoặc nguồn. Không dựng audit bù.
7. Không thực hiện kiểm thử tải lớn hoặc tranh chấp đồng thời đa tiến trình; đã có khóa transaction, unique constraint, kiểm tra version và kiểm thử replay/atomic rollback.
