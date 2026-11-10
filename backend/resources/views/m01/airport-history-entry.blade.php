@php
    $isProposal = $entry->kind === 'proposal';
    $who = $isProposal && $entry->status === 'APPROVED' ? $entry->approved_by : $entry->created_by;
    $before = \App\Support\AirportProfile::decode($entry->before_data);
    $after = \App\Support\AirportProfile::decode($entry->after_data);
    if(!$entry->created_by && in_array($entry->action,['airport.operational_status.simulated','airport.operational_status.simulation.cleared'],true)) {
        unset($before['operational_status_note'],$after['operational_status_note']);
    }
    $differences = \App\Support\AirportProfile::differences($before,$after);
    $outcome = ['PENDING'=>'Chờ duyệt','APPROVED'=>'Đã duyệt','REJECTED'=>'Từ chối','RECORDED'=>'Đã ghi nhận'][$entry->status] ?? $ui::label($entry->status);
@endphp
<article class="airport-history-row"><div>
    <strong>{{ \App\Support\AirportHistory::title($entry) }}</strong>
    <small>{{ $ui::date($entry->occurred_at) }} · {{ $who ? ($actors[$who] ?? 'Không còn thông tin người dùng') : 'Hệ thống' }} · {{ $outcome }}</small>
    <details><summary>Xem chi tiết</summary>
        @if($isProposal)<p class="mt-2">Người đề nghị: {{ $actors[$entry->created_by] ?? 'Không còn thông tin người dùng' }}</p>@endif
        @if($entry->reason)<p class="mt-2">Lý do: {{ \App\Support\ProductText::reason($entry->reason) }}</p>@endif
        @include('m01.airport-profile-diff',['differences'=>$differences])
        @if($canEdit && $isProposal && $entry->status === 'PENDING' && (int)$entry->created_by !== (int)auth()->id())
        <form class="mt-3" method="post" action="/workspace/actions/master_approve">@csrf<input type="hidden" name="change_id" value="{{ $entry->id }}"><button class="btn btn-outline-primary" type="submit">Duyệt đề nghị</button></form>
        @elseif($isProposal && $entry->status === 'PENDING')<p class="subtle mt-2">Chờ người khác có quyền phê duyệt.</p>@endif
    </details>
</div></article>
