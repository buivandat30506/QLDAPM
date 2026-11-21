# Trạng thái tổng hợp và vị trí hạ tầng

**Cập nhật 10/11/2026:** đã khởi tạo NORMAL cho 644 hồ sơ có hiệu lực chưa có condition, thống nhất nhãn và bỏ nội dung thử nghiệm khỏi giao diện. Không gán parent hoặc đổi ID/lịch. Xem [kết quả và quy tắc hiện hành](product-status-ui-update.md); số liệu NULL/trạng thái trong các phần khảo sát dưới đây thuộc snapshot trước lần khởi tạo này.

Ngày 09/11/2026. Lượt này chỉ chuẩn hóa cách trình bày, cải thiện chọn vị trí và quyền/trạng thái sân bay theo yêu cầu. **Không thêm/xóa/gộp loại hạ tầng, không đổi ID hoặc tự gán parent.** Danh mục vẫn 41 loại; xem [phân tích đầy đủ từng loại](airport-infrastructure-types-review.md).

## 1. Một cột Trạng thái, hai trường dữ liệu gốc

`airport_infrastructure.active` biểu thị hiệu lực danh mục, `technical_state` biểu thị condition đã khai báo. Hai trường vẫn tách biệt trong DB, form và quy trình duyệt. Bảng danh sách chuẩn hóa và các bảng tham chiếu M10/M11 chỉ dùng **một cột Trạng thái**; không dùng allocation để tính badge này.

| Ưu tiên | Dữ liệu Backend | Badge Frontend | Màu |
|---:|---|---|---|
| 1 | active=false, bất kể condition | Ngừng hiệu lực | Xám |
| 2 | active=true; NORMAL | Bình thường | Xanh lá |
| 2 | active=true; RESTRICTED | Hạn chế sử dụng | Vàng/cam |
| 2 | active=true; MAINTENANCE | Đang bảo trì | Tím |
| 2 | active=true; OUT_OF_SERVICE | Tạm ngừng hoạt động | Đỏ |
| 3 | active=true; condition NULL hoặc chưa nhận biết | Chưa xác định | Trung tính |

Ví dụ active=false và MAINTENANCE: danh sách hiển thị “Ngừng hiệu lực”, tooltip giải thích tình trạng gốc vẫn còn; chi tiết vẫn cho xem MAINTENANCE. Active=true nhưng NULL **không** trở thành “Bình thường”. Mã condition không nhận biết được không bị tự sửa trong DB; UI thể hiện chưa xác định.

`InfrastructureDisplay::state` và partial `m01.infrastructure-state` là nguồn mapping dùng chung. Badge có chấm tròn và tên; không phụ thuộc màu để truyền đạt trạng thái. Chi tiết hạ tầng giữ riêng “Hiệu lực danh mục” và “Tình trạng vận hành” để người dùng hiểu dữ liệu gốc; form vẫn có hai input riêng.

Các bảng legacy có active nhưng không có condition hiển thị Ngừng hiệu lực nếu false, Chưa xác định nếu true. Không gọi active=true là “khả dụng/trống lịch”. API legacy giữ các trường dữ liệu cũ, không thay field response thành một mã trạng thái tổng hợp mới.

## 2. Phân biệt với sân bay và lịch M18

Bốn trạng thái NORMAL/HIGH/OVERLOAD/MAINTENANCE của **sân bay** không được sao chép vào condition hạ tầng. HIGH/OVERLOAD không phải mã condition hạ tầng. Condition RESTRICTED và OUT_OF_SERVICE không được tự chuyển lên trạng thái sân bay.

M18 vẫn quản lý khoảng starts_at/ends_at, overlap, hiệu lực và gate projection theo backend hiện có. Phần lịch trong chi tiết đọc allocation thật tại thời điểm được ghi rõ, không biến trạng thái sử dụng theo lịch thành “Bình thường/Bảo trì”. Thay đổi condition không tự di chuyển/hủy allocation hoặc flight. Chưa có quy tắc BA cho những tác động đó.

## 3. Ý nghĩa “Thuộc khu vực / công trình”

Trường dữ liệu là `airport_infrastructure.parent_id`, tham chiếu một **hồ sơ công trình/khu vực** cùng sân bay. Không phải parent menu, mã nhà ga tự suy, quan hệ chuyến bay hay phân bổ theo lịch. `airport_code` luôn xác định sân bay; parent tùy chọn bổ sung vị trí bên trong sân bay.

