# Airline Smart — báo cáo tinh gọn phạm vi M01–M03

Ngày thực hiện: 13/11/2026 (Asia/Ho_Chi_Minh). Triển khai trên dự án local Docker trong Ubuntu WSL, cổng 8000. Không commit/push; không reset/stash Git. Các thay đổi M02/M03 có sẵn trước nhiệm vụ được giữ.

## 1. Phạm vi và kết quả

Giữ M01 sân bay và hạ tầng 10 loại; M02 đường bay/kế hoạch/phát sinh/kiểm tra/phê duyệt/công bố; M03 theo dõi/trạng thái/thời gian thực tế/nhật ký/điều phối; Dashboard, đặt chuyến, xác thực, hồ sơ, quyền và cấu hình thiết yếu.

Các trang quản trị độc lập M04–M20 dùng chung `scope-empty.blade.php`: **Chưa có thông tin hiển thị** / **Không có nội dung tại khu vực này trong phiên bản hiện tại.** Thông điệp mô tả phiên bản giao diện, không khẳng định Database không có dữ liệu phụ thuộc.

Đã xóa thực tế **18 bản ghi telemetry cũ**, không xóa bảng. **122/123 bảng giữ nguyên toàn bộ SHA-256 nội dung và tập ID** giữa hai bản kiểm kê ngay trước/sau xóa. Telemetry là bảng duy nhất thay đổi. Không thay ID/mã, không thay trạng thái chuyến hiện có, không xóa audit hoặc tài khoản.

## 2. Database và kiến trúc

| Service | Laravel connection | Database | Vai trò |
|---|---|---|---|
| db1 | ops | airline | Sân bay, hạ tầng, đường bay, lịch, khai thác, tổ bay, tài nguyên |
| db2 | mysql (mặc định) | airline | Xác thực, đặt chỗ, tồn chỗ, vé, thông báo |
| db3 | finance | airline | Checkout, thanh toán, hoàn tiền, sổ giao dịch và đối soát |

Đã xác minh ba `server_uuid` khác nhau bằng `/health/ready` và SQL. Đây là ba nguồn ghi nghiệp vụ độc lập, không phải ba replica đọc. `Events`, `WorkflowRelay`, `OutboxRelay`, `airline:work` chuyển sự kiện qua outbox/inbox và cập nhật giá/tồn chỗ khi công bố hoặc thay đổi chuyến; mail-worker xử lý hàng đợi email. Các service và worker được giữ.

Kiểm kê trực tiếp information_schema gồm bảng, PK, FK (kể cả FK tổng hợp), view, trigger, routine; cả ba node có **0 view, 0 trigger, 0 routine**. Có 56 bảng ops, 51 bảng mysql và 16 bảng finance. Migration lịch sử được giữ nguyên; không thêm migration phá hủy hay thay đổi schema.

### Phân loại toàn bộ bảng và số lượng

Số lượng dưới đây lấy tại cửa sổ bảo trì áp dụng xóa. `REMOVE_SAFE` ở telemetry chỉ áp dụng cho ID đo lường quá 30 phút trong kế hoạch đã thử; không tự động xóa bản ghi mới. Mọi bảng `NEEDS_REVIEW` được giữ nguyên.

