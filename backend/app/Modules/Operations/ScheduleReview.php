<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Shared\Access;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ScheduleReview
{
    private function scope(User $u): string
    {
        return $u->id.':'.$u->role.':'.$u->airport_code;
    }

    public function start(User $u, array $ids, array $filters): array
    {
        Access::role($u, 'operations', 'ops_manager');
        $token = (string) Str::uuid();
        Cache::put('m02-review:'.$token, ['scope' => $this->scope($u), 'ids' => array_values(array_unique(array_map('intval', $ids))), 'filters' => $filters, 'results' => [], 'started_at' => now()->toIso8601String()], now()->addHour());

        return $this->advance($u, $token);
    }

    public function read(User $u, string $token): array
    {
        Access::role($u, 'operations', 'ops_manager');
        abort_unless(Str::isUuid($token), 404);
        $run = Cache::get('m02-review:'.$token) ?? abort(410, 'Lượt kiểm tra đã hết hạn; hãy chạy kiểm tra mới.');
        abort_unless($run['scope'] === $this->scope($u), 403);
        $issues = [];
        $metrics = ['conflict' => 0, 'condition' => 0, 'warning' => 0, 'valid' => 0];
        $flightCounts = $metrics;
        foreach ($run['results'] as $result) {
            $categories = [];
            foreach ($result['issues'] as $i) {
                $issues[] = $i + ['flight_number' => $result['number']];
                $category = $i['category'] ?? 'condition';
                $metrics[$category]++;
                $categories[$category] = true;
            }if (! $categories) {
                $metrics['valid']++;
                $categories['valid'] = true;
            }foreach (array_keys($categories) as $c) {
                $flightCounts[$c]++;
            }
        }

        return $run + ['token' => $token, 'total' => count($run['ids']), 'count' => count($run['results']), 'remaining' => count($run['ids']) - count($run['results']), 'issues' => $issues, 'metrics' => $metrics, 'flight_counts' => $flightCounts];
    }

    public function advance(User $u, string $token): array
    {
        $this->read($u, $token);

        $result = Cache::lock('m02-review-lock:'.$token, 60)->get(function () use ($u, $token) {
            $run = Cache::get('m02-review:'.$token) ?? abort(410);
            $pending = array_values(array_diff($run['ids'], array_map('intval', array_keys($run['results']))));
            foreach (array_slice($pending, 0, 100) as $id) {
                $f = app(SchedulePlanning::class)->flight($u, $id);
                $run['results'][$id] = ['id' => $id, 'number' => $f->number, 'aircraft_id' => $f->aircraft_id, 'origin' => $f->origin, 'destination' => $f->destination, 'departure' => $f->departure, 'arrival' => $f->arrival, 'issues' => app(ScheduleConflicts::class)->inspect($f, true, $u), 'checked_at' => now()->toIso8601String()];
            }
            Cache::put('m02-review:'.$token, $run, now()->addHour());

            return $this->read($u, $token);
        });
        abort_if($result === false, 409, 'Lượt kiểm tra đang được xử lý; hãy thử lại sau.');

        return $result;
    }
}
