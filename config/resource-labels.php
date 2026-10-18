<?php

return [
    'crew_status' => ['ASSIGNED' => 'Chờ duyệt', 'APPROVED' => 'Đã duyệt', 'CONFIRMED' => 'Đã xác nhận', 'REPLACED' => 'Đã hủy/thay thế'],
    'rank' => ['CAPTAIN' => 'Cơ trưởng', 'FIRST_OFFICER' => 'Cơ phó', 'PURSER' => 'Tiếp viên trưởng', 'FLIGHT_ATTENDANT' => 'Tiếp viên'],
    'availability' => ['AVAILABLE' => 'Sẵn sàng', 'ON_DUTY' => 'Đang làm nhiệm vụ', 'RESTING' => 'Nghỉ ngơi', 'LEAVE' => 'Nghỉ phép'],
    'flight_status' => ['SCHEDULED' => 'Đã lên lịch', 'BOARDING' => 'Đang đón khách', 'DEPARTED' => 'Đã cất cánh', 'ARRIVED' => 'Đã hạ cánh', 'DELAYED' => 'Chậm chuyến', 'CANCELLED' => 'Đã hủy'],
    'crew_event' => ['ASSIGNED' => 'Lưu phân công', 'APPROVED' => 'Phê duyệt', 'REPLACED' => 'Thay thế nhân sự', 'REMOVED' => 'Hủy phân công',
        'MEMBER_CONFIRMED' => 'Thành viên xác nhận', 'OPERATOR_RECORDED_CONFIRMATION' => 'Điều hành ghi nhận xác nhận',
        'PROFILE_INVALIDATED' => 'Yêu cầu kiểm tra lại do đổi hồ sơ', 'AVAILABILITY_INVALIDATED' => 'Yêu cầu kiểm tra lại do đổi khả dụng', 'FLIGHT_PLAN_INVALIDATED' => 'Yêu cầu kiểm tra lại do đổi kế hoạch chuyến'],
    'audit_action' => ['CREATED' => 'Tạo hồ sơ', 'UPDATED' => 'Cập nhật', 'PROFILE_CHANGE' => 'Sửa hồ sơ', 'DELETED' => 'Xóa hồ sơ'],
];
