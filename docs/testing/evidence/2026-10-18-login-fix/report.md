# Kiểm tra lỗi đăng nhập demo — 18/10/2026

Build: `9c1a02d822fe1cb6ee139bb0a1dfbc1014a7b24fa86e8ec110960f05d7b6ddc7`; base commit và source hashes ở [build-manifest.json](build-manifest.json). Worktree gồm thay đổi chưa commit trước phiên, đã bảo toàn. Fingerprint được kiểm sau tests và không đổi.

Người dùng xác nhận `demo_admin` tại http://127.0.0.1:8000/login. [account-state.json](account-state.json) kiểm cả4account ACTIVE, profile password khớp DB; không lưu password/hash/key. Tái hiện được thông báo khi dùng password còn escape JSON; dùng giá trị decode đăng nhập thành công. Chưa có xác nhận người dùng đã sao chép chuỗi JSON hoặc đã thử helper.

Lệnh thực chạy (PowerShell trong repo):

```powershell
$env:TEST_EVIDENCE_DIR = Join-Path (Get-Location) 'docs/testing/evidence/2026-10-18-login-fix'
php scripts/snapshot-build.php
node scripts/verify-demo-login.mjs
php scripts/test-mysql.php --filter=AccessFoundationTest
```

| Kiểm tra | Kết quả | Bằng chứng |
| --- | --- | --- |
| Chrome login thật 4 account, trang đúng quyền, logout | Passed; 01:40:04–01:40:12 UTC | [login-verification.json](login-verification.json) |
| Password còn JSON escape bị từ chối, decoded password thành công | Passed | login-verification.json |
| Helper decode/copy 4 account, list không lộ secret, 2 yêu cầu sai không copy | Passed với Set-Clipboard mô phỏng trong process con | login-verification.json |
| .env và private profile byte-for-byte giữ nguyên trong smoke | Passed | login-verification.json |
| MySQL AccessFoundationTest | 25 Passed,108 assertions,20.095s,exit0 | [JUnit](mysql-focused.xml), [console](mysql-focused.txt), [environment](mysql-focused-environment.json) |
| Clipboard hệ điều hành thật | Not Run | Không thay clipboard cá nhân; người dùng chạy helper để copy |
| Full regression build mới/human UAT | Not Run/Pending | Kết quả G2 giữ ở thư mục riêng, không chuyển sang build mới |

MySQL8.0.46 localhost3318; feature DB riêng `airline_test_20261018_014004_872c50` được giữ lại. Browser dùng demo đang chạy, chỉ login/read/logout và audit/session, không đổi password/status/role. Không reset DB hoặc .env/key. Đây là khắc phục khả năng lấy credential demo, không phải hoàn tất UC-45 hoặc toàn dự án.
