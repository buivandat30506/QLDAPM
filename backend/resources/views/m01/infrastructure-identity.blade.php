@php($displayName = \App\Support\InfrastructureDisplay::name($item))
@if(\App\Modules\Shared\Access::allows(auth()->user(), 'data_admin'))
<a class="airport-name" href="{{ \App\Modules\Operations\InfrastructureRepository::url($item, $context ?? ['flow'=>'infrastructure', 'airport'=>$item->airport_code]) }}"><strong>{{ $displayName }}</strong></a>
@else
<strong>{{ $displayName }}</strong>
@endif
<small class="d-block subtle infrastructure-code">{{ $item->code }}</small>