| Node.bảng | Phân loại | Trước | Sau | Đối chiếu |
|---|---|---:|---:|---|
| ops.aircraft | KEEP_DEPENDENCY | 39 | 39 | Khớp SHA-256 nội dung và ID |
| ops.aircraft_models | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.airport_areas | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.airport_infrastructure | KEEP_CORE | 783 | 783 | Khớp SHA-256 nội dung và ID |
| ops.airport_resources | KEEP_CORE | 648 | 648 | Khớp SHA-256 nội dung và ID |
| ops.airports | KEEP_CORE | 22 | 22 | Khớp SHA-256 nội dung và ID |
| ops.audit_logs | KEEP_COMPATIBILITY | 2078 | 2078 | Khớp SHA-256 nội dung và ID |
| ops.change_reservations | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.crew_assignments | KEEP_DEPENDENCY | 5948 | 5948 | Khớp SHA-256 nội dung và ID |
| ops.crew_availability | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.crew_members | KEEP_DEPENDENCY | 406 | 406 | Khớp SHA-256 nội dung và ID |
| ops.crew_qualifications | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.crew_rosters | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.demo_installations | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.devices | KEEP_COMPATIBILITY | 6 | 6 | Khớp SHA-256 nội dung và ID |
| ops.disruptions | KEEP_CORE | 4 | 4 | Khớp SHA-256 nội dung và ID |
| ops.flight_actual_events | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.flight_changes | KEEP_CORE | 2 | 2 | Khớp SHA-256 nội dung và ID |
| ops.flight_schedule_changes | KEEP_CORE | 1 | 1 | Khớp SHA-256 nội dung và ID |
| ops.flight_season_occurrences | KEEP_CORE | 13 | 13 | Khớp SHA-256 nội dung và ID |
| ops.flight_seasons | KEEP_CORE | 5 | 5 | Khớp SHA-256 nội dung và ID |
| ops.flights | KEEP_CORE | 1007 | 1007 | Khớp SHA-256 nội dung và ID |
| ops.ground_equipment | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.ground_services | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.ground_task_equipment | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.ground_task_handoffs | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.ground_task_services | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.ground_tasks | KEEP_DEPENDENCY | 2910 | 2910 | Khớp SHA-256 nội dung và ID |
| ops.inbox | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_assets | KEEP_CORE | 22 | 22 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_belts | KEEP_CORE | 22 | 22 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_counters | KEEP_CORE | 1 | 1 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_fixture_sets | KEEP_COMPATIBILITY | 22 | 22 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_gates | KEEP_CORE | 202 | 202 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_links | KEEP_CORE | 3 | 3 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_runways | KEEP_CORE | 22 | 22 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_simplification_archive | KEEP_COMPATIBILITY | 1 | 1 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_sources | KEEP_CORE | 139 | 139 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_stands | KEEP_CORE | 201 | 201 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_taxiways | KEEP_CORE | 22 | 22 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_terminals | KEEP_CORE | 1 | 1 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_types | KEEP_CORE | 10 | 10 | Khớp SHA-256 nội dung và ID |
| ops.infrastructure_zones | KEEP_CORE | 24 | 24 | Khớp SHA-256 nội dung và ID |
| ops.integration_connections | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.m02_demo_sets | KEEP_COMPATIBILITY | 1 | 1 | Khớp SHA-256 nội dung và ID |
| ops.maintenance_items | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.maintenance_materials | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.master_changes | KEEP_CORE | 5 | 5 | Khớp SHA-256 nội dung và ID |
| ops.outbox | KEEP_COMPATIBILITY | 14 | 14 | Khớp SHA-256 nội dung và ID |
| ops.resource_allocations | KEEP_DEPENDENCY | 2922 | 2922 | Khớp SHA-256 nội dung và ID |
| ops.routes | KEEP_CORE | 44 | 44 | Khớp SHA-256 nội dung và ID |
| ops.runway_directions | KEEP_CORE | 2 | 2 | Khớp SHA-256 nội dung và ID |
| ops.telemetry | REMOVE_SAFE | 18 | 0 | Xóa đúng ID trong kế hoạch |
| ops.work_order_items | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.work_order_materials | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| ops.work_orders | KEEP_DEPENDENCY | 4 | 4 | Khớp SHA-256 nội dung và ID |
| mysql.aircraft_change_receipts | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.airport_status_authorities | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.analytics_dataset_records | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.analytics_datasets | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.analytics_model_runs | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.analytics_recommendations | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.audit_logs | KEEP_COMPATIBILITY | 984 | 984 | Khớp SHA-256 nội dung và ID |
| mysql.baggage | KEEP_DEPENDENCY | 3170 | 3170 | Khớp SHA-256 nội dung và ID |
| mysql.baggage_allowances | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.baggage_events | KEEP_DEPENDENCY | 15845 | 15845 | Khớp SHA-256 nội dung và ID |
| mysql.baggage_incidents | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.boarding_manifests | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.boarding_passes | KEEP_CORE | 19489 | 19489 | Khớp SHA-256 nội dung và ID |
| mysql.booking_changes | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.bookings | KEEP_CORE | 34789 | 34789 | Khớp SHA-256 nội dung và ID |
| mysql.cache | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.cache_locks | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.checkout_sessions | KEEP_CORE | 34784 | 34784 | Khớp SHA-256 nội dung và ID |
| mysql.customer_interactions | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.email_verifications | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.exchange_requests | KEEP_DEPENDENCY | 4 | 4 | Khớp SHA-256 nội dung và ID |
| mysql.failed_jobs | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.fare_classes | KEEP_CORE | 2953 | 2953 | Khớp SHA-256 nội dung và ID |
| mysql.fare_rule_versions | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.inbox | KEEP_COMPATIBILITY | 55 | 55 | Khớp SHA-256 nội dung và ID |
| mysql.inventory | KEEP_CORE | 989 | 989 | Khớp SHA-256 nội dung và ID |
| mysql.job_batches | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.jobs | KEEP_COMPATIBILITY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.migrations | KEEP_COMPATIBILITY | 32 | 32 | Khớp SHA-256 nội dung và ID |
| mysql.notification_deliveries | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.notification_templates | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.notifications | KEEP_CORE | 38 | 38 | Khớp SHA-256 nội dung và ID |
| mysql.outbox | KEEP_COMPATIBILITY | 32 | 32 | Khớp SHA-256 nội dung và ID |
| mysql.passenger_assistance_requests | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.passenger_document_verifications | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.passenger_revisions | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.password_reset_tokens | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.recovery_offers | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.reservation_ancillaries | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.reservation_passengers | KEEP_CORE | 2 | 2 | Khớp SHA-256 nội dung và ID |
| mysql.reservation_segments | KEEP_CORE | 3 | 3 | Khớp SHA-256 nội dung và ID |
| mysql.reservations | KEEP_CORE | 2 | 2 | Khớp SHA-256 nội dung và ID |
| mysql.seat_reassignments | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.sessions | KEEP_CORE | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.support_cases | NEEDS_REVIEW | 3 | 3 | Khớp SHA-256 nội dung và ID |
| mysql.support_compensations | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.support_messages | NEEDS_REVIEW | 6 | 6 | Khớp SHA-256 nội dung và ID |
| mysql.system_configurations | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| mysql.tickets | KEEP_CORE | 34780 | 34780 | Khớp SHA-256 nội dung và ID |
| mysql.user_roles | KEEP_CORE | 872 | 872 | Khớp SHA-256 nội dung và ID |
| mysql.users | KEEP_CORE | 858 | 858 | Khớp SHA-256 nội dung và ID |
| finance.audit_logs | KEEP_COMPATIBILITY | 37 | 37 | Khớp SHA-256 nội dung và ID |
| finance.checkout_intents | KEEP_CORE | 34783 | 34783 | Khớp SHA-256 nội dung và ID |
| finance.fee_catalog | KEEP_DEPENDENCY | 0 | 0 | Khớp SHA-256 nội dung và ID |
| finance.financial_adjustments | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| finance.inbox | KEEP_COMPATIBILITY | 32 | 32 | Khớp SHA-256 nội dung và ID |
| finance.ledger_entries | KEEP_CORE | 34782 | 34782 | Khớp SHA-256 nội dung và ID |
| finance.outbox | KEEP_COMPATIBILITY | 41 | 41 | Khớp SHA-256 nội dung và ID |
| finance.payments | KEEP_CORE | 34781 | 34781 | Khớp SHA-256 nội dung và ID |
| finance.reconciliation_adjustments | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| finance.reconciliation_entries | NEEDS_REVIEW | 11148 | 11148 | Khớp SHA-256 nội dung và ID |
| finance.reconciliation_imports | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| finance.reconciliation_reason_codes | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| finance.reconciliation_runs | NEEDS_REVIEW | 2 | 2 | Khớp SHA-256 nội dung và ID |
| finance.refunds | KEEP_CORE | 6 | 6 | Khớp SHA-256 nội dung và ID |
| finance.statement_batches | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |
| finance.statement_rows | NEEDS_REVIEW | 0 | 0 | Khớp SHA-256 nội dung và ID |

