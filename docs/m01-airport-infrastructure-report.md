> Cập nhật 10/11/2026: quyết định hiện hành là **10 loại / 4 nhóm**, đã tinh gọn database thật. Xem [báo cáo triển khai và ánh xạ](m01-infrastructure-simplification-report.md). Nội dung 41 loại bên dưới được giữ làm lịch sử khảo sát trước quyết định này.

# Bàn giao M01: sân bay, trạng thái vận hành và cơ sở hạ tầng

Ngày: 09/11/2026. Nhánh làm việc: `main`. Không commit, push, merge, reset hoặc stash.

## 1. Phạm vi và căn cứ

Đã khảo sát các route `/workspace/catalog`, `?flow=airports`, `?flow=infrastructure`, `/workspace/resources`; controller, service, schema, audit, quyền truy cập, layout/sidebar, `workspace.blade.php`, hai tệp `m01.blade.php`/`m01-form.blade.php` đang có và mã frontend.

**Không tìm thấy tài liệu Use Case/đặc tả, Business Analysis hoặc Sitemap gốc trong repository.** Các tài liệu đọc được là báo cáo thứ cấp: [requirements-summary.md](requirements-summary.md), [docx-coverage-audit.md](docx-coverage-audit.md), [architecture.md](architecture.md) và mã nguồn. Vì vậy phần M18 bên dưới là đối chiếu triển khai với yêu cầu của lượt này và các ghi chép thứ cấp, không phải nghiệm thu đầy đủ theo tài liệu gốc.

Workspace có nhiều thay đổi sẵn từ các lượt trước và các tệp chưa được Git theo dõi, gồm cả bản M01 cũ và phần thử nghiệm multi-role. Các thay đổi này được giữ nguyên. Hai tệp M01 cũ không được ghi đè; giao diện mới nằm trong thư mục `views/m01/`.

## 2. M01-F01 — Danh sách và chi tiết sân bay

### Danh sách

- Điểm vào: `/workspace/catalog?flow=airports`. `/workspace/catalog` cũng mở danh sách sân bay.
- Cột: mã, tên, thành phố, múi giờ, trạng thái vận hành và xem chi tiết. Tên sân bay và liên kết chi tiết đều dùng thẻ liên kết thật, hỗ trợ bàn phím.
- Tìm trên DB theo mã IATA/tên/thành phố; phân trang 12 sân bay. Liên kết chi tiết mang theo `q`/`page`; nút quay lại khôi phục ngữ cảnh danh sách.
- Thêm sân bay và nhập/xuất nằm trong vùng thu gọn. Các đề nghị nhập sân bay chưa tồn tại vẫn có chỗ duyệt độc lập tại vùng nhập/xuất, phân trang riêng bằng `imports_page` để không bỏ sót các đợt nhập cũ. Truy vấn nhận diện đúng giá trị JSON null của snapshot nhập.

### Trạng thái vận hành

Trước thay đổi, bảng `ops.airports` có `active` và các trường thông tin sân bay, **không có trạng thái vận hành, công suất/ngưỡng High/Overload hay dữ liệu bảo trì cấp sân bay**. Không sử dụng trạng thái hiệu lực để thay thế trạng thái vận hành.

| Giá trị lưu | Nhãn | Màu | Ý nghĩa và nguồn |
| --- | --- | --- | --- |
| `NULL` | Chưa xác định | Trung tính | Chưa có người có quyền xác nhận. Giữ nguyên cho sân bay cũ. |
| `NORMAL` | Bình thường | Xanh lá | Ghi nhận thủ công là khai thác bình thường. |
| `HIGH` | Tải cao | Vàng/cam | Ghi nhận thủ công kèm căn cứ; chưa có ngưỡng tính tự động. |
| `OVERLOAD` | Quá tải | Đỏ | Ghi nhận thủ công kèm căn cứ; chưa có ngưỡng công suất tự động. |
| `MAINTENANCE` | Đang bảo trì | Tím | Ghi nhận ảnh hưởng bảo trì cấp sân bay, không suy từ một cổng hoặc tàu bay. |