Nhãn danh sách và form đổi thành “Thuộc khu vực / công trình”. Nếu có dữ liệu, hiển thị chuỗi tổ tiên thực bằng code và name, ví dụ nhà ga đã khai báo / tầng đã khai báo. Không viết một đường dẫn T1/khu check-in chỉ dựa vào mã C01. Phần đọc dùng `InfrastructureDisplay::location`:

- Các loại được mô hình quy định trực thuộc sân bay (TERMINAL/PASSENGER_TERMINAL/CARGO_TERMINAL/RUNWAY/TAXIWAY) khi parent NULL: “Trực thuộc sân bay XXX”. Đây là cấp sân bay đã có FK, không phải tạo một vùng khu bay giả.
- Loại có thể có khu vực cha nhưng chưa biết: “Chưa khai báo khu vực (sân bay XXX)”. Đây là thiếu thông tin tùy chọn, không phải lỗi dữ liệu và không làm mất sân bay đã xác định.
- Khi có parent: đi theo các ID thật để trình bày đầy đủ công trình/khu vực tổ tiên. Không tạo link hoặc tên cho bản ghi không tồn tại; trường hợp dữ liệu bất thường có thông báo cần kiểm tra.

Snapshot thực có 644 hồ sơ, **644 parent_id NULL**; 44 TERMINAL trực thuộc sân bay là hợp lệ. 600 gate/counter/stand chưa được cung cấp khu vực/sân đỗ cụ thể. Lượt này không tự điền cho bất kỳ hồ sơ nào. Quan hệ đã có trong DB được bảo toàn; ví dụ cây TST trong kiểm thử chỉ nằm ở database cô lập.

## 4. Dropdown và validation dùng chung

Danh sách cha lấy theo đúng sân bay và category facility. Mã loại cha phù hợp được định nghĩa tập trung trong `InfrastructureDisplay::parentTypes`; `InfrastructureService::validate` dùng chính tập này, nên gọi API trực tiếp không thể né dropdown.

| Loại con | Cha có thể khai báo trong phạm vi triển khai |
|---|---|
| Ba loại nhà ga, RUNWAY, TAXIWAY | Không có parent hạ tầng; trực thuộc sân bay |
| LEVEL | Một trong ba loại nhà ga |
| DEPARTURE_HALL, ARRIVAL_HALL | Nhà ga hoặc LEVEL |
| STAND, TAXILANE | APRON |
| GATE, BUS_BOARDING_POINT, JET_BRIDGE | Nhà ga, LEVEL, sảnh đi/đến, ZONE, WAITING_AREA |
| COUNTER, BAG_DROP | Nhà ga, LEVEL, sảnh đi/đến, ZONE, CHECKIN_AREA |
| ARRIVAL_EXIT | Nhà ga, LEVEL, ARRIVAL_HALL, ZONE |
| ARRIVAL_BELT | Nhà ga, LEVEL, ARRIVAL_HALL, CLAIM_AREA, ZONE |
| DEPARTURE_BELT | Nhà ga, LEVEL, CHECKIN_AREA, BAG_SORTING_AREA, BAG_MAKEUP_AREA, ZONE |
| BELT chưa phân loại | Nhà ga, LEVEL, sảnh, ZONE, CHECKIN_AREA, CLAIM_AREA, BAG_SORTING_AREA, BAG_MAKEUP_AREA |
| Các khu trong nhà ga và ZONE | Nhà ga, LEVEL, sảnh đi/đến, ZONE; không được tạo self/cycle |
| Khu phân loại/tập kết/quá khổ | Nhà ga, LEVEL, sảnh, ZONE, CHECKIN_AREA, CLAIM_AREA, CARGO_WAREHOUSE |
| CARGO_WAREHOUSE | CARGO_TERMINAL hoặc ZONE |
| HOLDING_AREA, RESTRICTED_AREA | APRON hoặc ZONE |
| APRON và các công trình hỗ trợ/phụ trợ còn lại | ZONE nếu có quan hệ thực; có thể để trống trực thuộc sân bay |

Đây là ma trận validation tối thiểu phục vụ vị trí, **không phải bản quy hoạch sân bay được BA xác nhận**. Không áp dụng để tự chuyển các hồ sơ hiện hữu. Có thể cần mở rộng các quan hệ chuyên biệt như khu bảo dưỡng trong hangar hoặc gate–bridge sau khi BA chốt; không tạo liên kết giả ngoài ma trận trong lượt này.

