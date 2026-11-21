# M02 — Hoàn thiện quy trình và dữ liệu local

Ngày thực hiện: 10/11/2026. Ứng dụng kiểm tra: `http://localhost:8000`, Docker project đang dùng, database `airline` trên ba node. Không commit hoặc push trong lượt này.

## Căn cứ và phạm vi

Đã đối chiếu yêu cầu đính kèm, báo cáo M02 trước, tài liệu có trong repository, routes, WorkspaceFlow/StaffNavigation, SchedulePlanning, ScheduleConflicts, OperationsService, UseCaseService, FlightChangeService, logic tổ bay/cổng/công bố, OutboxRelay/WorkflowRelay, migration và database đang chạy. DOCX gốc và Sitemap riêng chưa có ở đường dẫn truy cập được; không coi báo cáo cũ là nguồn thay thế cho đặc tả thiếu.

F01 và phần lớn F02/F03/F05 đã có backend và giao diện hoạt động. Lần này tái sử dụng các chức năng đó, bổ sung tiến độ kiểm tra, phân loại kết quả, ba danh sách phê duyệt, liên kết theo kế hoạch/chuyến, ngừng phát sinh, M03 đọc cùng dữ liệu và bộ dữ liệu có nguồn gốc. Không thay Access, vai trò tài khoản, sidebar hay các quy tắc hàng không.

## Kiểm kê dữ liệu

Số liệu trước được đọc trực tiếp, trước migration và fixture:

| Dữ liệu | Trước | Sau bộ kịch bản M02 |
|---|---:|---:|
| Đường bay | 44 | 44 |
| Kế hoạch định kỳ | 0 | 4 |
| Chuyến bay | 987 | 1.006 |
| Nháp | 1 | 13 |
| Chờ duyệt | 1 | 5 |
| Có timestamp công bố | 985 | 988 |
| Tàu bay | 33 | 39 |
| Phân công tổ bay | 5.922 | 5.946 |
| Lịch sử dụng hạ tầng | 2.918 | 2.921 |

Riêng `m02-local-v1`: 19 chuyến = 12 DRAFT + 4 PENDING + 3 SCHEDULED đã công bố. Có 12 quan hệ kế hoạch/ngày/chuyến; 3 nháp trùng với lịch đã công bố, 2 nháp tàu đang bảo dưỡng, 2 nháp chồng nhau tạo cảnh báo. Kết quả kiểm tra toàn bộ tập: **3 chuyến xung đột, 2 lỗi điều kiện, 5 cảnh báo, 9 hợp lệ trong phạm vi kiểm tra**. Năm cảnh báo bao gồm ba chuyến đã công bố gặp đề xuất nháp trùng, cùng hai nháp chồng nhau. Một chuyến có thể thuộc nhiều nhóm; không cộng số vấn đề để suy ra tổng chuyến.

Bộ kịch bản tạo sáu tàu riêng, tám nhân sự tổ bay riêng, một cổng riêng và một lệnh bảo dưỡng trên tàu mới. Không làm tàu cũ mất khả dụng. Ba nháp xung đột được tạo sau ba chuyến công bố hợp lệ; không dựng cặp chuyến công bố sai quy tắc.

Lượt trình duyệt tạo thêm **kế hoạch #5 và chuyến #1007 (AS95001)** qua form, phân công hai thành viên tổ bay, gửi duyệt và công bố độc lập. Cuối lượt: **5 kế hoạch, 1.007 chuyến, 13 nháp, 5 chờ duyệt, 989 có timestamp công bố, 39 tàu, 5.948 phân công, 2.922 lịch sử dụng hạ tầng**. Đây là 20 chuyến mới tổng cộng; bốn chuyến mới đã công bố.

