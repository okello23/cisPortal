<div class="col-12">
    <label class="form-label" for="name-{{ $formId }}">Role name</label>
    <input class="form-control" id="name-{{ $formId }}" name="name" value="{{ $role?->name ?? old('name') }}" maxlength="255" required>
</div>
<div class="col-12">
    <label class="form-label" for="category-{{ $formId }}">Category</label>
    <select class="form-select" id="category-{{ $formId }}" name="category" data-role-category required>
        @foreach ($categories as $value => $label)
            <option value="{{ $value }}" @selected(($role?->category ?? old('category', 'global')) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-12">
    <label class="form-label" for="access-{{ $formId }}">Access level</label>
    <select class="form-select" id="access-{{ $formId }}" name="access_role" data-role-access required>
        @foreach ($categories as $category => $label)
            <optgroup label="{{ $label }}" data-category="{{ $category }}">
                @foreach (\App\Models\Role::accessRoles($category) as $value => $accessLabel)
                    <option value="{{ $value }}" @selected(($role?->access_role ?? old('access_role', 'ict_support_staff')) === $value)>{{ $accessLabel }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <div class="form-text">Users with this role receive the permissions of the selected access level.</div>
</div>