### Dữ liệu phụ thuộc phải giữ

- Tàu bay, mô hình cabin, bảo dưỡng và vật tư/hạng mục: kiểm tra khả dụng và sức chứa; không bỏ kiểm tra chỉ để công bố lịch.
- Nhân sự, năng định, thời hạn giấy phép, phân công, khả dụng: M02 công bố cần tối thiểu phi công/tiếp viên hợp lệ; đổi tàu/tổ bay còn kiểm tra tương thích và xung đột.
- Nhiệm vụ mặt đất bắt buộc và bằng chứng: M03 không được đóng cửa/khởi hành khi chưa hoàn tất.
- M18 tài nguyên/phân bổ, cửa, hạ tầng chuẩn hóa, lịch sử: kiểm tra cửa và xung đột khi công bố/điều phối.
- Giá, hạng vé, tồn chỗ, đặt chỗ, hành khách, checkout, thanh toán, vé và sổ giao dịch: luồng đặt chuyến thực tế.
- Baggage, boarding, thay đổi hành khách, recovery: còn được saga đổi tàu/gián đoạn/hoàn tiền sử dụng; không xóa theo mã phân hệ.
- Audit, outbox/inbox, tài khoản/quyền, fixture M01/M02 và kho lưu chuẩn hóa: bảo toàn khả năng kiểm chứng, đồng bộ và khôi phục.
- Lịch sử hỗ trợ, phân tích, đối soát: chưa đủ căn cứ để xóa an toàn; phân loại NEEDS_REVIEW, kể cả bảng hiện trống. Không ép xóa bảng để giảm số lượng.

### Dữ liệu đã xóa

Nguồn sử dụng telemetry đã rà soát: AnalyticsService (forecast quy tắc, chỉ đọc đo trong 30 phút), màn hình IoT/AI cũ và seeder opt-in. Không có service M01–M03/đặt chuyến, worker/event consumer đang hoạt động đọc telemetry. Telemetry không phải `flight_actual_events` hay audit; không có FK trỏ vào hoặc trigger trên bảng. Giữ `devices` và trạng thái/sequence để không tái sử dụng định danh. Plan khóa cutoff `2026-11-13 04:06:04` UTC, ID và hash từng bản ghi; apply kiểm tra lại metadata, fingerprint và local topology, dùng transaction, xóa `WHERE id = ...`, hỗ trợ chạy lại không xóa thêm.

