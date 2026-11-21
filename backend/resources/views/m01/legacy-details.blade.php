@if($legacyArea && !empty($legacyArea->metadata))
<section class="airport-card"><h2>Thông tin bổ sung từ hồ sơ khu vực</h2>@include('m01.value-list', ['values' => json_decode($legacyArea->metadata, true) ?? []])</section>
@endif
