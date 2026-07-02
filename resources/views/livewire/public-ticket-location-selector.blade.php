<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label">Region</label>
        <select name="region_id" class="form-select js-location-select" wire:model.live="regionId" data-placeholder="Select region">
            <option value="">Select region</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}">{{ $region->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">District</label>
        <select
            name="district_name"
            class="form-select js-location-select"
            wire:model.live="districtName"
            data-placeholder="Select district"
            @disabled($regionId === '')
        >
            <option value="">Select district</option>
            @foreach ($districtOptions as $district)
                <option value="{{ $district }}">{{ $district }}</option>
            @endforeach
        </select>
        <div class="form-text">
            {{ $regionId === '' ? 'Choose a region to load districts.' : 'District options are filtered by the selected region.' }}
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Facility</label>
        <select
            name="facility_id"
            class="form-select js-location-select"
            wire:model.live="facilityId"
            data-placeholder="Select facility"
            @disabled($regionId === '')
        >
            <option value="">Select facility</option>
            @foreach ($facilityOptions as $facility)
                <option value="{{ $facility->id }}">{{ $facility->name }}</option>
            @endforeach
        </select>
        <div class="form-text">
            {{ $districtName !== '' ? 'Facilities are filtered to the selected district.' : 'Select a district to narrow the facilities further.' }}
        </div>
    </div>
</div>
