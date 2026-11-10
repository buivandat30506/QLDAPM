@if(!empty($airport->operational_status_updated_at))
    <time class="airport-status-updated" datetime="{{ \Carbon\Carbon::parse($airport->operational_status_updated_at)->toIso8601String() }}">Cập nhật: {{ \App\Support\Ui::date($airport->operational_status_updated_at) }}</time>
@else
    <span class="airport-status-updated">Chưa cập nhật</span>
@endif
