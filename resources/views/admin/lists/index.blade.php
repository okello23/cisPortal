@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="content-card bg-white p-4">
                <h1 class="h4 mb-3">{{ $title }}</h1>
                <form method="POST" action="{{ route('lists.store', $listKey) }}" class="row g-3">
                    @csrf
                    @include('admin.lists.partials.form-fields')
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="content-card bg-white p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Name</th><th>Code</th><th>Status</th><th>Update</th></tr></thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td>{{ $record->name }}</td>
                                    <td>{{ $record->code }}</td>
                                    <td>{{ $record->active ? 'Active' : 'Inactive' }}</td>
                                    <td>
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
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