Badge có chữ, chấm màu, nền nhạt và chú giải; không nhấp nháy. Thay đổi trạng thái không sửa `active`, không tự hủy chuyến, đóng sân bay hoặc chặn đặt vé.

Migration bổ sung bốn cột nullable: `operational_status`, `operational_status_note`, `operational_status_updated_by`, `operational_status_updated_at`; thêm index trạng thái. Không backfill trạng thái. ID người cập nhật không tạo khóa ngoại sang DB khác vì người dùng nằm ở node thương mại.

Điểm cập nhật: chi tiết sân bay → Cập nhật trạng thái vận hành → chọn trạng thái và nhập căn cứ tối thiểu 5 ký tự. Backend kiểm tra quyền qua `Access::role(..., 'data_admin')`, kiểm tra trạng thái và ghi audit trong cùng transaction có khóa bản ghi. Admin được xử lý theo chính sách `Access` hiện hữu; không có đặc cách theo email QA.

### Hồ sơ chi tiết

Route: `/workspace/catalog/airports/{IATA}`.

- Thông tin chung: IATA, ICAO, tên, thành phố, mã quốc gia, tọa độ, hiệu lực bản ghi. Giá trị thiếu hiển thị rõ là chưa có thông tin.
- Giờ hoạt động: chỉ hiển thị cấu trúc đã lưu nếu có; không tự diễn giải lịch ngoài cấu trúc hiện có.
- Múi giờ: IANA, giờ tại sân bay khi tải trang, UTC tương ứng và độ lệch UTC đúng múi giờ đó. Không ép tất cả sân bay thành UTC+7.
- Hạ tầng: tóm tắt số bản ghi thật theo loại, tách danh mục tài nguyên khai thác và cấu trúc nhà ga/khu vực; liên kết truyền đúng sân bay/nguồn/loại sang trang hạ tầng.
- Trạng thái: nguồn ghi nhận thủ công, căn cứ, người cập nhật và thời điểm.
- Lịch sử: các đề nghị sửa thật trong `master_changes` và audit cập nhật sân bay/trạng thái; không tạo lịch sử giả. Hiển thị tối đa 10 bản ghi gần nhất mỗi nhóm.
- Sửa hồ sơ mở ngay trong chi tiết, tên/địa điểm/múi giờ được điền sẵn. Backend lấy sân bay từ route, không yêu cầu nhân viên nhập ID. Vẫn dùng `MasterDataService::propose` và duyệt độc lập.

Các cột trạng thái vận hành được loại khỏi phép so sánh phiên bản **riêng hồ sơ sân bay**, vì đây là nhóm dữ liệu vận hành độc lập. Điều này cũng giữ khả năng duyệt snapshot sân bay lập trước migration. Các thay đổi thông tin chung vẫn được kiểm tra stale-source như cũ; phê duyệt hồ sơ không ghi đè trạng thái vận hành.

## 3. M01-F02 — Hạ tầng theo sân bay

Điểm vào sidebar giữ nguyên `/workspace/catalog?flow=infrastructure`.

1. Chưa có `airport`: chỉ hiển thị bộ chọn sân bay và tìm kiếm tên/mã; không truy vấn hoặc hiển thị bảng tài nguyên trộn nhiều sân bay, không tự chọn sân bay đầu tiên.
2. Có ngữ cảnh `airport=HAN`: kiểm tra quyền/đối tượng rồi mới truy vấn hạ tầng HAN. Header có mã/tên, trạng thái vận hành nếu có và nút đổi sân bay.
3. Đổi sân bay quay về bước chọn, loại bỏ trang/bộ lọc sân bay trước.
4. Lọc loại, tìm mã/tên và phân trang đều áp dụng trên DB sau điều kiện `airport_code`. Các liên kết phân trang giữ sân bay, nguồn, loại và từ khóa; các form đổi lọc không giữ số trang cũ.

