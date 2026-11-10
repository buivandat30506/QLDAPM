# Bổ sung dữ liệu 10 loại hạ tầng tại BMV — local

> Cập nhật sau báo cáo này: xem [kết quả chuẩn hóa và bổ sung cho toàn bộ sân bay](m01-all-airports-infrastructure-report.md). Các số liệu dưới đây là snapshot của lượt bàn giao trước.


Ngày thực hiện: 10/11/2026. Database được khảo sát trực tiếp trước khi ghi: môi trường `local`, host Docker `db1`, database `airline`. Bộ dữ liệu chỉ phục vụ kiểm tra chức năng; không xác nhận hiện trạng sân bay Buôn Ma Thuột.

## Kết quả trước/sau

| Loại | Trước | Thêm | Sau |
|---|---:|---:|---:|
| Nhà ga | 2 | 1 | 3 |
| Khu vực nhà ga | 0 | 3 | 3 |
| Cổng lên/xuống máy bay | 8 | 1 | 9 |
| Quầy làm thủ tục | 8 | 1 | 9 |
| Cầu ống lồng | 0 | 1 | 1 |
| Băng chuyền hành lý | 0 | 1 | 1 |
| Đường băng | 0 | 1 | 1 |
| Đường lăn | 0 | 1 | 1 |
| Sân đỗ máy bay | 0 | 1 | 1 |
| Vị trí đỗ máy bay | 8 | 1 | 9 |

Tổng BMV: **26 → 38** hồ sơ. Toàn hệ thống: **644 → 656** hồ sơ. Danh mục giữ đúng 10 loại.

Tạo thêm cả nhà ga, cổng, quầy và vị trí đỗ riêng để bộ mới có cấu trúc thống nhất. Không gắn 26 hồ sơ BMV cũ vào các công trình mới, không suy quan hệ từ mã. Bộ mới có 12 hồ sơ vì có ba khu vực với chức năng riêng.

## Các hồ sơ được tạo

| ID | Mã | Tên | Loại | Vị trí/quan hệ |
|---|---|---|---|---|
| 645 | LCL-T1 | Nhà ga A | Nhà ga | Trực thuộc BMV |
| 646 | LCL-Z-DEP | Khu khởi hành A | Khu vực nhà ga | Nhà ga A |
| 647 | LCL-Z-CHECKIN | Khu làm thủ tục A | Khu vực nhà ga | Nhà ga A |
| 648 | LCL-Z-CLAIM | Khu nhận hành lý A | Khu vực nhà ga | Nhà ga A |
| 649 | LCL-APRON | Sân đỗ A | Sân đỗ máy bay | Trực thuộc BMV |
| 650 | LCL-P01 | Vị trí đỗ A01 | Vị trí đỗ máy bay | Sân đỗ A |
| 651 | LCL-G01 | Cổng A01 | Cổng lên/xuống máy bay | Khu khởi hành A; liên kết vị trí đỗ #650 |
| 652 | LCL-C01 | Quầy làm thủ tục A01 | Quầy làm thủ tục | Khu làm thủ tục A |
| 653 | LCL-JB01 | Cầu ống lồng A01 | Cầu ống lồng | Khu khởi hành A; liên kết cổng #651 và vị trí đỗ #650 |
| 654 | LCL-B01 | Băng chuyền hành lý A01 | Băng chuyền hành lý | Khu nhận hành lý A |
| 655 | LCL-RWY01 | Đường băng A | Đường băng | Trực thuộc BMV |
| 656 | LCL-TWY01 | Đường lăn A | Đường lăn | Trực thuộc BMV |

Nhà ga hành khách, phạm vi quốc nội, phục vụ đi/đến; ba khu vực có chức năng khởi hành, làm thủ tục và nhận hành lý. Quầy phục vụ kết hợp; băng chuyền phục vụ hành lý đến. Cổng phục vụ đi/đến trên cùng một đối tượng. Cầu ống lồng có mã tài sản nội bộ `LCL-BMV-JB01`. Đường lăn có chức năng chung.

