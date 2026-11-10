@php($state = \App\Support\InfrastructureDisplay::state((bool)$effective, $condition ?? null))
<span class="airport-status airport-status--{{ $state['tone'] }}" title="{{ $state['description'] }}"><span aria-hidden="true"></span>{{ $state['label'] }}</span>