### Hai nguồn dữ liệu thật

| Nguồn | Nội dung | Quan hệ được hiển thị |
| --- | --- | --- |
| `airport_resources` | TERMINAL/GATE/STAND/COUNTER theo các bản ghi thật | Có sân bay, chưa có khóa nhà ga/khu vực cha. Không gán cha bằng cách đoán mã. |
| `airport_areas` | TERMINAL/ZONE/GATE/STAND/COUNTER/BELT nếu thực sự có dữ liệu | Hiển thị `parent_id` đúng sân bay; không tự dựng cây liên kết với bảng tài nguyên. |

Dropdown loại chỉ liệt kê các loại đang có trong nguồn/sân bay được chọn. Nếu chưa khai báo khu vực, giao diện báo chưa có dữ liệu thay vì dựng nhà ga/cổng giả.

### Trạng thái và thao tác

- `active=true`: **Khả dụng**, xanh lá; `active=false`: **Không khả dụng**, đỏ.
- Đây là hiệu lực danh mục. Không hiển thị “Đang sử dụng”, High/Overload hoặc Maintenance cho tài nguyên vì các trường hiện tại không xác nhận các ý nghĩa đó.
- Mỗi dòng có chi tiết: mã, loại, sân bay, quan hệ cha thật nếu có, trạng thái; metadata khu vực nếu đã lưu.
- Chi tiết tài nguyên khai thác đọc thêm tối đa 10 phân bổ gần nhất từ M18 và các đề nghị sửa thật. Chỉ đọc dữ liệu M18, không tạo/sửa/gỡ lịch.
- Thêm tài nguyên tại sân bay đã chọn dùng nghiệp vụ `resource` hiện có với sân bay được điền theo ngữ cảnh. Đổi khả dụng từ chi tiết dùng `MasterDataService`, cần người khác duyệt; tài nguyên còn phân bổ hiệu lực vẫn không được ngừng sử dụng.
- Không bổ sung chỉnh sửa khu vực phân cấp mới trong lượt này; Backend có API khu vực nhưng giao diện hiện tại tập trung đọc đúng quan hệ thật.

### Trạng thái giao diện

Có hiển thị đang tải trên form tìm/chọn/lọc, không có dữ liệu, không tìm thấy, lỗi 403 theo layout hiện hữu, 404 cho đối tượng không có trong phạm vi và 503 có nút thử lại khi lỗi đọc DB. Chức năng GET/POST cơ bản hoạt động khi tắt JavaScript.

## 4. Phân quyền và multi-role

`Access` và `User` thực tế đang sử dụng quyền theo `users.role`; admin được xét theo chính sách trung tâm hiện có. Catalog M01 hiện được cấp cho `data_admin` và admin theo chính sách này, không tự cấp thêm quyền đọc/sửa cho operations, airport_agent hoặc hành khách.

Có tệp `UserRole`, migration và lệnh QA multi-role chưa được tích hợp vào `Access`/`User` đang chạy. Lượt này không kích hoạt hệ thống quyền đó, không sửa cấu trúc role và không chạy migration multi-role lên DB ứng dụng. Các controller mới gọi `Access` thay vì viết quyền theo email/tài khoản. Truy vấn sân bay cũng giữ điều kiện sân bay của các vai trò mà chính sách hiện hữu xác định là scoped; bản ghi ngoài truy vấn được phép không trả dữ liệu.

Không tuyên bố đã kiểm thử đầy đủ một mô hình multi-role hoạt động khi runtime hiện tại chưa sử dụng mô hình đó. Kiểm tra tính không thay đổi quyền gồm so sánh chính sách và quyền trước/sau thao tác M01.

## 5. Khảo sát M18 — không thiết kế lại hoặc thay đổi nghiệp vụ

### Đối chiếu M18-F01…F05

