<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label">Region <span class="text-danger">*</span></label>
        <select
            name="region_id"
            class="form-select js-location-select"
            wire:key="region-select-{{ $regionId }}"
            data-model="regionId"
            data-placeholder="Select region"
            required
        >
            <option value="">Select region</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}">{{ $region->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">District <span class="text-danger">*</span></label>
        <select
            name="district_name"
            class="form-select js-location-select"
            wire:key="district-select-{{ $regionId }}-{{ md5($districtName) }}"
            data-model="districtName"
            data-placeholder="Select district"
            @disabled($regionId === '')
            required
        >
            <option value="">Select district</option>
            @foreach ($districtOptions as $district)
                <option value="{{ $district }}">{{ $district }}</option>
            @endforeach
        </select>
        <div class="form-text">
            {{ $regionId === '' ? '' : 'District options are filtered by the selected' }}
        </div>
    </div>

    <div class="col-md-12">
        <label class="form-label">Health Facility Name <span class="text-danger">*</span></label>
        <select
            name="facility_id"
            class="form-select js-location-select"
            wire:key="facility-select-{{ $regionId }}-{{ md5($districtName) }}-{{ $facilityId }}"
            data-model="facilityId"
            data-placeholder="Select facility"
            @disabled($districtName === '')
            required
        >
            <option value="">Select facility</option>
            @foreach ($facilityOptions as $facility)
                <option value="{{ $facility->id }}">{{ $facility->name }}</option>
            @endforeach
        </select>
        <div class="form-text">
            {{ $districtName !== '' ? 'Facilities are filtered to the selected district.' : 'Choose a district first.' }}
        </div>
    </div>
</div>
