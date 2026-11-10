# Tên và vị trí hạ tầng M01

Ngày kiểm tra: 10/11/2026.

## Nguyên nhân và dữ liệu thực tế

Seeder tài nguyên cũ chỉ tạo `airport_code`, `code`, `type`. Bảng `airport_resources` không cung cấp tên riêng hoặc quan hệ công trình cha. `InfrastructureNormalizer` giữ tên từ `airport_areas` nếu có, còn tài nguyên không có tên được chuyển với `name = NULL`; không có căn cứ để tự gán T1/T2.

Khảo sát chỉ đọc trên database ứng dụng: 644 hạ tầng, 644 tên trống, 644 `parent_id` trống. Trong đó 44 nhà ga trực thuộc sân bay và 600 cổng/quầy/vị trí đỗ có thể bổ sung vị trí chi tiết. BMV có 26 hạng mục: 2 nhà ga, 8 cổng, 8 quầy và 8 vị trí đỗ; cả 26 chưa có parent, trong đó 24 thuộc loại có thể khai báo parent. Không phát hiện vị trí sai qua kiểm tra sân bay, loại cha và chuỗi tổ tiên.

## Cách hiển thị

- `InfrastructureDisplay::name()` ưu tiên tên đã lưu; nếu tên trống, suy ra nhãn từ loại và mã. Áp dụng đủ 10 loại hiện hành; GATE dùng “Cổng”, STAND dùng “Vị trí đỗ”, các loại khác dùng nhãn trong `InfrastructureCatalog`. Ví dụ: Quầy làm thủ tục C01, Cổng A01, Vị trí đỗ P01, Nhà ga T01.
- Danh sách M01 và tra cứu chung dùng `tableName()`: tên riêng nếu có, hoặc mã. Mã nằm dòng phụ nếu tên riêng khác mã. Cột Loại đã giải thích loại nên không lặp tên suy ra ở từng hàng.
- Chi tiết, lựa chọn công trình cha, vị trí đỗ và các nhãn phân bổ dùng hàm tên chung. Chi tiết nói rõ tên suy ra chưa phải tên riêng được xác nhận; ô nhập tên vẫn là dữ liệu gốc.
- Không có parent: hiển thị “Sân bay BMV”. Có parent: hiển thị chuỗi tên từ các bản ghi thật. Thiếu sân bay, thiếu bản ghi cha, sai sân bay, loại cha không phù hợp hoặc chu trình: báo cần kiểm tra, không dựng vị trí thay thế.
- Giữ nguyên 10 loại, nhóm hạ tầng và trạng thái. Không ghi tên suy ra vào DB.

## Quy trình cập nhật vị trí

1. Đăng nhập bằng quản trị hệ thống hoặc quản trị dữ liệu (`admin` / `data_admin`).
2. Mở hồ sơ, chọn “Cập nhật vị trí / hồ sơ”. Sân bay quản lý được hiển thị rõ và không thay đổi theo đề nghị.
3. Chọn công trình thật đã xác minh, cùng sân bay và đúng loại cha. Dropdown bỏ chính đối tượng và hậu duệ. Nếu chưa có công trình phù hợp, khai báo nhà ga/khu vực qua M01 trước.
4. Nhập lý do và gửi đề nghị. Có thể giữ vị trí chi tiết trống vì parent là tùy chọn.
5. Người khác có quyền duyệt độc lập. Backend kiểm tra lại phiên bản nguồn, sân bay, loại cha và chu trình trước khi áp dụng; ghi audit hiện có. Vị trí mới xuất hiện sau khi duyệt.

Tái sử dụng `InfrastructureService::propose/approve`; không thêm endpoint cập nhật trực tiếp hoặc luồng duyệt thứ hai. Nút chỉnh sửa và duyệt chỉ hiển thị cho vai trò được backend cho phép.

## Phạm vi các phân hệ

M01 dùng chung hàm tên/vị trí cho danh sách, hồ sơ và dropdown. M03 dùng tên chung tại bảng hạ tầng của các phân bổ đã lưu. M10/M11 dùng cùng cách trình bày tại trang tham chiếu. M18 và các form dùng `resource_id`/`allocation_id` dùng tên chung qua `WorkspaceForms` và `WorkspacePresentation`, giữ nguyên giá trị ID của option. M09 tiếp tục dùng mã cổng trong thông tin chuyến/boarding: đây là mã nghiệp vụ đã lưu, không suy nhà ga hoặc quan hệ vị trí từ mã này; các thành phần tham chiếu hạ tầng dùng chung vẫn theo M01. Không thay đổi nghiệp vụ check-in/boarding, lịch hoặc phân bổ.

## Cập nhật hàng loạt