Tên F01…F05 dưới đây theo báo cáo thứ cấp `docx-coverage-audit.md`; các nhóm câu hỏi của yêu cầu hiện tại được đối chiếu vào các khả năng tương ứng. Chưa có tài liệu gốc để xác nhận đầy đủ tiêu chí nghiệm thu.

| Chức năng M18 | Backend hiện tại | Frontend hiện tại | Đề xuất giai đoạn sau |
| --- | --- | --- | --- |
| F01 — Danh mục quầy/cửa/vị trí đỗ | `airport_resources`: TERMINAL/GATE/STAND/COUNTER, sân bay, `active`. Tạo bởi data_admin; sửa có phiên bản/duyệt độc lập. Không có trường bảo trì tài nguyên chuyên biệt. | Danh mục thuộc M01; M18 chọn tài nguyên đang active. Dropdown trước đây chủ yếu là mã tài nguyên. | Ghi rõ sân bay/loại/mã, năng lực và tiêu chí tương thích từng loại; định nghĩa bảo trì/khóa riêng nếu có đặc tả. |
| F02 — Lập lịch sử dụng | `resource_allocations`: `flight_id`, `resource_id`, `starts_at`, `ends_at`, người tạo, lý do. Khoảng dùng phải bao phủ giờ khởi hành. Khi công bố chuyến có giữ cửa tự động từ −45 đến +15 phút. | Bảng lịch; chọn chuyến/tài nguyên; nhập giờ địa phương, backend chuẩn hóa UTC. Chưa có lịch kéo theo thời gian. | Lịch theo sân bay/loại/ngày, giải thích rõ giờ địa phương/UTC và thời gian đệm. Không đồng nhất khung giờ này với năng lực hoạt động của sân bay. |
| F03 — Phân công theo chuyến | Phân bổ cho chuyến có cùng sân bay đi; không phân bổ trực tiếp cho công việc mặt đất. Người `operations` được tạo/gỡ; admin theo chính sách hiện hữu. Không có trạng thái yêu cầu hoặc bước duyệt phân bổ riêng. Phân bổ GATE cập nhật cửa/version chuyến. | Form tạo/gỡ và chọn chuyến; chưa có màn tiếp nhận nhu cầu hay hàng chờ duyệt M18 riêng. | Xây nhu cầu tài nguyên theo chuyến, quy tắc tương thích; chỉ thêm bước duyệt riêng khi có đặc tả. Không nhầm duyệt danh mục M01 với duyệt phân bổ M18. |
| F04 — Xung đột/khả dụng | Transaction, khóa sân bay/tài nguyên/chuyến; kiểm tra `active`, cùng sân bay, trạng thái chuyến, phương án đổi tàu đang chờ và giao khoảng thời gian. Điều kiện overlap: `old.starts_at < new.ends_at AND old.ends_at > new.starts_at`; hai khoảng chạm đầu/cuối không bị tính giao nhau. Chưa kiểm tra năng lực phục vụ theo loại tàu, bảo trì chuyên biệt hoặc thời gian đệm khác nhau. | Backend báo lỗi xung đột; chưa có trực quan khoảng bị trùng hoặc giải thích tài nguyên thay thế. | Cho xem khoảng xung đột, nhóm chuyến liên quan; bổ sung tương thích/công suất/bảo trì bằng dữ liệu có định nghĩa, không suy từ boolean. |
| F05 — Điều chỉnh/audit/thông báo | Gỡ bản ghi rồi phân bổ lại; audit `resource.allocated`/`resource.unallocated` có người/lý do. Gỡ xóa dòng allocation, audit không lưu toàn bộ snapshot khung giờ cũ. GATE phát `FlightChanged`; relay gửi thông báo cập nhật chuyến chung. Payload hiện không chứa mã cửa mới, chưa đủ để khẳng định có thông báo đổi cửa chi tiết. Gỡ không drain relay ngay ở nhánh dispatch, phụ thuộc worker. | Bảng lịch hiện có và form gỡ; chưa có timeline thay đổi, bản so sánh cũ/mới hay giao diện duyệt phân bổ. Audit tra cứu qua khu vực kiểm toán theo quyền. | Lưu phiên bản/soft-cancel lịch, snapshot trước/sau, timeline và trạng thái giao thông báo; bổ sung payload cửa cụ thể nếu đặc tả yêu cầu. |

