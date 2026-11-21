# Báo cáo triển khai cơ sở hạ tầng sân bay

Ngày 09/11/2026, repository `E:\Projects\QLDAPM`, Docker qua Ubuntu WSL. Phạm vi chỉ môi trường cục bộ. Không commit/push; giữ các thay đổi Git có trước và Multi-role WIP.

Hai tài liệu đi kèm: [mô hình dữ liệu/ERD](airport-infrastructure-data-model.md), [ma trận và câu hỏi dành cho BA](airport-infrastructure-ba-review.md). Các đề xuất chưa được tự đánh dấu BA phê duyệt.

## 1. Khảo sát và nguồn

Đã kiểm tra schema/migration, services/controllers/routes, Access/role/scope, sidebar/forms/views, tests và dữ liệu thực trên ba connection: `ops`→db1, `mysql`→db2, `finance`→db3. Đã đọc báo cáo M01 trước cùng tài liệu thứ cấp trong repo. Chưa có bộ Use Case/BA/Sitemap/Activity/Sequence gốc để đối chiếu; đặc biệt chưa thể chốt khác biệt M18-F02–F05.

Không thay đổi User/Access để kích hoạt Multi-role; runtime tiếp tục primary role và ngoại lệ admin đang có. Không sửa file demo/seeders đang dùng.

## 2. Phần đã triển khai

- Định danh chuẩn `airport_infrastructure`, danh mục 41 loại/6 nhóm/category; hồ sơ gate/runway/belt/stand/asset, hướng runway và quan hệ gate–stand. Parent tùy chọn, cùng sân bay, kiểm tra chu trình và loại cha phù hợp. Một gate có hai capability; một runway có nhiều hướng.
- Giữ `airport_resources.id`, `airport_areas.id` và allocation cũ. Normalizer kiểm tra trước khi ghi, dừng toàn bộ khi hai nguồn nhà ga/mã chưa rõ. Replay không ghi đè hồ sơ đã sửa. Adapter nối các đường ghi legacy vào cùng identity.
- Thêm mới theo quyền M01 hiện có; sửa bằng master_changes và người duyệt độc lập. Snapshot/version phát hiện stale; audit đầy đủ; chặn tắt hiệu lực khi còn phân bổ chưa kết thúc. Mở rộng audit từ 255 ký tự sang LONGTEXT để tránh lỗi/cắt snapshot.
- M01 theo thứ tự chọn sân bay→nhóm→hạng mục→chi tiết; tìm mã/tên, lọc loại, phân trang SQL 12 dòng; chi tiết con/lịch 10 dòng; parent và các profile theo loại. Dữ liệu chưa biết hiển thị rõ, không sinh hạng mục mẫu trên sân bay thật. Metadata khu vực nguồn cũ được giữ/hiển thị.
- Form thuộc tính theo loại; lỗi từng trường và phần tử danh sách. Hiệu lực, condition và lịch được hiển thị riêng. Condition chưa áp dụng tác động khai thác mới. Bốn trạng thái sân bay giữ nguyên.
- Sidebar giữ 5 nhóm/13 nút chính. Nhóm sân bay có tên “Quản lý sân bay”, ba submenu “Danh sách sân bay”, “Cơ sở hạ tầng sân bay”, “Lịch sử dụng hạ tầng”; submenu vẫn theo quyền người đăng nhập.

## 3. Mức tích hợp liên phân hệ