Đối chiếu SHA-256 theo đúng các cột cũ, có tính số bản ghi trùng hash, trên **mọi bảng có trước ở cả ba node**: `missing_old_rows = []`. Không có bản ghi cũ bị mất hoặc thay đổi. Các trạng thái khác giữ nguyên: ARRIVED 529, BOARDING 1, GATE_CLOSED 1, DEPARTED 1, CANCELLED 1, DELAYED 1; SCHEDULED tăng từ 451 lên 455. Cổng/nhân sự/tàu/lịch sử mới được thêm riêng, không đổi dữ liệu M01 cũ.

## Ma trận M02-F01 đến F05

| UC | Database | Backend | Frontend | Dữ liệu và kiểm thử | Kết quả |
|---|---|---|---|---|---|
| F01 | Giữ routes/master_changes/audit và ID cũ | Tái sử dụng kiểm tra sân bay, trùng tuyến, thời lượng/khoảng cách, phê duyệt độc lập | Danh sách/tìm mã hoặc tên/lọc/phân trang → chi tiết → đề nghị sửa → hàng đợi | 44 tuyến hiện có; M02PlanningTest kiểm tra thêm/trùng/sửa/duyệt | Giữ chức năng đã có, hồi quy đạt |
| F02 | Giữ flight_seasons và version | Kế hoạch dài hạn; thêm archive có khóa/version/audit, giữ chuyến đã tạo | Checkbox thứ, chi tiết, sửa, xem trước, liên kết các chuyến, form ngừng phát sinh thu gọn | Bốn kế hoạch daily/weekly/long/manual; replay và archive bảo toàn chỉnh sửa được kiểm tra | Đã triển khai theo quy tắc hiện có |
| F03 | Giữ occurrence unique theo kế hoạch/ngày và flight | Preview không ghi; generate transaction/version, bỏ qua occurrence đã có | Xem trước có số hiệu/tuyến/tàu/cổng/giờ hai sân bay; xác nhận riêng; danh sách/chi tiết/nguồn | 12 chuyến định kỳ của fixture; kiểm tra 31/32 ngày, UTC/DST, phát sinh lại, nguồn cũ | Đã triển khai và kiểm thử |
| F04 | Kết quả tạm lưu Cache có hạn; không thay dữ liệu chuyến | ScheduleReview snapshot ID, xử lý 100 chuyến/đợt, khóa đợt, quyền theo người/vai trò/sân bay; công bố luôn kiểm tra lại | Tab Kiểm tra và cảnh báo; tiến độ, bốn nhóm, nguyên nhân, mức độ, link, phân trang 12 kết quả | 19 tình huống local; bài kiểm tra 311 chuyến: 100 → 200 → 300 → 311, chạy lại không đếm trùng | Đã triển khai và kiểm thử; giới hạn kiểm tra nêu rõ |
| F05 | Giữ flight_schedule_changes, version, audit/outbox; thêm m02_demo_sets lưu provenance | Tái sử dụng submit/publish batch atomic, người duyệt độc lập; sửa nháp có diff/version; M03 đọc flights chung | Ba danh sách nháp/chờ duyệt/đã công bố; thao tác theo quyền, kiểm tra/xác nhận, lịch sử và link M03 | Ba công bố thật qua backend, một sửa nháp được duyệt; M03/M17/M18 được xác minh | Đã triển khai trong phạm vi backend hiện có |

## Cách thao tác trên website

