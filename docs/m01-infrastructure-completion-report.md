# Bàn giao quản lý cơ sở hạ tầng M01

> Cập nhật sau báo cáo này: xem [kết quả chuẩn hóa và bổ sung cho toàn bộ sân bay](m01-all-airports-infrastructure-report.md). Các số liệu dưới đây là snapshot của lượt bàn giao trước.


Ngày: 10/11/2026. Phạm vi: hoàn thiện chức năng quản lý 10 loại, giữ bốn nhóm và toàn bộ định danh, dữ liệu kế thừa, phân bổ đang tồn tại. Không nhập thêm hồ sơ sân bay thực tế khi chưa có dữ liệu được cung cấp.

## Chức năng theo loại

Tất cả 10 loại có danh sách, tìm mã/tên, lọc sân bay/nhóm/loại, phân trang, chi tiết, tạo mới theo quyền, đề nghị sửa và duyệt độc lập, trạng thái, quan hệ phù hợp và audit. Thêm mới được người quản trị dữ liệu thực hiện trực tiếp theo chính sách hiện có; sửa hồ sơ cần người khác duyệt. Không tự thêm một tầng duyệt mới.

| Loại | Thông tin chuyên biệt đã lưu và đọc qua Backend | Quan hệ đã triển khai |
|---|---|---|
| Nhà ga | Hành khách/hàng hóa/kết hợp; quốc nội/quốc tế/kết hợp; phục vụ đi/đến | Sân bay; các khu vực trực thuộc |
| Khu vực nhà ga | Chức năng khu vực, bao gồm sảnh, tầng, làm thủ tục, hành lý… | Nhà ga hoặc khu vực cha đã xác nhận |
| Cổng lên/xuống máy bay | Phục vụ đi/đến độc lập | Nhà ga/khu vực; vị trí đỗ liên kết |
| Quầy làm thủ tục | Làm thủ tục/ký gửi/kết hợp | Nhà ga/khu vực |
| Cầu ống lồng | Mã tài sản nếu được cung cấp | Nhà ga/khu vực; cổng và/hoặc vị trí đỗ liên kết đã xác nhận |
| Băng chuyền hành lý | Hành lý đi/đến/kết hợp | Nhà ga/khu vực |
| Đường băng | Chiều dài/rộng, giới hạn, các hướng khai thác | Sân bay; không bắt buộc nhà ga |
| Đường lăn | Chức năng đường lăn và mô tả | Sân bay; có thể khai báo sân đỗ nếu đã xác nhận |
| Sân đỗ máy bay | Mã, tên, mô tả và trạng thái | Sân bay; vị trí đỗ trực thuộc |
| Vị trí đỗ máy bay | Thông tin phục vụ/khả năng phù hợp máy bay | Sân bay; sân đỗ cha nếu đã xác nhận |

Thông số chuyên biệt và parent đều có thể để trống. Không suy vị trí từ mã. Các chức năng chi tiết là thuộc tính, không tái tạo danh mục 41 loại. Cổng và nhà ga có thể phục vụ cả đi lẫn đến trên cùng một hồ sơ vật lý.

## Backend và Database

- Tái sử dụng `InfrastructureService`, `InfrastructureController`, `InfrastructureRepository`, các bảng profile, `master_changes` và audit.
- Bổ sung `terminal_scope` nullable trong `infrastructure_terminals`. Bổ sung quan hệ `JET_BRIDGE_GATE` / `JET_BRIDGE_STAND` trong bảng liên kết hiện có, không nhân đôi cổng/vị trí đỗ.
- Kiểm tra mã trùng trong cùng sân bay, bao gồm các nguồn cũ chưa ánh xạ. Cầu ống lồng có mã tài sản được cung cấp thì chặn mã tài sản trùng tại cùng sân bay. Tên giống nhau không tự động được coi là cùng đối tượng vật lý.
- Cha phải cùng sân bay, đúng loại công trình, không tự tham chiếu hoặc tạo chu trình. Liên kết cầu ống lồng phải trỏ tới đúng cổng/vị trí đỗ cùng sân bay; Backend kiểm tra lại khi duyệt.
- Bảng `infrastructure_sources` lưu phương thức nhập, tài liệu/người cung cấp nếu có, người ghi nhận và thời điểm. Dữ liệu nhập tay có người có quyền cung cấp; CSV yêu cầu nguồn cung cấp. Lịch sử đề nghị và audit giữ người lập, người duyệt và nội dung trước/sau. Các đường API tương thích nguồn cũ cũng lưu dấu vết người cung cấp khi tạo/cập nhật.
- Không tự chứng nhận nguồn là chính xác chỉ vì đã được nhập. Không backfill nguồn xác minh giả cho hồ sơ kế thừa.
- Bổ sung migration `2026_11_10_020000_complete_infrastructure_management.php`, chỉ thêm cột/bảng. Đã áp dụng cho ứng dụng và database test riêng; không reset, không chạy `migrate:fresh`, không seed dữ liệu vào ứng dụng. Migration có thể tiếp tục sau DDL bị gián đoạn và từ chối rollback khi đã có dữ liệu mới cần giữ.
- Đề nghị đang chờ trước khi có cột nhà ga mới vẫn duyệt được nếu giá trị mới chỉ là NULL; không làm sai việc so sánh phiên bản nguồn.

