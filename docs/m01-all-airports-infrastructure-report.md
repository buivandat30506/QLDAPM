# Chuẩn hóa tên/mã và bổ sung hạ tầng cho toàn bộ sân bay local

Ngày thực hiện: 10/11/2026. **Đã thực thi trên database đang phục vụ `http://localhost:8000`, không chỉ tạo command.**

## Kết quả triển khai

- Danh sách sân bay được truy vấn từ DB: hiện có **22 sân bay**. Không hardcode danh sách hoặc số lượng trong command.
- Trước: 656 hồ sơ, BMV đủ 10 loại; 21 sân bay còn lại mỗi sân bay thiếu 6 loại.
- Đã thêm **126 hồ sơ** vào 21 sân bay; sau: **782 hồ sơ**, mọi sân bay có ít nhất một hồ sơ cho từng loại trong danh mục 10 loại.
- Chạy command lần hai: **0 hồ sơ mới**, không ghi đè thông tin, ID hoặc nguồn đã tồn tại.
- BMV và bộ 12 hồ sơ được tạo ở lượt trước giữ nguyên. Không thêm nhà ga/cổng/quầy/vị trí đỗ ở các sân bay đã có các loại đó.
- Bảng Mã/Tên dùng tên đậm ở trên, mã gốc ở dưới; tên suy ra chỉ dùng lúc đọc, không ghi đè `name` trong DB.

## Môi trường và sao lưu

Đã xác nhận container `airline-app-1` ánh xạ `127.0.0.1:8000 → 8000` và mount `/mnt/e/Projects/QLDAPM/backend → /app`. HTTP `/login` trả 200; phiên kiểm tra quản trị mở trực tiếp trang BMV qua `localhost:8000` trả 200 và hiển thị dữ liệu trong database khảo sát.

Ứng dụng có môi trường `local`; kết nối hạ tầng `ops` dùng `db1`, database `airline`. Command được bật bằng biến môi trường chỉ trong phiên Docker, không sửa `.env` để bật thường trực.

Trước khi ghi đã tạo bản sao SQL đầy đủ database `ops` bằng `mysqldump --single-transaction --no-tablespaces`:

- File: `backend/storage/app/private/m01-all-airports-before.sql`.
- Kích thước: 2.596.060 byte.
- SHA-256: `30b71bfe73d386a2faea8a1ae2ae3f783c7210d9b250cba3feda26bb77027fa7`.
- File nằm trong thư mục private bị Git bỏ qua; không đưa bản sao dữ liệu vào repository.

Snapshot số lượng, khóa gốc và dấu vân tay các bảng trước/sau: `backend/storage/app/private/all-airports-{before,after}.json`. Kiểm thử ghi/dọn trước khi triển khai được thực hiện trên `airline_test`, có guard ngăn PHPUnit chạy trên database ứng dụng.

## Ma trận trước khi ghi

| Sân bay | Nhà ga | Khu vực | Cổng | Quầy | Cầu ống lồng | Băng chuyền | Đường băng | Đường lăn | Sân đỗ | Vị trí đỗ |
|---|---|---|---|---|---|---|---|---|---|---|
| BMV | 3 | 3 | 9 | 9 | 1 | 1 | 1 | 1 | 1 | 9 |
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
| **Tổng** | 45 | 3 | 201 | 201 | 1 | 1 | 1 | 1 | 1 | 201 |

## Ma trận sau triển khai — truy vấn từ DB

| Sân bay | Nhà ga | Khu vực | Cổng | Quầy | Cầu ống lồng | Băng chuyền | Đường băng | Đường lăn | Sân đỗ | Vị trí đỗ |
|---|---|---|---|---|---|---|---|---|---|---|
| BMV | 3 | 3 | 9 | 9 | 1 | 1 | 1 | 1 | 1 | 9 |
| CAH | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| CXR | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| DAD | 2 | 1 | 16 | 16 | 1 | 1 | 1 | 1 | 1 | 16 |
| DIN | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| DLI | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| HAN | 2 | 1 | 16 | 16 | 1 | 1 | 1 | 1 | 1 | 16 |
| HPH | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| HUI | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| PQC | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| PXU | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| SGN | 2 | 1 | 16 | 16 | 1 | 1 | 1 | 1 | 1 | 16 |
| TBB | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| THD | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| UIH | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VCA | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VCL | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VCS | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VDH | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VDO | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VII | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| VKG | 2 | 1 | 8 | 8 | 1 | 1 | 1 | 1 | 1 | 8 |
| **Tổng** | 45 | 24 | 201 | 201 | 22 | 22 | 22 | 22 | 22 | 201 |