### Giải thích nghiệp vụ bằng ví dụ

M01 khai báo **cửa A2 tại HAN có trong danh mục và có hiệu lực**. M18 giữ cửa A2 cho chuyến AS101 trong khoảng, ví dụ 09:15–10:15, bao phủ giờ khởi hành 10:00. Chuyến khác muốn dùng A2 lúc 09:45–10:30 sẽ bị từ chối vì giao khoảng. Có thể dùng lại từ đúng 10:15 nếu các điều kiện khác hợp lệ theo quy tắc hiện tại.

Không thể kết luận “HAN quá tải” chỉ vì A2 có lịch hoặc không khả dụng. Trạng thái bảo trì của sân bay trong M01 cũng không tự khóa tất cả tài nguyên hay hủy phân bổ M18.

### Những điểm khó hiểu cho nhân viên tại M18

- Bảng dữ liệu và biểu mẫu tách nhau; người dùng khó nhận ra khoảng phân bổ và bản ghi cần gỡ dù một số ID đã được chuyển thành nhãn.
- Mã tài nguyên đơn lẻ có thể trùng giữa nhiều sân bay khi admin xem toàn hệ thống; nên kèm sân bay và loại.
- Chưa phân biệt rõ “hiệu lực danh mục”, “đang có lịch chiếm dụng” và “đang bảo trì”.
- Chưa trực quan khoảng xung đột, thời gian đệm, chuyến liên quan hoặc lịch sử sau khi gỡ.
- Không có quy trình phê duyệt riêng cho allocation; giao diện không nên tạo cảm giác có một bước duyệt đang tồn tại.

### Backend có dữ liệu/khả năng nhưng UI M18 chưa khai thác hết

- Allocation đã lưu `actor_id`, `created_at`, `updated_at`, nhưng bảng M18 hiện tập trung vào chuyến, tài nguyên, khung giờ và lý do; chưa trình bày người phân bổ và thời điểm tạo/cập nhật theo từng dòng.
- Backend đã phát hiện khoảng giao nhau và từ chối thao tác, nhưng UI chưa vẽ các khoảng bị trùng hoặc cho xem lịch tài nguyên ngay cạnh form trước khi gửi.
- Có audit cấp node và sự kiện cập nhật chuyến, nhưng UI M18 chưa có timeline theo allocation/tài nguyên hoặc trạng thái giao thông báo. Audit hiện hữu cũng chưa chứa đủ snapshot để dựng lại mọi khung giờ đã bị gỡ.
- Có giữ cửa tự động khi công bố lịch, nhưng UI M18 chưa giải thích rõ nguồn phân bổ tự động và cửa sổ −45/+15 phút ngay tại bảng.

Ngược lại, quy trình tiếp nhận nhu cầu, phê duyệt allocation riêng và quy tắc tương thích/công suất chi tiết chưa có Backend hoàn chỉnh; đây không phải những chức năng chỉ cần thêm một nút ở Frontend.

Không sửa `OperationsService::allocate/unallocate`, cách phát sự kiện hoặc dữ liệu lịch sử M18 trong lượt này. Sidebar “Phân bổ tài nguyên” vẫn dẫn `/workspace/resources`.

## 6. Tệp và mục đích

