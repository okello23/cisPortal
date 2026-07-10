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
    @elseif ($listKey === 'alis-key-update-reasons')
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="content-card bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h1 class="h4 mb-0">{{ $title }}</h1>
                        @if ($editingRecord)
                            <a href="{{ route('lists.index', $listKey) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Cancel Edit</a>
                        @endif
                    </div>

                    <p class="text-muted mb-4">Manage the predefined reasons staff select whenever an A-LIS backup SSH key is updated.</p>

                    <form method="POST" action="{{ $editingRecord ? route('lists.update', [$listKey, $editingRecord->id]) : route('lists.store', $listKey) }}" class="row g-3">
                        @csrf
                        @if ($editingRecord)
                            @method('PUT')
                        @endif
                        <div class="col-12">
                            <label class="form-label">Reason Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $editingRecord?->name) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control">{{ old('description', $editingRecord?->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="active" name="active" value="1" @checked(old('active', $editingRecord?->active ?? true))>
                                <label class="form-check-label" for="active">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-dark rounded-pill px-4">{{ $editingRecord ? 'Update Reason' : 'Save Reason' }}</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="content-card bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 mb-0">Saved Reasons</h2>
                        <span class="small text-muted">{{ $records->total() }} total</span>
                    </div>

                    <div class="table-responsive border rounded-4 overflow-hidden">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th class="text-end">Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $record->name }}</div>
                                            <div class="small text-muted">{{ $record->description ?: 'No description' }}</div>
                                        </td>
                                        <td>{{ $record->active ? 'Active' : 'Inactive' }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                <a href="{{ route('lists.index', [$listKey, 'edit' => $record->id]) }}" class="btn btn-sm btn-outline-dark rounded-pill">Edit</a>
                                                @include('admin.lists.partials.toggle-form', ['record' => $record])
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $records->links() }}
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
