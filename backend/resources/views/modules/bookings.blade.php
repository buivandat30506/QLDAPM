@extends('modules._shell')
@section('module-body')
<section class="card-surface mb-4">
    <h2>Đặt chỗ gần nhất</h2>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mã đặt chỗ</th><th>Chuyến bay</th><th>Trạng thái</th><th>Giá trị</th></tr></thead><tbody>
        @forelse($bookings as $booking)
            <tr><td>{{ $booking->reference }}</td><td>{{ $booking->flight ? \App\Support\Ui::flight($booking->flight) : 'Chưa có thông tin chuyến' }}</td><td>{{ \App\Support\Ui::label($booking->status) }}</td><td>{{ \App\Support\Ui::money($booking->amount) }}</td></tr>
        @empty<tr><td colspan="4" class="subtle">Chưa có đặt chỗ trong phạm vi được xem.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="card-surface">
    <h2>Hồ sơ đặt chỗ theo hành trình</h2>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mã hồ sơ</th><th>Loại hành trình</th><th>Trạng thái</th><th>Tổng tiền</th></tr></thead><tbody>
        @forelse($reservations as $reservation)
            <tr><td>{{ $reservation->reference }}</td><td>{{ $reservation->trip_type === 'ROUND_TRIP' ? 'Khứ hồi' : 'Một chiều' }}</td><td>{{ \App\Support\Ui::label($reservation->status) }}</td><td>{{ \App\Support\Ui::money($reservation->total_amount) }}</td></tr>
        @empty<tr><td colspan="4" class="subtle">Chưa có hồ sơ đặt chỗ.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
