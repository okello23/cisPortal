@extends('layouts.app', ['title' => $title])

@section('content')
    @if ($listKey === 'facilities')
        <div class="content-card bg-white p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h1 class="h3 mb-1">{{ $title }}</h1>
                    <p class="text-muted mb-0">Facility records can be synchronized from the IRRDS facility API, or added manually when needed.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ url('/lists/facilities/sync') }}">
                        @csrf
                        @method('PUT')
                        <button class="btn btn-outline-dark rounded-pill px-4">Sync Facilities</button>
                    </form>
                    <button class="btn btn-dark rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addFacilityModal">Add New Facility</button>
                </div>
            </div>

            <div class="table-responsive">
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
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $records->links() }}
            </div>
        </div>

        <div class="modal fade" id="addFacilityModal" tabindex="-1" aria-labelledby="addFacilityModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 px-4 pt-4">
                        <div>
                            <h2 class="modal-title h4 mb-1" id="addFacilityModalLabel">Add New Facility</h2>
                            <p class="text-muted mb-0">Create a facility record manually.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 pb-4">
                        <form method="POST" action="{{ route('lists.store', $listKey) }}" class="row g-3">
                            @csrf
                            @include('admin.lists.partials.form-fields')
                            <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-dark rounded-pill px-4">Save Facility</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="content-card bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h1 class="h4 mb-0">{{ $title }}</h1>
                    </div>

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
                                        <td>@include('admin.lists.partials.toggle-form', ['record' => $record])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