BMV không có thay đổi số lượng. Tất cả các mã sân bay IATA/ICAO, tên, thông tin và trạng thái sân bay cũ đều giữ nguyên.

## Danh sách hồ sơ mới

Mỗi sân bay còn thiếu được bổ sung đúng sáu hồ sơ dưới đây. Tên và mã được tạo nhất quán; mã chỉ cần duy nhất trong cùng sân bay.

| Mã mới | Tên lưu trong bộ kiểm thử | Loại | Quan hệ/thuộc tính |
|---|---|---|---|
| LCL-Z01 | Khu nhận hành lý Z01 | Khu vực nhà ga | Thuộc một nhà ga đang có; chức năng nhận hành lý |
| LCL-AP01 | Sân đỗ AP01 | Sân đỗ máy bay | Trực thuộc sân bay; không tự nhận vị trí đỗ cũ làm con |
| LCL-JB01 | Cầu ống lồng JB01 | Cầu ống lồng | Thuộc nhà ga; chưa xác nhận cổng/vị trí đỗ liên kết |
| LCL-B01 | Băng chuyền hành lý B01 | Băng chuyền hành lý | Thuộc khu nhận hành lý mới; phục vụ hành lý đến |
| LCL-RWY01 | Đường băng RWY01 | Đường băng | Trực thuộc sân bay; không sinh hướng khai thác hoặc kích thước |
| LCL-TWY01 | Đường lăn TWY01 | Đường lăn | Trực thuộc sân bay; chức năng đường lăn chung |

ID từng hồ sơ mới, đối chiếu với mã ở tiêu đề cột:

| Sân bay | LCL-Z01 | LCL-AP01 | LCL-JB01 | LCL-B01 | LCL-RWY01 | LCL-TWY01 |
|---|---|---|---|---|---|---|
| CAH | 657 | 658 | 659 | 660 | 661 | 662 |
| CXR | 663 | 664 | 665 | 666 | 667 | 668 |
| DAD | 669 | 670 | 671 | 672 | 673 | 674 |
| DIN | 675 | 676 | 677 | 678 | 679 | 680 |
| DLI | 681 | 682 | 683 | 684 | 685 | 686 |
| HAN | 687 | 688 | 689 | 690 | 691 | 692 |
| HPH | 693 | 694 | 695 | 696 | 697 | 698 |
| HUI | 699 | 700 | 701 | 702 | 703 | 704 |
| PQC | 705 | 706 | 707 | 708 | 709 | 710 |
| PXU | 711 | 712 | 713 | 714 | 715 | 716 |
| SGN | 717 | 718 | 719 | 720 | 721 | 722 |
| TBB | 723 | 724 | 725 | 726 | 727 | 728 |
| THD | 729 | 730 | 731 | 732 | 733 | 734 |
| UIH | 735 | 736 | 737 | 738 | 739 | 740 |
| VCA | 741 | 742 | 743 | 744 | 745 | 746 |
| VCL | 747 | 748 | 749 | 750 | 751 | 752 |
| VCS | 753 | 754 | 755 | 756 | 757 | 758 |
| VDH | 759 | 760 | 761 | 762 | 763 | 764 |
| VDO | 765 | 766 | 767 | 768 | 769 | 770 |
| VII | 771 | 772 | 773 | 774 | 775 | 776 |
| VKG | 777 | 778 | 779 | 780 | 781 | 782 |

Danh sách đầy đủ 126 hàng gồm ID, sân bay, mã, tên, loại, parent và trạng thái được xuất từ DB vào `backend/storage/app/private/all-airports-new-records.csv`; JSON đối chiếu tại `all-airports-records.json` cùng thư mục. Đây là các bản ghi thực, không phải dòng được dựng trên Frontend.

## Quy chuẩn tên và mã

`InfrastructureDisplay::name()` là hàm dùng chung cho tên. Ưu tiên nguyên tên đã lưu; nếu tên trống, ghép nhãn loại với mã thật. Cổng dùng nhãn gọn “Cổng”, vị trí đỗ dùng “Vị trí đỗ”; các loại còn lại dùng nhãn tiếng Việt trong danh mục. Nếu mã đã bắt đầu bằng nhãn loại, không thêm nhãn lần nữa.