1. Vào **Quản lý chuyến bay → Lập và công bố lịch bay**. Mở **Bộ lọc và phạm vi**, chọn bộ `m02-local-v1` để xem riêng các kịch bản mới. Bỏ bộ lọc để xem toàn bộ dữ liệu.
2. Ở **Lịch bay định kỳ**, chọn kế hoạch để mở hồ sơ. Kế hoạch `MANUAL` chưa phát sinh, có tàu riêng để thử. Chọn khoảng ngày trong hiệu lực → **Xem trước lịch chuyến bay** → kiểm tra kết quả → **Xác nhận phát sinh lịch nháp**. Mỗi đợt tối đa 31 ngày, kế hoạch có thể dài hơn.
3. Từ hồ sơ kế hoạch, mở **Danh sách chuyến thuộc kế hoạch** hoặc **Kiểm tra và cảnh báo**. Chọn phạm vi và chạy kiểm tra. Nếu còn chuyến chưa kiểm tra, bấm **Kiểm tra tiếp**. Mỗi kết quả có thời điểm; bản kiểm tra hết hạn sau một giờ. Chuyến mới xuất hiện sau khi chụp danh sách ID cần một lượt kiểm tra mới.
4. Chuyến mới phát sinh cần tổ bay thực tế trước công bố. Người phân công dùng chức năng tổ bay hiện có (M06). Bộ kịch bản đã phân công tổ bay cho các chuyến định kỳ để có thể thử gửi duyệt/công bố ngay.
5. Người `operations` chọn chuyến nháp trong bảng hoặc mở chi tiết → kiểm tra → xác nhận gửi phê duyệt. Người `ops_manager` độc lập chọn danh sách chờ duyệt → kiểm tra → xác nhận phê duyệt và công bố. `data_admin` được lập kế hoạch/tạo chuyến, không được công bố.
6. Xem lịch sử trên hồ sơ chuyến: nguồn kế hoạch/ngày/phiên bản, đề nghị sửa và trước/sau, người/thời gian, gửi duyệt/công bố. Chuyến legacy thiếu dấu vết được ghi là chưa ghi nhận; không dựng audit bù.
7. Sau công bố, bấm **Mở chuyến trong điều hành M03** hoặc link từ kết quả đợt. M03 đọc cùng chuyến, giờ, tàu, cổng, tổ bay, lịch sử dụng hạ tầng. Chuyến DRAFT/PENDING không xuất hiện ở danh sách khai thác. Form cập nhật khai thác gọi action flight_state hiện có và giữ các điều kiện chuyển trạng thái. Đổi lịch đã công bố đi qua quy trình gián đoạn M03 hiện có.

Các kế hoạch/chuyến trong kịch bản là dữ liệu mô phỏng được lưu thật trong database; giao diện không vẽ mảng dữ liệu giả. Nhãn hợp lệ chỉ nói về tàu bay/thời gian/giữ tàu đã kiểm tra, không chứng nhận đủ tổ bay/cổng. Công bố còn kiểm tra lại các điều kiện đó.

## Dữ liệu có thể dựng lại và cách chạy an toàn

Migration mới: `2026_11_10_060000_add_m02_demo_sets.php`, chỉ thêm bảng provenance trên ops. Đã thử trên `airline_test`, sao lưu ba node trước khi áp dụng local. Không reset database hoặc sửa bản ghi nghiệp vụ cũ.

Lệnh đã chạy trên local:

```text
docker exec airline-app-1 php artisan migrate --force
docker exec -e AIRLINE_M02_FIXTURES=true airline-app-1 php artisan airline:m02-demo --key=m02-local-v1 --start=2026-11-13
```

Trên máy khác dùng Compose, sau migrate và khi đã có các tài khoản demo/tuyến HAN–SGN:

```text
docker compose --env-file backend/.env exec -e AIRLINE_M02_FIXTURES=true app php artisan airline:m02-demo --key=m02-local-v1
docker compose --env-file backend/.env exec -e AIRLINE_M02_FIXTURES=true app php artisan airline:m02-demo --key=m02-local-v1 --inspect
```

Không truyền `--start` sẽ chọn ngày Việt Nam hiện tại +3 cho bộ mới. Cùng key giữ ngày gốc và manifest, chạy lại không tạo trùng hoặc ghi đè chỉnh sửa người dùng. Key đã gắn ngày khác bị từ chối. Bộ đang PREPARING cần đúng các tài khoản nguồn để tiếp tục. Có thể chỉ rõ ID tài khoản qua `--creator/--operator/--reviewer/--fleet/--crew/--technician`; command không tự tạo tài khoản hay cấp quyền.