| Phân hệ | Đã thực hiện | Giới hạn |
|---|---|---|
| M18 | Form/endpoint mới chỉ GATE/COUNTER/STAND; nhãn sân bay/loại/mã/tên; JS lọc tài nguyên theo sân bay chuyến; backend kiểm tra và dùng nguyên OperationsService.allocate/unallocate | Không thay đổi overlap, cửa sổ bao giờ khởi hành, UTC, audit, tự cập nhật gate/outbox hoặc thêm duyệt phân bổ. API generic cũ vẫn giữ hợp đồng, chưa thu hẹp type |
| M03 | Bảng allocation thật của các chuyến đang xem, join canonical qua resource_id cũ, tối đa 50 dòng; sân bay đi/đến/type/code/name/thời gian | Không tự gán runway; không thay đổi status, lịch bay hay kiểm tra an toàn |
| M10 | Tra cứu read-only băng chuyền/khu vực hành lý và CLAIM_AREA, theo sân bay/quyền, hiển thị parent; dữ liệu từ M01 | Chưa có gán chuyến–belt, lịch/time window/capacity; không ghi vào baggage/boarding/check-in |
| M11 | Tra cứu vị trí và optional FK `ground_equipment.location_infrastructure_id`; backend cùng sân bay, audit; cập nhật vị trí của thiết bị hiện hữu | Không nhân bản thiết bị, đổi status, người được phân công, ca trực hoặc ground_task_equipment |
| M02/M09/M19 | Giữ nguyên scheduling/boarding/disruption services, khóa ID và các projection hiện hữu; chạy hồi quy liên quan | Không mở rộng nghiệp vụ; lỗi kỳ vọng admin của test cũ được ghi riêng bên dưới |

M01 chỉ cho data_admin/admin truy cập theo chính sách hiện hữu. Trang tham chiếu M10/M11/M03 dùng quyền module riêng, không cấp thêm quyền sửa M01 cho các vai trò đó.

## 4. Backup, migration và bảo toàn

Backup đủ ba node: `backups/20261109T140748Z-15827/{db1,db2,db3}.sql.gz`. Đã kiểm tra gzip và restore SQL thật vào schema mới trên từng node. Không reset/truncate database làm việc, không xóa volume.

Môi trường kiểm tra:

- `airline_test` / user `airline_test` trên cả ba node: PHPUnit, với guard bắt buộc database/user test trước khi tạo fixtures. Seeder demo không đổi; TestCase chỉ tái nạp metadata loại khi bảng mới có mặt.
- `infra_verify_20261109` trên ba node: restore đầy đủ, kiểm tra hash, migration/normalization/idempotency/down/up; sau đó mới dùng browser fixtures riêng ở sân bay **TST** thuộc bản sao này.
- `infra_verify_final_20261109` trên ba node: restore lần nữa để thử phiên bản migration cuối sau khi bổ sung rollback guard vị trí thiết bị. Kết quả: up/down/up và toàn bộ hash legacy khớp, 644 canonical, 2.918 allocation giữ nguyên.
- Database làm việc `airline`: đã áp dụng riêng migration mới và chuẩn hóa lúc **22:08:27 ngày 09/11/2026 (UTC+7)**, sau khi các kiểm tra trên đạt. Không áp dụng production hoặc database nhóm.

Migration mới: `backend/database/migrations/2026_11_09_010000_normalize_airport_infrastructure.php`. Schema ở connection ops; lệnh chuẩn hóa tách riêng, mặc định dry-run, chỉ `--apply` mới ghi. Không chạy migration analytics đang chờ hoặc migration Multi-role trong lượt này. Migration trạng thái sân bay của lượt trước giữ nguyên.

Rollback chỉ xác minh trên bản sao dữ liệu legacy trước khi có nghiệp vụ hạ tầng mới. Migration từ chối down nếu có audit tạo/sửa hạ tầng mới, vị trí thiết bị không NULL hoặc audit dài hơn dung lượng cũ. Khi đã phát sinh dữ liệu mới phải thiết kế migration chuyển dữ liệu được rà soát; không dùng down làm nút khôi phục tùy ý.

Snapshot ban đầu trong `backend/storage/app/private/infra-before.json`:

