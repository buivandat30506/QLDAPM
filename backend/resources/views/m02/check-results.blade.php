<section class="card-surface"><h2>Kết quả kiểm tra và cảnh báo</h2>
@if($checked===null)<p class="text-muted">Chọn phạm vi rồi bấm “Kiểm tra và cảnh báo”. Chưa chạy kiểm tra.</p>
@else
<p>Đã kiểm tra <strong>{{ $checked['count'] }} / {{ $checked['total'] }}</strong> chuyến · Chưa kiểm tra: <strong>{{ $checked['remaining'] }}</strong>.</p>
<progress class="m02-progress" value="{{ $checked['count'] }}" max="{{ max(1,$checked['total']) }}" aria-label="Tiến độ kiểm tra"></progress>
@if($checked['remaining'])<form method="post" action="/workspace/planning/checks/{{ $checked['token'] }}/continue" class="m02-actions">@csrf<button class="btn btn-primary">Kiểm tra tiếp {{ min(100,$checked['remaining']) }} chuyến</button><span class="text-muted">Các chuyến chưa kiểm tra chưa có kết luận.</span></form>@endif
@php($selectedGroup=request('group','all'))
<nav class="m02-check-groups" aria-label="Nhóm kết quả">
@foreach(['all'=>'Tất cả đã kiểm tra','conflict'=>'Xung đột','condition'=>'Lỗi điều kiện','warning'=>'Cảnh báo','valid'=>'Hợp lệ'] as $key=>$label)
<a href="{{ '/workspace/planning?'.http_build_query($checked['filters']+['tab'=>'conflicts','check'=>$checked['token'],'group'=>$key]) }}" @if($selectedGroup===$key) aria-current="page" @endif>{{ $label }} <strong>{{ $key==='all'?$checked['count']:$checked['flight_counts'][$key] }}</strong><small>{{ $key==='all'||$key==='valid'?'chuyến':($checked['metrics'][$key].' vấn đề') }}</small></a>
@endforeach</nav>
<small>Kết quả theo từng chuyến tại thời điểm kiểm tra; một chuyến có thể thuộc nhiều nhóm. Bản kiểm tra lưu trong một giờ. Khi gửi duyệt và công bố luôn kiểm tra lại.</small>
<div class="m02-table"><table><thead><tr><th>Chuyến / thời điểm kiểm tra</th><th>Tàu bay / lịch liên quan</th><th>Khoảng thời gian (UTC)</th><th>Phân loại / nguyên nhân</th></tr></thead><tbody>
@php($displayed=0)
@foreach($checkRows as $result)
@php($matched=$selectedGroup==='all'||($selectedGroup==='valid'?!$result['issues']:collect($result['issues'])->contains('category',$selectedGroup)))
@if($matched)@php($displayed++)<tr><td><a href="/workspace/planning/flights/{{ $result['id'] }}">{{ $result['number'] }}</a><small>{{ $result['checked_at'] }}</small></td><td>@if($result['issues']){{ $result['issues'][0]['aircraft'] }}@else{{ $aircraft->firstWhere('id',$result['aircraft_id'])?->registration }}@endif
@foreach($result['issues'] as $i)@if($i['related_number'])<small>@if($i['related_id'])<a href="/workspace/planning/flights/{{ $i['related_id'] }}">{{ $i['related_number'] }}</a>@else{{ $i['related_number'] }}@endif</small>@endif @endforeach</td><td>{{ $result['departure'] }}<small>đến {{ $result['arrival'] }}</small></td><td>
@forelse($result['issues'] as $i)@if($selectedGroup==='all'||$selectedGroup===$i['category'])<p><span class="m02-badge {{ $i['severity']==='blocking'?'danger':'warning' }}">{{ ['conflict'=>'Xung đột','condition'=>'Lỗi điều kiện','warning'=>'Cảnh báo'][$i['category']] }} · {{ $i['severity']==='blocking'?'Chặn thao tác':'Cần chú ý' }}</span><br>{{ $i['reason'] }}</p>@endif @empty<span class="m02-badge good">Hợp lệ trong phạm vi đã kiểm tra</span>@endforelse
<small><a href="/workspace/planning/flights/{{ $result['id'] }}">Xem chi tiết và thao tác theo quyền →</a></small></td></tr>@endif
@endforeach
@if(!$displayed)<tr><td colspan="4">Không có chuyến đã kiểm tra thuộc nhóm này.</td></tr>@endif
</tbody></table></div>{{ $checkRows->links('m02.pagination') }}
@endif
<p class="text-muted">Kiểm tra tàu bay, khoảng quay đầu 30 phút và lịch giữ tàu theo quy tắc hiện có. Công bố kiểm tra lại cả tổ bay và cổng.</p></section>