Partial `m01.infrastructure-identity` dùng chung cho bảng M01 và bảng tham chiếu: dòng trên là tên in đậm, có liên kết chi tiết nếu vai trò được quyền mở; dòng dưới luôn là mã gốc với chữ nhỏ/màu phụ. Các vai trò chỉ có quyền tra cứu tiếp tục thấy tên/mã nhưng không được cấp thêm quyền vào hồ sơ M01. Trang chi tiết và dropdown tiếp tục sử dụng cùng hàm tên; API trả mã/ID và `name` gốc, kể cả NULL.

Tìm kiếm dùng chung tại `InfrastructureRepository::search`, tìm được mã, tên lưu trong DB và tên hiển thị suy ra khi tên trống. Ký tự `%`/`_` trong đầu vào được xử lý như ký tự tìm kiếm, không tự biến thành lọc toàn bộ dữ liệu.

Form tạo mới gợi ý các tiền tố `T`, `Z`, `G`, `C`, `JB`, `B`, `RWY`, `TWY`, `AP`, `P`. Đây là gợi ý cho người nhập, không phải đổi quy tắc lưu mã cũ hoặc tự sinh mã khai thác đã được xác minh. Command bổ sung dùng namespace `LCL-` với các tiền tố trên để có thể nhận diện, tránh chiếm mã khai thác thật.

Không có mã cũ nào được đổi: `T1`, `T2`, `C01…`, `A01…`, `P01…`, các mã thuộc sân bay khác và toàn bộ mã `LCL-*` của bộ BMV trước đó giữ nguyên. Không đổi primary key, legacy ID hoặc khóa tham chiếu M18. Mã đường băng nội bộ tách biệt với `runway_directions`; không suy ký hiệu hướng chính thức từ `RWY01`.

## Quan hệ và nguồn gốc dữ liệu

- Khu nhận hành lý mới → nhà ga đã tồn tại cùng sân bay. Nhà ga được chọn theo hồ sơ thật của DB để thiết kế kịch bản, không dựa vào việc giải mã tiền tố cổng/quầy/vị trí đỗ.
- Băng chuyền mới → khu nhận hành lý mới. Chức năng được lưu trong profile.
- Cầu ống lồng mới → nhà ga đã tồn tại. Không tự liên kết nó với cổng/vị trí đỗ cũ vì chưa có căn cứ thiết kế quan hệ đó. Bộ BMV cũ giữ nguyên ba liên kết đã khai báo.
- Đường băng, đường lăn và sân đỗ mới trực thuộc sân bay; không bắt thuộc nhà ga. Parent của 656 hồ sơ trước đó không bị cập nhật.
- Không sinh chiều dài/rộng đường băng, sức chứa nhà ga, điều kiện an toàn hoặc thông số tiếp nhận tàu bay cho bộ bổ sung. Các hướng giả định của bộ BMV ở lượt trước giữ nguyên nguồn gốc và giá trị cũ, không được xác nhận thành dữ liệu thực.

Nguồn 126 hàng mới: `entry_method = LOCAL_FIXTURE`, phiên bản `infrastructure-local-missing-v1`, manifest riêng từng sân bay tại `infrastructure_fixture_sets`; nguồn, thiết kế, ID, tham chiếu tới nhà ga cũ và snapshot từng hồ sơ được giữ trong DB. Ghi chú nguồn nêu rõ dữ liệu chưa được xác minh, mã nội bộ không phải ký hiệu đường băng khai thác chính thức. Không đưa ghi chú thử nghiệm dài vào bảng nghiệp vụ.

Command từ chối production, host/tên DB ngoài phạm vi local/test, thiếu opt-in hoặc thiếu quyền quản trị dữ liệu khi ghi. Từ chối bổ sung vào dữ liệu chưa đủ loại nhưng đã có nguồn được người dùng/đơn vị cung cấp, có mã xung đột hoặc có nguồn cũ chưa xác minh ánh xạ. Không tự nhận các hồ sơ của người dùng vào manifest.

Nếu sân bay mới chưa có loại nào, chế độ bổ sung chỉ tạo mỗi loại còn thiếu; quan hệ tới cổng/vị trí đỗ chỉ được thiết lập khi cả hai đầu là thành phần mới có thiết kế rõ. Với bộ dữ liệu thực tế hiện tại, không tạo thêm cổng/quầy/vị trí đỗ nên không có liên kết khai thác mới.