| Connection/bảng | Số bản ghi trước = sau | SHA-256 dữ liệu cũ (bỏ riêng cột liên kết mới) |
|---|---:|---|
| ops.airports | 22 | 176b7da19027d85cd4a439bedd44fe0dea1c86460cbc61ce8f794f7e1e1ca411 |
| ops.airport_resources | 644 | 401a4fb22c218e5ad8da903e6a161fd5a698c4a553f163250d6b44abcf76d219 |
| ops.airport_areas | 0 | 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945 |
| ops.resource_allocations | 2.918 | 7233d9013fb3de22c5b71346f66b143b5ab61202f1a9aa75a02db3e64e99e1e7 |
| ops.flights | 987 | 8d0bc1a959041281659b34d0caaa1aa89faef7103a61fe71ed6c7a5c878b36d2 |
| ops.ground_equipment | 0 | 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945 |
| ops.ground_tasks | 2.910 | 4ebc4b9750f393f5d36482d79d9cbe4c217963b9cca5563ee32cd814c618f30c |
| ops.master_changes | 5 | 37c1521a74c51f9417000688446f6613c5954f91769dc2518b0b383b01865044 |
| mysql.bookings | 34.789 | de27e6b400e26f333359477e797d5601a1f0d2ecfac429fe5664abec52c87e04 |
| mysql.boarding_passes | 19.489 | d6293618021c5c91849d4c06648327cd251471c19251c9d51c24a40f44875414 |
| mysql.baggage | 3.170 | 71804b013c6ee08e9242c4bb2954e4bd33b97bab6bc80ea6986be79684895fe1 |
| mysql.baggage_events | 15.845 | e10d36e1c9cb09cef9a1757e0dcb44815eb37c3d623ce61b643394e1c9a5867f |
| finance.payments | 34.781 | 55f4ba42ab03c839297f14878261ef08220ecae3d51319bee644b408672c9967 |
| finance.ledger_entries | 34.782 | 40565e92877601dacdf89009666afc64913636cfc043ac21c215df690e24522d |

Audit legacy đối chiếu với bản khôi phục: ops 1.113 dòng (hash `5de7f764a6b789533bada4b504913889430fb1095ec69c5d5bd12ed414b39490`), mysql 974 (`2f8d8696755317b4579f25a3fb653d11b50a5afa934fe5c3455b16b4a5f38028`), finance 37 (`74e4340fe5a4685575045e1d85d5071b1ca0fb48ed55f323b08955256c6bf98d`). Mở rộng kiểu cột không thay nội dung audit.

Mapping thực: 44 TERMINAL giữ chưa phân loại, 200 GATE, 200 COUNTER, 200 STAND; không có areas để suy quan hệ. Tổng 644 hồ sơ import không có parent/name/condition giả. Không tự phân loại nhà ga hành khách/hàng hóa. Các loại mới không có dữ liệu ở sân bay thật hiển thị trạng thái trống.

## 5. Danh sách file triển khai

File mới chính:

- `app/Support/InfrastructureCatalog.php`; `app/Models/{AirportInfrastructure,InfrastructureType}.php`.
- `app/Modules/Operations/{InfrastructureService,InfrastructureNormalizer,InfrastructureCompatibility,InfrastructureRepository}.php`.
- `app/Console/Commands/NormalizeInfrastructure.php`.
- `app/Http/Controllers/{InfrastructureController,InfrastructureReferenceController,InfrastructureScheduleController}.php`.
- Migration chuẩn hóa nêu trên.
- `resources/views/m01/{unified-infrastructure,infrastructure-form,infrastructure-fields,infrastructure-detail,infrastructure-reference,flight-infrastructure,airport-infrastructure-summary,technical-state,effective-state,schedule-state,legacy-details,infra-error}.blade.php`.
- `tests/Feature/InfrastructureNormalizationTest.php`; ba báo cáo trong docs.

File tích hợp đã sửa trong lượt này (đường dẫn tính từ backend nếu không ghi khác):

