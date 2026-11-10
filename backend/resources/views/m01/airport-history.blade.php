<section class="airport-card" id="airport-history">
    <div class="airport-card-heading"><h2>Lịch sử dữ liệu</h2><small class="subtle">{{ $history->total() }} cập nhật</small></div>
    @forelse($history as $entry)
        @include('m01.airport-history-entry',['entry'=>$entry])
    @empty<p class="subtle">Chưa có cập nhật hồ sơ.</p>@endforelse
    {{ $history->links('pagination::bootstrap-5') }}
    @if($systemHistory->total())
    <details class="airport-inline-detail mt-3" @if(request()->has('system_page')) open @endif><summary>Hoạt động hệ thống ({{ $systemHistory->total() }})</summary>
        @foreach($systemHistory as $event)
        @php($entry = (object) array_merge((array)$event,['kind'=>'event','status'=>'RECORDED','before_data'=>$event->before_state,'after_data'=>$event->after_state,'reason'=>null,'created_by'=>$event->actor_id,'approved_by'=>null,'occurred_at'=>$event->created_at]))
        @include('m01.airport-history-entry',['entry'=>$entry])
        @endforeach
        {{ $systemHistory->links('pagination::bootstrap-5') }}
    </details>
    @endif
</section>
