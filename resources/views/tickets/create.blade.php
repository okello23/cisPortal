@extends('layouts.app', ['title' => 'Report ICT Issue'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="content-card bg-white p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted fw-semibold small mb-2">Public Support Portal</p>
                        <h1 class="h2 mb-1">Report an ICT issue</h1>
                        <p class="text-muted mb-0">No account is required. Provide either your email address or phone number so you can track progress later.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="row g-4">
                    @csrf
                    <div class="col-12">
                        <h2 class="h5">System Information</h2>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">System Name</label>
                        <select name="system_id" class="form-select" required id="system-select">
                            <option value="">Select system</option>
                            @foreach ($systems as $system)
                                <option value="{{ $system->id }}" @selected(old('system_id', $selectedSystem) == $system->id)>{{ $system->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Module Name</label>
                        <select name="module_id" class="form-select" id="module-select">
                            <option value="">Select module</option>
                            @foreach ($modules as $module)
                                <option value="{{ $module->id }}" data-system-id="{{ $module->system_id }}" @selected(old('module_id', $selectedModule) == $module->id)>{{ $module->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <h2 class="h5">User Information</h2>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="{{ old('full_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Region</label>
                        <select name="region_id" class="form-select">
                            <option value="">Select region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}" @selected(old('region_id') == $region->id)>{{ $region->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Facility</label>
                        <select name="facility_id" class="form-select">
                            <option value="">Select facility</option>
                            @foreach ($facilities as $facility)
                                <option value="{{ $facility->id }}" @selected(old('facility_id') == $facility->id)>{{ $facility->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Department/Unit</label>
                        <select name="department_id" class="form-select">
                            <option value="">Select department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <h2 class="h5">Ticket Information</h2>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Issue Type</label>
                        <select name="issue_type_id" class="form-select" required>
                            <option value="">Select issue type</option>
                            @foreach ($issueTypes as $issueType)
                                <option value="{{ $issueType->id }}" @selected(old('issue_type_id') == $issueType->id)>{{ $issueType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select name="priority_level_id" class="form-select" required>
                            <option value="">Select priority</option>
                            @foreach ($priorityLevels as $priorityLevel)
                                <option value="{{ $priorityLevel->id }}" @selected(old('priority_level_id') == $priorityLevel->id)>{{ $priorityLevel->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Issue Description</label>
                        <textarea name="description" rows="5" class="form-control" required>{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attachment/Screenshot</label>
                        <input type="file" name="attachment" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Source URL</label>
                        <input type="url" name="source_url" class="form-control" value="{{ old('source_url', url()->previous()) }}">
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-dark btn-lg rounded-pill px-4">Submit Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const systemSelect = document.getElementById('system-select');
        const moduleSelect = document.getElementById('module-select');
        const moduleOptions = Array.from(moduleSelect.querySelectorAll('option[data-system-id]'));

        function syncModules() {
            const systemId = systemSelect.value;
            moduleOptions.forEach(option => {
                option.hidden = systemId && option.dataset.systemId !== systemId;
            });
            if (moduleSelect.selectedOptions[0]?.hidden) {
                moduleSelect.value = '';
            }
        }

        systemSelect.addEventListener('change', syncModules);
        syncModules();
    </script>
@endpush
