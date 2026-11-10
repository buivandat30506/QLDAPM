> Cập nhật 10/11/2026: quyết định hiện hành là **10 loại / 4 nhóm**, đã tinh gọn database thật. Xem [báo cáo triển khai và ánh xạ](m01-infrastructure-simplification-report.md). Nội dung 41 loại bên dưới được giữ làm lịch sử khảo sát trước quyết định này.

# Rà soát BA: Cơ sở hạ tầng sân bay

Ngày 09/11/2026. Tài liệu này là hồ sơ **đề nghị rà soát**, không phải biên bản BA phê duyệt.

## Nguồn và độ chắc chắn

- Yêu cầu trực tiếp: nội dung người dùng cung cấp cho lượt chuẩn hóa database → M01 → liên phân hệ, cùng cấu trúc sidebar đã thống nhất trong hội thoại.
- Hiện trạng đã kiểm tra: migrations, OperationsService, MasterDataService, UseCaseService, WorkspaceForms/Presentation, Access, routes, views, tests và dữ liệu ba node Docker cục bộ.
- Báo cáo thứ cấp trong repo: `m01-airport-infrastructure-report.md`, `sidebar-workflow-report.md`, `docx-coverage-audit.md`, `requirements-summary.md`, `architecture.md` và tài liệu ERD hiện có. Chúng hỗ trợ xác định chỗ cần hỏi lại, không thay thế đặc tả gốc được ký duyệt.
- Chưa truy cập được bộ Use Case/đặc tả, Business Analysis, Sitemap và Activity/Sequence gốc của nhóm. Không đánh dấu bất kỳ mở rộng nào là “đã được tài liệu gốc xác nhận”. Những khác biệt M18-F02–F05 cần đối chiếu lại trên đúng phiên bản gốc.

## Ma trận yêu cầu

