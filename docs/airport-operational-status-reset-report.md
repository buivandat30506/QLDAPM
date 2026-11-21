# M01 — Chuẩn hóa trạng thái vận hành sân bay

Ngày thực hiện: 10/11/2026 (Asia/Saigon). Báo cáo này thay thế các quy tắc nhãn/phân bố demo của những lượt trước; không thay thế hệ thống condition hạ tầng.

## 1. Nhãn và màu dùng chung

Nguồn duy nhất: `AirportWorkspace::STATUSES`. Giữ mã backend, đổi nhãn hiển thị:

| Backend | Nhãn giao diện | Màu chữ / nền |
|---|---|---|
| NORMAL | Normal | Xanh lá #17603d / #eaf7ef |
| HIGH | High | Vàng/cam #8d5100 / #fff5df |
| OVERLOAD | Overload | Đỏ #a52b38 / #fff0f1 |
| MAINTENANCE | Maintenance | Tím #7040a1 / #f3ecfb |

Badge có chấm, nền nhạt, chữ rõ, không nhấp nháy. Tooltip tiếng Việt giải thích ý nghĩa. Danh sách, chi tiết, chú giải, dropdown form và lịch sử trạng thái đều dùng nguồn này. Không có bộ lọc riêng theo trạng thái trong danh sách hiện tại; bộ tìm sân bay giữ nguyên. API lưu/nhận bốn mã in hoa như trước.

Hạ tầng vẫn hiển thị Bình thường/Hạn chế sử dụng/Đang bảo trì/Tạm ngừng hoạt động/Ngừng hiệu lực/Chưa xác định theo hệ thống riêng. Không đổi condition, active, identity, parent hoặc cấu trúc 41 loại.

## 2. Tái sử dụng command, không tạo seeder trùng

Mở rộng command hiện có `airline:simulate-airport-statuses` và service `AirportStatusSimulation`. Phiên bản nguồn `airport-status-local-v2`; vẫn nhận biết và dọn an toàn nguồn v1. Không thêm migration hoặc tự seed khi mở trang. Cập nhật mới nhất bổ sung xáo trộn một lần có seed, lưu vào database như phần 8.

```sh
# Xem trước việc điền sân bay chưa có trạng thái
php artisan airline:simulate-airport-statuses
php artisan airline:simulate-airport-statuses --apply

# Xem trước / xác nhận phân bố lại dữ liệu demo còn nguyên
php artisan airline:simulate-airport-statuses --rebalance
php artisan airline:simulate-airport-statuses --rebalance --apply

# Xem trước / dọn dữ liệu demo của v1/v2 chưa bị sửa
php artisan airline:simulate-airport-statuses --clear
php artisan airline:simulate-airport-statuses --clear --apply
```

Không kết hợp --clear và --rebalance. Cả service và command chỉ cho local/testing, host ops cục bộ theo guard hiện hữu. Không chạy production hoặc database nhóm.

Mặc định chỉ điền hồ sơ có toàn bộ trạng thái/note/actor/time/source/key/fingerprint NULL. --rebalance mở rộng tới hồ sơ source=SIMULATION, key v1/v2 và SHA-256 fingerprint khớp đầy đủ dữ liệu hiện tại. Trạng thái MANUAL, dữ liệu cũ chưa rõ nguồn, và demo đã bị sửa ngoài luồng bị giữ nguyên. Không có --force để ghi đè thủ công.

## 3. Phân bố ổn định và thay đổi số sân bay

Với 22 sân bay: 15 NORMAL, 4 HIGH, 2 OVERLOAD, 1 MAINTENANCE. Khi số lượng khác, tính quota theo trọng số 15:4:2:1, làm tròn có điều chỉnh và bảo đảm mỗi loại ít nhất một nếu có từ bốn sân bay. Dưới bốn ưu tiên Normal, High, Overload theo số lượng có thể biểu diễn.

Chế độ --rebalance thông thường ưu tiên DAD cho Maintenance, CXR/HAN cho Overload, CAH/SGN/PQC/DLI cho High; phần còn lại theo mã sân bay đã sort. Chế độ --randomize mới không dùng các ưu tiên này. Đây là lựa chọn minh họa, không phải đánh giá tình trạng thực tế của những sân bay đó. Khi mã không đủ điều kiện, chỉ dùng các hồ sơ còn được phép cập nhật. Không tạo sân bay để đạt quota.