- `07c59252-1b12-4e9c-abf4-0775dfcfac6f` — SHA-256 `0c880e6d1e40154b287ac58407ccd4e9ea1cc1e869f91005f2285fc0a3439279`
- `200f7a86-bf27-45d8-9d1f-bee10a0add7d` — SHA-256 `b611ca6add208d63e739527e4194930c82819204a07c6b1adc8831f724140747`
- `36498760-1fdc-48fb-8371-89e961271ccd` — SHA-256 `7d4babed6d4401fb12257f288931903adaee1d4466bcc55869904ecb7b8e3d3f`
- `3fcb570f-3a8a-4de6-8a6a-b7956966d5f5` — SHA-256 `172ec50b633c3089bd5448912a2ff40d7a4985334a4e63177632c039ea7c5aa1`
- `428b0df4-8e7f-49fc-b180-385cf8b9f44c` — SHA-256 `4f2d2b3f01b2a185836021a073c9dc606681a1b3e56e1c3137b4800cca5152f0`
- `4618191f-554b-4f43-9741-b44f997cd95d` — SHA-256 `d6f7fa9c1d88049c1cf477e894fc6f232413711e8528e76342fde1da01bdd2f3`
- `48217b12-7055-45cc-a81d-2d9fd3cb934d` — SHA-256 `d8e396fc0ccb89a0ac57b549d0ebaaf3b8fb3204ce9f75ce5ab07b777cabb53e`
- `658225d1-b877-4212-b856-7f752227b7f9` — SHA-256 `e0597b5f5350fd5fa10a4ee22abaae2a9e42476153ad2cce57a64fe110d3a6f9`
- `72c77e4c-96dc-4152-8227-83a9c2cc8f1d` — SHA-256 `5cda8fa09517da9301c01115e1cd8c35c503ff07a4887c0fd29da44fcc187b8b`
- `7af479cc-7367-485b-bdc7-6bf117886c8a` — SHA-256 `662792dd52101a2787b7d849883d9cadd84f4a198f1a71bf2993f78a8073b833`
- `a1265119-937a-4ac3-aadf-344407d65fc9` — SHA-256 `d4566aa50f7c58ed298747324c708a550aa84a5c3b64212117a38c25f59a16b1`
- `a518316a-d10d-4e6b-94e9-d462152f885a` — SHA-256 `5f8c3f3e5f4a2b6da408c7afd9997f8c89e0209c3616e166e038215ead360350`
- `b84ba226-39a2-4792-99fd-9307649b6124` — SHA-256 `096c46591875b39639a4689756a1c693f22211c001055f508e6825e8f8c0e6ca`
- `bb60c121-bb2c-4bd7-b7c5-3d743bc9c728` — SHA-256 `646eb830c4acbcf3bb3e4ec57c724ab3a5bda12820d92055134da14256f6edc6`
- `c20b2123-0c3e-44e9-8145-a555a76a1955` — SHA-256 `dbec85d77a01b367f022eb07ee388adf5051d7f7be9c2084db3050a7e89c718a`
- `c52ac0aa-e8d8-4a02-b08d-71af077cc5c4` — SHA-256 `4cad49157d48f4bd999f182c507760d79a71b55f89b3c77654cc9ca80f6c5aa4`
- `c76e28d9-ff7d-476e-b341-5d78dde6df6f` — SHA-256 `3390e19c480797df15d91a3b14a93c694008e374afedddc73dfc931f5d8cfa91`
- `d3a41221-8572-48f3-b643-859903d3429b` — SHA-256 `6c632e5cb3e4fe71233b818f5f55365b2759deec5e9de078bfb5de723c62c7d8`

### Hash bảo toàn dữ liệu cốt lõi

Hash nội dung được tạo từ từng hàng chuẩn hóa key, theo thứ tự PK; hash ID được tạo độc lập. Hai hash dưới đây khớp hoàn toàn trước/sau trên database local. Toàn bộ 123 bảng, PK, FK và hash đầy đủ ở các artifact private nêu bên dưới.

