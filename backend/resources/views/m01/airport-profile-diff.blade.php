<div class="table-responsive"><table class="table airport-table"><thead><tr><th>Thông tin</th><th>{{ $beforeLabel ?? 'Trước thay đổi' }}</th><th>{{ $afterLabel ?? 'Sau thay đổi' }}</th></tr></thead><tbody>
@forelse($differences as $difference)<tr><td>{{ $difference['label'] }}</td><td>{{ $difference['before'] }}</td><td>{{ $difference['after'] }}</td></tr>
@empty<tr><td colspan="3">Không có nội dung thay đổi được ghi nhận.</td></tr>@endforelse
</tbody></table></div>