Trạng thái được bảo vệ được tính vào phân bố hiện tại, sau đó mới bù quota bằng các hồ sơ được phép cập nhật. Nếu trạng thái thủ công đã chiếm quá nhiều một loại hoặc không còn đủ hồ sơ có thể thay đổi, việc giữ nguyên dữ liệu thủ công được ưu tiên; tỷ lệ cuối có thể khác đề xuất hoặc chưa thể đủ bốn loại. Command báo eligible/changed/skipped và targets để rà soát, không tự thay đổi quyền hoặc dữ liệu thật để đạt tỷ lệ.

Tải lại trang không gọi command và không thay đổi dữ liệu. Chạy lại --rebalance với cùng dữ liệu v2 đúng kế hoạch không ghi lại timestamp/audit. Thay đổi số lượng hoặc cập nhật thủ công chỉ ảnh hưởng lần chạy command có chủ đích tiếp theo.

## 4. Nguồn, nhận biết và dọn an toàn

Giữ operational_status_source=SIMULATION, note mô tả mục đích trình bày, key v2, fingerprint và timestamp. Ghi audit trước–sau với actor hệ thống, không giả một Giám đốc đã xác nhận.

Theo điều chỉnh giao diện mới nhất, bỏ chữ **Demo** cạnh badge và trong nội dung hiển thị M01. Nguồn được giải thích qua tooltip và phần chi tiết “Dữ liệu trình bày”, không được trình bày như thông tin khai thác thực tế. Bốn nhãn trạng thái vẫn chính xác tiếng Anh. Khi người có quyền cập nhật thủ công, source chuyển MANUAL và key/fingerprint bị xóa như luồng cũ. Không đổi trạng thái đã lưu hoặc audit khi chỉnh nhãn hiển thị.

--clear chỉ đặt các trường trạng thái của demo v1/v2 còn nguyên về NULL, không xóa sân bay hoặc audit. Fingerprint không khớp thì skipped; MANUAL không nằm trong phạm vi dọn. Không chạy clear trên database trình bày trong lượt này; cleanup được kiểm thử trên database test, và có dry-run để kiểm tra tập bản ghi có thể dọn.

## 5. Quyền và phạm vi

Giữ `AirportStatusAuthority` và bảng bổ nhiệm hiện có: airport_director theo sân bay; system_director theo các mã được giao hoặc phạm vi `*` được cấp rõ ràng. Nhân viên thông thường chỉ xem theo phạm vi. Admin kỹ thuật/data_admin không tự có quyền ghi trạng thái. Endpoint kiểm tra Backend, reason và confirmed trước khi ghi; không chỉ ẩn nút.

Không thay `Access`, `users.role`, Multi-role WIP hoặc tự cấp quyền cho tài khoản QA/admin. Chưa có UI quản trị bổ nhiệm; sử dụng command bổ nhiệm theo quyết định tổ chức như báo cáo quyền trước. Lượt này không tạo bổ nhiệm trên database làm việc.

## 6. File chỉnh sửa

| File (tính từ backend nếu không ghi khác) | Nội dung |
|---|---|
| app/Support/AirportWorkspace.php | Bốn nhãn tiếng Anh, tooltip tiếng Việt |
| app/Modules/Operations/AirportStatusSimulation.php | Nguồn v2, quota thích ứng, bảo vệ manual/fingerprint, rebalance và cleanup tương thích v1 |
| app/Console/Commands/SimulateAirportStatuses.php | Thêm --rebalance và kiểm tra lựa chọn đối nghịch |
| resources/views/m01/status.blade.php | Badge giữ đúng nhãn, nguồn Demo và tooltip |
| resources/views/m01/index.blade.php | Truyền nguồn trạng thái vào badge danh sách |
| resources/views/m01/airport-detail.blade.php | Truyền nguồn trạng thái vào badge chi tiết |
| resources/views/m01/airport-status-panel.blade.php | Nguồn demo rõ ràng; form/dropdown dùng nhãn chung |
| tests/Feature/AirportStatusResetTest.php (mới) | Phân bố 22, quota thay đổi số lượng, replay, nâng nguồn v1, bảo vệ thủ công/demo đã sửa và nhãn độc lập hạ tầng |
| tests/Feature/AirportStatusDisplayTest.php | Cập nhật kỳ vọng nhãn sân bay theo yêu cầu mới |
| tests/Feature/InfrastructureProductStatusTest.php | Tách kỳ vọng nhãn sân bay khỏi nhãn hạ tầng |
| docs/airport-operational-status-reset-report.md (mới) | Báo cáo này |
| docs/airport-status-permission-report.md; docs/product-status-ui-update.md | Dẫn chiếu quy tắc mới, phân biệt với lịch sử các lượt trước |