Chưa thêm nút hàng loạt. Hệ thống hiện có snapshot và duyệt độc lập cho từng hồ sơ, chưa có đối tượng đợt thay đổi, màn hình xem trước hay quyết định duyệt cho cả đợt. Gửi nhiều lệnh rồi báo đã cập nhật sẽ không bảo đảm kết quả của cả nhóm.

Phương án triển khai: chọn ID thật trong một sân bay; chọn cha đã xác minh; xem trước mã, tên, vị trí cũ/mới của từng đối tượng; kiểm tra quyền và toàn bộ đồ thị; xác nhận lý do; tạo các đề nghị hiện có trong một transaction kèm mã đợt để audit. Khi duyệt cần quy định rõ duyệt từng đề nghị hay nguyên đợt; mọi đề nghị phải được kiểm tra lại snapshot và quan hệ tại thời điểm duyệt. Trước khi có chức năng này, thực hiện đề nghị từng hồ sơ theo quy trình trên.

## Các file thay đổi trong lượt này

- `backend/app/Support/InfrastructureDisplay.php`: tên và vị trí dùng chung, phát hiện quan hệ bất hợp lệ.
- `backend/app/Modules/Operations/InfrastructureNormalizer.php`: chú thích việc giữ dữ liệu tên nguồn; không đổi hành vi chuyển dữ liệu.
- `backend/app/Http/Controllers/InfrastructureController.php`, `InfrastructureReferenceController.php`: lấy thêm sân bay của bản ghi cha để xác minh khi hiển thị.
- `backend/app/Support/WorkspaceForms.php`, `WorkspacePresentation.php`: nhãn tài nguyên/phân bổ dùng chung.
- `backend/resources/views/m01/unified-infrastructure.blade.php`, `infrastructure-reference.blade.php`: bảng gọn với cột Vị trí.
- `backend/resources/views/m01/infrastructure-detail.blade.php`, `infrastructure-fields.blade.php`, `infrastructure-location-field.blade.php`: hồ sơ, quyền thao tác, nhãn lựa chọn và hướng dẫn khai báo vị trí.
- `backend/resources/views/m01/flight-infrastructure.blade.php`: tên hạ tầng ở bảng phân bổ chuyến.
- `backend/tests/Feature/InfrastructureDisplayTest.php`: kiểm thử nhãn, vị trí, dữ liệu chỉ đọc và đề nghị–duyệt.
- `backend/tests/Feature/InfrastructureProductStatusTest.php`: đối chiếu nhãn với trạng thái HAN đã lưu thay vì giả định HAN luôn là Normal trong dữ liệu mô phỏng.
- `scripts/check-infrastructure-display.cjs`: kiểm tra trình duyệt trên HTML render từ dữ liệu thật; không đăng nhập hay thay đổi dữ liệu ứng dụng.

## Xác minh

- Bộ hồi quy `InfrastructureNormalizationTest`, `InfrastructureSimplificationTest`, `AirportStatusDisplayTest`: 28 test, 398 assertion, đạt. Bao gồm kiểm tra sai sân bay, vòng lặp, ID cũ, phân bổ M18 và lựa chọn cha.
- `InfrastructureDisplayTest` và `InfrastructureProductStatusTest`: 6 test, 81 assertion, đạt sau khi sửa hai kỳ vọng test theo chính sách quyền đọc M01 và trạng thái sân bay đã lưu. Tổng cộng 34 test, 479 assertion đạt; không thay đổi chính sách quyền hoặc trạng thái sân bay để đáp ứng test.
- Blade compile thành công (`php artisan view:cache`).
- Kiểm tra Chrome headless với HTML render bằng controller trên dữ liệu BMV thật: cả danh sách và hồ sơ quầy ở 1440px và 390px, không tràn ngang document, không lỗi JavaScript; bảng có vùng cuộn ngang trên mobile. Ảnh tại `backend/storage/app/private/infrastructure-{display,detail}-{1440,390}.png`. Đây là kiểm tra render/layout và script, không thay thế một phiên đăng nhập thao tác đầy đủ qua trình duyệt.
- Khảo sát và dấu vân tay dữ liệu chỉ đọc tại `backend/storage/app/private/infrastructure-display-survey.json`; không chạy migrate, seed, chuẩn hóa hoặc cập nhật dữ liệu trên DB ứng dụng trong lượt này. PHPUnit dùng `airline_test`, có guard ngăn chạy trên database ứng dụng.
- Ba lần khảo sát trước/sau có cùng SHA-256 cho toàn bộ `airport_infrastructure`, `airport_resources`, `airport_areas`, `resource_allocations`; tên, parent, ID và phân bổ trên DB ứng dụng được giữ nguyên.
- Không commit hoặc push.