| Tệp | Mục đích |
| --- | --- |
| `backend/database/migrations/2026_11_09_000000_add_airport_operational_status.php` | Thêm bốn cột nullable và index; không backfill. |
| `backend/app/Http/Controllers/AirportController.php` | Danh sách, chi tiết, lọc hạ tầng, trạng thái thủ công và chuyển đề nghị sửa vào service hiện có. |
| `backend/app/Support/AirportWorkspace.php` | Danh mục badge, nhãn loại, query sân bay được phép và URL giữ ngữ cảnh. |
| `backend/app/Http/Controllers/WorkspaceController.php` | Chuyển hai luồng M01 sang controller riêng; luồng đường bay/M02 và M18 giữ nguyên. |
| `backend/app/Modules/Operations/MasterDataService.php` | Tách trạng thái vận hành khỏi stale-check hồ sơ sân bay, tương thích snapshot trước migration. |
| `backend/routes/web.php` | Route chi tiết/cập nhật M01, giữ auth/verified/throttle. |
| `backend/bootstrap/app.php` | Trang lỗi đọc DB M01 và không tìm thấy; không hiển thị lỗi SQL cho người dùng. |
| `backend/app/Support/StaffNavigation.php` | Chỉ thêm nhận diện active cho route chi tiết M01; không tổ chức lại sidebar. |
| `backend/resources/views/m01/{index,infrastructure,airport-detail,resource-detail,status,load-error,not-found,import-export-links,value-list}.blade.php` | UI M01 theo đối tượng, dữ liệu thật, nhập/xuất, hiển thị giờ hoạt động/thuộc tính bổ sung bằng nhãn và trạng thái tải/lỗi/rỗng. |
| `frontend/app.css`, `frontend/app.js` | Giao diện navy/xanh, badge, responsive, bộ chọn có tìm kiếm, trạng thái tải và mở form theo ngữ cảnh. |
| `backend/public/app.css`, `backend/public/app.js` | Bản asset đồng bộ qua `npm run assets`. |
| `backend/tests/Feature/AirportWorkspaceTest.php` | Kiểm thử dữ liệu, audit, quyền, lọc/phân trang, lỗi, phê duyệt và bảo toàn nghiệp vụ. |
| `backend/tests/Feature/SidebarWorkflowTest.php` | Cập nhật kiểm tra hai luồng catalog theo UI M01 mới; giữ kiểm tra các luồng còn lại. |

## 7. Migration, dữ liệu làm việc và kiểm thử

Migration có thể cần khóa DDL ngắn khi thêm cột/index. Không xóa bảng/cột cũ, không sửa `active`, không tạo ngưỡng hoặc tự gán Normal. Phải thử trên DB `airline_test` riêng trước khi áp dụng đúng tệp migration này vào DB làm việc; không chạy toàn bộ các migration chưa theo dõi của người dùng.

Snapshot trước thay đổi trên DB làm việc: **22 sân bay, 644 tài nguyên, 0 khu vực phân cấp, 987 chuyến và 2.918 phân bổ**. Có hash dữ liệu sân bay cũ, tài nguyên và phân bổ để so sánh sau migration; snapshot lưu riêng trong `backend/storage/app/private/`.

Migration đã được kiểm thử trên `airline_test` rồi áp dụng **riêng tệp mới** vào DB làm việc. So sánh sau migration: số lượng và hash các trường sân bay cũ/tài nguyên/phân bổ không đổi; **22/22 sân bay vẫn có trạng thái vận hành NULL**. Không cập nhật trạng thái demo để làm đẹp giao diện.