## Command đã thực thi

Xem trước toàn bộ sân bay:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --all
```

Kết quả xem trước: 22 sân bay, 126 hồ sơ dự kiến, không ghi dữ liệu.

Tạo chủ động bằng người quản trị đang tồn tại (máy hiện tại: ID 1):

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --all --actor=1 --apply
```

Đã chạy thực tế: `created = 126`, `deleted = 0`, `blocked = []`. Chạy lại cùng lệnh: `created = 0`, `deleted = 0`. Kết quả nguyên bản lưu tại `all-airports-apply.json` và `all-airports-repeat.json` trong thư mục private.

Chỉ bổ sung loại còn thiếu ở một sân bay:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --airport=HAN --missing-only --actor=1 --apply
```

Lệnh kiểm tra trước tất cả sân bay rồi mới ghi, trong transaction chung. Nếu một sân bay xung đột, không lưu từng phần cho các sân bay trước đó. Không gọi từ startup Docker, migration hoặc DatabaseSeeder.

## Kiểm tra và dọn riêng bộ bổ sung

Đã chạy kiểm tra dọn, chưa xóa:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --all --clear
```

Kết quả tại thời điểm bàn giao: 126 hồ sơ đủ điều kiện, `deleted = 0`, không có phụ thuộc chặn. Bộ dữ liệu vẫn giữ lại trên website.

Khi muốn dọn bộ bổ sung chưa bị sửa/sử dụng:

```powershell
docker exec -e AIRLINE_INFRASTRUCTURE_FIXTURES=true airline-app-1 php artisan airline:infrastructure-fixtures --all --actor=1 --clear --apply
```

`--all --clear` chỉ xét các manifest của chế độ bổ sung mới; **không xóa bộ BMV cũ**, nhà ga được tham chiếu hoặc 644 hồ sơ kế thừa. Với một sân bay, dùng `--airport=HAN --missing-only --clear` (thêm `--actor=1 --apply` khi thực sự muốn dọn).

Giữ cơ chế kiểm tra snapshot, con/liên kết bên ngoài, đề nghị, lịch sử sử dụng, thiết bị, chuyến bay, phân bổ M18 và các FK tới hồ sơ/tài nguyên. Nếu có thành phần bị sửa hoặc phụ thuộc, cả đợt dọn bị chặn. Không có tùy chọn ép xóa; manifest và audit vẫn được giữ để tra cứu nguồn.

Chế độ kịch bản đầy đủ cũ vẫn được giữ để quản lý manifest `infrastructure-local-v1:BMV`; nó không được dùng để thêm lặp các loại đã có trong lượt triển khai toàn bộ sân bay này.

## Bảo toàn dữ liệu trước đó

| Bảng | Trước | Sau | Tập hàng cũ giữ nguyên |
|---|---:|---:|---|
| `airports` | 22 | 22 | Có |
| `airport_infrastructure` | 656 | 782 | Có |
| `airport_resources` | 647 | 647 | Có |
| `infrastructure_types` | 10 | 10 | Có |
| `resource_allocations` | 2918 | 2918 | Có |
| `flights` | 987 | 987 | Có |
| `infrastructure_links` | 3 | 3 | Có |
| `runway_directions` | 2 | 2 | Có |
| `infrastructure_sources` | 12 | 138 | Có |
| `infrastructure_fixture_sets` | 1 | 22 | Có |

Đối chiếu bằng SHA-256 của từng tập hàng theo khóa gốc trước/sau, không chỉ đếm tổng số. Tất cả các bảng profile, nguồn và manifest đã có cũng giữ nguyên. Không có mã/ID/parent/trạng thái của hồ sơ cũ bị thay đổi để ép kết quả.

Không tự phân bổ tài nguyên mới cho chuyến bay, không tạo lịch đường băng/băng chuyền, không đổi quy tắc xung đột hay công bố lịch. Không mở rộng quyền và không thay đổi Sidebar. Số lượng nhóm tiếp tục truy vấn từ DB.

## Kiểm thử và kiểm chứng website