- `app/Http/Controllers/AirportController.php`: điều hướng HTML sang danh mục mới; JSON legacy/explicit source vẫn tương thích.
- `app/Modules/Operations/{OperationsService,MasterDataService,MasterFileService}.php`, `app/Modules/Shared/UseCaseService.php`: adapter, proposal/approve, export canonical.
- `app/Support/{WorkspaceForms,WorkspacePresentation,StaffNavigation}.php`, `routes/web.php`: endpoint mới, lựa chọn/nhãn, sidebar.
- `resources/views/{workspace,m01/infrastructure,m01/airport-detail}.blade.php`: tích hợp nội dung và fallback schema cũ.
- `frontend/app.css`, `frontend/app.js` và bản đồng bộ `backend/public/app.css`, `backend/public/app.js`.
- `tests/TestCase.php`, `tests/Feature/AirportWorkspaceTest.php`, `tests/Feature/OperationsWorkflowTest.php`: metadata test và kỳ vọng nhãn UI mới; không đổi kỳ vọng phân quyền admin cũ để che lỗi.

Các file WIP khác xuất hiện trong git status thuộc công việc trước; không gom tất cả thành thay đổi của lượt chuẩn hóa. Các helper, snapshot/log/screenshot nằm trong storage/app/private, không đưa credential/backup vào Git.

## 6. Kiểm thử và nghiệm thu cuối

| Phạm vi | Kết quả thực tế | Bằng chứng / giới hạn |
|---|---|---|
| Backup/restore SQL ba node | PASS | Kiểm tra gzip, restore thật vào hai bộ schema mới; đối chiếu các bảng trong snapshot |
| Migration cuối up → normalize → replay → down → up → normalize | PASS | infra_verify_final_20261109; giữ 644 tài nguyên cũ, 2.918 allocation và hash của toàn bộ bảng snapshot; không chạy down trên DB làm việc |
| Guard rollback với hồ sơ mới/vị trí thiết bị | PASS | InfrastructureNormalizationTest; từ chối rollback, giữ bản ghi |
| Bộ cuối liên quan trực tiếp | **PASS: 38 tests, 362 assertions**, PHP 8.4.27 / PHPUnit 11.5.57 | InfrastructureNormalizationTest (12 bài), AirportWorkspaceTest, SidebarWorkflowTest, UseCaseBackendWorkflowTest và bốn bài OperationsWorkflowTest cho render/overlap/duyệt/alias |
| Hồi quy rộng | Đã chạy **89 tests, 770 assertions; lần đó 5 FAIL** | Ba lỗi về Blade, phạm vi CLAIM_AREA và kỳ vọng tiêu đề đã được sửa, chạy lại đạt trong bộ 38 bài. Hai lỗi kỳ vọng quyền admin cũ vẫn còn, xem bên dưới. Không tuyên bố bộ rộng đã PASS toàn bộ |
| Browser Chrome/Playwright | **PASS: 24 trang × 6 độ rộng** | 1920/1440/1024/768/390/320; scrollWidth không vượt viewport; không có pageerror JavaScript. Bảng rộng có vùng cuộn nội bộ |
| Luồng browser nghiệp vụ | PASS | Tạo runway bằng form; sửa→PENDING; tự duyệt 409; reviewer khác duyệt 200 và UI hiển thị tên đã duyệt; lọc resource M18 theo sân bay; operations không được mở M01; baggage ngoài sân bay bị 404 |
| Bàn phím và lỗi từng trường | PASS | Chọn SGN và submit bằng Enter; mở sidebar bằng Enter; input hướng runway sai trả về lỗi phần tử hiển thị trong form |
| Blade/JS/assets/Git whitespace | PASS | 22 template compile + token parse; node --check frontend/app.js; npm run assets; git diff --check không có lỗi whitespace (có cảnh báo chuẩn hóa CRLF của Git) |
| Dữ liệu cục bộ sau áp dụng | PASS | 644 hồ sơ chuẩn, 41 loại metadata; 0 mapping sai; 0 allocation mồ côi; 0 pending/conflict; 644 condition và parent còn NULL; không tạo runway/belt mẫu |
| Audit cũ sau áp dụng | PASS | Số dòng và hash ops/mysql/finance khớp bản khôi phục như phần 4 |
| Production/database nhóm, tải lớn, thiết bị thật, điều phối ATC | **CHƯA KIỂM TRA / ngoài phạm vi** | Không triển khai hoặc kết nối môi trường đó; không coi test ứng dụng là xác nhận an toàn khai thác hàng không |