## Trạng thái và M18

Giữ badge Bình thường (xanh), Đang bảo trì (tím), Tạm ngừng hoạt động (đỏ). Không đổi ý nghĩa trạng thái Hạn chế sử dụng, Chưa xác định và Ngừng hiệu lực đã có; hiệu lực và trạng thái vận hành vẫn là hai trường độc lập.

Khi phê duyệt chuyển một tài nguyên đang khả dụng sang bảo trì/tạm ngừng/ngừng hiệu lực, nếu còn phân bổ chưa kết thúc thì trả về xung đột. Không hủy, chuyển hoặc sửa phân bổ để ép thao tác thành công. Phân bổ mới và bước giữ cổng khi công bố chuyến kiểm tra lại hồ sơ chuẩn hóa, hiệu lực và trạng thái. Tài nguyên bảo trì/tạm ngừng không được nhận phân bổ mới. Các nguồn cũ có trạng thái chưa xác định vẫn giữ chính sách tương thích hiện hành; không bị tự diễn giải thành Bình thường.

Chỉ GATE/COUNTER/STAND tham gia lịch theo quy tắc hiện hành. Không triển khai phân lịch băng chuyền hoặc điều phối đường băng. ID option, `airport_resources.id`, `infrastructure_id`, `resource_allocations.resource_id` không bị thay đổi.

## Frontend

- Giữ luồng chọn sân bay → nhóm → danh sách → chi tiết → thao tác.
- Bộ chọn loại tối đa 10 mục, theo nhóm; bỏ ô tìm loại và đoạn hướng dẫn thừa. Tên loại trên giao diện nghiệp vụ bằng tiếng Việt.
- Bảng Mã/Tên, Loại, Vị trí, Trạng thái, Thao tác; tiếp tục dùng hàm tên/vị trí chung của lượt chuẩn hóa trước.
- Loại chưa có dữ liệu hiển thị “Chưa có hạng mục nào được ghi nhận” và liên kết thêm mới cho người có quyền; không dựng dòng dữ liệu.
- Form cầu ống lồng chọn liên kết thật, form nhà ga có quốc nội/quốc tế; các trường khác hiện theo loại và lưu vào profile thật.
- Trang chi tiết có bảng so sánh trước/sau của đề nghị để người duyệt xem trước; dịch thuộc tính, trạng thái và quan hệ sang nhãn dễ hiểu.
- Thông báo kiểm tra dữ liệu hạ tầng dùng tên trường tiếng Việt. Không thêm các nhãn “Dữ liệu demo”, “Dữ liệu mô phỏng”, “Dữ liệu trình bày” vào giao diện nghiệp vụ.

## Nhập CSV đã triển khai

Từ danh sách chọn **Nhập từ CSV**, tải tệp mẫu chỉ chứa tiêu đề, chọn tài liệu/người cung cấp và tệp UTF-8 (1 MB, tối đa 200 hạng mục).

Backend đọc và kiểm tra dữ liệu, sau đó hiển thị sân bay, mã/tên, loại, vị trí, trạng thái và toàn bộ thông tin bổ sung sẽ nhập. Xem trước không tạo dữ liệu. Xác nhận nhập dùng bản xem trước lưu trong session, gắn người dùng và token, hết hạn sau 30 phút; không tin dữ liệu hàng do client gửi lại. Sau khi nhập thành công, token không dùng lại được.

Khi xác nhận, toàn bộ hàng được kiểm tra lại trong một transaction, dùng chính `InfrastructureService::create`. Bất kỳ xung đột nào cũng hoàn tác cả tệp, gồm hồ sơ, nguồn cũ tương thích, profile, nguồn cung cấp và audit. Không cập nhật ghi đè mã đã có. Các parent/cổng/vị trí đỗ liên kết phải tồn tại trước tại sân bay đang chọn; nhập công trình trước rồi nhập các đối tượng trực thuộc ở tệp tiếp theo. Bỏ trống trạng thái CSV được giữ là chưa xác định.

