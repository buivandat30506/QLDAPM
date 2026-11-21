@if($resource)
<p class="subtle">Tại {{ \App\Support\Ui::date($scheduleAt) }} (giờ Việt Nam): <strong>{{ $scheduledNow > 1 ? 'Có nhiều lịch chồng lấn' : ($scheduledNow === 1 ? 'Đang có lịch sử dụng' : 'Không có lịch tại thời điểm này') }}</strong>. Đây là dữ liệu kế hoạch, không xác nhận việc sử dụng thực tế hoặc khả dụng ở thời điểm khác.</p>
@endif
