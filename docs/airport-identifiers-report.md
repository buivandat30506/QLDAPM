# Bổ sung mã ICAO và mã quốc gia ISO cho sân bay

Ngày: 10/11/2026. Đã bổ sung trực tiếp hai trường `icao`, `country_code` còn trống của 22 sân bay trên database Docker local `airline`. Không tạo sân bay mới, không thay đổi mã IATA, tên, trạng thái, active, hạ tầng, chuyến bay hay phân bổ.

## Nguồn đối chiếu

- ICAO: [AIP Việt Nam – VATM, AD 1.3, bản 01/10/2026](https://aim.vatm.vn/images/stories/vnaic.vn/SanPhamDichVu/AIPVietNam/AIP/2026-10-01-AIRAC/html/eAIP/VV-AD-1.3-vi-VN.html).
- IATA và tên đối chiếu: [Vietnam Airlines – danh sách sân bay](https://www.vietnamairlines.com/go/vi/plan-book/travel/travel-guide/cac-san-bay-o-viet-nam). Trang này ghi nhầm Chu Lai thành VVCI/HPH; lấy **VCL/VVCA** theo AIP và hồ sơ IATA hiện hữu, không sao chép lỗi đó.
- Quốc gia: [UNECE – danh mục ISO 3166-1 alpha-2](https://unece.org/trade/cefact/unlocode-code-list-country-and-territory), mục Viet Nam = **VN**. [ISO OBP Việt Nam](https://www.iso.org/obp/ui/#iso:code:3166:VN) là liên kết tra cứu tiêu chuẩn. Trường country_code hiện có độ dài 2, lưu alpha-2; không lưu VNM hay mã điện thoại +84.

Tất cả 22 sân bay hiện có thuộc Việt Nam. Không gán VN theo múi giờ hoặc tên chung cho sân bay ngoài danh sách đã xác minh.

## Dữ liệu đã bổ sung

| IATA | ICAO | ISO alpha-2 |
| --- | --- | --- |
| BMV | VVBM | VN |
| CAH | VVCM | VN |
| CXR | VVCR | VN |
| DAD | VVDN | VN |
| DIN | VVDB | VN |
| DLI | VVDL | VN |
| HAN | VVNB | VN |
| HPH | VVCI | VN |
| HUI | VVPB | VN |
| PQC | VVPQ | VN |
| PXU | VVPK | VN |
| SGN | VVTS | VN |
| TBB | VVTH | VN |
| THD | VVTX | VN |
| UIH | VVPC | VN |
| VCA | VVCT | VN |
| VCL | VVCA | VN |
| VCS | VVCS | VN |
| VDO | VVVD | VN |
| VDH | VVDH | VN |
| VII | VVVH | VN |
| VKG | VVRG | VN |

## Cách cập nhật

- Nguồn có phiên bản/ngày xác minh: `backend/database/data/vietnam-airport-identifiers.json`.
- Seeder riêng: `backend/database/seeders/AirportIdentifierSeeder.php`. Chạy chủ động bằng `php artisan db:seed --class=AirportIdentifierSeeder`; chỉ cho local/testing, không tự chạy khi tải trang.
- Chỉ điền trường NULL/chuỗi rỗng của sân bay đã tồn tại. Gặp giá trị khác nguồn thì giữ nguyên cả hồ sơ và báo xung đột. Không tạo lại sân bay bị xóa; sân bay ngoài 22 mã không bị sửa.
- Kiểm tra mã ICAO trùng với sân bay khác trước khi ghi. Toàn bộ cập nhật nằm trong transaction, mỗi hồ sơ có audit `airport.identifiers.imported` trước/sau và người thực hiện “Hệ thống”.
- Sao lưu 22 dòng đầy đủ trước khi ghi: `backend/storage/app/private/airport-identifiers-before-20261110-064835.json` (Git ignore).
- Database có một đề nghị sửa Vân Đồn PENDING. Giữ nguyên before_data/after_data và trạng thái đề nghị. `MasterDataService` chỉ bỏ qua chênh lệch định danh trống→có mã đã được audit bởi lần import khi đề nghị không sửa chính trường đó. Thay đổi thủ công khác với audit vẫn bị chặn do dữ liệu cũ. Không nới quyền hoặc bỏ yêu cầu người duyệt độc lập.
- `AirportController` và partial lịch sử hiển thị sự kiện bổ sung mã. Dòng “Bản ghi có hiệu lực” đã được bỏ khỏi Thông tin chung theo yêu cầu trước.

## Kết quả kiểm tra

- Trước: 22/22 sân bay thiếu ICAO và country_code. Sau: 0 trường hợp thiếu; 22/22 đã đủ cả hai mã.
- Chạy import lần hai: 0 hồ sơ cập nhật, không sinh audit dư.
- So sánh từng dòng sân bay: chỉ icao/country_code thay đổi. Hash của airport_infrastructure, airport_resources, resource_allocations, flights, master_changes giữ nguyên.
- `AirportIdentifierImportTest`: 3/3 PASS, 15 assertions; kiểm tra dry-run, tính ổn định, giữ dữ liệu khác, không ghi đè giá trị thủ công/airport lạ, giữ đề nghị chờ duyệt và chặn thay đổi thủ công gây stale.
- PHP syntax và Pint cho Seeder/test đạt. Kết quả giao diện được kiểm tra trên trang chi tiết sau cập nhật.

Kết quả chi tiết riêng tư: `backend/storage/app/private/airport-identifiers-result.json`, log `airport-identifiers-tests.log`. Không sửa cấu trúc database, không reset dữ liệu, không commit/push.
