# Tài khoản QA local/test

Hệ thống hiện được mở rộng để một User có nhiều role qua bảng `user_roles`. `admin` vẫn không phải wildcard; các role nghiệp vụ được gán tường minh và mọi kiểm tra scope/validation/độc lập vẫn được giữ.

Project cung cấp lệnh opt-in để tạo tài khoản QA bằng các role hiện có. Lệnh chỉ chạy trong `local` hoặc `testing`, không được gọi bởi `scripts/setup.sh`, không chạy trong production và không ghi mật khẩu vào repository.

## Tạo tài khoản QA chính

Trong `backend/.env`, đặt các biến local sau bằng trình soạn thảo riêng:

```dotenv
QA_ACCOUNTS_ENABLED=true
QA_ACCOUNT_EMAIL=qa@airline.test
QA_ACCOUNT_NAME="Kiểm thử toàn hệ thống"
QA_ACCOUNT_PASSWORD=<mật khẩu riêng tối thiểu 16 ký tự>
QA_ACCOUNT_ROLE=admin
```

Sau khi các container đã chạy:

```bash
cd /mnt/e/Projects/QLDAPM
docker compose exec app php artisan airline:qa-account
```

Lệnh có thể chạy lại an toàn. Nó chỉ cập nhật đúng tài khoản QA đã cấu hình; nếu email đã thuộc một role khác, lệnh dừng và không đổi role. Mật khẩu được hash bằng Laravel và không được in ra log.

## Tạo QA full-role và reviewer

Để gán toàn bộ role hiện có vào **cùng `qa@airline.test`**:

```bash
docker compose exec app php artisan airline:qa-account --all-roles
```

Nếu cần kiểm thử quy tắc người lập/người duyệt độc lập, chạy thêm tùy chọn reviewer:

```bash
docker compose exec app php artisan airline:qa-account --all-roles --reviewers
```

Tài khoản chính được lưu role chính `admin` để tương thích mã cũ, còn toàn bộ role được lưu trong `user_roles`. Các reviewer độc lập có dạng `qa.<role>.reviewer@airline.test`. Với role scoped, QA chính dùng `scope_all=true` chỉ trong local/testing; reviewer không được tự động có toàn bộ sân bay.

Ma trận role cần thiết để kiểm thử đầy đủ gồm `data_admin`, `operations`, `ops_manager`, `fleet_manager`, `maintenance`, `crew_scheduler`, `ground_agent`, `airport_agent`, `customer_service`, `finance`, `analyst`, `integration_admin`, `auditor` và `passenger`. Với `--reviewers`, lệnh tạo thêm các tài khoản reviewer ổn định cho `data_admin`, `maintenance`, `finance` và `customer_service` (`qa.<role>.reviewer@airline.test`) để các workflow yêu cầu hai User khác nhau vẫn giữ nguyên kiểm tra độc lập.

## Giới hạn

- Tài khoản QA vẫn chịu validation, state machine, scope sân bay, audit và kiểm tra độc lập.
- Không có quyền wildcard hoặc bypass local.
- `qa@airline.test` không bypass kiểm quyền; `--all-roles` chỉ gán các role đã tồn tại trong source vào tập role của User.
- Không dùng mật khẩu QA trong Git, `.env.example`, log hoặc ảnh chụp màn hình.
- Không chạy lệnh này trong production.


## V? hi?u h?a c? ch? QA

??t `QA_ACCOUNTS_ENABLED=false` trong `backend/.env`, sau ?? ch?y `docker compose exec app php artisan config:clear`. L?nh provisioning s? kh?ng t?o ho?c c?p nh?t t?i kho?n khi c? n?y t?t; kh?ng c?n x?a d? li?u ?? t?t kh? n?ng provisioning.