Command chỉ chạy khi opt-in, local/testing, host/database local được cho phép, thanh toán mock và SMTP local. Không có hook tự tạo fixture lúc Docker khởi động. Đây là kiểm tra cấu hình, không thay cho việc người vận hành chọn đúng database riêng.

Đối với **máy cài mới**, `python scripts/demo.py` gọi bộ cài đầy đủ, nay bổ sung bộ `portable-m02-v1` bằng cùng service. Vì vậy máy tải code này có thể dựng lại các tình huống M02 và hạ tầng M01 theo [hướng dẫn demo](portable-demo.md). Bộ cài đã READY từ phiên bản trước giữ nguyên dữ liệu; bổ sung M02 bằng command opt-in, không tự ghi đè khi khởi động lại. Các chỉnh sửa thủ công trên một máy không được Git tự đồng bộ sang máy khác.

Để ngừng phát sinh từ bộ dữ liệu:

```text
docker compose --env-file backend/.env exec -e AIRLINE_M02_FIXTURES=true app php artisan airline:m02-demo --key=m02-local-v1 --archive
```

Lệnh lưu trữ các kế hoạch chưa bị người dùng sửa; kế hoạch đã sửa được liệt kê là giữ nguyên. Chuyến, công bố, phân bổ, inventory và audit được giữ lại. Không cung cấp lệnh xóa hàng loạt các chuyến đã công bố để gọi là dọn test. Muốn thử rồi bỏ toàn bộ bộ dữ liệu, dùng database/project demo riêng ngay từ đầu; lượt này không xóa volume nào. Chưa chạy archive trên bộ local đang phục vụ người dùng.

Snapshot SQL và kiểm kê hash nguồn nằm trong `backend/storage/app/private/m02-workflow-before/`; manifest và ảnh trình duyệt cũng ở storage private. Không đưa SQL, cookie, mật khẩu hoặc ảnh chứa dữ liệu máy tác giả lên Git.

## Kiểm thử và giới hạn

Backend: **65 tests, 937 assertions PASS**, 0 FAIL, 7 phút 19 giây. Gồm M02CompleteWorkflowTest, M02PlanningTest, OperationsWorkflowTest, SidebarWorkflowTest, FlightResourceChangeTest và InfrastructureManagementTest. Mọi bài ghi dữ liệu chạy bằng user/database `airline_test`; TestCase chặn trước khi reset nếu không đúng cả tên database và username test.

Sau rà soát khóa dùng chung giữa chuẩn bị và lưu trữ fixture, chạy lại riêng bốn bài M02CompleteWorkflowTest: **4/4 PASS, 64 assertions**, 52,6 giây; không cộng thành 69 bài khác nhau.

Bốn bài mới kiểm tra fixture qua backend thực; nguồn/audit/version; M03 đọc cùng dữ liệu và ngăn nháp/sai sân bay; inventory M17 và phân bổ M18; replay công bố; replay fixture giữ chỉnh sửa; archive không xóa lịch; kiểm tra 311 chuyến không đếm trùng và không chia sẻ kết quả cho người khác; từ chối fixture khi thiếu opt-in, production hoặc host DB ngoài danh sách. Các bài M02 cũ tiếp tục kiểm tra DST, giới hạn phát sinh, stale version/snapshot, tự duyệt, batch rollback và kiểm tra lại khi gửi/công bố.

Script trình duyệt: `scripts/check-m02-complete-workflow.cjs --exercise-local`, chỉ chạy có cờ vì tạo một kế hoạch/chuyến mới qua form và thực hiện công bố. Script có checkpoint private để tiếp tục lượt dở, không tạo thêm khi chạy lại cùng fixture. Có chế độ `--role-sessions` cho phiên local đã xác thực tạm, không thay tài khoản hay mật khẩu; cookie không được commit.

