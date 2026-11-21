@php($workflow = \App\Support\StaffNavigation::context(auth()->user(), request()))
@if(!empty($workflow['links']))
    <nav class="workflow-links" aria-label="Các bước trong {{ $workflow['label'] }}">
        <span class="workflow-label">{{ $workflow['label'] }}</span>
        <div>
            @foreach($workflow['links'] as $step)
                @php($stepActive = \App\Support\StaffNavigation::isActive($step, request()))
                <a class="{{ $stepActive ? 'active' : '' }}" href="{{ $step['href'] }}" @if($stepActive) aria-current="page" @endif>{{ $step['label'] }}</a>
            @endforeach
        </div>
    </nav>
@endif