- `AllAirportsInfrastructureTest`: **5/5 test, 48 assertion đạt** trên `airline_test`. Bao gồm danh sách sân bay động (thêm sân bay test ngoài dữ liệu nền), chỉ bổ sung loại thiếu, quan hệ, giữ nguyên loại/hồ sơ cũ, chạy lại, dọn riêng bộ mới và giữ BMV cũ, preflight toàn bộ khi có xung đột, từ chối nguồn chưa ánh xạ, tìm kiếm theo tên suy ra và giữ nguyên mã/tên trong DB.
- `InfrastructureFixturesTest` và `InfrastructureDisplayTest` được chạy cùng bộ mới: 14 ca trong lượt đầu có một lỗi so sánh thứ tự hàng của test. Đã sắp thứ tự theo ID và chạy lại toàn bộ 5 ca mới đạt; 9 ca còn lại đã đạt ở lượt trước. Không thay dữ liệu ứng dụng để đáp ứng test.
- Hồi quy quản lý, chuẩn hóa, đơn giản hóa hạ tầng và lịch tài nguyên/công bố chuyến: **23/23 test, 459 assertion đạt**. Không chạy reset database ứng dụng.
- PHP lint và compile Blade thành công; `git diff --check` không có lỗi khoảng trắng trong các file tracked đã kiểm tra.
- Kiểm tra DB sau triển khai: 0 mã trùng và 0 lỗi vị trí/chu trình/sai sân bay trên toàn bộ 782 hồ sơ.
- HTTP thật trên `http://localhost:8000`: **22/22 sân bay** trả 200, tổng số hồ sơ và số lượng bốn nhóm khớp ma trận DB; mã/ID/tên gốc trong JSON khớp dữ liệu đã lưu. **42/42 kiểm tra trình duyệt đạt**: danh sách BMV/HAN/SGN ở 1440px và 390px; đủ 10 bộ lọc trên HAN ở cả hai kích thước; chi tiết đủ 10 loại trên mobile và các hồ sơ quầy/băng chuyền/cầu ống lồng đại diện ở BMV/SGN. Hai truy vấn tìm theo mã và tên suy ra trả đúng hồ sơ. Không có lỗi JavaScript, không tràn ngang document, các hàng đều có liên kết tên đậm và mã nhỏ ở dưới. Đây là kiểm tra HTTP/UI đại diện cùng kiểm tra dữ liệu cho toàn bộ sân bay; không tuyên bố đã duyệt thủ công mọi tổ hợp sân bay × loại × viewport.

Chứng cứ: `backend/storage/app/private/all-airports-http-results.json`, ảnh `all-airports-*.png`, các snapshot và CSV ở cùng thư mục. Script `scripts/check-all-airports-infrastructure.cjs` gọi HTTP và điều khiển trình duyệt tới URL thật; không chặn response để thay bằng HTML snapshot. Phiên kiểm tra dùng tài khoản quản trị hiện có, không tạo user hoặc đổi quyền; phiên tạm được dọn sau khi kết thúc.

## Giới hạn

Đủ 10 loại trong database local là độ phủ chức năng kiểm thử, không xác nhận mỗi sân bay thực tế có cầu ống lồng hoặc đúng số lượng/hạng mục được tạo. Dữ liệu chính thức vẫn cần mã/tên, sơ đồ vị trí, thông số và tài liệu nguồn do đơn vị quản lý cung cấp. Dùng luồng CSV/quản trị nguồn và đề nghị–duyệt để tiếp nhận dữ liệu đã xác minh; không dùng bộ sinh này cho production hoặc database dùng chung.

## File chính thay đổi

- `InfrastructureDisplay.php`, `InfrastructureCatalog.php`, `InfrastructureRepository.php`: tên chung, gợi ý mã và tìm kiếm tên hiển thị.
- `InfrastructureController.php`, `InfrastructureReferenceController.php`: dùng truy vấn tìm kiếm chung.
- `m01/infrastructure-identity.blade.php`, `unified-infrastructure.blade.php`, `infrastructure-reference.blade.php`, `infrastructure-fields.blade.php`: cùng định dạng tên trên/mã dưới, gợi ý mã tạo mới.
- `InfrastructureFixtures.php`, `PopulateInfrastructure.php`: chế độ bổ sung loại còn thiếu, toàn bộ sân bay động, preflight và dọn theo manifest riêng.
- `AllAirportsInfrastructureTest.php`, cập nhật kỳ vọng hiển thị của `InfrastructureDisplayTest.php`, `scripts/check-all-airports-infrastructure.cjs`.

Không reset DB, không chạy `migrate:fresh`/`db:wipe`, không xóa Docker volume, không commit hoặc push.