Hướng đường băng `01` / `19` và thông tin tiếp nhận nhóm tàu bay A chỉ là giả định của kịch bản. Chiều dài/rộng đường băng để NULL. Không nhập thông số kỹ thuật được coi là thực tế của BMV. Tất cả trạng thái NORMAL được chọn rõ trong thiết kế bộ thử nghiệm, không suy từ loại hoặc mã.

Quan hệ được lưu bằng FK thật: 8 liên kết cha/con, 3 liên kết cổng–vị trí đỗ/cầu ống lồng, 2 hướng khai thác. Đường băng, đường lăn, sân đỗ không thuộc nhà ga. Không tạo lịch M18 cho bộ mới.

## Nguồn gốc và phạm vi sử dụng

- Phiên bản: `infrastructure-local-v1`; khóa bộ: `infrastructure-local-v1:BMV`.
- `infrastructure_fixture_sets` lưu thiết kế, sân bay, môi trường/database, ID từng hạng mục, toàn bộ snapshot ban đầu của hồ sơ/profile/nguồn/tài nguyên, người tạo, trạng thái và thời điểm dọn.
- `infrastructure_sources`: 12 dòng với `entry_method = LOCAL_FIXTURE` và ghi chú rõ đây là kịch bản kiểm thử, chưa xác minh thực địa. Không gán nguồn AUTHORIZED_ENTRY hoặc CSV_IMPORT cho bộ này.
- Audit giữ lịch sử tạo, dọn và sử dụng tài nguyên. Người thực hiện lệnh tại máy hiện tại là tài khoản quản trị có ID 1; không tạo tài khoản hoặc đổi quyền.
- Không thêm nhãn “Dữ liệu demo”, “Dữ liệu mô phỏng”, “Dữ liệu trình bày” vào giao diện nghiệp vụ. Thông tin nguồn vẫn có đầy đủ trong DB và audit nội bộ.
- Không tự chạy khi khởi động hoặc qua `DatabaseSeeder`. Không thay đổi `.env` để bật vĩnh viễn. Cờ cho phép được truyền riêng vào từng phiên lệnh Docker.
- Từ chối production, host ngoài danh sách local, tên DB ngoài `airline` / `airline_test`, thiếu opt-in hoặc người ghi không có quyền. Từ chối tạo vào sân bay đã có nguồn dữ liệu do người dùng/đơn vị cung cấp. Không nhận mã của người dùng làm tài sản của bộ thử nghiệm.

Chỉ sử dụng lệnh dưới đây trên database Docker cục bộ riêng của dự án. Không bật cờ này trên database dùng chung, bản dữ liệu vận hành đã xác minh hoặc production. `APP_ENV=local` đơn thuần không phải căn cứ để coi một database dùng chung là an toàn.

## Command tạo và kiểm tra

Mặc định chỉ xem trước:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --airport=BMV
```

Tạo chủ động, chỉ định người quản trị đang tồn tại:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --airport=BMV --actor=1 --apply
```

Lượt đầu đã tạo 12 hồ sơ với ID 645–656. Lượt thứ hai trả `created = 0` và cùng danh sách ID; không thêm bản ghi, không đổi tên/thông tin/quan hệ kể cả khi người dùng đã sửa bộ thử nghiệm. Nếu mã đã tồn tại ngoài manifest, lệnh từ chối thay vì tự nhận hoặc ghi đè.

## Command dọn và quy tắc bảo toàn

Kiểm tra điều kiện dọn, chưa xóa:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --airport=BMV --clear
```

Tại thời điểm bàn giao: `eligible = 12`, `blocked = []`, `deleted = 0`. **Chưa chạy xóa trên database ứng dụng**, để giữ bộ dữ liệu cho người dùng kiểm tra giao diện.

Khi thực sự muốn dọn bộ chưa được sử dụng:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --airport=BMV --actor=1 --clear --apply
```

