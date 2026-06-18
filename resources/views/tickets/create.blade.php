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
                        <label class="form-label">System Name <span class="text-danger">*</span></label>
                        <select name="system_id" class="form-select" required id="system-select">
                            <option value="">Select system</option>
                            @foreach ($systems as $system)
                                <option value="{{ $system->id }}" @selected(old('system_id', $selectedSystem) == $system->id)>{{ $system->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <h2 class="h5">User Information</h2>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Full Name (Reported By) <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="{{ old('full_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Region</label>
                        <select name="region_id" class="form-select" id="region-select">
                            <option value="">Select region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}" @selected(old('region_id') == $region->id)>{{ $region->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">District</label>
                        <select name="district_name" class="form-select" id="district-select">
                            <option value="">Select district</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Facility</label>
                        <select name="facility_id" class="form-select" id="facility-select">
                            <option value="">Select facility</option>
                            @foreach ($facilities as $facility)
                                <option
                                    value="{{ $facility->id }}"
                                    data-region-id="{{ $facility->region_id }}"
                                    data-district-name="{{ $facility->district_name }}"
                                    @selected(old('facility_id') == $facility->id)
                                >
                                    {{ $facility->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <h2 class="h5">Ticket Information</h2>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Issue Type <span class="text-danger">*</span></label>
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
                        <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                        <textarea name="description" rows="5" class="form-control" required>{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attachment/Screenshot</label>
                        <input type="file" name="attachment" class="form-control">
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
        const regionSelect = document.getElementById('region-select');
        const districtSelect = document.getElementById('district-select');
        const facilitySelect = document.getElementById('facility-select');
        const facilityOptions = Array.from(facilitySelect.querySelectorAll('option[data-region-id]'));
        const selectedDistrict = @json(old('district_name'));

        function buildDistrictOptions() {
            const regionId = regionSelect.value;
            if (!regionId) {
                districtSelect.innerHTML = '<option value="">Select district</option>';
                districtSelect.disabled = true;
                return;
            }

            const districtNames = [...new Set(
                facilityOptions
                    .filter(option => option.dataset.regionId === regionId)
                    .map(option => option.dataset.districtName)
                    .filter(Boolean)
            )].sort((a, b) => a.localeCompare(b));

            const currentDistrict = districtSelect.value || selectedDistrict;
            districtSelect.innerHTML = '<option value="">Select district</option>';

            districtNames.forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                if (name === currentDistrict) {
                    option.selected = true;
                }
                districtSelect.appendChild(option);
            });

            districtSelect.disabled = districtNames.length === 0;
        }

        function syncFacilities() {
            const regionId = regionSelect.value;
            const districtName = districtSelect.value;

            if (!regionId) {
                facilityOptions.forEach(option => {
                    option.hidden = true;
                });
                facilitySelect.value = '';
                facilitySelect.disabled = true;
                return;
            }

            facilityOptions.forEach(option => {
                const matchesRegion = option.dataset.regionId === regionId;
                const matchesDistrict = !districtName || option.dataset.districtName === districtName;
                option.hidden = !(matchesRegion && matchesDistrict);
            });

            if (facilitySelect.selectedOptions[0]?.hidden) {
                facilitySelect.value = '';
            }

            facilitySelect.disabled = facilityOptions.filter(option => !option.hidden).length === 0;
        }

        function syncLocationCascade() {
            buildDistrictOptions();
            syncFacilities();
        }

        systemSelect.addEventListener('change', syncModules);
        regionSelect.addEventListener('change', () => {
            districtSelect.value = '';
            syncLocationCascade();
        });
        districtSelect.addEventListener('change', syncFacilities);

        syncLocationCascade();
    </script>
@endpush
