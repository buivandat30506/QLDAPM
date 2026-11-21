> Cập nhật 10/11/2026: quyết định hiện hành là **10 loại / 4 nhóm**, đã tinh gọn database thật. Xem [báo cáo triển khai và ánh xạ](m01-infrastructure-simplification-report.md). Nội dung 41 loại bên dưới được giữ làm lịch sử khảo sát trước quyết định này.

# Rà soát đầy đủ 41 loại cơ sở hạ tầng Airline Smart

Ngày khảo sát: 09/11/2026. Đây là **phân tích và đề xuất để người dùng/BA quyết định**, không phải yêu cầu đã được BA duyệt. Lượt này không xóa/gộp mã, đổi tên loại trong database, đổi khóa, chuyển dữ liệu hoặc thêm loại.

## 1. Nguồn và cách đọc

Danh mục được lấy từ `backend/app/Support/InfrastructureCatalog.php`, đối chiếu bảng `ops.infrastructure_types` và đếm `ops.airport_infrastructure` trên database làm việc cục bộ. Kết quả: **41 loại, 644 hồ sơ**, gồm 44 TERMINAL, 200 GATE, 200 COUNTER, 200 STAND; 37 loại còn lại có 0 hồ sơ. Không dùng dữ liệu sân bay TST trong database kiểm thử để làm số liệu thực.

M01 hỗ trợ quản lý hồ sơ theo loại, quan hệ, hiệu lực/condition, đề nghị sửa–duyệt độc lập và audit cho cả 41 loại. “Có Backend/Frontend danh mục” **không đồng nghĩa** đã có nghiệp vụ điều phối, bảo trì hoặc xử lý hành khách cho loại đó.

Quy ước dùng trong bảng:

- **M01:** quản lý danh mục; mọi loại đều có luồng này.
- **M18/M03:** chỉ GATE/COUNTER/STAND có luồng phân bổ mới được hỗ trợ; M03 đọc allocation thật. M18 vẫn giữ ID legacy. TERMINAL có nguồn tài nguyên cũ nhưng không nằm trong lựa chọn phân lịch mới.
- **M10 tra cứu:** BELT, ARRIVAL_BELT, DEPARTURE_BELT, BAG_SORTING_AREA, BAG_MAKEUP_AREA, OVERSIZE_BAG_POINT và CLAIM_AREA; chưa gán băng chuyền cho chuyến, chưa có lịch hay capacity. Nhà ga/tầng/sảnh có thể xuất hiện gián tiếp trong đường dẫn vị trí thực.
- **M11 vị trí:** mọi hồ sơ chuẩn có thể được tham chiếu làm vị trí của thiết bị hiện hữu theo cùng sân bay. Đây là khả năng kỹ thuật chung; chưa xác nhận tất cả 41 loại đều hợp lý cho mọi thiết bị. Không biến hạ tầng thành phương tiện, nhiệm vụ hoặc lịch công việc M11.
- **A — Cốt lõi:** nên ưu tiên trong mô hình đồ án hiện tại; có thể gồm loại chưa có dữ liệu nhưng cần cho cấu trúc hành khách/hành lý. **B — Mở rộng hữu ích:** hợp lý khi mô phỏng sân bay chi tiết hơn. **C — Có thể đơn giản hóa:** chưa có luồng sử dụng rõ, quá chi tiết hoặc là loại tổng quát dễ gây chồng chéo. A/B/C là đề xuất, không phải thay đổi UI/DB tự động.

Các ví dụ dưới đây minh họa cấu trúc, **không phải khẳng định sân bay thật có hạng mục đó**. Vị trí chỉ nhập khi đã xác nhận; không suy từ mã C01, A01, T1 hoặc P01.