Lệnh khóa sân bay và các hồ sơ/tài nguyên liên quan trong transaction, so sánh snapshot và kiểm tra phụ thuộc trước khi dọn toàn bộ bộ dữ liệu. Nếu một hồ sơ đã sửa/mất, có con hoặc liên kết bên ngoài, đề nghị thay đổi, thiết bị mặt đất, phân bổ/chuyến bay, lịch sử sử dụng hoặc tham chiếu FK từ phân hệ khác thì không xóa bất kỳ hạng mục nào. Không có `--force` bỏ qua các điều kiện này.

M18 ghi nhận dấu sử dụng riêng cho tài nguyên có nguồn LOCAL_FIXTURE; vì vậy đã phân bổ rồi gỡ lịch cũng không tự xóa được bộ. Lịch sử vị trí thiết bị cũng được kiểm tra. Các FK tới hồ sơ/tài nguyên được rà soát từ metadata MySQL để chặn tham chiếu của phân hệ bổ sung sau này.

Chỉ xóa các profile/quan hệ/tài nguyên và hồ sơ thuộc manifest còn nguyên. Không vô hiệu FK, không xóa audit và không xóa manifest: manifest chuyển CLEARED, giữ nguồn gốc để tra cứu. Dọn hai lần không xóa thêm dữ liệu. Một bộ đã chỉnh sửa/sử dụng cần được quản trị viên đánh giá phụ thuộc riêng; lệnh không tự biến nó trở lại trạng thái ban đầu để xóa.

## Đối chiếu dữ liệu cũ

| Bảng | Số hàng trước | Số hàng sau | Hàng gốc không đổi |
|---|---:|---:|---|
| `airport_infrastructure` | 644 | 656 | Có |
| `airport_resources` | 644 | 647 | Có |
| `infrastructure_types` | 10 | 10 | Có |
| `resource_allocations` | 2918 | 2918 | Có |
| `flights` | 987 | 987 | Có |
| `airport_areas` | 0 | 0 | Có |
| `infrastructure_gates` | 200 | 201 | Có |
| `infrastructure_stands` | 200 | 201 | Có |

Đối chiếu theo từng khóa gốc, hash SHA-256 của toàn bộ hàng trước/sau, không chỉ so sánh tổng số. Các bản ghi mới được loại khỏi phép so sánh phần kế thừa. Cả hồ sơ/ID/parent/trạng thái và các hàng profile cũ đều khớp. Không chạy chuẩn hóa, reset, seed lại dữ liệu cũ hay `migrate:fresh` trên ứng dụng.

## Giao diện và các phân hệ

Mở `/workspace/catalog?flow=infrastructure&airport=BMV`. Bốn nhóm hiện có số lượng thật từ truy vấn DB:

| Nhóm | Số hồ sơ |
|---|---:|
| Nhà ga và khu vực | 6 |
| Cổng và quầy phục vụ | 19 |
| Hệ thống hành lý | 1 |
| Khu bay và sân đỗ | 12 |

Không đổi Sidebar hoặc danh mục 10 loại. Bộ lọc từng loại hoạt động trên dữ liệu lưu thật; tìm `LCL-` để xem đúng 12 hạng mục mới. Chi tiết dùng cùng luồng thêm/sửa/duyệt, profile và hàm hiển thị đã triển khai. Không tự gán bộ mới cho chuyến, hành khách, hành lý hoặc thiết bị; M03/M09/M10/M11 dùng các tham chiếu chuẩn hóa hiện có. M18 chỉ có thêm dấu audit khi người dùng thực sự sử dụng tài nguyên của bộ này, không đổi phân bổ cũ.

## Kiểm thử