Chrome thật qua HTTP: **19 màn hình × 6 độ rộng (1920, 1440, 1024, 768, 390, 320 px) PASS**; không lỗi JavaScript, không tràn ngang toàn trang. Bảng có vùng cuộn riêng. Đã thực hiện form tạo kế hoạch, xem trước không ghi, phát sinh một chuyến, hai form phân công M06, xem trước/gửi duyệt, xem trước/công bố bằng người khác; kiểm tra ba danh sách theo vai trò; chạy kiểm tra thật và chọn bốn nhóm; xác minh nháp xung đột không có nút xác nhận; xem lịch sử sửa đã duyệt và mở danh sách/chi tiết M03. Ảnh và `results.json` ở storage private.

Đọc trực tiếp database sau lượt trình duyệt: bốn chuyến mới đã công bố (#996–998 và #1007) đều có **2 phân công tổ bay đang dùng, 1 phân bổ cổng, 1 bản ghi inventory M17, 1 audit schedule.published và quan hệ kế hoạch nguồn**. Không chuyển giả sang BOARDING/DEPARTED. Chạy lại cùng command/key/ngày trả manifest cũ, không tạo thêm chuyến.

Mật khẩu cấu hình local hiện không khớp tài khoản demo cũ, nên kiểm tra trình duyệt lượt này dùng phiên tạm gắn các tài khoản có sẵn, theo đúng vai trò. Không tính đây là kiểm thử đăng nhập bằng mật khẩu thành công, không reset mật khẩu và không cấp quyền mới. Backend vẫn chạy qua middleware, scope, CSRF, validation và các service thật khi thao tác.

Bốn phiên xác thực theo vai trò đã được xóa sau khi kiểm tra. Lượt đối chiếu cuối sau replay và dọn các phiên vẫn cho `missing_old_rows = []` trên cả ba node.

Không chạy lại toàn bộ bộ cài mới trên database trống trong lượt này: phần bổ sung M02 của bộ cài dùng đúng service đã chạy trên test và local; bằng chứng cài mới trước đó nằm trong báo cáo portable demo. Không gộp bằng chứng cũ thành một lần kiểm thử mới. `git diff --check` và Node syntax check đều đạt.

Chưa kiểm thử tải lớn hoặc tranh chấp đa tiến trình. Cache snapshot có khóa, hết hạn một giờ; không thay thế kiểm tra trong transaction công bố. Nếu một chuyến trong snapshot bị xóa hoặc không còn đọc được, thao tác dừng báo lỗi, cần chạy phạm vi mới. Không có chức năng sửa hàng loạt lịch đã công bố bằng M02.

## Nội dung BA cần xác nhận

- Quy tắc lịch mùa/IATA, mã mùa, phiên bản mùa và cách thay đổi cả chuỗi đã phát sinh chưa có đặc tả đầy đủ; hiện dùng kế hoạch dài hạn, phát sinh từng đợt, lưu trữ và tạo kế hoạch mới.
- Backend hiện phê duyệt và công bố là một transaction. Chưa tự thêm trạng thái đã duyệt nhưng chưa công bố, hoặc quy trình thu hồi/hủy đề nghị sửa.
- Giữ khoảng quay đầu 30 phút, nghỉ tổ bay 12 giờ, năng định/giấy phép và kiểm tra cổng hiện có. Chưa tự đặt thêm chuẩn hàng không, năng lực sân bay hoặc giới hạn mới.
- Sửa M02 chỉ cho lịch chưa công bố theo cơ chế version/snapshot. Thay đổi đã công bố tiếp tục qua M03/M04/M18 hiện có. M03 mới trong lượt này là danh sách/chi tiết và điểm vào thao tác hiện có, không phải tái triển khai toàn bộ điều hành.
- Quyền admin trung tâm đang có được giữ nguyên; chưa diễn giải thành quyền mới. Sự khác nhau giữa metadata kế hoạch do operations/ops_manager quản lý và tác nhân data_admin cần đối chiếu khi có đặc tả gốc đầy đủ.

Không tuyên bố hoàn thành 100% các quy tắc nghiệp vụ chưa có đặc tả hoặc kiểm thử ngoài phạm vi trên.
