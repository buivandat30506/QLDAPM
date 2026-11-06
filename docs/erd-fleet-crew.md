# Mở rộng ERD M04/M06

Sáu bảng mới bổ sung, giữ nguyên 20 thực thể nền. Các FK mới restrict delete; snapshot cấu hình/event không có route sửa/xóa. Sơ đồ chỉ mô tả phần đã triển khai tại checkpoint tài nguyên, không đại diện schema của các module chưa làm.

```mermaid
erDiagram
    AIRPORTS ||--o{ AIRCRAFTS : base_airport_code
    AIRCRAFT_MODELS ||--o{ AIRCRAFTS : model_id
    AIRCRAFTS ||--o{ AIRCRAFT_CONFIGURATIONS : aircraft_tail
    AIRCRAFT_CONFIGURATIONS o|--o{ OPERATIONAL_FLIGHTS : pinned_configuration
    AIRCRAFTS ||--o{ OPERATIONAL_FLIGHTS : aircraft_tail
    OPERATIONAL_FLIGHTS ||--o| AIRCRAFT_USAGE_RECORDS : unique_flight_id
    AIRCRAFT_USAGE_RECORDS ||--o{ AIRCRAFT_USAGE_RECEIPTS : deduplicated_aliases
    AIRCRAFT_USAGE_RECORDS ||--o| AIRCRAFT_USAGE_ALERTS : threshold_snapshot
    AIRPORTS ||--o{ CREW_MEMBERS : base_airport_code
    CREW_MEMBERS ||--o{ CREW_AVAILABILITY_WINDOWS : crew_id
    CREW_MEMBERS ||--o{ FLIGHT_CREW_ASSIGNMENTS : crew_id
    OPERATIONAL_FLIGHTS ||--o{ FLIGHT_CREW_ASSIGNMENTS : flight_id
    CREW_RULE_VERSIONS o|--o{ FLIGHT_CREW_ASSIGNMENTS : rule_version_code
    FLIGHT_CREW_ASSIGNMENTS ||--o{ CREW_ASSIGNMENT_EVENTS : immutable_snapshots
    FLIGHT_CREW_ASSIGNMENTS ||--o{ CREW_NOTIFICATIONS : internal_delivery
    USERS o|--o{ CREW_MEMBERS : linked_identity
```

`aircrafts.current_configuration_id` trỏ về bản hiện hành; mỗi flight giữ riêng `aircraft_configuration_id` và `seat_capacity_snapshot`. `source/external_id` unique trên receipts; unique flight_id của ledger bảo vệ số dư kể cả nguồn gửi alias khác nhau. Crew assignment có approved_by/confirmed_by nullable cho legacy/CLI và SELF/OPERATOR cho xác nhận mới; không tự bịa actor cho dữ liệu cũ.
