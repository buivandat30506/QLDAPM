<?php

$root = dirname(__DIR__);
$requirements = json_decode(file_get_contents($root.'/docs/requirements-source.json'), true, 512, JSON_THROW_ON_ERROR)['use_cases'];
$access = require $root.'/config/access.php';
$rules = [];
foreach ($requirements as $uc) {
    foreach (['Business Rule' => 'BR', 'Non-Functional' => 'NFR'] as $field => $prefix) {
        preg_match_all('/'.$prefix.'-\d+/', $uc['attributes'][$field] ?? '', $matches);
        foreach (array_unique($matches[0]) as $id) {
            $rules[$id]['ucs'][] = $uc['uc_id'];
            if (preg_match('/'.preg_quote($id, '/').':[ \t]*([^\r\n]+)/u', $uc['attributes'][$field], $definition)) {
                $rules[$id]['known'][] = trim($definition[1]);
            }
        }
    }
}
ksort($rules);
$markdown = "# Đăng ký BR/NFR\n\nTrích mã và định nghĩa có thật ở phụ lục A; không suy ra nguyên văn từ một mã tham chiếu. Ngưỡng tại config/operations.php là giả định minh họa, không phải quy định khai thác.\n\n| Mã | UC nguồn | Nội dung biết được từ nguồn | Phần thiếu / xử lý | Kiểm thử |\n| --- | --- | --- | --- | --- |\n";
foreach ($rules as $id => $rule) {
    $known = implode(' / ', array_unique($rule['known'] ?? [])) ?: 'Nguồn chỉ tham chiếu mã.';
    $markdown .= '| '.$id.' | '.implode(', ', array_unique($rule['ucs'])).' | '.str_replace('|', '/', $known).' | Chưa có danh mục đầy đủ/ngưỡng định lượng; xác minh nghiệp vụ trước cấu hình thật. | Xem traceability.csv và baseline-mapping.csv; chưa bao phủ toàn bộ. |'.PHP_EOL;
}
$markdown .= "\nCác quy tắc kỹ thuật được diễn giải (DERIVED): global/airport/flight/self gắn từng vai trò; delegation [start,end), không dây chuyền, mất hiệu lực khi grantor mất quyền; API trả 404 với đối tượng ngoài scope; CSV prefix apostrophe cho công thức; redact PII/secret đệ quy; audit correlation server sinh; token API hết hạn 8 giờ. Đây là lựa chọn triển khai được ghi trong decisions.md, không gán mã BR/NFR giả.\n";
file_put_contents($root.'/docs/rules-register.md', $markdown);
$matrix = fopen($root.'/docs/permissions-matrix.csv', 'w');
fputcsv($matrix, ['role', 'uc_id', 'action', 'read_write_approve_export', 'data_scope', 'status', 'permission_code']);
foreach ($access['permissions'] as $permission => [$ucRefs, $action, $roles]) {
    foreach (explode(';', $ucRefs) as $ucId) {
        foreach ($roles as $role) {
            $scope = str_starts_with($permission,'airport.') ? 'global;airport (M01 object checks and filtered collections)'
                : (in_array($role, ['CUSTOMER', 'CREW_MEMBER']) ? 'self' : (in_array($role, ['AIRPORT_STAFF']) ? 'airport;flight;global' : 'global (other legacy core scopes fail closed)'));
            fputcsv($matrix, [$role, $ucId, $action, $action, $scope, 'Implemented grant; workflow may be Partial', $permission]);
        }
    }
}
foreach ($requirements as $uc) {
    foreach ($access['roles'] as $role => $name) {
        if ($role === 'CREW_MEMBER' || ! str_contains($uc['attributes']['Actor'], $name)) { continue; }
        $hasPermission = false;
        foreach ($access['permissions'] as [$refs, $action, $roles]) {
            if (in_array($uc['uc_id'], explode(';', $refs), true) && in_array($role, $roles, true)) { $hasPermission = true; break; }
        }
        if (! $hasPermission) {
            fputcsv($matrix, [$role, $uc['uc_id'], 'Source workflow (planned)', 'Pending decomposition', $role === 'CUSTOMER' ? 'self; no staff actions' : 'Pending airport/unit/flight assignment', 'Planned; default deny in code', '']);
        }
    }
}
fclose($matrix);
echo 'BR/NFR and permissions registers generated from exact source plus explicitly marked implementation assumptions.'.PHP_EOL;