Tham khảo thuật ngữ chuyên ngành: tài liệu [FAA Airport Design](https://www.faa.gov/documentLibrary/media/Advisory_Circular/AC-150-5300-13B-Airport-Design-Chg1-w-errata.pdf) phân biệt đường băng, đường lăn và đường lăn sân đỗ; [glossary của Melbourne Airport](https://www.melbourneairport.com.au/corporate/glossary) giải thích taxilane phục vụ tiếp cận vị trí đỗ. Phân tích phạm vi Airline Smart ở đây do đối chiếu source, không phải áp dụng các tiêu chuẩn thiết kế này làm validation khai thác.

## 2. Bảng đủ 41 loại và số lượng thực

| STT | Tên hạ tầng đang hiển thị | Mã kỹ thuật | Chức năng thực tế | Nằm ở đâu | Phân hệ sử dụng hiện tại | Số bản ghi | Đánh giá đề xuất |
|---|---|---|---|---|---|---:|---|
| 1 | Nhà ga chưa phân loại | TERMINAL | Công trình nhà ga nguồn cũ, chưa xác định hành khách/hàng hóa | Trực thuộc sân bay | M01; legacy M18; M11 vị trí | 44 | A chuyển tiếp: phải giữ nguồn cũ, chưa tự phân loại |
| 2 | Nhà ga hành khách | PASSENGER_TERMINAL | Công trình tập trung các khu phục vụ hành khách | Trực thuộc sân bay | M01; parent gián tiếp; M11 vị trí | 0 | A: gốc cấu trúc phục vụ hành khách |
| 3 | Nhà ga hàng hóa | CARGO_TERMINAL | Công trình phục vụ luồng hàng hóa | Trực thuộc sân bay | M01; M11 vị trí | 0 | B: hợp lý nếu có phạm vi cargo |
| 4 | Tầng | LEVEL | Cấp không gian theo tầng trong nhà ga | Nhà ga, nếu có dữ liệu | M01; parent gián tiếp; M11 vị trí | 0 | B: hữu ích cho nhà ga nhiều tầng |
| 5 | Sảnh khởi hành | DEPARTURE_HALL | Không gian lớn phục vụ luồng khách đi | Nhà ga hoặc tầng | M01; parent gián tiếp; M11 vị trí | 0 | A: phân biệt rõ luồng khách đi |
| 6 | Sảnh đến | ARRIVAL_HALL | Không gian lớn phục vụ luồng khách đến | Nhà ga hoặc tầng | M01; parent gián tiếp; M11 vị trí | 0 | A: phân biệt luồng khách đến |
| 7 | Khu vực làm thủ tục | CHECKIN_AREA | Khu chứa các quầy/điểm làm thủ tục | Nhà ga/tầng/sảnh/khu vực | M01; parent gián tiếp; M11 vị trí | 0 | A: tránh đồng nhất khu với từng quầy |
| 8 | Khu vực an ninh | SECURITY_AREA | Vùng phục vụ kiểm tra an ninh hành khách | Nhà ga/tầng/sảnh/khu vực | M01; M11 vị trí | 0 | B: có ích cho sơ đồ, chưa có kiểm tra an ninh nghiệp vụ |
| 9 | Khu vực xuất nhập cảnh | IMMIGRATION_AREA | Vùng kiểm soát xuất/nhập cảnh khi áp dụng | Nhà ga/tầng/sảnh/khu vực | M01; M11 vị trí | 0 | C: chưa có quy trình/cơ quan xử lý trong đồ án |
| 10 | Khu vực hải quan | CUSTOMS_AREA | Vùng phục vụ thủ tục hải quan khi áp dụng | Nhà ga/tầng/sảnh/khu vực | M01; M11 vị trí | 0 | C: cần phân biệt với hành lý và xuất nhập cảnh |
| 11 | Khu vực chờ | WAITING_AREA | Không gian hành khách chờ trước khi tiếp cận cổng | Nhà ga/tầng/sảnh/khu vực | M01; parent gián tiếp; M11 vị trí | 0 | B: mô tả vị trí, chưa quản lý sức chứa |
| 12 | Khu nhận hành lý | CLAIM_AREA | Khu hành khách nhận hành lý, có thể chứa nhiều băng chuyền | Nhà ga/tầng/sảnh/khu vực | M01/M10 tra cứu; M11 vị trí | 0 | A: khác thiết bị băng chuyền |
| 13 | Khu vực chưa phân loại | ZONE | Vùng tổng quát khi chưa có loại chi tiết | Sân bay hoặc công trình có quan hệ | M01; parent gián tiếp; M11 vị trí | 0 | C chuyển tiếp: linh hoạt nhưng dễ thành loại dùng cho mọi thứ |
| 14 | Cổng tiếp cận máy bay | GATE | Một điểm/cổng vật lý phục vụ khách đi, đến hoặc cả hai | Nhà ga/tầng/sảnh/khu chờ | M01/M18/M03; M09 qua gate hiện có; M11 vị trí | 200 | A: resource đang có luồng sử dụng thật |
| 15 | Cửa ra sảnh đến | ARRIVAL_EXIT | Điểm ra khỏi luồng đến vào sảnh, khác cổng tiếp cận máy bay | Nhà ga/tầng/sảnh đến/khu vực | M01; M11 vị trí | 0 | B: hữu ích khi mô phỏng luồng khách đến |
| 16 | Quầy làm thủ tục | COUNTER | Từng quầy phục vụ check-in | Nhà ga/tầng/sảnh/khu làm thủ tục | M01/M18/M03; M11 vị trí | 200 | A: resource có phân bổ thật |
| 17 | Quầy gửi hành lý | BAG_DROP | Điểm nhận hành lý ký gửi, có thể tách quầy check-in | Nhà ga/tầng/sảnh/khu làm thủ tục | M01; M11 vị trí | 0 | B: chỉ tách khi là đối tượng vật lý riêng |
| 18 | Điểm lên xe buýt sân đỗ | BUS_BOARDING_POINT | Vị trí hành khách tập trung/lên xe ra máy bay | Nhà ga/tầng/sảnh/khu chờ | M01; M11 vị trí | 0 | B: phù hợp kịch bản cổng xa |
| 19 | Cầu ống lồng | JET_BRIDGE | Hạng mục nối luồng hành khách giữa nhà ga và máy bay | Vị trí phục vụ gắn với công trình đã xác nhận | M01; M11 vị trí | 0 | B: asset riêng, chưa vận hành/bảo trì thiết bị |
| 20 | Băng chuyền chưa phân loại | BELT | Băng chuyền nguồn tổng quát chưa xác định chiều xử lý | Nhà ga/khu hành lý phù hợp | M01/M10 tra cứu; M11 vị trí | 0 | C chuyển tiếp: không nên chọn khi đã biết đi/đến |
| 21 | Băng chuyền hành lý đến | ARRIVAL_BELT | Thiết bị đưa hành lý đến để hành khách nhận | Nhà ga/tầng/sảnh đến/khu nhận hành lý | M01/M10 tra cứu; M11 vị trí | 0 | A: danh mục đến rõ ràng, chưa gán chuyến |
| 22 | Băng chuyền hành lý đi | DEPARTURE_BELT | Thiết bị đưa hành lý đi vào luồng xử lý phía sau | Nhà ga/tầng/khu thủ tục/phân loại/tập kết | M01/M10 tra cứu; M11 vị trí | 0 | A: khác luồng trả hành lý đến |
| 23 | Khu phân loại hành lý | BAG_SORTING_AREA | Không gian phân loại hành lý theo chuyến/đích | Nhà ga/khu hành lý/kho được khai báo | M01/M10 tra cứu; M11 vị trí | 0 | B: khu vực, không phải một băng chuyền |
| 24 | Khu tập kết hành lý | BAG_MAKEUP_AREA | Không gian tập kết/chuẩn bị hành lý trước khi vận chuyển | Nhà ga/khu hành lý/kho được khai báo | M01/M10 tra cứu; M11 vị trí | 0 | B: khác công đoạn phân loại |
| 25 | Vị trí hành lý quá khổ | OVERSIZE_BAG_POINT | Điểm phục vụ hành lý không phù hợp tuyến tiêu chuẩn | Nhà ga/khu làm thủ tục/nhận hành lý | M01/M10 tra cứu; M11 vị trí | 0 | B: chỉ cần khi có quy trình hành lý đặc biệt |
| 26 | Đường băng vật lý | RUNWAY | Công trình dùng cho cất/hạ cánh | Trực thuộc sân bay, không thuộc nhà ga | M01; M11 vị trí kỹ thuật chung | 0 | B trong đồ án: danh mục, chưa điều phối bay |
| 27 | Đường lăn | TAXIWAY | Tuyến cho máy bay di chuyển trên mặt đất giữa các khu | Trực thuộc sân bay | M01; M11 vị trí kỹ thuật chung | 0 | B: mô tả khu bay, chưa điều hành di chuyển |
| 28 | Đường nối vị trí đỗ | TAXILANE | Phần đường tiếp cận các vị trí đỗ trên sân đỗ | Sân đỗ nếu đã xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: mức chi tiết cao, chưa có routing |
| 29 | Sân đỗ | APRON | Vùng phục vụ/đỗ máy bay chứa các vị trí đỗ | Sân bay hoặc khu vực đã xác nhận | M01; parent của STAND; M11 vị trí | 0 | A: cần cho cấu trúc vị trí đỗ |
| 30 | Vị trí đỗ máy bay | STAND | Một vị trí phục vụ/đỗ cụ thể của máy bay | Sân bay hoặc sân đỗ đã xác nhận | M01/M18/M03; M11 vị trí | 200 | A: resource có phân bổ thật |
| 31 | Khu chờ khu bay | HOLDING_AREA | Vùng chờ máy bay trong mô tả khu bay | Sân bay/sân đỗ/khu vực đã xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: chưa đủ đặc tả holding/clearance |
| 32 | Khu hạn chế khai thác | RESTRICTED_AREA | Một vùng vật lý có tính chất hạn chế được khai báo | Sân bay/sân đỗ/khu vực đã xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: dễ nhầm với trạng thái hạn chế của hạng mục khác |
| 33 | Nhà chứa máy bay | HANGAR | Công trình che chứa máy bay, có thể phục vụ bảo dưỡng | Sân bay/khu vực được xác nhận | M01; M11 vị trí | 0 | B: khác khu bảo dưỡng và work order |
| 34 | Khu bảo dưỡng | MAINTENANCE_AREA | Vùng phục vụ hoạt động bảo dưỡng | Sân bay/khu vực được xác nhận | M01; M11 vị trí | 0 | B: vị trí, không phải quy trình bảo dưỡng |
| 35 | Khu tiếp nhiên liệu | FUEL_AREA | Vùng/công trình hỗ trợ nhiên liệu | Sân bay/khu vực được xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: chưa có nghiệp vụ/năng lực/an toàn nhiên liệu |
| 36 | Trạm cứu nạn và cứu hỏa | FIRE_STATION | Công trình bố trí lực lượng/phương tiện ứng cứu | Sân bay/khu vực được xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: ngoài luồng vận hành ứng cứu hiện tại |
| 37 | Khu vận hành phương tiện mặt đất | GROUND_VEHICLE_AREA | Khu đỗ/chờ/phục vụ phương tiện mặt đất | Sân bay/khu vực được xác nhận | M01/M11 vị trí | 0 | B: dùng được cho liên kết thiết bị M11 |
| 38 | Kho hàng hóa | CARGO_WAREHOUSE | Công trình lưu giữ hàng hóa | Sân bay/nhà ga hàng hóa/khu vực | M01; M11 vị trí | 0 | B nếu mở rộng cargo; chưa có quản lý kho |
| 39 | Bãi đỗ phương tiện | VEHICLE_PARKING | Bãi đỗ ô tô/phương tiện nói chung | Sân bay/khu vực được xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: chưa có nghiệp vụ bãi xe |
| 40 | Khu đón trả khách | DROPOFF_AREA | Khu tiếp cận mặt đất để đưa/đón hành khách | Sân bay/khu vực được xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: chưa quản lý giao thông đón trả |
| 41 | Hạ tầng tiếp cận | ACCESS_AREA | Vùng tiếp cận sân bay/công trình chưa phân loại chi tiết | Sân bay/khu vực được xác nhận | M01; M11 vị trí kỹ thuật chung | 0 | C: tên/phạm vi quá rộng, cần BA định nghĩa |

## 3. Giải thích chi tiết từng loại

### 01. TERMINAL — Nhà ga chưa phân loại

Đây là loại tương thích dữ liệu cũ, không phải một “khu vực nhà ga” nhỏ. Có 44 hồ sơ trong nguồn `airport_resources`, tất cả được liên kết tới identity chuẩn mà không đổi ID M18. Tên loại cố ý cho biết chưa xác định nhà ga hành khách hay hàng hóa. Không đủ căn cứ để biến T1 thành PASSENGER_TERMINAL chỉ vì mã hoặc tên quen thuộc. M01 quản lý hồ sơ; UI phân lịch mới không cho chọn TERMINAL, dù API generic cũ vẫn giữ hành vi rộng hơn. Đề xuất A chuyển tiếp: giữ toàn bộ hồ sơ, sau này có quy trình phân loại lại được duyệt và bảo toàn lịch sử, không tạo thêm nhà ga mới trùng vật lý.

### 02. PASSENGER_TERMINAL — Nhà ga hành khách

Là công trình phục vụ hành khách, chứa tầng, sảnh, khu làm thủ tục, khu chờ hoặc nhận hành lý khi quan hệ được khai báo. Ví dụ một nhà ga có sảnh đi ở tầng trên và sảnh đến ở tầng dưới; đó vẫn là một nhà ga, không phải hai nhà ga theo luồng. Liên quan chuyến bay thông qua cổng/quầy nằm trong nó, nhưng chưa có FK chuyến–nhà ga hoặc lịch nhà ga riêng. Backend/Frontend hiện là quản lý danh mục và quan hệ; chưa tính công suất toàn nhà ga. Đề xuất A vì giúp định vị các resource; gần TERMINAL về bản chất vật lý, khác ở mức thông tin phân loại đã biết.

### 03. CARGO_TERMINAL — Nhà ga hàng hóa

Là công trình phục vụ luồng hàng hóa, không phải kho hành lý ký gửi của hành khách. Có thể chứa khu hoặc kho hàng nếu thực tế xác nhận, nhưng không được mặc định mọi CARGO_WAREHOUSE đều ở trong một nhà ga hàng hóa. Liên hệ chuyến chở hàng là tiềm năng nghiệp vụ, chưa có workflow cargo của Airline Smart trong lượt này. Không xếp lịch kiểu cổng/quầy. M01 có form chung phù hợp công trình, parent để trống ở cấp sân bay; chưa có quản lý hàng tồn hay thủ tục cargo. Đề xuất B: giữ khả năng mô hình hóa, có thể chưa ưu tiên nhập dữ liệu/UI nếu đồ án chỉ quản lý hành khách.

### 04. LEVEL — Tầng

Là cấp không gian của công trình, không phải thiết bị hoặc tài nguyên được phân bổ. Ví dụ tầng 2 thuộc nhà ga hành khách đã có, chứa một sảnh khởi hành. Mô hình không bắt buộc phải tạo tầng nếu chỉ biết cổng thuộc nhà ga; như vậy tránh “tầng giả” để vượt validation. Khi có parent, LEVEL chỉ nhận nhà ga; hiện có quản lý hồ sơ/quan hệ, chưa có bản đồ mặt bằng. Không có lịch M18 hay tác động đến chuyến. Đề xuất B: hữu ích cho sân bay nhiều tầng, có thể bỏ qua cấp này trong nhập liệu đơn giản mà không xóa loại hoặc gộp nó với nhà ga/sảnh.

### 05. DEPARTURE_HALL — Sảnh khởi hành

Là một không gian lớn phục vụ khách đi bên trong nhà ga hoặc tầng đã biết. Có thể chứa nhiều khu chức năng; không đồng nghĩa một quầy làm thủ tục hoặc một cổng. Ví dụ sảnh khởi hành chứa khu quầy C01–C10, nhưng phải nhập quan hệ thật mới được thể hiện. Liên quan luồng khách đi về mặt vị trí; chưa có workflow hành khách đi qua sảnh, đếm người hoặc giới hạn tải của sảnh. M01 quản lý danh mục, không phân lịch toàn sảnh trong M18. Đề xuất A trong cấu trúc hành khách; tên hiện tại rõ, cần phân biệt với CHECKIN_AREA vì một sảnh có thể chứa nhiều khu khác nhau.

### 06. ARRIVAL_HALL — Sảnh đến

Là không gian phục vụ hành khách đến; có thể liên quan khu nhận hành lý và cửa ra sảnh. Không phải “cổng phục vụ máy bay đến”: cổng nằm gần luồng tiếp cận máy bay, còn sảnh là một không gian lớn trong nhà ga. Hiện M01 lưu quan hệ nhà ga/tầng, M10 có thể hiển thị tên sảnh trong đường dẫn vị trí của băng chuyền; chưa có luồng điều phối chuyến đến theo sảnh. Không có lịch riêng hoặc tự gán hành khách. Đề xuất A để mô hình đi/đến rõ ràng; nếu UI cần ngắn hơn có thể đặt dưới nhóm “Sảnh” nhưng vẫn giữ hai mã dữ liệu khi BA quyết định.

### 07. CHECKIN_AREA — Khu vực làm thủ tục

Là khu vực tập hợp quầy hoặc điểm làm thủ tục, không phải từng quầy vật lý. Ví dụ khu check-in phía A chứa C01 và C02, hai quầy vẫn có resource_id riêng để M18 phân bổ. M01 có thể khai báo quan hệ COUNTER/BAG_DROP tới khu này; M09 hiện không tự chọn quầy theo khu và không thay đổi quy trình check-in vì tạo một khu mới. Không xếp lịch cả khu bằng logic COUNTER. Đề xuất A: cấp trung gian này làm vị trí dễ hiểu hơn, nhưng chỉ tạo khi thực tế có dữ liệu; không tự suy “C” trong C01 là tên khu hoặc nhà ga.

### 08. SECURITY_AREA — Khu vực an ninh

Là một khu vực phục vụ kiểm tra an ninh trong mô hình không gian. Nó không phải một máy soi, một nhân viên an ninh hay kết quả kiểm tra của hành khách. Ví dụ một khu an ninh thuộc sảnh khởi hành; M01 chỉ lưu vị trí và condition khai báo. Backend chưa có hàng đợi, cửa kiểm soát, xác nhận người đã qua an ninh hoặc quy tắc năng lực theo khu. Không có lịch M18. Đề xuất B cho sơ đồ hành trình sân bay; chưa cần mở rộng thành phân hệ an ninh. Nếu cần quản lý từng thiết bị trong khu, phải thiết kế asset riêng và quy trình phù hợp, không nhân danh mục khu này.

### 09. IMMIGRATION_AREA — Khu vực xuất nhập cảnh

Là vùng thực hiện kiểm soát xuất/nhập cảnh khi áp dụng, phân biệt với kiểm tra an ninh và hải quan. Ví dụ khu kiểm soát hộ chiếu trong luồng quốc tế; đây là ví dụ thuật ngữ, không xác nhận quan hệ của bất kỳ sân bay nào trong DB. M01 mới lưu danh mục, chưa có actor/cơ quan xử lý, hồ sơ hộ chiếu, quyền truy cập hay workflow xuất nhập cảnh. Không tham gia phân bổ chuyến trong M18 và không làm điều kiện boarding. Đề xuất C trong phạm vi đồ án hiện tại: giữ mã để BA quyết định, chưa nên quảng bá là chức năng quản lý xuất nhập cảnh đã hoạt động.

### 10. CUSTOMS_AREA — Khu vực hải quan

Là vùng phục vụ thủ tục hải quan, không đồng nhất với xuất nhập cảnh hoặc khu nhận hành lý. Một hành khách có thể nhận hành lý tại CLAIM_AREA và đi qua vùng hải quan theo bố trí thực tế; hai khái niệm không vì vậy thành một đối tượng. Hiện không có quản lý khai báo, kiểm tra hàng hóa, thuế hay quyết định thông quan. M01 chỉ có hồ sơ/quan hệ, M10 không tự xử lý hành lý qua hải quan, M18 không phân lịch. Đề xuất C vì chưa có workflow và quyền chuyên trách. Nếu giữ trong UI, nên mô tả rõ “Danh mục khu vực” để người dùng không hiểu thành nghiệp vụ hải quan.

### 11. WAITING_AREA — Khu vực chờ

Là không gian hành khách chờ, có thể phục vụ một hoặc nhiều cổng. Không phải cổng GATE và không phải khu chờ máy bay ở khu bay. Ví dụ khu ghế chờ trước một nhóm cổng, được khai báo như vị trí cha của GATE khi quan hệ đã được xác nhận. M01 hiện chỉ quản lý hồ sơ, không đếm hành khách hoặc xác định quá tải của khu chờ. Chưa có resource allocation cho ghế/khu vực. Đề xuất B: hữu ích để trình bày vị trí và mở rộng trải nghiệm hành khách, nhưng không cần cho thuật toán phân bổ cổng đang có. Có thể đổi nhãn đề xuất thành “Khu chờ hành khách” để phân biệt HOLDING_AREA.

### 12. CLAIM_AREA — Khu nhận hành lý

Là khu vực hành khách đến nhận hành lý, có thể chứa nhiều băng chuyền. Ví dụ một khu nhận hành lý gồm B01 và B02: khu là facility, từng băng chuyền là resource danh mục. Hiện M10 tra cứu trực tiếp khu này và các băng chuyền liên quan qua parent đã nhập; chưa tự gán chuyến đến, hành lý hoặc thời gian phục vụ. M01 quản lý khu và condition, không phân lịch cả khu. Đề xuất A vì đây là cấp vị trí cần thiết để tránh nhập mọi băng chuyền trực tiếp dưới nhà ga. Không gộp CLAIM_AREA với ARRIVAL_BELT; một bên là không gian, một bên là thiết bị/tài nguyên.

### 13. ZONE — Khu vực chưa phân loại

Là loại tổng quát để biểu diễn một vùng chưa xác định chức năng chi tiết, có nguồn hỗ trợ trong code cũ nhưng hiện 0 hồ sơ. Ví dụ một khu mang tên do đơn vị quản lý cung cấp mà chưa biết đó là check-in hay khu chờ. M01 có quan hệ cha/con, song loại này dễ bị lạm dụng để nhập mọi thứ vào một danh mục không rõ nghĩa. Không có lịch hay tác động vận hành riêng. Đề xuất C chuyển tiếp: giữ để tương thích, hướng dẫn chọn loại chuyên biệt khi đã biết. Không tự đổi ZONE thành một loại khác hoặc dùng nó làm parent giả; phân loại lại cần BA và lịch sử dữ liệu.

### 14. GATE — Cổng tiếp cận máy bay

Là một cổng/điểm tiếp cận vật lý, có hai thuộc tính nullable `serves_departures` và `serves_arrivals`. Một cổng phục vụ cả đi và đến vẫn chỉ có một identity. ARRIVAL_EXIT là cửa ra sảnh khác chức năng, còn JET_BRIDGE là hạng mục phục vụ có thể liên quan nhưng chưa có quan hệ gate–bridge chuyên biệt. Hiện có 200 cổng, form capability và liên kết stand; M18 phân bổ bằng ID cũ, giữ overlap/window/audit và cập nhật gate chuyến theo quy tắc sẵn có. M03 đọc allocation; M09 dùng gate chuyến hiện hữu, không có danh mục cổng thứ hai. Đề xuất A. Không tự suy capability từ mã hoặc biến capability thành hai cổng.

### 15. ARRIVAL_EXIT — Cửa ra sảnh đến

Là một điểm/cửa trong luồng đến dẫn ra sảnh, không phải vị trí máy bay và cũng không phải chính ARRIVAL_HALL. Ví dụ khách đi từ khu nhận hành lý qua cửa ra sảnh; vị trí cụ thể phải khai báo dựa trên dữ liệu thực. Hiện chỉ có danh mục/parent và form M01, chưa có gán chuyến, điều tiết cửa hoặc theo dõi khách qua cửa. Không dùng lịch GATE cho loại này. Đề xuất B nếu đồ án muốn mô tả luồng đến rõ hơn. Tên hiện tại tương đối dễ hiểu; UI có thể xếp cùng nhóm “Cổng và cửa” nhưng database vẫn nên phân biệt chức năng nếu được giữ.

### 16. COUNTER — Quầy làm thủ tục

Là từng quầy vật lý phục vụ check-in, khác CHECKIN_AREA là khu chứa nhiều quầy. Có 200 hồ sơ và luồng M18 phân bổ thật, dùng cửa sổ phải bao giờ khởi hành theo code cũ, không phải một mô hình công suất quầy mới. M03 có thể hiển thị allocation liên quan; M09 hiện không tự chọn quầy hoặc xác nhận năng lực từ hồ sơ M01. M01 quản lý vị trí/condition, sửa qua duyệt độc lập; ngừng hiệu lực còn bị chặn khi có phân bổ chưa kết thúc. Đề xuất A. BAG_DROP chỉ là loại riêng khi có điểm vật lý riêng, tránh nhân đôi một quầy vừa check-in vừa nhận hành lý.

### 17. BAG_DROP — Quầy gửi hành lý

Là điểm tiếp nhận hành lý ký gửi có thể tách khỏi quầy check-in, ví dụ điểm phục vụ khách đã làm thủ tục trước đó. Không phải băng chuyền phía sau hoặc một chiếc cân. Backend/Frontend hiện có danh mục và vị trí, chưa có workflow giao hành lý tại bag drop, đo capacity hoặc phân lịch loại này. M10 không tự chọn BAG_DROP cho kiện hành lý. Đề xuất B: hữu ích khi phạm vi có self-service hoặc phân tách điểm nhận hành lý. Nếu thực tế cùng một quầy COUNTER, cần BA quyết định mô hình capability/điểm phục vụ chung, không tạo bản ghi thứ hai chỉ để đổi tên chức năng.

### 18. BUS_BOARDING_POINT — Điểm lên xe buýt sân đỗ

Là vị trí hành khách tập trung/lên xe buýt để đi ra sân đỗ, không phải xe buýt hoặc vị trí đỗ máy bay. Ví dụ một điểm lên xe trong luồng cổng xa; phương tiện vẫn thuộc danh mục thiết bị/phương tiện M11. Hiện M01 có hồ sơ/vị trí, chưa có lịch xe, gán xe–chuyến–điểm, năng lực vận chuyển hay luồng kiểm tra boarding theo điểm. M18 chưa xếp lịch loại này. Đề xuất B cho mô phỏng phục vụ cổng xa. Có thể nhóm chung với GATE ở UI về “điểm phục vụ hành khách”, nhưng không tự gộp identity hay áp dụng thuật toán phân bổ cổng.

### 19. JET_BRIDGE — Cầu ống lồng

Là hạng mục/thiết bị vận hành phục vụ kết nối hành khách giữa công trình nhà ga và máy bay. Trong model hiện tại nó thuộc category asset, có `asset_reference`, khác GATE là điểm phục vụ và STAND là vị trí máy bay. M01 có form riêng cho tham chiếu và condition; chưa có capability kỹ thuật, lịch bảo trì, vận hành cầu hoặc quan hệ gate–bridge/stand–bridge được đặc tả. M11 mới tham chiếu vị trí chung, không quản lý cầu như thiết bị được phân công. Đề xuất B: giữ tách về bản chất dữ liệu nếu quản lý vòng đời thiết bị; nếu chỉ cần hiển thị cổng có cầu thì BA có thể cân nhắc mô hình thuộc tính ở giai đoạn sau.

### 20. BELT — Băng chuyền chưa phân loại

Là loại nguồn tổng quát đã được backend cũ hỗ trợ nhưng hiện không có hồ sơ thực. Không nên tự hiểu nó là băng chuyền đến; hướng xử lý còn NULL. M01 có belt profile, M10 tra cứu được, nhưng chưa có lịch hay gán chuyến. Đề xuất C chuyển tiếp: khi nhập mới và đã biết chức năng nên ưu tiên ARRIVAL_BELT/DEPARTURE_BELT; giữ BELT cho nguồn dữ liệu chưa đủ rõ. Nếu một thiết bị có chức năng thay đổi theo thời gian hoặc hai chiều, hiện mô hình chưa có capability hai chiều cho belt; BA cần chốt trước khi chọn loại, không tạo hai bản ghi vật lý trùng nhau để né hạn chế.

### 21. ARRIVAL_BELT — Băng chuyền hành lý đến

Là tài nguyên/thiết bị đưa hành lý đến cho hành khách nhận, khác khu nhận hành lý và khác hệ thống xử lý hành lý đi. Ví dụ B01 nằm trong một CLAIM_AREA đã nhập. Hồ sơ belt xác định flow ARRIVAL từ loại chọn; M10 chỉ tra cứu cùng sân bay và đường dẫn khu vực thực, chưa liên kết flight–belt hoặc phân bổ theo thời gian. Không được hiểu trạng thái “Bình thường” là đang trống để nhận chuyến khác. Đề xuất A trong danh mục hành lý, nhưng workflow phục vụ chuyến cần BA duyệt riêng. Băng chuyền và khu vực nhận phải tách dữ liệu để nhiều thiết bị cùng thuộc một khu.

### 22. DEPARTURE_BELT — Băng chuyền hành lý đi

Là thiết bị/tuyến xử lý hành lý theo luồng đi, không phải nơi hành khách đến lấy hành lý. Có thể liên quan khu làm thủ tục, phân loại hoặc tập kết khi đã biết vị trí. M01 lưu flow DEPARTURE; M10 tra cứu nhưng chưa có định tuyến kiện, năng lực tuyến, phân bổ chuyến hoặc time window. Đề xuất A nếu đồ án mô tả cả vòng đời hành lý đi và đến; chỉ có dữ liệu danh mục thì không gọi là “quản lý hệ thống BHS” hoàn chỉnh. Không tự áp dụng lịch GATE/COUNTER cho belt. Cần giữ khác ARRIVAL_BELT về chức năng, đồng thời có chính sách một identity nếu thiết bị thực được xác nhận dùng chung.

### 23. BAG_SORTING_AREA — Khu phân loại hành lý

Là không gian phục vụ phân loại hành lý theo thông tin hành trình/chuyến, không đồng nhất một băng chuyền hoặc thiết bị phân loại. Ví dụ một khu hậu trường chứa nhiều tuyến/điểm xử lý; hiện dữ liệu chỉ ghi khu, không mô hình máy phân loại hoặc thuật toán định tuyến. M10 tra cứu được, M01 quản lý hồ sơ/parent/condition. Chưa có phân lịch cả khu, đo năng lực hoặc điều phối hành lý tự động. Đề xuất B vì giúp mô tả hậu trường sân bay; nếu đồ án chỉ cần tra cứu băng chuyền đến thì có thể chưa ưu tiên UI. Gần BAG_MAKEUP_AREA về vị trí nhưng khác mục đích xử lý và tập kết.

### 24. BAG_MAKEUP_AREA — Khu tập kết hành lý

Là không gian tập kết/chuẩn bị hành lý trước một bước vận chuyển/phục vụ chuyến, khác khu phân loại theo chức năng. Ví dụ hành lý đã được phân loại được tập hợp tại khu phục vụ, nhưng ví dụ không hàm ý hệ thống đã theo dõi được vị trí của từng kiện ở đó. M01 có hồ sơ và M10 có tra cứu; chưa có gán vị trí tập kết cho chuyến, gán xe/ULD, thời gian sẵn sàng hoặc kiểm tra năng lực. Đề xuất B nếu mở rộng phục vụ hành lý mặt đất. Có thể nhóm cùng BAG_SORTING_AREA dưới “Khu xử lý hành lý” ở UI nhưng giữ mã riêng nếu thực tế có hai vùng độc lập.

### 25. OVERSIZE_BAG_POINT — Vị trí hành lý quá khổ

Là điểm phục vụ hành lý kích thước đặc biệt/quá khổ, không mặc định là một quầy, băng chuyền hay thiết bị cụ thể. Ví dụ điểm tiếp nhận hoặc bàn giao vật dụng lớn theo quy trình thực tế của đơn vị; chiều đi/đến và giới hạn tiếp nhận chưa có trong model chuyên biệt. M01 lưu hồ sơ, M10 tra cứu, không tự xác định kiện quá khổ hoặc gán điểm xử lý. Chưa có lịch M18, phí, năng lực hoặc luồng xử lý đặc biệt riêng. Đề xuất B khi Use Case hành lý đặc biệt được duyệt; có thể dùng tên dễ hiểu hơn “Điểm phục vụ hành lý quá khổ”, nhưng chưa đổi nhãn/mã thực trong lượt này.

### 26. RUNWAY — Đường băng vật lý

Là công trình vật lý dùng cho cất/hạ cánh; không phải mỗi hướng khai thác là một đường băng khác. Profile hiện có kích thước/giới hạn nullable và `runway_directions` con; ví dụ một hồ sơ có các định danh hướng đã xác nhận, không tự sinh hướng đối ứng. M01 quản lý danh mục/condition, chưa có flight–runway assignment, lịch ATC hoặc cấp phép. Do Airline Smart hiện không phải hệ thống điều hành không lưu, đề xuất B dù đường băng rất quan trọng trong sân bay thực. Không bắt buộc thuộc nhà ga; form không có lựa chọn parent nhà ga. Các số đo/giới hạn do người dùng nhập chưa trở thành kiểm tra an toàn hay tương thích chuyến bay.

### 27. TAXIWAY — Đường lăn

Là tuyến cho máy bay di chuyển trên mặt đất giữa các phần của khu bay, khác đường băng dùng cất/hạ cánh. Trong model này TAXIWAY trực thuộc sân bay, không tạo parent nhà ga. Hiện M01 mới có mã/tên/condition/mô tả, chưa có mạng đoạn đường, giao điểm, lộ trình taxi, giới hạn kỹ thuật hoặc phát hiện xung đột di chuyển. Không có lịch M18 hoặc FK chuyến–taxiway. Đề xuất B để mô tả danh mục khu bay; nếu không làm bản đồ mô phỏng thì không cần ưu tiên nhập nhiều tuyến. TAXILANE là loại hẹp hơn phục vụ tiếp cận stand, không nên gọi cả hai bằng cùng nhãn “đường nối” thiếu ngữ cảnh.

### 28. TAXILANE — Đường nối vị trí đỗ

Là phần đường tiếp cận các vị trí đỗ trên sân đỗ, có chức năng hẹp hơn đường lăn tổng quát. Ví dụ một lối tiếp cận dãy stand, khi có dữ liệu có thể gắn APRON. Backend hiện không lưu mạng liên thông hoặc thứ tự di chuyển; form chỉ là danh mục và parent phù hợp. M03/M18 chưa sử dụng để định tuyến hoặc phân bổ, M11 tham chiếu vị trí chung không phải điều khiển phương tiện/máy bay. Đề xuất C trong đồ án hiện tại: mức chi tiết này chỉ có giá trị khi mở rộng sơ đồ khu bay. Tên đề xuất dễ hiểu hơn là “Đường lăn tiếp cận vị trí đỗ”; chưa đổi tên thực hoặc gộp với TAXIWAY.

### 29. APRON — Sân đỗ

Là một vùng phục vụ/đỗ máy bay, có thể chứa nhiều vị trí đỗ. Ví dụ sân đỗ A chứa P01/P02 khi đơn vị đã xác nhận quan hệ; không suy từ chữ A/P trong mã. APRON là facility, còn STAND là resource cụ thể được M18 phân bổ. M01 có danh mục và quan hệ; M18 chưa phân lịch cả sân đỗ hoặc tự tổng hợp năng lực từ số stand. Đề xuất A vì 200 stand hiện vẫn chưa có parent; đây là cấu trúc cần nhập bằng dữ liệu thật để người dùng hiểu vị trí. Việc thiếu parent không biến stand thành dữ liệu lỗi và không cho phép tạo sân đỗ giả để làm đầy UI.

### 30. STAND — Vị trí đỗ máy bay

Là một vị trí phục vụ/đỗ cụ thể, khác toàn bộ sân đỗ. Có 200 hồ sơ, resource_id và allocation thật được bảo toàn. Parent nếu khai báo chỉ là APRON cùng sân bay; không bắt buộc một nhà ga. M01 có ghi chú tương thích và gate–stand link do người dùng xác nhận; các ghi chú/link chưa tự chặn hoặc gán chuyến. M18 phân bổ theo quy tắc hiện có, M03 đọc lịch đó, không có engine tối ưu stand mới. Đề xuất A. Một stand và một gate liên quan là hai đối tượng khác chức năng, nên liên kết thay vì gộp ID hoặc nhân một stand thành nhiều bản ghi theo cổng.

### 31. HOLDING_AREA — Khu chờ khu bay

Là vùng chờ của máy bay trong mô tả khu bay, khác WAITING_AREA dành cho hành khách. Thuật ngữ hiện còn rộng: chưa chốt là holding bay, điểm chờ hay loại khu vực mô phỏng nào. M01 chỉ lưu danh mục/condition/parent được xác nhận; không có clearance, hàng đợi máy bay, lệnh chờ hay lịch khai thác khu này. Đề xuất C vì nếu hiển thị không giải thích sẽ dễ bị hiểu thành chức năng điều hành bay đã tồn tại. Cần BA chốt nghĩa và tên trước khi thu thập dữ liệu. Không dùng tạo một điểm chờ giả hoặc gắn vào chuyến để mô phỏng điều hành không có đặc tả.

### 32. RESTRICTED_AREA — Khu hạn chế khai thác

Là loại của một vùng vật lý được khai báo với mục đích hạn chế, không phải condition RESTRICTED của bất kỳ hạng mục nào. Ví dụ một vùng trong sơ đồ có phạm vi hạn chế; giới hạn không gian, lý do, thời gian và quyền ra vào chưa được mô hình hóa chuyên biệt. M01 có hồ sơ chung; chưa có geofence, kiểm soát truy cập hoặc tự ảnh hưởng đến chuyến/stand lân cận. Đề xuất C: dễ nhầm giữa “loại vùng” và “trạng thái tạm thời”. BA cần quyết định đây là đối tượng ổn định hay nên dùng hồ sơ hạn chế có hiệu lực theo thời gian trong tương lai; không gộp hoặc chuyển dữ liệu ngay.

### 33. HANGAR — Nhà chứa máy bay

Là công trình che chứa máy bay; có thể được dùng cho bảo dưỡng nhưng không đồng nghĩa mọi khu bảo dưỡng đều là hangar. Ví dụ một nhà chứa có nhiều vị trí làm việc, song model hiện chưa có bay/slot bên trong hoặc lịch sử dụng theo tàu bay. M01 chỉ quản lý công trình và condition; quy trình work order/bảo dưỡng máy bay của phân hệ khác không tự được liên kết vào hangar. Không dùng phân bổ STAND thay cho lịch hangar. Đề xuất B nếu đồ án mô tả căn cứ bảo dưỡng; giữ khác MAINTENANCE_AREA về bản chất công trình/vùng. Tên đề xuất “Nhà chứa/bảo dưỡng máy bay” cần BA cân nhắc để không hạn chế mục đích sử dụng sai.

### 34. MAINTENANCE_AREA — Khu bảo dưỡng

Là một vùng phục vụ hoạt động bảo dưỡng, có thể khác công trình hangar và không phải trạng thái MAINTENANCE. Ví dụ một khu làm việc kỹ thuật tại sân bay; không tự kết luận toàn khu đang bảo trì chỉ vì type có chữ maintenance. M01 quản lý vị trí/condition, M11 có thể ghi nhận vị trí thiết bị; chưa gắn work order, đội kỹ thuật, vật tư, lịch bảo dưỡng công trình hoặc máy bay vào khu này. Đề xuất B: vị trí hữu ích nhưng không phải phân hệ bảo dưỡng hoàn chỉnh. Nếu muốn đặt khu bên trong hangar, cần BA bổ sung ma trận quan hệ cụ thể; không tạo link ngoài validation hiện tại để minh họa.

### 35. FUEL_AREA — Khu tiếp nhiên liệu

Là một vùng/công trình hỗ trợ nhiên liệu theo danh mục, không mặc định là xe tiếp nhiên liệu hoặc một nhiệm vụ tiếp nhiên liệu. Tên hiện có thể chưa phân biệt kho chứa, trạm cấp và điểm tiếp nhiên liệu; model chưa có loại nhiên liệu, sức chứa, tồn kho, an toàn hoặc quy trình cung cấp. M01 chỉ quản lý hồ sơ chung; M11 chưa tự gán thiết bị/nhân viên hoặc kiểm tra khả năng phục vụ tại đây. Không có lịch M18. Đề xuất C cho phạm vi hiện tại vì yêu cầu chuyên môn và an toàn riêng chưa được xác nhận. Nếu giữ, cần BA định nghĩa rõ ý nghĩa trước khi đưa dữ liệu vào vận hành.

### 36. FIRE_STATION — Trạm cứu nạn và cứu hỏa

Là công trình bố trí phục vụ lực lượng/phương tiện ứng cứu, khác một xe cứu hỏa hoặc một vụ việc khẩn cấp. Ví dụ một trạm có phương tiện thuộc danh mục M11, nhưng liên hệ đó chưa được triển khai thành quy trình ứng cứu. M01 hiện có form công trình/condition; không quản lý mức đáp ứng, điều động, SLA cứu nạn hoặc chứng nhận. Không tham gia phân bổ chuyến trong M18 và không tự đóng sân bay khi condition thay đổi. Đề xuất C vì đồ án chưa có nghiệp vụ khẩn cấp chuyên biệt. Có thể giữ danh mục để mở rộng sau, nhưng UI không nên khiến người dùng hiểu là hệ thống bảo đảm khả năng cứu nạn.

### 37. GROUND_VEHICLE_AREA — Khu vận hành phương tiện mặt đất

Là vùng đỗ/chờ/phục vụ phương tiện mặt đất, không phải bản thân phương tiện hoặc một stand máy bay. Ví dụ khu tập trung thiết bị phục vụ, nơi một ground_equipment đang có được khai báo vị trí. M01 quản lý khu; M11 có optional FK vị trí cùng sân bay và audit, giữ nguyên status và bảng phân công thiết bị. Chưa có theo dõi GPS, năng lực khu, ca trực hoặc tự điều phối phương tiện. Đề xuất B vì đã có liên kết vị trí tối thiểu phù hợp. Gần VEHICLE_PARKING về hình thức nhưng cần phân biệt khu phương tiện phục vụ khai thác với bãi xe nói chung; chưa tự gộp hai loại.

### 38. CARGO_WAREHOUSE — Kho hàng hóa

Là công trình lưu giữ hàng hóa, khác nhà ga hàng hóa là một công trình/phạm vi phục vụ rộng hơn. Ví dụ kho thuộc CARGO_TERMINAL khi có quan hệ thực, hoặc trực thuộc sân bay nếu không biết nhà ga; không tạo nhà ga cha giả. Hiện M01 có hồ sơ/parent/condition, chưa có tồn kho, vị trí lưu kiện, chứng từ giao nhận, hàng nguy hiểm hoặc liên kết lô hàng–chuyến. M11 có thể tham chiếu vị trí chung, M10 không mặc định coi kho cargo là kho hành lý hành khách. Đề xuất B nếu mở rộng cargo, chưa cần ưu tiên UI nếu đồ án chỉ theo dõi hành lý ký gửi của hành khách.

### 39. VEHICLE_PARKING — Bãi đỗ phương tiện

Là vùng đỗ phương tiện nói chung, không phải vị trí đỗ máy bay. Ví dụ một bãi ô tô được quản lý trong danh mục sân bay; hiện chưa có vé bãi xe, chỗ đỗ từng xe, thu phí hoặc kiểm soát ra vào. M01 chỉ lưu khu; M11 cho tham chiếu vị trí kỹ thuật chung nhưng chưa có quy tắc phân biệt phương tiện phục vụ với xe khách. Không có lịch M18. Đề xuất C vì không trực tiếp phục vụ quản lý chuyến/hành khách/vé/hành lý trong source hiện tại. Có thể đề xuất tên “Bãi đỗ phương tiện đường bộ” để tránh nhầm STAND; chưa thay đổi tên thật trong lượt này.

### 40. DROPOFF_AREA — Khu đón trả khách

Là vùng tiếp cận mặt đất phục vụ đưa/đón hành khách, khác sảnh đến, cửa ra sảnh và điểm lên xe buýt sân đỗ. Ví dụ vùng phía ngoài nhà ga theo bố trí thực; model không tự suy rằng nó thuộc một sảnh hoặc cho xe bất kỳ tiếp cận khu bay. M01 có danh mục, chưa có quản lý làn xe, thời gian dừng, điều tiết giao thông hoặc dịch vụ vận chuyển hành khách. Không được M18 phân lịch và không làm điều kiện hành trình trong M03/M09. Đề xuất C cho đồ án hiện tại, có thể giữ trong nhóm phụ trợ nhưng chưa ưu tiên thu thập dữ liệu khi chưa có workflow sử dụng.

### 41. ACCESS_AREA — Hạ tầng tiếp cận

Là loại vùng/công trình tiếp cận có định nghĩa còn tổng quát trong danh mục đề xuất. Có thể bị dùng cho nhiều đối tượng khác nhau như khu tiếp cận công trình, nhưng không nên tự hiểu là một con đường, cổng kiểm soát hoặc phương tiện. Hiện chỉ có các trường danh mục chung, chưa có profile chuyên biệt, topology, kiểm soát truy cập hoặc lịch. M01 quản lý hồ sơ; M11 chỉ có khả năng tham chiếu vị trí chung. Đề xuất C vì dễ chồng chéo ZONE, DROPOFF_AREA và các loại đường nếu không chốt phạm vi. Cần BA định nghĩa đối tượng vật lý rõ hoặc quyết định ẩn UI trong một lượt được duyệt sau này.

## 4. Những cặp dễ nhầm và cách quyết định

| Cặp/nhóm | Phân biệt cần giữ | Có thể thu gọn UI thế nào (chưa thực hiện) |
|---|---|---|
| Nhà ga / khu vực nhà ga | Nhà ga là công trình; khu là một phần không gian hoặc vùng chức năng. Một nhà ga chứa nhiều khu | Một cây vị trí có thể trình bày cả hai; không gộp identity |
| Nhà ga / tầng / sảnh | Công trình, cấp tầng và không gian sảnh khác nhau. Không bắt buộc mọi bản ghi phải đủ ba cấp | Cho phép bỏ qua cấp chưa có dữ liệu, không sinh bản ghi cha giả |
| GATE đi/đến / ARRIVAL_EXIT | Hai luồng đi/đến là capability của một gate vật lý; cửa ra sảnh là chức năng khác | Một mục “Cổng và cửa”, gate có nhãn capability, không thêm hai loại gate theo luồng |
| RUNWAY / hướng khai thác | RUNWAY là identity vật lý; hướng là bản ghi runway_directions con, không thuộc 41 type | Chi tiết đường băng chứa danh sách hướng; không tạo hai dòng đường băng theo hai hướng |
| TAXIWAY / TAXILANE | Tuyến đường lăn tổng quát / lối tiếp cận stand trên sân đỗ | Nhóm “Đường lăn”, giữ phân loại con nếu BA giữ mức chi tiết |
| APRON / STAND | Vùng sân đỗ / từng vị trí máy bay trong vùng | Hiển thị cây sân đỗ→stand; chỉ stand được phân bổ theo code hiện có |
| ARRIVAL_BELT / DEPARTURE_BELT | Hai chức năng xử lý hành lý khác nhau, không tự coi chúng là cùng một thiết bị | Một mục “Băng chuyền”, bộ lọc chiều đi/đến; không gộp mã hoặc tạo bản trùng thiết bị dùng chung |
| CLAIM_AREA / ARRIVAL_BELT | Khu vực chứa thiết bị / từng thiết bị tài nguyên | Cây khu nhận hành lý→băng chuyền |
| HANGAR / MAINTENANCE_AREA | Công trình che chứa / vùng hoạt động bảo dưỡng; không đồng nhất với condition MAINTENANCE | Cùng nhóm “Hạ tầng kỹ thuật”; quan hệ trong hangar còn cần BA chốt |
| GROUND_VEHICLE_AREA / VEHICLE_PARKING | Khu phương tiện phục vụ khai thác / bãi phương tiện nói chung | Nếu BA muốn một loại, cần mô hình mục đích sử dụng và kế hoạch mapping; chưa tự gộp |
| RESTRICTED_AREA / condition RESTRICTED | Loại vùng vật lý / tình trạng của một hạng mục | UI phải dùng nhãn “Khu hạn chế” và “Hạn chế sử dụng” khác nhau |

## 5. Danh mục tối thiểu và đầy đủ đề xuất

### Danh mục tối thiểu nên ưu tiên

Giữ bốn loại đang có dữ liệu: **TERMINAL, GATE, COUNTER, STAND**; đây là điều kiện bảo toàn hiện trạng, không phải quyết định xóa các loại khác. Nếu cần cấu trúc hành khách/hành lý tối thiểu đầy đủ hơn, ưu tiên thêm **PASSENGER_TERMINAL, DEPARTURE_HALL, ARRIVAL_HALL, CHECKIN_AREA, CLAIM_AREA, APRON, ARRIVAL_BELT, DEPARTURE_BELT**. Tổng phạm vi ưu tiên A là 12 mã kể cả TERMINAL chuyển tiếp.

Không được tự phân loại lại 44 TERMINAL hoặc tạo PASSENGER_TERMINAL song song cho cùng một nhà ga. Hai loại belt thuộc danh mục ưu tiên nhưng chỉ tra cứu, chưa có phân lịch. Đường băng là hạ tầng thiết yếu ngoài đời, song không bắt buộc phải có nghiệp vụ điều phối đường băng trong đồ án quản lý hãng bay này.

### Danh mục đầy đủ phù hợp đồ án

Đề xuất dùng A+B làm phạm vi chức năng danh mục được trình bày chính: bổ sung nhà ga hàng hóa/tầng/khu an ninh/khu chờ/cửa đến/bag drop/điểm xe buýt/cầu ống lồng/khu xử lý hành lý/runway/taxiway/hangar/khu bảo dưỡng/khu phương tiện/kho cargo khi nhóm có dữ liệu và mục đích mô phỏng tương ứng. Không bắt buộc có bản ghi ở mọi sân bay.

Phương án đầy đủ này gồm **29 mã = 12 A + 17 B**. Bổ sung vào 12 mã A đã nêu: CARGO_TERMINAL, LEVEL, SECURITY_AREA, WAITING_AREA, ARRIVAL_EXIT, BAG_DROP, BUS_BOARDING_POINT, JET_BRIDGE, BAG_SORTING_AREA, BAG_MAKEUP_AREA, OVERSIZE_BAG_POINT, RUNWAY, TAXIWAY, HANGAR, MAINTENANCE_AREA, GROUND_VEHICLE_AREA, CARGO_WAREHOUSE. Phần cargo hoặc khu bay vẫn có thể không ưu tiên nhập liệu nếu nhóm chưa dùng đến.

**12 mã C** cần quyết định riêng: IMMIGRATION_AREA, CUSTOMS_AREA, ZONE, BELT, TAXILANE, HOLDING_AREA, RESTRICTED_AREA, FUEL_AREA, FIRE_STATION, VEHICLE_PARKING, DROPOFF_AREA, ACCESS_AREA. Không đánh đồng C với “được phép xóa”: ZONE/BELT là loại tương thích; các loại còn lại có thể cần cho một phạm vi mở rộng được duyệt sau này.

Nhóm C nên được BA cân nhắc để trong chế độ “Danh mục mở rộng/loại nguồn cũ”, thay vì xuất hiện nổi bật khi nhập mới. **Lượt này vẫn giữ toàn bộ 41 lựa chọn**, chỉ thêm nhóm và tìm kiếm loại. Chưa ẩn bất kỳ loại nào: người dùng sẽ quyết định sau khi đọc báo cáo.

### Gộp cách hiển thị, giữ tách dữ liệu

Có thể gộp nhãn nhóm “Nhà ga và không gian”, “Cổng/điểm phục vụ”, “Băng chuyền và khu hành lý”, “Khu bay”, “Hạ tầng kỹ thuật”, “Phụ trợ” ở UI; các mã chuyên biệt vẫn tồn tại. Giữ tách database cho công trình với resource/asset, apron với stand, khu với thiết bị, runway với các hướng. Chưa tạo capability mới cho BAG_DROP/BELT hoặc đổi schema để gộp chúng với COUNTER/GATE.

## 6. Mức Backend/Frontend và việc còn thiếu

| Mức triển khai | Loại áp dụng | Đã có | Chưa có |
|---|---|---|---|
| Hồ sơ/quan hệ chung | Cả 41 | Chọn sân bay; nhóm/loại; tìm kiếm/phân trang; mã/tên; parent hợp lệ; active/condition riêng; badge tổng hợp; tạo/sửa–duyệt/audit | Bản đồ, capacity, GIS/topology hoặc tác động khai thác tự động |
| Gate profile | GATE | Hai capability nullable, link stand cùng sân bay | Quy tắc arrival, engine tương thích, gate–bridge mapping |
| Runway profile | RUNWAY | Kích thước/giới hạn khai báo, hướng con một identity | Flight assignment, lịch runway, ATC/clearance, kiểm tra an toàn |
| Belt profile/lookup | BELT/ARRIVAL_BELT/DEPARTURE_BELT | Flow chưa biết/đến/đi; M10 tra cứu cùng khu hành lý | Flight–belt, thời gian/capacity, định tuyến kiện |
| Stand profile | STAND | Ghi chú tương thích, parent APRON, link gate | Kiểm tra tương thích kỹ thuật hoặc tối ưu vị trí |
| Asset profile | JET_BRIDGE | Mã tham chiếu hạng mục | Vòng đời/bảo trì/điều khiển asset |
| Allocation thật | GATE/COUNTER/STAND | M18 allocate/unallocate, overlap, hiệu lực, audit, cập nhật gate theo code cũ; M03 đọc | Thuật toán mới cho loại khác hoặc lịch arrival |
| Vị trí thiết bị | Cả danh mục chuẩn, kỹ thuật chung | Optional FK M11 cùng sân bay | Ma trận loại thiết bị–vị trí và quy trình duyệt vị trí chi tiết |

IATA mô tả hạ tầng theo dõi hành lý và sự phối hợp giữa hãng, sân bay và đơn vị phục vụ trong [Baggage Tracking](https://www.iata.org/en/programs/ops-infra/baggage/baggage-tracking/). Việc Airline Smart có danh mục belt hoặc khu hành lý **chưa chứng minh** đáp ứng một tiêu chuẩn theo dõi hay tích hợp BHS/BRS thực tế. Các luồng hiện hữu vẫn cần được phân biệt với đề xuất mở rộng.

## 7. Quyết định đề nghị người dùng/BA

Với từng mã, đề nghị quyết định: giữ trong UI chính / giữ trong chế độ mở rộng / chỉ giữ tương thích / cần nghiên cứu gộp trong lượt sau. Đồng thời xác nhận ma trận parent, thuật ngữ HOLDING_AREA/FUEL_AREA/ACCESS_AREA, policy phân loại lại TERMINAL/ZONE/BELT, khả năng dùng chung thiết bị vật lý và loại nào được xếp lịch.

Chỉ sau quyết định đó mới thiết kế migration/alias/mapping, quy trình duyệt và kiểm thử tương ứng. Hiện **41 mã, 644 hồ sơ và mọi ID/liên kết cũ đều được giữ nguyên**; báo cáo này không thực hiện một quyết định tinh giản nào thay người dùng.