| Node.bảng | SHA-256 nội dung | SHA-256 ID |
|---|---|---|
| ops.airport_infrastructure | `a5bb61e90b97607f4f5e241661d1bd66ca03cb81d60c058a097b3eb9233c9d38` | `b0b6e6cfb6f76e39aba5ac3cc691e4e525f892324ec32e58b475772fdc514bed` |
| ops.airports | `f191ce99c758255d94ab888e1b669ade743edd63a3d40ddb35dc687f371d8769` | `769bea9abecee8bdaa35f42ca7ce31bed8d5bd4af2ba6f270a40dde45b425a2e` |
| ops.audit_logs | `a8cbc9dce20c01e8631ed9a28261e33c664c729304617d6fb8535456e338338d` | `2c07b2d75ca0352d9a931e2586425e75ca98304953987f86505124f7e3e07494` |
| ops.crew_assignments | `63a0bd15374bbd2babf07ff9af3d476e2d8e9d212b48c4d1ebfb139188dd004c` | `bf31baf96e7a04c33d47f1ede358d9ee450e89c96f739fac393be11cfcd03702` |
| ops.flight_actual_events | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| ops.flight_changes | `7baf4a12e284c1324e671a083da487e3a35c764a5107d6efd1d8d0e571f93803` | `91ae829c85bb1728d6ffa2625e1a870f6b21b85a7ab3f96d37df5b6211a4df44` |
| ops.flight_schedule_changes | `b60895b8041bd5cb64fb6132e2e61493d4d48ac03eee1de89aefa0cbbdd0708a` | `51bc513113548e062ada62b03efea153cae2abdf46b051c551c3e62a4dfb88cf` |
| ops.flight_season_occurrences | `147f371557d0fd4325da1e6fdb659c332fbb1b8c72199f2660d69d7b95fe4cea` | `ab72fe75fbbbc77cc57067497938e3c3caa3ce9b7e75fafd02494577805c60f9` |
| ops.flight_seasons | `4369ec2406955915a3d76b32fce77bfc05635bbf74df419b02a4a735e2c7ca0d` | `f4d592f649ab8910c2ee784f40aa74d1845ed070552ce042f232ecca8e86c437` |
| ops.flights | `e36643144d7f6054a86b492da53115ca633466dcd12201530ccb1672ed19d7f6` | `c10488ac29dacd23e5950ac9391e95dbfdcfb0c4cb720e6230adac12a423c606` |
| ops.infrastructure_types | `cdb87875055322f9fa66d4ec123f18cc113f8fed63d1f8fcb477c3b21ec14aa5` | `57188efcda72f72c88d465083f4ecf3be74c386131776231249aad5a409fd331` |
| ops.m02_demo_sets | `33d259b497b26c65da3a8d3d019bcf2b8f695619e26557d309030cf6f7aeb0ea` | `85a01f0abea934b9b2191a91c04c63eeb78c4e60b7cd3d296734d2919e23c084` |
| ops.master_changes | `44749c81acdc992986008b08976b139644747c2e2560f0d2f62765836796d5cd` | `f4d592f649ab8910c2ee784f40aa74d1845ed070552ce042f232ecca8e86c437` |
| ops.resource_allocations | `902d5ef3098d7fb360bfa7399d48df709c9ec5f47cbdfcb16548e868b6b55d82` | `0a4bb7f50c1d79d74adf53a0304b8ac5fe762f1eaf7237230f39a296ef7dc300` |
| ops.routes | `bf04383d81a36e99a58c4222bc7f2656ad7b2a1b452bcc97b9cc14090a7b88b1` | `8c79efc2728256d0b18803e482ccac60b3e4ce2467d4a055eab6fde827d9c7d1` |
| mysql.audit_logs | `cc95ce9700aa448f30fbca78323f41c5caa37e8d8bf7d07995d3cb2381d8a01b` | `8468ff375b19cf93c82099bfe5186a7295afcbf1b84bd01198ad6c5b9ce2dc25` |
| mysql.bookings | `f7b12001287d7093da74c1db4750f1fa855a262bd22847445340f1e827bdf355` | `ffaa35ee9cf3476de37e6e4f0d372875f4c4230a8b3d5990a950d5b2a8a84bfa` |
| mysql.inventory | `fbf492c629875dcfefc466f5014bcf9538cdba57fb635f569742ee7861f0d8cf` | `3246dad42c36ce80a713130f0d30eda16cdb6e31542994b554b89ca12663181b` |
| mysql.reservations | `ce277433d9d2fc92094ecc16189c892095ea5725b96d689935d9ee737b2a9c6b` | `a4ff286bab2384657cc0d1f73db4d6a4c7885f3e59a1c49cccd6a68aadcbb4bf` |
| mysql.user_roles | `e56df7da02dadbbe19afcc5e29b1fa8b2a2f0d9db70be3d05d497b6f14405374` | `50f65accc8ed2b597dd71f19c302d8ec07274dabc3397a9f9392a43c92dface6` |
| mysql.users | `30d302b924e039a7f5db7579f0c44c15851c42fe68c222cbf7517c99c6728382` | `da21873ec7eca25545f711b1071d9f98d16c844daac1c8cb0b96a2f02daaebb2` |
| finance.audit_logs | `c3a730925a5c7f6e49f0d9c8e3e6a4102fbf463f2d7f9b31ef80b83bd3e9adf5` | `5e1f872066ec3a36fd34e2781a903648d6c08be3b6717b9c331ccb6d1aca9bb4` |
| finance.checkout_intents | `7e981031157f2eb7d5a7a565d39fbdb71aa893e5ccdab6a759ef6e8d69d84312` | `b26fb8edd4f0963b8ed801edd39f13f7e6123aa14fd50801c8538fad5fcb6c30` |
| finance.payments | `cac92ce8bab251df6e934c89ea63e49b102bbe96f4db42243c310762cea2b7d0` | `d56867ca45d6163cb3cce573ce5c055cf61267432bf15978c23e2561e48efcaa` |

Phân bố trạng thái chuyến trước/sau giống nhau: `{"ARRIVED": 529, "SCHEDULED": 455, "BOARDING": 1, "DRAFT": 13, "PENDING": 5, "GATE_CLOSED": 1, "DEPARTED": 1, "CANCELLED": 1, "DELAYED": 1}`.

Toàn vẹn: **0 bản ghi mồ côi ở tất cả FK thực**, bao gồm FK tổng hợp. Các kiểm tra liên node `inventory.flight_id`, `bookings.flight_id`, `fare_classes.flight_id`, `payments.booking_id`, `checkout_intents.booking_id` đều **0 mồ côi** trước/sau. Đây là các quan hệ liên node đã kiểm tra trực tiếp; không coi chúng là FK do MySQL thực thi giữa các server. ID/hash của toàn bộ bảng còn lại được bảo toàn. Audit đăng nhập phát sinh khi kiểm tra trình duyệt trước cửa sổ xóa được giữ trong bản kiểm kê ngay trước xóa.

### Sao lưu, diễn tập và rollback

