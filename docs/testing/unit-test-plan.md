# Kế hoạch Unit / Feature / Integration

Nguồn: phụ lục C.1, 4 baseline W1 và 20 baseline W2. Giữ ID theo `UT-W1-LOGIN-01`, `UT-W1-FLIGHT-01/02`, `UT-W1-BOOK-01`; W2: AUTH-01/02, AIRPORT-01, ROUTE-01, FLIGHT-01/02/03, CREW-01, BOOK-01/02, TICKET-01, CHECKIN-01, BAG-01, MAINT-01/02, DASH-01, AI-01/02, IOT-01, AUDIT-01.

Các baseline có HTTP/DB được phân loại Feature/Integration thực tế, không đổi thành unit bằng mock DB. File `test-cases.csv` giữ UC main/alternative/exception và DERIVED deny/ownership; baseline có namespace riêng. Execution ledger chỉ điền từ JUnit thật. Test SQLite hỗ trợ vòng lặp nhanh; test khóa/concurrency và migration MySQL bắt buộc chạy DB riêng. Không coi MariaDB là MySQL.

Gate giữ nguyên master prompt: coverage trọng yếu line >=85%/branch >=75% nếu có driver; 100% baseline thực thi, 100% UC acceptance coverage, race/invariants và không critical/high tồn đọng. Chưa đạt gate phải ghi chưa nghiệm thu. Không có coverage driver thì ghi Not Measured.

Mở rộng checkpoint G2: FoundationWorkflowTest kiểm validation/IATA/ICAO/coordinate/CHECK/scope/mass assignment/schema/import/quality/revision/published/API/export; FoundationMySqlRaceTest kiểm import và công bố; OperationalResourceRaceTest kiểm shared aircraft/gate/crew bằng2 independent connections+lock observation. CsvSafetyTest/CsvSyntaxTest là unit logic thuần (formula/whitespace/BOM, CSV quoting/EOF/multiline); RuntimeHealthTest kiểm readiness lỗi và freshness. Không dùng tên baseline UT để đổi các HTTP/DB tests thành unit.

Chi tiết method/actual build/time/status nằm trong JUnit và execution ledger; kết quả cuối ở release-test-report.md. CHECK/race skip SQLite có lý do và đã chạy trên MySQL, không bỏ khỏi gate. Bootstrap missing-key branches, boundary import limits/load, mọi payment/QR/refund/integration failure còn thiếu phải mở rộng ở các giai đoạn sau.