Dropdown loại bỏ chính hồ sơ đang sửa và mọi công trình/khu vực có chuỗi tổ tiên chứa hồ sơ đó. Backend tiếp tục kiểm tra cùng sân bay, loại cha, category facility và chu trình; đọc/khóa chuỗi cha trong transaction để kiểm tra dữ liệu hiện tại. FK ghép (parent_id,airport_code) tiếp tục ngăn liên kết sai sân bay. Đường băng không có dropdown nhà ga; input hidden trống giữ đúng parent NULL.

Thêm/sửa parent vẫn qua service và quy trình đề nghị–duyệt độc lập đã có; không có endpoint tự sửa SQL hoặc tự duyệt. Không tự áp dụng tác động đến lịch/chuyến vì đổi parent.

## 5. Bộ lọc 41 loại

Giữ sáu nhóm và link “Tất cả”; chọn nhóm trước giúp dropdown chỉ có các loại trong nhóm. Trong phạm vi tất cả, dropdown có optgroup theo sáu nhóm và ô tìm theo tên tiếng Việt hoặc mã kỹ thuật. Cùng component dùng cho bộ lọc và mở form tạo mới.

Tìm loại chỉ thu gọn **lựa chọn** trên trình duyệt; truy vấn danh sách hạ tầng vẫn lọc SQL theo airport/group/kind/q, phân trang 12 dòng. Không tải toàn bộ tài nguyên lên trang để lọc bằng JavaScript. Mọi loại vẫn có thể lọc trực tiếp qua kind hoặc qua nhóm, kể cả loại 0 hồ sơ. Không ẩn loại khỏi DB hoặc áp dụng đề xuất A/B/C của báo cáo rà soát vào UI.

Khi tìm tên không khớp lựa chọn hiện tại, giữ lựa chọn đó cho tới khi người dùng chủ động đổi/xóa; không âm thầm sửa kind đang được áp dụng. Có thông báo số loại phù hợp. Input/select chuẩn HTML dùng được bằng bàn phím; nhóm và form vẫn có label.

## 6. File mới/sửa trong lượt này

Các đường dẫn backend tính từ `backend/`:

| File | Mục đích |
|---|---|
| database/migrations/2026_11_09_020000_add_airport_status_authorities_and_sources.php (mới) | Nguồn mô phỏng/thủ công và bảng bổ nhiệm, không đổi schema hạ tầng |
| app/Support/AirportStatusAuthority.php (mới) | Quyền Giám đốc và phạm vi, ghi nhận bổ nhiệm |
| app/Modules/Operations/AirportStatusSimulation.php (mới) | Mapping cố định, guard local/test, fingerprint, seed/clear/audit |
| app/Console/Commands/AirportStatusAuthorityCommand.php (mới) | Console bổ nhiệm/thu hồi có chủ đích |
| app/Console/Commands/SimulateAirportStatuses.php (mới) | Console seed/clear, mặc định dry-run |
| app/Support/InfrastructureDisplay.php (mới) | Badge tổng hợp, ma trận parent, lọc dropdown, đường dẫn vị trí |
| app/Support/AirportWorkspace.php | Quyền đọc trạng thái theo phạm vi; giữ riêng quyền catalog hạ tầng |
| app/Support/StaffNavigation.php | Link đọc danh sách sân bay theo quyền; giữ cấu trúc nhóm/nút |
| app/Http/Controllers/AirportController.php | Quyền trạng thái riêng, xác nhận/reason/source/audit, lịch sử mô phỏng |
| app/Http/Controllers/InfrastructureController.php | Parent choices đúng loại, map tổ tiên thực |
| app/Http/Controllers/InfrastructureReferenceController.php | Cùng cách hiển thị vị trí trên bảng tra cứu M10/M11 |
| app/Modules/Operations/InfrastructureService.php | Backend dùng chung ma trận cha, giữ kiểm tra chu trình/sân bay |
| app/Modules/Operations/MasterDataService.php | Các trường provenance trạng thái độc lập với stale snapshot hồ sơ |
| resources/views/m01/airport-status-panel.blade.php (mới) | Form Giám đốc/readonly, nguồn, xác nhận, validation |
| resources/views/m01/airport-status-legend.blade.php (mới) | Chú giải bốn trạng thái và nguồn mô phỏng |
| resources/views/m01/airport-history.blade.php (mới) | Audit người thực hiện hoặc công cụ mô phỏng |
| resources/views/m01/infrastructure-state.blade.php (mới) | Một badge dùng chung |
| resources/views/m01/infrastructure-location-field.blade.php (mới) | Chọn cha phù hợp hoặc giải thích trực thuộc sân bay |
| resources/views/m01/infrastructure-type-picker.blade.php (mới) | Nhóm/tìm loại, dùng chung filter/create |
| resources/views/m01/index.blade.php | Nhãn mô phỏng/legend, chỉ người có quyền M01 thấy tạo/import |
| resources/views/m01/airport-detail.blade.php | Ghép panel/history, tách quyền trạng thái và sửa hồ sơ |
| resources/views/m01/status.blade.php | Nguồn mô phỏng rõ ràng bên cạnh badge |
| resources/views/m01/unified-infrastructure.blade.php | Một cột trạng thái, tên vị trí dễ hiểu, type picker |
| resources/views/m01/infrastructure-fields.blade.php | Parent field theo loại, không đổi active/condition input |
| resources/views/m01/infrastructure-detail.blade.php | Đường dẫn vị trí thực, vẫn giữ hai trạng thái gốc |
| resources/views/m01/infrastructure-reference.blade.php | Cùng cột trạng thái/vị trí trên trang tham chiếu |
| resources/views/m01/infrastructure.blade.php | Fallback legacy không suy active thành khả dụng |
| tests/Feature/AirportStatusDisplayTest.php (mới) | Quyền/scope, mô phỏng/clear, trạng thái, parent và 41 filter |
| tests/Feature/AirportWorkspaceTest.php | Cập nhật kỳ vọng quyền mới, xác nhận và badge legacy |
| frontend/app.js; backend/public/app.js | Tìm loại trên danh sách options |
| frontend/app.css; backend/public/app.css | Badge xám, nhãn mô phỏng, legend, checkbox, type picker |
| docs/airport-infrastructure-types-review.md (mới) | Báo cáo đủ 41 loại và đề xuất tinh giản chưa áp dụng |
| docs/airport-status-permission-report.md (mới) | Cơ chế nguồn, seed/clear và quyền Giám đốc |
| docs/infrastructure-status-location-report.md (mới) | Tài liệu này và nghiệm thu |

Các helper snapshot/backup/restore/browser chỉ là công cụ kiểm tra trong storage/app/private, không phải tính năng sản phẩm. Các file WIP khác có sẵn trong git status được giữ nguyên; không tuyên bố chúng đều thuộc lượt này. Assets modules.css/modules.js chỉ có cảnh báo line ending từ đồng bộ, không có thay đổi nghiệp vụ.

## 7. Nghiệm thu