Hai lỗi còn lại của bộ rộng:

1. `JourneyTest::test_ownership_and_role_boundaries`: dòng cuối kỳ vọng admin boarding bị 403, thực tế 422 do token giả không hợp lệ sau khi đã qua quyền.
2. `OperationsWorkflowTest::test_roles_and_airport_scope_are_checked_for_workspace_and_mutations`: kỳ vọng admin mở finance bị 403, thực tế 200.

`Access::allows` trong source hiện hữu cho admin quyền các role nhân viên; Access và BoardingService không có thay đổi Git trong lượt này. WorkspaceController vẫn dùng Access::role như trước. Không sửa chính sách quyền hoặc đổi hai assertion để che kết quả. Cần người sở hữu quyền/BA chốt lại kỳ vọng test so với policy hiện có; các bài mới vẫn kiểm tra nhân viên đúng vai trò/phạm vi và từ chối quyền sai.

M01 mới dùng nhãn “Danh sách sân bay”, nên bài render workspace chung được cập nhật riêng kỳ vọng tiêu đề catalog; các tiêu đề/phân quyền khác giữ nguyên.

Artifact cục bộ (không commit):

- `backend/storage/app/private/infra-local-verification.json`: thời gian áp dụng, 14 bảng với count/hash trước/sau, pending 644→0, canonical 644.
- `infra-final-integrity.json`: không có mapping sai/phân bổ mồ côi, không có runway/belt giả; migration analytics vẫn chờ.
- `infra-migration-verification.json`, `infra-audit-verification.json`: kiểm chứng bản khôi phục, up/down/up và audit cũ.
- `infra-focused.log`, `infra-regression.log`, `infra-browser-results.json`, `infra-ui-validation-results.json`: kết quả chạy thật. Lỗi trung gian được ghi để phân biệt với lần kiểm tra cuối.
- `infra-{list,gate,runway,create-runway,schedule,reference}-{1440,390}.png`: ảnh giao diện trên bản sao cô lập.

Schema test/restore được giữ tách biệt với dữ liệu làm việc. Preview tạm dừng sau kiểm tra; không để tài khoản/credential thử nghiệm trong source hoặc báo cáo. Không chạy các nghiệp vụ tạo/sửa mẫu trên DB `airline`.

## 7. Giới hạn và bước tiếp

Chưa triển khai: lịch/gán băng chuyền, điều phối đường băng, năng lực/tương thích tự động, bảo trì công trình riêng, tự hủy/chuyển lịch khi condition đổi, đổi identity/phân loại lại, merge TERMINAL mơ hồ và Multi-role. API area legacy vẫn có sửa trực tiếp theo hành vi cũ; UI chuẩn hóa sửa qua duyệt độc lập. API allocation generic vẫn rộng hơn UI mới; cần BA chốt và lộ trình API versioning.

Kiểm tra browser chỉ dùng bản khôi phục và account đúng vai trò ở đó; không dùng tài khoản QA đặc quyền để bỏ qua chính sách. Fixtures runway/belt/parent đều thuộc sân bay TST cô lập. Không coi dữ liệu thử này là dữ liệu vận hành thực.

Trước môi trường khác: BA chốt ma trận yêu cầu, review migration/quyền/API legacy, backup và restore thử chính môi trường đó, chạy conflict scan, xác minh mapping, triển khai có kiểm soát rồi đối chiếu hash/ID. Không tự chạy lên database dùng chung.
