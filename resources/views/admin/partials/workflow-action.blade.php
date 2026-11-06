@php
$permission = match ($kind) {
    'master' => in_array($action, ['approve', 'publish']) ? 'master.approve' : 'master.write',
    'schedule' => in_array($action, ['approve', 'publish']) ? 'schedule.approve' : 'schedule.write',
    'maintenance' => in_array($action, ['approve', 'release']) ? 'maintenance.approve' : 'maintenance.write',
    'crew-approve' => 'crew.approve', 'crew-confirm' => auth()->user()->can('crew.approve') ? 'crew.approve' : 'crew.confirm',
    default => 'crew.write',
};
@endphp
@can($permission, [['self' => auth()->id()]])
<form action="{{ route('admin.workflows.act') }}" method="POST" class="d-inline-block my-1">
    @csrf
    <input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="id" value="{{ $id }}">
    <input type="hidden" name="action" value="{{ $action }}">
    <button class="btn btn-sm btn-outline-primary" type="submit">{{ $label }}</button>
</form>
@endcan