CSV hỗ trợ đầy đủ trường tùy chọn của 10 loại, các hướng đường băng và liên kết theo mã thật; không phải chỉ nhập mã/tên. Không nhập phân bổ M18. Không tự điền nhà ga, khu vực, thông số hay tên chính thức.

## Số lượng hồ sơ theo sân bay

Các số dưới đây là **số hồ sơ đang có trong DB**, không phải xác nhận hiện trạng sân bay thực tế. Dữ liệu kế thừa 644 hồ sơ bắt nguồn từ bộ sinh dữ liệu trước đây; được bảo toàn theo ràng buộc không xóa. Không tự chuyển nguồn gốc của các hồ sơ này thành dữ liệu đã xác minh. Cần đơn vị quản lý đối chiếu trước khi dùng vận hành thực tế.

| Sân bay | Nhà ga | Khu vực | Cổng | Quầy | Cầu ống lồng | Băng chuyền | Đường băng | Đường lăn | Sân đỗ | Vị trí đỗ |
|---|---|---|---|---|---|---|---|---|---|---|
| BMV | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| CAH | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| CXR | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| DAD | 2 | 0 | 16 | 16 | 0 | 0 | 0 | 0 | 0 | 16 |
| DIN | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| DLI | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| HAN | 2 | 0 | 16 | 16 | 0 | 0 | 0 | 0 | 0 | 16 |
| HPH | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| HUI | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| PQC | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| PXU | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| SGN | 2 | 0 | 16 | 16 | 0 | 0 | 0 | 0 | 0 | 16 |
| TBB | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| THD | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| UIH | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VCA | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VCL | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VCS | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VDH | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VDO | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VII | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| VKG | 2 | 0 | 8 | 8 | 0 | 0 | 0 | 0 | 0 | 8 |
| **Tổng** | 44 | 0 | 200 | 200 | 0 | 0 | 0 | 0 | 0 | 200 |

Toàn bộ 644 hồ sơ hiện chưa có tên riêng hoặc parent được xác nhận. 600 cổng/quầy/vị trí đỗ có thể bổ sung vị trí chi tiết; 44 nhà ga trực thuộc sân bay. Sáu loại còn lại chưa có hồ sơ được cung cấp: khu vực, cầu ống lồng, băng chuyền, đường băng, đường lăn, sân đỗ. Số 0 chỉ biểu thị thiếu dữ liệu trong DB, không kết luận sân bay không có loại hạ tầng đó.

## Thống nhất các phân hệ

M01 là hồ sơ chuẩn. Các bảng tài nguyên cũ chỉ làm cầu nối tương thích với ID phân bổ; hồ sơ tạo mới cho GATE/COUNTER/STAND liên kết lại qua `infrastructure_id`, không tạo một hồ sơ vật lý độc lập trong mỗi phân hệ. M03 dùng các phân bổ đã lưu; M09 giữ mã cổng trong thông tin chuyến/boarding và không suy thêm vị trí. M10/M11 dùng tra cứu hạ tầng chuẩn hóa. M18 giữ chính sách lịch và khóa/xung đột hiện có, bổ sung kiểm tra khả dụng tại Backend.

## Giới hạn và yêu cầu BA cần xác nhận

- Chức năng quản lý và dữ liệu thực tế là hai phần khác nhau: không có nguồn thực địa cho sáu loại còn trống nên chưa nhập thêm hồ sơ vào DB ứng dụng.
- Cần thống nhất mã tài sản/định danh vật lý dùng chung cho mọi loại để phát hiện cùng một vật thể được khai báo bằng hai mã khác nhau. Hiện bảo đảm duy nhất mã trong sân bay và mã tài sản cầu ống lồng nếu có; không tự gộp theo tên.
- Cần xác nhận số lượng liên kết tối đa cầu ống lồng–cổng/vị trí đỗ, quy trình xử lý tài nguyên bảo trì còn lịch và chính sách đối với nguồn cũ chưa biết trạng thái. Hiện không tự điều phối lại lịch.
- Cần quy định thẩm quyền xác minh tài liệu nguồn và chuẩn dữ liệu bàn giao. Nguồn nhập nội bộ ghi lại người cung cấp/người duyệt; phần mềm không xác thực thay đơn vị quản lý.
- Chưa có cập nhật vị trí hàng loạt hoặc nhập CSV để sửa hồ sơ; CSV hiện chỉ tạo mới theo chính sách quyền hiện hành. Mọi chỉnh sửa vẫn theo đề nghị–duyệt từng hồ sơ. Không có nút hàng loạt giả.
- Chưa có import cả cây mới trong một tệp: công trình/đối tượng liên kết cần được tạo trước. Không tự dựng parent trong quá trình nhập.
- Quyền tiếp tục là `admin` / `data_admin` cho quản trị M01; không cấp thêm quyền cho vai trò điều hành, mặt đất hoặc nhân viên sân bay.