| Mã | Yêu cầu | Nguồn yêu cầu | Đã triển khai | Cần BA xác nhận | Ảnh hưởng phân hệ |
|---|---|---|---|---|---|
| INF-01 | Một identity vật lý, bảo toàn resource ID/phân bổ | Người dùng + FK/data/code cũ | Hồ sơ chuẩn, lớp mapping; không đổi ID cũ | Xác nhận mô hình là chuẩn chung | M01/M03/M09/M18 |
| INF-02 | Nhà ga/tầng/sảnh/khu vực riêng; parent có thể trống | Đề xuất mở rộng lượt này | Loại riêng + parent tùy chọn; kiểm tra sân bay/chu trình | Ma trận parent chi tiết, bắt buộc dữ liệu từng loại | M01/M10 |
| INF-03 | Xử lý hai nguồn TERMINAL | Schema cũ + yêu cầu người dùng | Phát hiện xung đột và dừng, không tự merge | Quy trình đối soát, ai xác minh cùng một nhà ga; đổi loại TERMINAL cũ | M01/M18 |
| INF-04 | Một cổng phục vụ đi/đến; cửa ra sảnh riêng | Đề xuất người dùng | Gate profile hai capability nullable; ARRIVAL_EXIT riêng | Capability có bắt buộc không; dùng trong luồng đến thế nào | M01/M03/M09/M18 |
| INF-05 | Liên kết cổng–vị trí đỗ, vị trí đỗ–sân đỗ | Đề xuất người dùng | Quan hệ cùng sân bay do người dùng khai báo; chưa tác động gán chuyến | Một/nhiều liên kết, hiệu lực theo ngày, kiểm soát tương thích | M01/M03/M18 |
| INF-06 | Phân biệt băng chuyền đi/đến | Đề xuất người dùng; BELT có trong backend cũ | Ba loại BELT/ARRIVAL_BELT/DEPARTURE_BELT + tham chiếu M10 | Phạm vi thực, xử lý băng chuyền hai chiều; đổi loại legacy | M01/M10 |
| INF-07 | Đường băng vật lý và các hướng | Đề xuất mở rộng | Hồ sơ kích thước/giới hạn, hướng con cùng ID đường băng | Chỉ danh mục hay kế hoạch mô phỏng; dữ liệu kỹ thuật nguồn nào | M01/M03 |
| INF-08 | Xếp lịch cổng/quầy/vị trí đỗ | Backend OperationsService hiện hữu + ưu tiên người dùng | Luồng mới dùng 3 loại và chính resource_id cũ, giữ overlap/window/UTC/audit/gate | Danh sách loại, thời gian đi/đến, kết thúc lịch, quan hệ arrival | M18/M03/M09 |
| INF-09 | Xếp lịch băng chuyền | Chưa có quy tắc gán/window được xác minh | **Chưa triển khai**, chỉ danh mục/tra cứu | Chuyến đến hay đi, cửa sổ thời gian, capacity, xung đột | M10/M18 |
| INF-10 | Điều phối đường băng/cất hạ cánh | Chưa có đặc tả được xác minh | **Chưa triển khai**, không gán vào flights | Mức mô phỏng, an toàn, quyền và lịch dùng riêng | M03/M18 |
| INF-11 | Active, condition và lịch là ba khái niệm | Người dùng đề xuất; active/allocations có sẵn | Badge tách biệt; condition nullable; lịch thực có timestamp | Ý nghĩa/transition condition; tác động bảo trì/hạn chế | M01/M18/M03/M11 |
| INF-12 | Không thay đổi status sân bay/High/Overload tự động | Ràng buộc người dùng + M01 lượt trước | Giữ bốn trạng thái sân bay; không tự tính ngưỡng | Chủ sở hữu và quy trình cập nhật trạng thái tổng thể | M01/cảnh báo |
| INF-13 | Sáu nhóm UI, airport-first, tìm kiếm/phân trang DB | Yêu cầu người dùng | Backend + UI; form theo loại; trạng thái trống | Tên nhãn và loại cần ẩn theo phạm vi thực | M01/sidebar |
| INF-14 | Thêm/sửa/duyệt độc lập, audit | MasterDataService/Access cũ | Tạo theo quyền data_admin/admin; sửa chuẩn qua master_changes, SOD/stale | Tạo mới có cần duyệt? Legacy area API đang sửa trực tiếp; cần đồng nhất chính sách? | M01/audit |
| INF-15 | Vị trí thiết bị mặt đất | ground_equipment/assignment cũ + đề xuất | FK vị trí tùy chọn, kiểm tra cùng sân bay, audit; không đổi task/equipment rules | Loại vị trí cho từng phương tiện; có duyệt hay không | M11/M01 |
| INF-16 | Hạ tầng của chuyến trong M03 | resource_allocations cũ + yêu cầu | Đọc allocation thực, join type/code/name/sân bay | Luồng đến; dữ liệu runway tương lai | M03/M18 |
| INF-17 | Bảo trì/năng lực/tương thích hạ tầng | Chưa đủ quy tắc BA | Chỉ tình trạng/ghi chú; **chưa có engine hạn chế/capacity** | Đơn vị đo, ngưỡng, lịch bảo trì, phê duyệt và tác động | M01/M05/M11/M18 |
| INF-18 | Không phá API cũ | Ràng buộc người dùng | Giữ output API legacy; thêm endpoint riêng | Lộ trình ngừng generic allocation cũ (vẫn nhận TERMINAL), versioning | API/M18 |
| INF-19 | Phạm vi sân bay, không tự cấp quyền/multi-role | Access hiện hữu + ràng buộc | Dùng primary role, admin bypass hiện hữu; M01 data_admin/admin; tham chiếu theo module | Data_admin hiện không scoped; cần thay đổi vai trò/quyền nào? | Toàn hệ thống |
| INF-20 | Đổi mã/loại/sân bay sau chuẩn hóa | Quyết định triển khai bảo toàn identity | Chặn thay đổi identity trong các đường ghi đã tích hợp | Quy trình rename/reclassify, alias và lịch sử; không tạo bản vật lý thứ hai để né | M01/API/M18 |