- Bản sao lưu logic ba node: `backend/storage/app/private/scope-backup/db1.sql`, `db2.sql`, `db3.sql`. Tổng khoảng 60 MB. Đã dừng app/worker/mail-worker khi sao lưu. Export sắp xếp bảng theo phụ thuộc; không dùng DROP/TRUNCATE và không tắt khóa ngoại.
- Đã khôi phục vào database **airline_scope_copy** riêng trên cả ba node, dùng tài khoản DB quản trị vốn có, không tạo/grant quyền mới. **123/123 bảng khớp count, hash nội dung và hash ID** với bản gốc. Đây là chứng cứ khôi phục thực, không chỉ kiểm tra file tồn tại.
- Đã dry-run bằng `airline:scope-data plan`, xóa thử đúng 18 ID trên bản sao, xác nhận 122 bảng còn lại nguyên vẹn, kiểm thử hồi quy sau xóa rồi mới áp dụng local.
- Receipt `scope-rehearsal-receipt.json` kiểm chứng SHA-256 plan, cả ba backup, khôi phục và kết quả kiểm thử. Lệnh apply local bắt buộc receipt; chỉ chấp nhận môi trường local/testing, database airline/airline_scope_copy và host db1 đúng UUID.
- Bổ sung bản sao lưu ngay trước xóa: `backend/storage/app/private/scope-final-backup/`, cùng bản kiểm kê `scope-local-before-delete.json`. Các file private không được commit.
- Rollback dữ liệu đã xóa: dừng app và worker; chạy `docker compose run --rm --no-deps app php tools/scope-telemetry-archive.php restore`. Công cụ dùng `scope-deleted-rows.json`, đối chiếu từng ID/hash với plan, không ghi đè hàng đã thay đổi, chèn trong transaction với FK còn bật; chạy lại không tạo trùng. Đã thử khôi phục 18 hàng trên bản sao, lần hai khôi phục 0 hàng, rồi áp dụng lại plan xóa đúng 18 hàng. Log: `scope-rollback-test.log`. Không chạy lại toàn bộ dump lên database đang có dữ liệu. Bản sao đầy đủ được phục hồi vào **schema rỗng riêng** bằng `scripts/scope-copy.sh restore`; lệnh CREATE cố ý thất bại nếu schema đã tồn tại, không reset schema.
- Rollback giao diện: gỡ middleware EnforceReleaseScope khỏi bootstrap và khôi phục Dashboard bằng diff riêng của nhiệm vụ; source/view/service cũ vẫn còn. Không dùng git reset/stash để tránh ảnh hưởng thay đổi có sẵn.

## 3. Frontend, Sidebar và URL

Giữ nguyên `StaffNavigation` cùng biểu tượng, tên, thứ tự, submenu, related links và kiểm tra quyền. Trình duyệt xác nhận **5 nhóm (tính cả Tổng quan), 13 mục chính**. Không thêm/xóa mục Sidebar do phạm vi mới. Hamburger, active state, Topbar và shell hiện hữu được giữ.

Dashboard dùng dữ liệu thật và kiểm tra vai trò/sân bay: sân bay, đường bay, kế hoạch, lịch chờ duyệt, chuyến đã công bố trong ngày theo UTC+7, chuyến đang khai thác, chuyến chậm, phương án chờ duyệt và thay đổi danh mục. KPI dẫn đến nghiệp vụ tương ứng; không còn KPI tài chính/AI/hỗ trợ/bảo dưỡng hoặc nhãn tình trạng hệ thống hardcode. Mục quản trị tài khoản vẫn giữ.

Các lối phụ thuộc tối thiểu đặt trong Dashboard, không thay Sidebar: `/workspace/planning/crew-assignments` (crew_scheduler), `/workspace/operations/ground-tasks` (ground_agent, chỉ người nhận được thao tác), `/staff/payments` (finance, xác nhận chuyển khoản thật). Màn hình đội bay/tổ bay/mặt đất/tài chính độc lập vẫn rỗng. Không tự cấp role mới.

M03 chi tiết hiển thị thời gian kế hoạch/thực tế, các sự kiện `flight_actual_events`, nhật ký và form ghi nhận giờ qua service hiện có. Giữ các luồng gián đoạn/đổi nguồn lực/hỗ trợ khách là phụ thuộc M03.

Đã sửa lỗi biểu mẫu chung trong `frontend/app.js`: bảo toàn name/value của nút được chọn trước khi vô hiệu hóa nút chống bấm lặp. Trước sửa, nút xác nhận mock thanh toán mất trường `result` và bị validation từ chối; sau sửa, callback ký đúng và phát hành vé theo luồng thật. Cùng sửa này giữ lựa chọn approve/reject trên các biểu mẫu hiện hữu. Đã build lại assets từ source. Audit của sự kiện `flight.actual_time.updated` mới được gắn đúng ID chuyến để xuất hiện trong nhật ký M03; không sửa các audit lịch sử.

Luồng đặt chuyến trên giao diện hiện tại hỗ trợ một hành khách, một chiều/khứ hồi, chọn hạng vé, xem lại lựa chọn, nhập thông tin hành khách/liên hệ, giữ chỗ, chọn thanh toán và tra cứu. Không có bước chọn ghế/dịch vụ bổ sung riêng trong `trip-passengers.blade.php`; không giả định đã có hoặc thêm bước giả. Backend reservation vẫn giữ khả năng nhiều coupon/hành khách theo triển khai hiện hữu.

