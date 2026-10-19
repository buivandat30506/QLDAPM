# Docker local

Trạng thái: **Not Run** trên máy phiên này (không có Docker CLI/daemon). Không xem cấu hình đã viết là bằng chứng build/start thành công. Profile này chỉ dành local, chưa là cấu hình public deploy.

`compose.yaml` có MySQL/InnoDB, init, web, worker, scheduler và volume DB/storage. Images khóa tag: PHP 8.2.34-cli-bookworm, Composer 2.10.3, Node 24.16.0-bookworm-slim, MySQL 8.0.46. PHP container dùng patch mới hơn PHP 8.2.12 đã đo trên Windows; cần xác minh profile riêng. Tham khảo [Docker PHP](https://docs.docker.com/guides/php/) và [official image manifests](https://github.com/docker-library/official-images/tree/master/library); không dùng tag latest.

```powershell
php scripts/create-compose-env.php
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up --build -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker exec app php artisan app:health
docker compose --env-file .env.docker exec app php artisan access:bootstrap admin_local admin@example.test
```

Bootstrap hỏi mật khẩu qua prompt; không đưa vào command/log. ADMIN/global quản trị account/audit, cần cấp DATA_ADMIN/FLIGHT_OPS… qua UI cho nghiệp vụ. Init chỉ migrate additive và seed quyền; không migrate:fresh, không tự tạo tài khoản demo cố định. Có thể seed dữ liệu mẫu bằng `docker compose --env-file .env.docker exec app php artisan db:seed` khi chấp nhận dữ liệu mẫu trong DB local này; mật khẩu mới là random, lấy lại qua mailbox reset private. Dataset hiện chưa đủ định mức master prompt.

Truy cập http://127.0.0.1:8080/login. Không publish cổng DB. Readiness có thể 503 đến khi scheduler enqueue heartbeat và worker xử lý; kiểm tra lại sau một phút. `failed_jobs`/`pending_jobs` có ở CLI health; HTTP chỉ trả status. Heartbeat không thay outbox nghiệp vụ chưa triển khai.

Giữ `.env.docker` và bản backup riêng an toàn: đổi APP_KEY sẽ làm mất khả năng giải mã nội dung hỗ trợ cũ. Script tạo env từ chối ghi đè file đã có. DB/storage dùng named volumes; `docker compose --env-file .env.docker down` giữ dữ liệu. Không thêm `--volumes` khi cần bảo toàn dữ liệu. Dùng runbook backup trước nâng cấp; không sửa/xóa volume để né lỗi migration ICAO trùng.

```powershell
docker compose --env-file .env.docker logs --tail=100 app worker scheduler
docker compose --env-file .env.docker exec app php artisan queue:failed
docker compose --env-file .env.docker exec app php artisan queue:retry <job-id>
docker compose --env-file .env.docker down
```

Không có payment/SSO/BHS/MRO/ERP/AI/IoT adapters hoàn chỉnh trong profile này. Không có endpoint gửi tiền/tin thật được tự bật. Cần bổ sung profiles theo các giai đoạn còn lại. Runbook restore thử hiện chạy MySQL client trên host Windows/Linux, chưa được xác minh bên trong container PHP.
