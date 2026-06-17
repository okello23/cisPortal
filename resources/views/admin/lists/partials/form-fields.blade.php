<div class="col-12">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" required>
</div>
<div class="col-12">
    <label class="form-label">Code</label>
    <input type="text" name="code" class="form-control">
</div>
@if (in_array('system_id', $fields, true))
    <div class="col-12">
        <label class="form-label">System</label>
        <select name="system_id" class="form-select" required>
            <option value="">Select system</option>
            @foreach ($systems as $system)
                <option value="{{ $system->id }}">{{ $system->name }}</option>
            @endforeach
        </select>
    </div>
@endif
@if (in_array('region_id', $fields, true))
    <div class="col-12">
        <label class="form-label">Region</label>
        <select name="region_id" class="form-select">
            <option value="">Select region</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}">{{ $region->name }}</option>
            @endforeach
        </select>
    </div>
@endif
@if (in_array('sla_hours', $fields, true))
    <div class="col-12">
        <label class="form-label">SLA Hours</label>
        <input type="number" min="1" name="sla_hours" class="form-control" value="72">
    </div>
@endif
@if (in_array('color', $fields, true))
    <div class="col-12">
        <label class="form-label">Color</label>
        <input type="text" name="color" class="form-control" placeholder="info">
    </div>
@endif
<div class="col-12">
    <label class="form-label">Description</label>
    <textarea name="description" rows="3" class="form-control"></textarea>
</div>
<div class="col-md-6">
    <label class="form-label">Sort Order</label>
    <input type="number" min="0" name="sort_order" class="form-control" value="0">
</div>
<div class="col-md-6 d-flex align-items-end">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="active" name="active" value="1" checked>
        <label class="form-check-label" for="active">Active</label>
    </div>
</div>
