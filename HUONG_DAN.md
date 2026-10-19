# Cách dùng bộ hướng dẫn

1. Giải nén. Đặt `AGENTS.md` và `CODEX_MASTER_PROMPT_QL_HANG_KHONG.md` vào thư mục gốc dự án bạn muốn Codex làm. Nếu đã có AGENTS.md, hợp nhất nội dung thay vì xóa hướng dẫn đang có.
2. Mở thư mục dự án trong Codex/VS Code. Nếu làm dự án mới, dùng thư mục riêng trống.
3. Mở `PROMPT_TRIEN_KHAI.md`, sao chép khối “Prompt bắt đầu” và gửi Codex.
4. Khi cần tiếp tục phiên mới, gửi “Prompt tiếp tục”. Nhật ký và truy vết nằm trong `docs/` do Codex tạo khi làm dự án.
5. Trước bàn giao, gửi “Prompt kiểm tra trước bàn giao”.

`AGENTS.md` chứa quy tắc làm việc; master prompt chứa phạm vi và đặc tả nguồn; prompt triển khai giao nhiệm vụ hành động. Bộ này là hướng dẫn cho Codex xây dựng dự án, chưa phải source code ứng dụng. Hoàn thành phải được xác nhận qua ứng dụng chạy và bằng chứng kiểm thử, không được bảo đảm chỉ bằng prompt.