- `InfrastructureFixturesTest`: **6 test, 121 assertion đạt** trên `airline_test`. Bao gồm đủ 10 loại, chi tiết, bộ lọc/tìm kiếm/phân trang, profile, quan hệ, nguồn gốc, xem trước không ghi, tạo lại không trùng, dọn và dọn lại, bảo toàn dữ liệu cũ, không ghi đè sửa đổi, chặn phụ thuộc/đề nghị, chặn production/remote/shared/thiếu quyền/thiếu opt-in và bảo vệ tài nguyên đã phân bổ rồi gỡ lịch.
- Bộ hồi quy chọn theo thay đổi: **12 test, 328 assertion đạt**, gồm quản lý/sửa/duyệt 10 loại, tên/vị trí, quan hệ, công bố chuyến M03/M09, M18 giao lịch, tham chiếu hành lý M10 và thiết bị mặt đất M11.
- Đã chạy command thực tế tại local: xem trước, tạo 12 hồ sơ, chạy lần hai tạo 0 hồ sơ, xem trước dọn đủ điều kiện 12 nhưng không xóa.
- Tất cả 24 trang dữ liệu thật (danh sách, tìm kịch bản, 10 bộ lọc, 12 trang chi tiết) đã render qua controller mà không lỗi.
- Chrome headless: **48/48 kiểm tra đạt**, gồm 24 trang ở 1440px và 390px; không tràn ngang toàn trang, không lỗi JavaScript, không lộ ghi chú nguồn thử nghiệm ở giao diện chính. Kiểm tra riêng chuỗi nhà ga → khu nhận hành lý → băng chuyền và các liên kết cầu ống lồng trỏ đúng ID.
- Báo cáo khảo sát trước/sau ở `backend/storage/app/private/infrastructure-population-{before,after}.json`; kết quả trình duyệt tại `population-browser-results.json`, ảnh `population-{list,scenario,detail-bridge,detail-belt,detail-runway}-{1440,390}.png` cùng thư mục. Kiểm tra trình duyệt dùng HTML render của dữ liệu ứng dụng, không gửi form ghi dữ liệu vào ứng dụng. Các thao tác tạo/sửa/duyệt và dọn có ghi thực tế được kiểm thử trong DB cô lập.

## Những dữ liệu thực tế vẫn cần được cung cấp

Đơn vị quản lý cần cung cấp mã/tên chính thức, sơ đồ nhà ga/khu vực, quan hệ cổng–cầu ống lồng–vị trí đỗ, số lượng và hướng phục vụ băng chuyền, hiện trạng đường băng/đường lăn/sân đỗ, giới hạn tiếp nhận tàu bay và tài liệu xác nhận nguồn. Những giả định của kịch bản không thay thế dữ liệu đó.

Khi tiếp nhận dữ liệu vận hành đã xác minh, dùng luồng nhập CSV/quản trị nguồn và đề nghị–duyệt hiện có. Không dùng bộ sinh này để điền thông tin thực cho BMV.

## File thay đổi

- `backend/app/Modules/Operations/InfrastructureFixtures.php`: thiết kế, guard, tạo lặp an toàn, manifest, kiểm tra phụ thuộc và dọn.
- `backend/app/Console/Commands/PopulateInfrastructure.php`: command chủ động.
- `backend/config/infrastructure.php`: cờ opt-in riêng.
- Migration `2026_11_10_030000_add_infrastructure_fixture_sets.php`: bảng metadata bộ thử nghiệm, không tạo dữ liệu nghiệp vụ tự động.
- `InfrastructureService.php`, `OperationsService.php`: nguồn LOCAL_FIXTURE và audit sử dụng riêng bộ thử nghiệm.
- `InfrastructureController.php`, `unified-infrastructure.blade.php`: số lượng thật theo bốn nhóm.
- `backend/tests/Feature/InfrastructureFixturesTest.php`, `scripts/check-infrastructure-population.cjs`.

Không commit, không push. Bộ mới vẫn được giữ trong local để tiếp tục kiểm tra quản lý.