| URL kiểm tra trực tiếp | Kết quả | HTTP |
|---|---|---:|
| `/staff` | Giữ hoạt động | 200 |
| `/workspace/catalog?flow=airports` | Giữ hoạt động | 200 |
| `/workspace/catalog?flow=infrastructure` | Giữ hoạt động | 200 |
| `/workspace/resources` | Giữ hoạt động | 200 |
| `/workspace/catalog?flow=routes` | Giữ hoạt động | 200 |
| `/workspace/scheduling?flow=planning` | Giữ hoạt động | 200 |
| `/workspace/scheduling?flow=monitoring` | Giữ hoạt động | 200 |
| `/workspace/disruptions` | Giữ hoạt động | 200 |
| `/workspace/fleet` | Màn hình rỗng | 200 |
| `/workspace/maintenance` | Màn hình rỗng | 200 |
| `/workspace/crew?flow=profiles` | Màn hình rỗng | 200 |
| `/workspace/crew?flow=assignments` | Màn hình rỗng | 200 |
| `/workspace/ground?flow=tasks` | Màn hình rỗng | 200 |
| `/workspace/ground?flow=tracking` | Màn hình rỗng | 200 |
| `/staff/passengers` | Màn hình rỗng | 200 |
| `/workspace/boarding-control` | Màn hình rỗng | 200 |
| `/workspace/baggage` | Màn hình rỗng | 200 |
| `/workspace/customer` | Màn hình rỗng | 200 |
| `/modules/m2?flow=bookings` | Màn hình rỗng | 200 |
| `/workspace/fare` | Màn hình rỗng | 200 |
| `/modules/m3?flow=alerts` | Màn hình rỗng | 200 |
| `/workspace/analytics` | Màn hình rỗng | 200 |
| `/workspace/finance?flow=transactions` | Màn hình rỗng | 200 |
| `/workspace/finance?flow=reconciliation` | Màn hình rỗng | 200 |
| `/profile` | Giữ hoạt động | 200 |
| `/workspace/notifications` | Giữ hoạt động | 200 |
| `/admin/users` | Giữ hoạt động | 200 |
| `/admin/connections` | Giữ hoạt động | 200 |
| `/workspace/audit` | Giữ hoạt động | 200 |
| `/workspace/integrations` | Giữ hoạt động | 200 |
| `/workspace/fleet-cabin` | Màn hình rỗng | 200 |
| `/workspace/infrastructure-reference/ground` | Màn hình rỗng | 200 |
| `/workspace/infrastructure-reference/baggage` | Màn hình rỗng | 200 |
| `/workspace/reports/999/export` | Màn hình rỗng | 200 |
| `/workspace/passenger-revisions/00000000-0000-0000-0000-000000000001` | Màn hình rỗng | 200 |
| `/workspace/master/export/aircraft` | Màn hình rỗng | 200 |
| `/workspace/master/export/crew_members` | Màn hình rỗng | 200 |
| `/staff/payments` | Giữ hoạt động | 200 |
| `/workspace/planning/crew-assignments` | Giữ hoạt động | 200 |
| `/workspace/operations/ground-tasks` | Giữ hoạt động | 200 |
| `/plan` | Giữ hoạt động | 200 |

## 4. Backend và API

Middleware `EnforceReleaseScope` áp dụng sau session và AccountAccess; người dùng chưa đăng nhập/chưa xác minh vẫn qua auth/verified hiện có. Trước khi trả view rỗng, kiểm tra vai trò của trang đích. JSON màn hình tạm ngừng trả metadata/trạng thái cùng `data: []`, không chạy truy vấn dữ liệu nghiệp vụ. GET/HEAD đều được kiểm soát.

Chặn 403 các action quản trị độc lập cũ (tạo tàu, bảo dưỡng, giá vé/hạng vé quản trị, telemetry/AI, đối soát, hỗ trợ độc lập...), bao gồm `/workspace/actions/*`, `/api/v1/actions/*`, REST aliases và `/modules/*/actions/*`. Generic master_propose/master_approve chỉ còn entity sân bay/hạ tầng/đường bay, không thể lách qua entity aircraft/crew. Các export đội bay/tổ bay cũ cũng trả màn hình rỗng.

Giữ action M01–M03 và phụ thuộc cụ thể: lập lịch/season/submit/publish, kiểm tra, sửa lịch, actual_time, flight_state, allocate/unallocate, assign_crew, ground_state, resource_propose/resource_decide, disruption/approve_disruption, recovery_propose/recovery_decide. Service tự kiểm tra quyền, scope, người duyệt độc lập và điều kiện nghiệp vụ như trước.

Không đổi service thanh toán, chữ ký callback VNPay/MoMo, CSRF/session, IPN, giá/tồn chỗ, reserve/pay/cancel, tra cứu hành trình và tài liệu vé. Phương thức mô phỏng thanh toán vẫn là cơ chế local sẵn có; không tự gán trạng thái thành công. Màn hình đối chiếu chuyển khoản tối thiểu dùng GatewayPayments thật; không dùng đối soát tổng hợp tạm ngừng để xác nhận giả.

Lưu ý: `/modules/m1`, `/modules/m2`, `/modules/m3` là các màn hình tổng hợp cũ (core nhiều phân hệ / vé / AI), **không tương ứng M01/M02/M03** trong phạm vi hiện tại. Đã xử lý theo nghiệp vụ thực; M01/M02/M03 hoạt động ở các URL workspace nói trên.

## 5. Kiểm thử và bằng chứng

