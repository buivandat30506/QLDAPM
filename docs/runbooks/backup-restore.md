# Backup và restore

Rehearsal đã chạy trên MySQL riêng với fixture tổng hợp; bằng chứng hiện hành tại `docs/testing/evidence/2026-10-18-g2/restore.json`. Không phải kiểm thử production hoặc xác nhận RPO/RTO.

## Chạy lại kiểm chứng

Chuẩn bị MySQL test và chạy browser trước để có hồ sơ/hỗ trợ mã hóa thực:

```powershell
$env:TEST_EVIDENCE_DIR = Join-Path (Get-Location) 'docs/testing/evidence/2026-10-18-g2'
php scripts/prepare-browser.php
npm run test:acceptance
php scripts/verify-restore.php
```

Script chỉ nhận source `airline_test_browser_*` từ manifest prepare-browser; luôn tạo đích mới `airline_test_restore_*`, không nhận tên DB đích hiện hữu và không chạy DROP DATABASE. Windows mặc định dùng client tại `C:/Program Files/MySQL/MySQL Server 8.0/bin/`; máy khác đặt MYSQLDUMP_BINARY/MYSQL_BINARY. TEST_MYSQL_PASSWORD đi qua environment của client, không command line hoặc báo cáo. Không echo env/credential khi chẩn đoán.

Quy trình thực chạy: mysqldump single-transaction/no-create-db/no-add-drop-table → TAR bằng PharData → kiểm SHA256 SQL/upload fixture → giải nén chỉ các entry allowlist → tạo DB mới → mysql import → so số hàng và fingerprint nội dung từng bảng → kiểm mọi FK không orphan → đọc lại assistance encrypted bằng APP_KEY nguồn. Upload nhị phân 256 byte là fixture kiểm archive, không được nhận là đã hoàn thành workflow upload chứng từ các module khác.

Archive/log SQL đặt trong storage/app/private/restore-* đã bị Git ignore. Evidence chỉ chứa tên DB/bảng, số lượng/hash/thời gian, không nội dung PII hoặc khóa. Giữ các DB/archive khi còn cần đối chiếu. PHP host không có ZipArchive; phiên này dùng TAR/PharData, không yêu cầu bật extension toàn máy.

## Quy trình cho dữ liệu cần bảo toàn

1. Xác định DB/engine/phiên bản, tài khoản được phép backup và phạm vi private uploads. Tạm dừng các writer/worker khi cần snapshot nhất quán giữa SQL và files. Không dùng script rehearsal với DB thật.
2. Backup InnoDB bằng mysqldump single-transaction; lưu migrations/build manifest, file uploads private và các key/config cần giải mã ở kho bí mật riêng. APP_KEY/credentials không nằm trong archive chia sẻ hay Git. Kiểm checksum và phân quyền file/ACL.
3. Tạo DB restore riêng, kiểm checksum trước import, khôi phục files vào thư mục riêng. Cấu hình app kiểm chứng dùng DB/key tương ứng; chưa chuyển traffic.
4. Đối chiếu số lượng, FK, fingerprint và giải mã; chạy health và smoke theo role. Kiểm queue/outbox/idempotency khi những workflow đó đã được triển khai. Đo thời gian và mức mất dữ liệu thực tế trước khi chốt RPO/RTO.
5. Chỉ cutover khi có quyền phù hợp và kết quả kiểm chứng được duyệt. Giữ nguồn cũ để rollback. Không tự down migration phá dữ liệu; ưu tiên migration sửa tiếp và backup trước đó.

Mất APP_KEY không được chữa bằng key:generate trên DB mã hóa cũ. DB mất kết nối trả readiness503; dừng writer, kiểm credential/port/log riêng, phục hồi kết nối rồi kiểm failed jobs. Worker chết: khởi động lại worker, kiểm scheduler/heartbeat và queue:failed; retry theo job ID khi đã xác định idempotency. Không sửa/xóa audit để làm kết quả khớp.
