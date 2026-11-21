@php($differences = \App\Support\InfrastructureChangeDisplay::rows($change))
<details class="mt-2"><summary>Xem nội dung thay đổi</summary>
<div class="table-responsive"><table class="table airport-table"><thead><tr><th>Thông tin</th><th>Trước thay đổi</th><th>Đề nghị</th></tr></thead><tbody>@forelse($differences as $difference)<tr><td>{{ $difference['label'] }}</td><td>{{ $difference['before'] }}</td><td>{{ $difference['after'] }}</td></tr>@empty<tr><td colspan="3">Không có thay đổi thông tin hồ sơ.</td></tr>@endforelse</tbody></table></div>
</details>
