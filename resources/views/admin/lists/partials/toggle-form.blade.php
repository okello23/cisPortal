<form method="POST" action="{{ route('lists.update', [$listKey, $record->id]) }}" class="row g-2">
    @csrf
    @method('PUT')
    <input type="hidden" name="name" value="{{ $record->name }}">
    <input type="hidden" name="code" value="{{ $record->code }}">
    <input type="hidden" name="description" value="{{ $record->description }}">
    <input type="hidden" name="sort_order" value="{{ $record->sort_order }}">
    @if (array_key_exists('sla_hours', $record->getAttributes()))
        <input type="hidden" name="sla_hours" value="{{ $record->sla_hours }}">
    @endif
    @if (array_key_exists('color', $record->getAttributes()))
        <input type="hidden" name="color" value="{{ $record->color }}">
    @endif
    @if (array_key_exists('system_id', $record->getAttributes()))
        <input type="hidden" name="system_id" value="{{ $record->system_id }}">
    @endif
    @if (array_key_exists('region_id', $record->getAttributes()))
        <input type="hidden" name="region_id" value="{{ $record->region_id }}">
    @endif
    <input type="hidden" name="active" value="{{ $record->active ? 0 : 1 }}">
    <button class="btn btn-sm {{ $record->active ? 'btn-outline-danger' : 'btn-outline-success' }} rounded-pill">
        {{ $record->active ? 'Deactivate' : 'Activate' }}
    </button>
</form>