Các helper snapshot/backup/browser nằm trong storage/app/private để kiểm chứng, không phải chức năng sản phẩm. Giữ nguyên các thay đổi Git có sẵn; không gom các file WIP khác vào thay đổi lượt này.

## 7. Số liệu và nghiệm thu

Số liệu trước khi áp dụng trên local: 22 sân bay, Normal 6, High 6, Overload 5, Maintenance 5; đều thuộc dữ liệu demo đã được theo dõi từ lượt trước. Đã backup ba node vào `backups/20261109T172120Z-20316` và lập snapshot trước khi ghi.

Đã áp dụng trên database **airline cục bộ**, hoàn tất **00:29:21 ngày 10/11/2026 (UTC+7)**:

| Trạng thái | Trước | Sau |
|---|---:|---:|
| Normal | 6 | 15 |
| High | 6 | 4 |
| Overload | 5 | 2 |
| Maintenance | 5 | 1 |
| Chưa xác định | 0 | 0 |
| Tổng | 22 | 22 |

22 hồ sơ demo được nâng nguồn v1→v2; một số giữ nguyên mã trạng thái nhưng cập nhật phiên bản nguồn và audit. Không có trạng thái thủ công trong tập dữ liệu làm việc tại thời điểm chạy; các trường hợp thủ công hoặc demo đã sửa được kiểm thử trên database riêng. Chạy lại rebalance cập nhật **0** hồ sơ. Clear dry-run nhận diện 22 demo còn nguyên, không thực hiện clear trên database làm việc.

Đối chiếu snapshot: hash của **17 bảng** liên quan giữ nguyên khi bỏ riêng các trường trạng thái sân bay được phép thay đổi. Bao gồm toàn bộ hồ sơ hạ tầng và infrastructure_types, tài nguyên nguồn cũ, chuyến bay, phân bổ M18, người dùng, đặt chỗ, hành lý và thanh toán. Trường active và các trường hồ sơ sân bay khác không đổi. Nội dung audit cũ giữ nguyên; chỉ thêm audit cập nhật demo. Không reset/xóa dữ liệu, không chạy migration hoặc thay đổi schema trong lượt này.

| Kiểm tra | Kết quả |
|---|---|
| PHPUnit | **PASS — 28 tests, 365 assertions** |
| Phân bố đúng 22 sân bay; quota 0/1/2/3/4/5/7/22/25/100 | PASS |
| Seed dry-run, replay, rebalance v1/v2, bảo vệ manual/demo bị sửa, cleanup | PASS |
| Quyền Giám đốc theo phạm vi, reason/confirmed/audit, admin chưa bổ nhiệm bị cấm ghi | PASS trong bộ quyền hiện có và browser |
| Badge tiếng Anh, dropdown và lịch sử đồng nhất | PASS |
| Màu thực tế | PASS: Normal rgb(23,96,61), High rgb(141,81,0), Overload rgb(165,43,56), Maintenance rgb(112,64,161) |
| Reload giữ nguyên dữ liệu API | PASS |
| Browser 4 trang × 6 độ rộng | PASS: 1920/1440/1024/768/390/320; không tràn ngang trang, không pageerror JavaScript |
| Hạ tầng giữ nguyên nhãn/condition độc lập | PASS bằng test, browser và hash dữ liệu |
| 27 template M01 compile/parse; git diff --check | PASS, không lỗi whitespace; có cảnh báo chuẩn hóa CRLF sẵn có của Git |

Browser chạy trên bản sao cô lập `status_verify_20261109`, có dữ liệu kiểm thử TST của những lượt trước và tài khoản đúng vai trò. Không dùng số sân bay trên bản sao này làm số liệu trước/sau; bảng 22 sân bay phía trên lấy từ database làm việc. Hai lỗi của script kiểm tra ban đầu (thiếu header Accept JSON và selector chọn cả badge chú giải) được sửa trong script, sau đó chạy lại đủ luồng đạt PASS; không sửa ứng dụng để né kiểm tra.