| Kiểm tra | Kết quả |
| --- | --- |
| M01: nguồn trạng thái, validation/audit, không đổi active/chuyến, chi tiết và ngữ cảnh, lọc trước phân trang, quan hệ cha thật, đề nghị sửa, không ngừng tài nguyên còn lịch, snapshot trước migration, quyền, lỗi đọc DB, đề nghị nhập trước khi sân bay tồn tại | **PASS — 11 tình huống**; lỗi active của sidebar và truy vấn JSON null trong hàng chờ nhập được sửa và kiểm tra lại. |
| Điều hướng và các luồng đã có | **PASS — 7 tình huống SidebarWorkflowTest**; kiểm tra chọn luồng theo vai trò được chạy lại sau sửa active. |
| M18/Master: trùng lịch và gỡ trước phân bổ lại; duyệt độc lập/stale-source; validation/quyền API tài nguyên; nhập CSV/duyệt độc lập/rollback dòng sai và xuất CSV | **PASS — 4 tình huống hồi quy OperationsWorkflowTest**. |
| PHP lint, Blade và format các tệp mới/điều hướng/test | **PASS**. |
| `npm run assets`, kiểm tra cú pháp JavaScript | **PASS**. |
| Dữ liệu làm việc trước/sau migration | **PASS** — 22 sân bay, 644 tài nguyên, 987 chuyến, 2.918 phân bổ giữ nguyên; 0 khu vực vẫn là 0. |
| Toàn bộ suite PHP của dự án | **CHƯA KIỂM TRA** trong lượt này; chỉ chạy các nhóm liên quan ở trên. |
| Multi-role đầy đủ | **CHƯA KIỂM TRA** vì runtime quyền hiện tại chưa tích hợp các tệp multi-role thử nghiệm. Không sửa hoặc kích hoạt kiến trúc này. |
| Trình duyệt: badge, bàn phím, ngữ cảnh danh sách, giờ Tokyo, chọn sân bay trước, trạng thái tải, lọc/phân trang, quan hệ cha, đổi sân bay, kết quả rỗng, 404 và 403 | **PASS** trên Chromium, dùng riêng DB test. Nhập CSV và hàng chờ duyệt cũng được kiểm tra qua HTTP thật. |
| Responsive 1920, 1440, 1024, 768, 390, 320 px | **PASS — 24 tổ hợp**: danh sách sân bay, chi tiết sân bay, bước chọn sân bay và danh sách hạ tầng. Không tràn ngang toàn trang hoặc chữ vượt khung trong các phần đã đo. Mở/đóng sidebar trên màn hình nhỏ đạt. |
| JavaScript trình duyệt | **PASS** — không ghi nhận lỗi JavaScript trong lượt chạy hoàn tất. |
| Hồi quy trình duyệt M02/M03/M18 | **PASS** — tuyến đường bay, lập lịch, theo dõi chuyến và phân bổ tài nguyên trả trang hợp lệ. |

Ảnh xem trước (dữ liệu test, không phải trạng thái được gán cho demo) lưu trong `backend/storage/app/private/`: `m01-airports-1440.png`, `m01-detail-1440.png`, `m01-picker-1440.png`, `m01-infrastructure-1440.png` và các bản tương ứng `390.png`.

Dịch vụ test tạm dùng cổng 18081 được dừng sau kiểm tra. Ứng dụng làm việc vẫn ở cổng 8000. Các tệp điều khiển/credential test tạm được dọn; giữ snapshot kiểm chứng và ảnh xem trước trong vùng private, ngoài Git.

## 8. Các giới hạn còn lại

- Cần chủ sở hữu nghiệp vụ định nghĩa công suất, ngưỡng và phạm vi bảo trì trước khi tự động hóa High/Overload/Maintenance.
- DB làm việc chưa có bản ghi `airport_areas`; giao diện khu vực hiển thị trạng thái trống đúng dữ liệu, không tự sinh demo.
- Chưa có quan hệ `airport_resources → airport_areas`, nên không dựng cây tài nguyên giả dưới nhà ga.
- Giờ địa phương là ảnh chụp lúc tải trang, không phải đồng hồ cập nhật liên tục.
- Chi tiết chỉ sửa các trường hồ sơ mà `MasterDataService` hiện hỗ trợ; không thêm quy tắc sửa tọa độ/ICAO/giờ hoạt động mới. Các giá trị này vẫn được hiển thị khi có.
- Chưa mở rộng M18 sang phê duyệt riêng, tương thích năng lực, bảo trì tài nguyên hoặc lịch sử có phiên bản; đề xuất nằm trong phần khảo sát.
- Thiếu tài liệu gốc và runtime multi-role hoàn chỉnh nên chưa thể xác nhận nghiệm thu hai phần đó.