| Hạng mục | Kết quả | Phạm vi bằng chứng |
|---|---|---|
| Kiểm kê/khôi phục ba node | PASS | 123 bảng khớp nội dung và ID |
| Dry-run/xóa thử | PASS | 18 ID; 122 bảng còn lại khớp hash; replay không xóa thêm |
| Backend trước xóa thử | PASS | 4 test, 194 assertions; gồm kiểm tra stale fingerprint và replay trên telemetry còn dữ liệu |
| Backend sau xóa thử / phiên bản cuối | PASS | Sau xóa thử 5 test/202 assertions; phiên bản cuối 5 test/208 assertions; PHPUnit cấu hình tests/scope.xml |
| M01 nghiệp vụ | PASS | HTTP + trình duyệt preview, gửi đề nghị, tài khoản khác phê duyệt, lịch sử; xem hạ tầng |
| M02 nghiệp vụ | PASS | HTTP + trình duyệt tạo kế hoạch, phát sinh, phân công tổ bay, kiểm tra/gửi duyệt/công bố |
| M02 → M03 | PASS | Inventory/phân bổ/tổ bay xuất hiện đúng; trình duyệt cập nhật BOARDING và xem nhật ký |
| Điều phối đổi tàu | PASS | HTTP test thực hiện prepare/commit saga, kiểm tra nguồn lực, phê duyệt độc lập, outbox và kết quả tàu mới |
| Đặt chuyến | PASS | Trình duyệt tìm/chọn chuyến đã công bố, nhập hành khách, giữ chỗ, thanh toán VNPay mock gốc, xác nhận và tra cứu; backend kiểm tra tồn chỗ/giá |
| Sidebar/URL cũ/phân quyền | PASS | 41 URL GET trả 200; API ghi độc lập trả 403; passenger không vào trang staff trái quyền |
| Responsive | PASS | 1920/1440/1024/768/390/320 px; không tràn ngang toàn trang ở các màn hình đã ghi nhận |
| JavaScript | PASS | Không có pageerror trong các lượt kiểm tra |
| Kiểm tra website local sau xóa | PASS | 10 URL nghiệp vụ/hỗ trợ ở sáu kích thước, 4 endpoint ghi tạm ngừng trả 403; scope-browser-final/results.json |
| Frontend build | PASS | npm run assets, đồng bộ từ frontend/ theo quy trình repo |
| Blade compile / Git whitespace | PASS | artisan view:cache và git diff --check |
| Gateway live, chuyển khoản tiền thật | CHƯA KIỂM TRA | Không thực hiện giao dịch thật; giữ nguyên cơ chế đối chiếu/chữ ký/service |
| Toàn bộ test suite lịch sử | CHƯA CHẠY | Harness gốc xóa/truncate test database và tắt FK; dùng suite rollback trên bản sao để tuân thủ ràng buộc nhiệm vụ |

Suite mới mở transaction trên cả ba node và rollback từng bài, không reset DB, không tắt FK. Chuẩn bị mật khẩu cho sáu tài khoản demo **chỉ trên bản sao** để đăng nhập trình duyệt; giữ nguyên ID/role/scope. Không đổi mật khẩu hay quyền trên airline local. Container web kiểm thử cổng 8002 được dừng sau nghiệm thu; database copy giữ làm bằng chứng.

Màn hình nghiệp vụ đã chạy bằng trình duyệt trên bản sao sau xóa:

- m03-published-detail: `/workspace/operations/flights/1014`
- m03-updated-history: `/workspace/operations/flights/1014`
- booking-held: `/reservations/024f70fb-3146-4d09-8d00-8741a56c07e1`
- payment-original-mock-provider: `/payments/mock/c5a3ef59-5ba9-49b1-ada5-2f4aa7e8649d`
- booking-confirmed: `/reservations/024f70fb-3146-4d09-8d00-8741a56c07e1`
- booking-lookup: `/journeys`
- m01-preview: `scope-workflows/m01-preview.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).
- m01-approved-history: `scope-workflows/m01-approved-history.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).
- m02-generation-preview: `scope-workflows/m02-generation-preview.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).
- m02-submit-preview: `scope-workflows/m02-submit-preview.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).
- m02-publish-preview: `scope-workflows/m02-publish-preview.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).
- booking-passenger-details: `scope-workflows/booking-passenger-details.png` (capture đã kiểm tra sáu kích thước trước khi tiếp tục checkpoint thanh toán).

Artifact private (không chứa trong Git): `scope-before.json`, `scope-restored.json`, `scope-plan.json`, `scope-copy-after.json`, `scope-local-before-delete.json`, `scope-after.json`, `scope-rehearsal-receipt.json`, `scope-tests-before.log`, `scope-tests-after.log`, `scope-tests-final.log`, `scope-browser/results.json`, `scope-workflows/results.json`; screenshot trong hai thư mục browser/workflows. Hai inventory ngay trước/sau xóa là đối chiếu chính thức. Script tái kiểm tra: `scripts/scope-evidence.py`, `scripts/check-scope-reduction.cjs`, `scripts/check-scope-workflows.cjs`.

## 6. Giới hạn và khôi phục về sau

Database chỉ giảm 18 phép đo IoT cũ; phần lớn dung lượng nằm trong đặt chỗ/vé/thanh toán/lịch sử và các phụ thuộc bắt buộc. Không hứa giảm mạnh dung lượng vật lý InnoDB bằng DELETE; không OPTIMIZE, DROP bảng hoặc xóa volume. Những bảng NEEDS_REVIEW và cấu trúc trống được giữ vì chưa chứng minh được quyền xóa an toàn/tương thích.

Các màn hình rỗng là quyết định phạm vi, không phải kết luận các phân hệ không có dữ liệu. Các API phụ thuộc còn hoạt động vẫn tuân thủ quyền hiện hữu; người dùng chỉ có vai trò thuộc phân hệ tạm ngừng sẽ thấy ít công việc trên Dashboard. Chưa mở rộng vai trò hoặc bỏ điều kiện bảo dưỡng/tổ bay/mặt đất để tạo kết quả thành công.

Không chạy migrate:fresh, db:wipe, TRUNCATE, DELETE không giới hạn, không tắt FK, không xóa migration/volume và không chạm production/database dùng chung. Toàn bộ source/view cũ được giữ để có thể mở lại sau một quyết định phạm vi mới.
