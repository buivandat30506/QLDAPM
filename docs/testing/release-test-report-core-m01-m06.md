# Regression checkpoint M01–M06 — 18/10/2026

**Kết quả chưa phải nghiệm thu MySQL hoặc UAT.** Mục tiêu lượt này là hồi quy nghiệp vụ M01–M06 sau các thay đổi trong worktree.

| Kiểm tra | Kết quả thực chạy | Bằng chứng |
|---|---|---|
| `php vendor/bin/phpunit --colors=never --filter='(FoundationWorkflowTest|FlightPlanningTest|FlightOperationsTest|FleetManagementTest|CrewWorkflowTest|MaintenanceWorkflowTest|DiagramWorkflowTest|Module1Test)' --log-junit=docs/testing/evidence/2026-10-18-core/sqlite.xml` | 95 tests; 93 passed, 2 skipped, 1,075 assertions; exit 0; 25.706s | [JUnit](evidence/2026-10-18-core/sqlite.xml), [console](evidence/2026-10-18-core/sqlite.txt) |
| `npm run build` | Vite build passed; 330ms | Terminal output phiên 18/10; build artifacts trong `public/build` |
| `php scripts/lint.php` | 229 PHP files, 0 syntax failures | Terminal output phiên 18/10 |
| `php scripts/verify-traceability.php` | 48 UC / 16 modules / source flows verified structurally | Terminal output phiên 18/10 |
| `php scripts/verify-api-docs.php`; `php scripts/verify-api-routes.php` | 82 OpenAPI operations parse; all 82 map to Laravel routes | Terminal output phiên 18/10 |
| `php artisan view:clear`; `php artisan view:cache`; `git diff --check` | Passed | Terminal output phiên 18/10 |

Runtime: Windows, PHP 8.2.12, Laravel 12.69.2, PHPUnit 11.5.56, SQLite feature-test profile. Test set includes foundation/master data, flight planning, flight operations, fleet/usage, crew and maintenance. SQLite does not verify row locks, MySQL CHECK constraints, or race behavior; the 2 relevant cases are skipped by design.

MySQL integration/browser acceptance **Not Run** this checkpoint. MySQL Community on 3306 requires credentials not present in the current local config; port 3307 is the XAMPP MariaDB service, which is not accepted as MySQL evidence. No migration was run against the application DB. Earlier MySQL/browser reports belong to earlier builds and are not presented as evidence for this worktree.

Source revision at run: `0bd32bc5b774c70c8cc5d28ff39e7238169fa74a`, dirty worktree. No commit was created by this checkpoint.