Các dòng ghi “đã triển khai” mô tả code, không biểu thị đã được BA chấp thuận nghiệp vụ.

## Mười quyết định cần chốt

1. **Nhà ga/tầng/sảnh/khu vực:** chấp nhận mô hình parent tùy chọn hiện tại? Cần matrix nào cho từng loại; khu vực có thể trực thuộc sân bay? Giữ TERMINAL chưa phân loại hay bổ sung quy trình phân loại lại 44 hồ sơ cũ?
2. **Cổng:** thống nhất quản lý một đối tượng vật lý với capability đi/đến. Capability có làm điều kiện xếp lịch không? Phân biệt ARRIVAL_EXIT thế nào trong Use Case đến/boarding?
3. **Băng chuyền:** xác nhận đến/đi, hai chiều, phân loại/tập kết và quá khổ nằm trong phạm vi nào; ai cung cấp dữ liệu nhà ga/khu vực? Chưa có gán chuyến hoặc lịch băng chuyền trong lượt này.
4. **Đường băng:** chỉ quản lý danh mục/hướng/kích thước hay bổ sung kế hoạch sử dụng mô phỏng? Cần hợp đồng flight–runway nào? Chưa có runway assignment hoặc cấp phép.
5. **M18:** xác nhận GATE/COUNTER/STAND; có lịch arrival không, cửa sổ phục vụ nào? BELT/TERMINAL có cần hỗ trợ hay loại khỏi API cũ theo phiên bản? UI mới hiện chỉ ba loại.
6. **Tương thích/năng lực:** loại máy bay, kích thước, trọng tải, hành khách/giờ, số quầy, liên kết cổng–stand cần quy tắc gì? Ghi chú hiện chỉ tham khảo, không kiểm tra an toàn hoặc chặn chuyến.
7. **Bảo trì:** hạng mục nào có hồ sơ/lịch/đơn vị phụ trách riêng? Condition ảnh hưởng lịch đã có và phân bổ mới thế nào? Không mặc định dùng quy trình bảo trì máy bay cho công trình.
8. **Quyền:** xác nhận người tạo/sửa/duyệt và phạm vi sân bay; tạo mới có duyệt không; xử lý API area sửa trực tiếp đang có ra sao? Multi-role là WIP riêng, chưa kích hoạt.
9. **M18-F02 đến F05:** yêu cầu BA cung cấp BA và Use Case cùng phiên bản, đối chiếu từng chức năng, actor, pre/post-condition, ngoại lệ, phê duyệt và source of truth. Chưa đủ tài liệu gốc để khẳng định tên/mapping F02–F05; không tự chọn một phía hoặc tự thêm bước duyệt phân bổ.
10. **Tài liệu bị ảnh hưởng:** cập nhật UC M01, các tham chiếu M03/M10/M11/M18 và M09; ERD identity/profile/link; Sitemap giữ 5 nhóm/13 nút; Activity/Sequence chọn sân bay→chi tiết, tạo/sửa→duyệt, mapping dữ liệu cũ, phân bổ→overlap→gate projection, tra cứu hành lý và đổi vị trí thiết bị. Cần xác định số hiệu sơ đồ từ bộ gốc, không tự gán số.

## Phần chưa được triển khai/phê duyệt

Chưa có tự gán băng chuyền, lịch/điều phối runway, tự suy capability/parent, tính capacity/High/Overload, tự hủy/di chuyển phân bổ vì condition, bảo trì công trình riêng, rename/reclassify hồ sơ, hợp nhất nguồn TERMINAL mơ hồ hoặc kích hoạt multi-role. Chỉ triển khai các liên kết và quản lý danh mục có validation; chờ BA chốt trước khi thêm tác động vận hành.

Đề nghị BA trả lời từng mã INF với trạng thái chấp nhận/điều chỉnh/ngoài phạm vi, người quyết định, phiên bản tài liệu và tiêu chí nghiệm thu. Sau quyết định mới cập nhật policy, migration dữ liệu và regression tương ứng.
