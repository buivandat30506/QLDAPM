@php
    $badge = isset($available)
        ? ['label' => $available ? 'Khả dụng' : 'Không khả dụng', 'tone' => $available ? 'normal' : 'unavailable', 'description' => 'Trạng thái hiệu lực của danh mục; không phản ánh lịch chiếm dụng.']
        : \App\Support\AirportWorkspace::status($status ?? null);
@endphp
<span class="airport-status airport-status--{{ $badge['tone'] }}"><span aria-hidden="true"></span>{{ $badge['label'] }}</span>
