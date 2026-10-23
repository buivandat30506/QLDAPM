<div class="row g-3">
@if(!$airport)<div class="col-md-3"><label for="iata_code" class="form-label">Mã IATA</label><input id="iata_code" name="iata_code" maxlength="3" pattern="[A-Za-z]{3}" class="form-control" required value="{{ old('iata_code') }}"></div>@endif
@foreach(['icao_code'=>'Mã ICAO','name'=>'Tên sân bay','city'=>'Thành phố','country'=>'Quốc gia','timezone'=>'Múi giờ (mã IANA)','operating_hours'=>'Giờ hoạt động','latitude'=>'Vĩ độ','longitude'=>'Kinh độ'] as $field=>$label)
<div class="col-md-{{ $field==='name' ? '6' : '3' }}"><label class="form-label" for="{{ $field }}">{{ $label }}</label>
<input id="{{ $field }}" name="{{ $field }}" class="form-control" value="{{ old($field, $airport?->{$field} ?? ['country'=>'Vietnam','timezone'=>'Asia/Ho_Chi_Minh','operating_hours'=>'24/7'][$field] ?? '') }}"
@if(in_array($field,['latitude','longitude'])) type="number" step="0.0000001" min="{{ $field==='latitude' ? -90 : -180 }}" max="{{ $field==='latitude' ? 90 : 180 }}" @else type="text" @endif
@if(in_array($field,['name','city','country','timezone','operating_hours'])) required @endif></div>
@endforeach
<div class="col-md-3"><label class="form-label" for="airport-status">Trạng thái sân bay</label><select class="form-select" id="airport-status" name="status">@foreach(['ACTIVE'=>'Hoạt động','INACTIVE'=>'Tạm ngừng','CLOSED'=>'Đóng cửa'] as $v=>$label)<option value="{{ $v }}" @selected(old('status', $airport?->status ?? 'ACTIVE')===$v)>{{ $label }}</option>@endforeach</select></div>
</div>
<p class="form-text">IATA là mã sân bay 3 chữ cái; ICAO là mã sân bay 4 chữ cái. Múi giờ dùng mã IANA, ví dụ Asia/Ho_Chi_Minh.</p>
