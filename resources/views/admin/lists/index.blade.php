@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="content-card bg-white p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h1 class="h4 mb-0">{{ $title }}</h1>
                    @if ($listKey === 'facilities')
                        <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('lists.facilities.sync') ? route('lists.facilities.sync') : url('/lists/facilities/sync') }}">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill">Sync From IRRDS</button>
                        </form>
                    @endif
                </div>

                @if ($listKey === 'facilities')
                    <div class="alert alert-info rounded-4">
                        Facility records can be synchronized from the IRRDS facility API. You can still add or maintain records manually if needed.
                    </div>
                @endif

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
                    @if ($listKey === 'facilities')
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Facility</th>
                                    <th>Region</th>
                                    <th>District</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $record->name }}</div>
                                            <div class="small text-muted">{{ $record->code }}</div>
                                            @if ($record->nhlds_uuid)
                                                <div class="small text-muted">NHLDS: {{ $record->nhlds_uuid }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $record->region?->name ?? 'Unmapped' }}</td>
                                        <td>{{ $record->district_name ?? 'N/A' }}</td>
                                        <td>{{ $record->facility_type ?? 'N/A' }}</td>
                                        <td>{{ strtoupper($record->source_system ?? 'manual') }}</td>
                                        <td>{{ $record->active ? 'Active' : 'Inactive' }}</td>
                                        <td>@include('admin.lists.partials.toggle-form', ['record' => $record])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <table class="table align-middle">
                            <thead><tr><th>Name</th><th>Code</th><th>Status</th><th>Update</th></tr></thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>{{ $record->name }}</td>
                                        <td>{{ $record->code }}</td>
                                        <td>{{ $record->active ? 'Active' : 'Inactive' }}</td>
                                        <td>@include('admin.lists.partials.toggle-form', ['record' => $record])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