Artifact cục bộ trong `backend/storage/app/private/`: airport-reset-before.json, airport-reset-local-verification.json, airport-reset-tests.log, airport-reset-browser-results.json và ảnh airport-reset-*-{1440,390}.png. Snapshot/verifier ghi đầy đủ count/hash, tập demo có thể dọn và kết quả replay. Backup lưu riêng như trên, không đưa credential hoặc SQL vào Git. Preview tạm được dừng và helper/credential kiểm thử được dọn sau nghiệm thu.

Giới hạn: chưa kiểm tra production, database nhóm, tải lớn hoặc tình trạng khai thác thực tế. Demo không là nguồn thông báo sân bay. Tỷ lệ đề xuất có thể bị giới hạn bởi dữ liệu thủ công cần giữ nguyên; không tự nâng quyền hoặc ép trạng thái để đạt tỷ lệ. Không commit/push.

## 8. Cập nhật xáo trộn ngẫu nhiên một lần — 10/11/2026

Theo yêu cầu mới, bổ sung `--randomize` và `--seed` vào chính command/service hiện có. --randomize tạo seed ngẫu nhiên bằng random_bytes nếu không chỉ định seed; seed được in trong kết quả và ghi cùng snapshot audit các bản ghi được cập nhật. Thứ tự mã sân bay được xáo theo hash SHA-256(seed:code), không ưu tiên cố định DAD/HAN/CXR hoặc vị trí đầu danh sách. Sau đó gán theo quota 15/4/2/1, giữ nguyên bảo vệ MANUAL/fingerprint.

```sh
# Xem trước, lấy seed trong output
php artisan airline:simulate-airport-statuses --randomize
# Áp dụng đúng cách xáo trộn đã xem trước
php artisan airline:simulate-airport-statuses --randomize --seed=SEED_VUA_NHAN --apply
# Hoặc tạo và lưu một lần với seed mới
php artisan airline:simulate-airport-statuses --randomize --apply
```

Seed mới cho cách phân bố khác; cùng seed và cùng dữ liệu có thể tái tạo đúng cách phân bố. Không tự tạo seed hoặc đổi trạng thái khi tải lại trang. Command mặc định không ghi đè các trạng thái đã thiết lập. Chạy lại cùng seed không đổi timestamp/audit của bản ghi đã đúng; --clear không kết hợp các lựa chọn xáo trộn. Tất cả vẫn chỉ local/testing.

Đã backup ba node `backups/20261109T173615Z-20916` và áp dụng cục bộ lúc **00:37:19 ngày 10/11/2026 (UTC+7)**, seed `ae636d4f2dd02fef`. Có **10 sân bay đổi mã trạng thái** so với cách phân bố cố định trước; tổng vẫn **15 Normal / 4 High / 2 Overload / 1 Maintenance**. Không có bản ghi thủ công trong tập dữ liệu làm việc lúc chạy; bảo vệ thủ công và demo đã sửa được xác minh trong tests. Chạy lại cùng seed và chạy seed mặc định đều cập nhật 0 bản ghi. Hash 17 bảng liên quan, gồm hạ tầng/chuyến bay/phân bổ/đặt chỗ/người dùng/thanh toán, giữ nguyên ngoài các trường trạng thái sân bay được phép đổi.

Kiểm thử: **6 tests, 41 assertions PASS**, gồm tỷ lệ, quota thích ứng, nâng nguồn cũ, hai seed khác nhau, tái lập cùng seed, dữ liệu lưu ổn định và bảo vệ cập nhật thủ công/demo đã sửa. CLI dry-run với --randomize --seed đã chạy thực tế, không ghi dữ liệu. Không chỉnh Frontend, quyền hoặc schema trong lần xáo trộn này; không chạy lại browser vì cấu trúc hiển thị không đổi. git diff --check không lỗi whitespace.

File thay đổi lần này: AirportStatusSimulation.php, SimulateAirportStatuses.php, AirportStatusResetTest.php và báo cáo này. Bằng chứng cục bộ: storage/app/private/airport-random-before.json, airport-random-verification.json, airport-random-tests.log. Không commit/push.