| Phạm vi | Kết quả thực tế | Bằng chứng/giới hạn |
|---|---|---|
| Snapshot/backup/restore ba node | PASS | Backup 20261109T153211Z-18453; restore SQL vào status_verify_20261109; không reset DB làm việc |
| Migration up/down/up trên bản khôi phục | PASS | Schema bổ nhiệm + provenance; hash dữ liệu legacy khớp; không chạy down trên DB làm việc |
| Bộ hồi quy chính | **PASS: 62 tests, 704 assertions** | AirportStatusDisplayTest, AirportWorkspaceTest, InfrastructureNormalizationTest, SidebarWorkflowTest, UseCaseBackendWorkflowTest, FlightResourceChangeTest, các bài operations/boarding/disruption liên quan |
| Kiểm tra lại sau điều chỉnh quyền đọc sân bay | **PASS: 28 tests, 366 assertions** | AirportStatusDisplayTest, AirportWorkspaceTest, SidebarWorkflowTest; nhân viên xem đúng phạm vi và không có quyền ghi |
| Ma trận trạng thái/giữ DB riêng | PASS | Inactive ưu tiên; active+NULL chưa xác định; 4 condition còn lại đúng nhãn; bảng 1 cột, chi tiết giữ trường gốc |
| Quan hệ vị trí | PASS | STAND chỉ thấy APRON; RUNWAY không có parent select; GATE không nhận RUNWAY; self/descendant loại khỏi dropdown; kiểm tra cycle/cross-airport hiện hữu vẫn đạt |
| Filter 41 loại | PASS | Gọi cả 41 kind theo nhóm và kiểm tra kết quả SQL; browser tìm TAXI còn TAXIWAY/TAXILANE; nhóm passenger còn 6 loại; không xóa loại |
| Browser/responsive | **PASS: 13 trang × 6 độ rộng** | 1920/1440/1024/768/390/320; không vượt scrollWidth toàn trang; bảng rộng có vùng cuộn nội bộ; không pageerror JavaScript |
| Quyền/confirm trên browser | PASS | data_admin/admin không bổ nhiệm bị API 403; Giám đốc HAN chỉ thấy HAN, ngoài phạm vi 403; thiếu confirmed 422; form chưa tích không submit; có xác nhận chuyển nguồn MANUAL và ghi lý do |
| M03/M10/M11/M18 | PASS trong các luồng được kiểm tra | Trang đọc/tham chiếu/phân bổ mở được; regression overlap/duyệt/boarding/disruption/resource change đạt; không triển khai lịch belt/runway |
| Blade/JS/assets | PASS | 28 templates compile+parse; node --check; npm run assets; git diff --check không lỗi whitespace, có cảnh báo CRLF từ Git |
| Áp dụng cục bộ và bảo toàn | PASS | 23:44:36 ngày 09/11/2026 UTC+7; 17 bảng legacy khớp hash; audit cũ ops/mysql/finance giữ nội dung; source/audit mô phỏng mới đúng phạm vi |
| Production/database nhóm/tải lớn/thiết bị thật/ATC | **CHƯA KIỂM TRA / ngoài phạm vi** | Không triển khai môi trường đó, không xác nhận an toàn khai thác hoặc ngưỡng tải |

Lần hồi quy đầu phát hiện 6 lỗi cùng nguyên nhân regex chứa ký tự `|` trong chuỗi validation scope; đã chuyển rule sang dạng array, chạy lại đạt kết quả trên. Không dùng lần lỗi làm bằng chứng PASS. Hai bất nhất kỳ vọng admin của bộ rộng lượt trước không thuộc các thay đổi nghiệp vụ hiện tại; không tự sửa chính sách admin ở các module khác.

Database làm việc sau áp dụng:

| Dữ liệu | Trước | Sau |
|---|---:|---:|
| Sân bay | 22 | 22 |
| Hồ sơ hạ tầng chuẩn | 644 | 644 |
| Loại hạ tầng | 41 | 41 |
| Tài nguyên nguồn cũ | 644 | 644 |
| Khu vực nguồn cũ | 0 | 0 |
| Chuyến bay | 987 | 987 |
| Phân bổ M18 | 2.918 | 2.918 |
| Hồ sơ hạ tầng chưa có parent | 644 | 644 |
| Trạng thái sân bay mô phỏng | 0 | 22 (6 NORMAL, 6 HIGH, 5 OVERLOAD, 5 MAINTENANCE) |
| Bổ nhiệm quyền trạng thái tự tạo trên DB làm việc | 0 | 0 |

Số liệu bảng review được kiểm tra tự động: đủ 41 dòng, mã kỹ thuật/nhãn tiếng Việt/số bản ghi khớp snapshot thực. Không lấy các ví dụ TST của browser làm số liệu trong review.

Artifacts kiểm chứng nằm trong `backend/storage/app/private/` (không commit): `status-before.json`, `status-local-verification.json`, `status-migration-verification.json`, `status-tests.log`, `status-directory-tests.log`, `status-browser-results.json` và ảnh `status-*-{1440,390}.png`. Artifact local có count/hash, trạng thái seed/replay/clear dry-run và audit prefix trước–sau. Preview tạm đã dừng; credential/helper thử nghiệm đã dọn, giữ backup và bằng chứng kết quả.

## 8. Nội dung cần BA quyết định

Ma trận parent chi tiết, ý nghĩa một số loại tổng quát, tên gọi dễ hiểu hơn, danh mục ưu tiên A/B/C, vòng đời bổ nhiệm và việc tích hợp Multi-role cần BA/quản trị chốt. Chưa có chính sách tình trạng kỹ thuật tác động tự động đến phân bổ, bảo trì riêng cho công trình, lịch băng chuyền/runway hoặc chuyển trạng thái tổng thể từ tài nguyên. Không tự triển khai các hành vi đó trong lượt này.
