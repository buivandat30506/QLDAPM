@php
    // Display labels only. Stored codes, submitted values and permissions stay unchanged.
    $uiLabels = [
        'master_status' => ['DRAFT'=>'Nháp', 'PENDING'=>'Chờ duyệt', 'APPROVED'=>'Đã duyệt', 'PUBLISHED'=>'Đã công bố'],
        'import_status' => ['READY'=>'Sẵn sàng nhập', 'INVALID'=>'Có lỗi cần sửa', 'COMMITTED'=>'Đã nhập'],
        'user_status' => ['ACTIVE'=>'Hoạt động', 'INACTIVE'=>'Ngừng hoạt động', 'SUSPENDED'=>'Tạm khóa'],
        'resource_status' => ['AVAILABLE'=>'Khả dụng', 'OCCUPIED'=>'Đang sử dụng', 'MAINTENANCE'=>'Tạm ngừng / bảo trì'],
        'resource_type' => ['GATE'=>'Cổng ra máy bay', 'STAND'=>'Vị trí đỗ'],
        'source' => ['legacy'=>'Dữ liệu cũ (legacy)', 'OPERATIONS'=>'Khai thác chuyến bay (OPERATIONS)', 'DEMO'=>'Mô phỏng (DEMO)', 'AODB'=>'Cơ sở dữ liệu khai thác sân bay (AODB)', 'FIDS'=>'Hệ thống thông tin chuyến bay (FIDS)'],
        'cabin' => ['ECONOMY'=>'Phổ thông', 'PREMIUM_ECONOMY'=>'Phổ thông đặc biệt', 'BUSINESS'=>'Thương gia', 'FIRST'=>'Hạng nhất'],
        'audit_action' => ['CREATE'=>'Tạo mới', 'CREATED'=>'Tạo mới', 'UPDATE'=>'Cập nhật', 'UPDATED'=>'Cập nhật', 'DELETE'=>'Xóa', 'DELETED'=>'Đã xóa', 'SUBMIT'=>'Gửi duyệt', 'APPROVE'=>'Phê duyệt', 'PUBLISH'=>'Công bố', 'REVISE'=>'Tạo bản sửa đổi', 'REJECT'=>'Từ chối', 'CANCEL'=>'Hủy', 'LOGIN_SUCCEEDED'=>'Đăng nhập thành công', 'LOGIN_FAILED'=>'Đăng nhập không thành công', 'LOGOUT'=>'Đăng xuất', 'ACCESS_DENIED'=>'Truy cập bị từ chối'],
        'outcome' => ['SUCCESS'=>'Thành công', 'DENIED'=>'Bị từ chối', 'FAILED'=>'Không thành công'],
        'maintenance_status' => ['PLANNED'=>'Chờ thực hiện', 'WAITING_MATERIALS'=>'Chờ vật tư', 'IN_PROGRESS'=>'Đang thực hiện', 'COMPLETED'=>'Đã ký kỹ thuật', 'APPROVED'=>'Đã phê duyệt', 'RELEASED'=>'Đã giải phóng'],
    ];
@endphp
{{ $uiLabels[$uiGroup][$uiCode] ?? $uiFallback ?? $uiCode }}
@if(($uiShowCode ?? false) && isset($uiLabels[$uiGroup][$uiCode])) ({{ $uiCode }})@endif