## Kiểm thử và chứng cứ

- Lệnh hồi quy: `php vendor/bin/phpunit --filter 'Infrastructure|AirportStatusDisplayTest|OperationsWorkflowTest'` trong container PHP 8.4: 73 ca, 1086 assertion ở lượt đầu, 71 đạt và 2 lỗi kỳ vọng cũ của test.
- Hai lỗi được sửa ở fixture/kỳ vọng, không sửa quyền hay trạng thái ứng dụng: test yêu cầu bốn badge nay dùng đủ 22 sân bay test; test quyền tài chính phân biệt `data_admin` bị chặn với `admin` được phép theo `Access` hiện hành.
- Chạy lại hai ca đó cùng toàn bộ ba test nhập CSV và test API tương thích sau thay đổi: **6/6 đạt, 79 assertion**. Toàn bộ 73 ca trong phạm vi trên đã được xác minh qua lượt hồi quy và lượt chạy lại; không tuyên bố đây là một lượt chạy 73/73 duy nhất.
- Riêng bộ chức năng mới ở lượt đầu: 6/6 ca, 256 assertion; bộ hồi quy sau đó cũng chạy thêm ca bảo toàn đề nghị nhà ga cũ sau khi thêm trường nullable. Các ca này kiểm tra tạo/sửa/duyệt đủ 10 loại, thuộc tính riêng, đúng quan hệ, chặn tự duyệt, mã tài sản trùng, trạng thái có phân bổ và quyền thao tác.
- CSV: kiểm tra xem trước không ghi dữ liệu, lưu profile và nguồn, không suy trạng thái, token giả, không phát lại, trùng mã sau xem trước hoàn tác toàn bộ, sai cấu trúc/loại cũ/mã lặp/mã tài sản lặp/parent chưa xác nhận và người không có quyền.
- Các ca hồi quy bao gồm M18 giao lịch, định danh cũ, công bố chuyến, check-in/boarding, hành lý và dịch vụ mặt đất. Không mở rộng điều phối sang loại chưa có quy tắc.
- Compile Blade và kiểm tra cú pháp PHP của các lớp mới/thay đổi thành công.
- Chrome headless: 24 kiểm tra (10 form + trang rỗng + trang nhập CSV, mỗi trang ở 1440px và 390px); không lỗi JavaScript, không tràn ngang document. Có kiểm tra chọn phạm vi nhà ga và tồn tại lựa chọn liên kết cầu ống lồng. Đây là kiểm tra giao diện render/script, không phải đăng nhập rồi ghi dữ liệu vào ứng dụng thật.
- Dấu SHA-256 của `airport_infrastructure`, `airport_resources`, `airport_areas`, `resource_allocations` trùng snapshot trước lượt thay đổi. Các lần khảo sát trong lượt này cũng giữ nguyên dấu vân tay `flights`. Database ứng dụng vẫn có 644 hồ sơ, 10 loại và 0 bản ghi nguồn mới: không có dữ liệu thử nghiệm hay hồ sơ mới tự tạo được trộn vào ứng dụng.

Tất cả fixture mới chỉ được tạo trong `airline_test`, có guard từ chối chạy trên database ứng dụng. Kiểm tra trình duyệt dùng HTML render bằng controller trên dữ liệu ứng dụng chỉ đọc; không gửi form tạo/sửa vào ứng dụng.

## File chính trong lượt này

- Backend: `InfrastructureService.php`, `InfrastructureController.php`, `InfrastructureCompatibility.php`, `OperationsService.php`, `InfrastructureCatalog.php`, `InfrastructureChangeDisplay.php`.
- Nhập dữ liệu: `InfrastructureImport.php`, `InfrastructureImportController.php`, các route import, `infrastructure-import.blade.php`.
- Database: migration `2026_11_10_020000_complete_infrastructure_management.php`.
- Giao diện M01: `unified-infrastructure`, `infrastructure-type-picker`, `infrastructure-fields`, `infrastructure-detail`, `infrastructure-location-field`, `infrastructure-bridge-links`, `infrastructure-change-preview`.
- Kiểm thử: `InfrastructureManagementTest.php`, `InfrastructureImportTest.php`, sửa kỳ vọng/fixture của `AirportStatusResetTest.php` và `OperationsWorkflowTest.php`, `scripts/check-infrastructure-completion.cjs`.
- Khảo sát nội bộ: `backend/storage/app/private/infrastructure-completion-survey.json`; ảnh `completion-{TERMINAL,JET_BRIDGE,empty,import}-{1440,390}.png` cùng thư mục.

Không commit hoặc push.
