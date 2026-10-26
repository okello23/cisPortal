@extends('layouts.app', ['title' => 'Manage Users'])

@section('content')
@php
    $selectedRoles = old('roles', $editingUser?->roleCodes() ?? []);
    $selectedRoles = is_array($selectedRoles) ? $selectedRoles : [];
@endphp
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div><h1 class="h3 mb-1">Manage Users</h1><p class="text-muted mb-0">One account can have multiple roles.</p></div>
    <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="btn btn-outline-dark rounded-pill">Back to Home</a>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="content-card bg-white p-4" id="user-form">
            <h2 class="h4 mb-3">{{ $editingUser ? 'Edit User' : 'Add User' }}</h2>
            <form method="POST" action="{{ $editingUser ? route('admin.users.update', $editingUser) : route('admin.users.store') }}" class="row g-3">
                @csrf
                @if ($editingUser) @method('PUT') @endif
                @foreach ([['name','Full Name','text'],['email','Email Address','email'],['phone','Phone Number','text'],['designation','Designation','text'],['place_of_work','Place of Work','text']] as [$name,$label,$type])
                    <div class="col-12">
                        <label class="form-label" for="user-{{ $name }}">{{ $label }}</label>
                        <input id="user-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $editingUser?->{$name} ?? (in_array($name, ['designation', 'place_of_work']) ? $editingUser?->nutritionProfile?->{$name} : null)) }}" class="form-control" @required(in_array($name, ['name', 'email']))>
                    </div>
                @endforeach
                <div class="col-12">
                    <fieldset>
                        <legend class="fs-6">Roles</legend>
                        <input type="hidden" name="roles_present" value="1">
                        @foreach ($roles as $category => $items)
                            <div class="border rounded-3 p-3 mb-2">
                                <h3 class="h6">{{ $categories[$category] }} Roles</h3>
                                @foreach ($items as $role)
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="role-{{ $role->id }}" name="roles[]" value="{{ $role->code }}" @checked(in_array($role->code, $selectedRoles, true))>
                                        <label for="role-{{ $role->id }}" class="form-check-label">{{ $role->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                        <div class="form-text">Select all roles this user needs. Designation and place of work are required for Micronutrient roles. Users without roles cannot access either dashboard.</div>
                    </fieldset>
                </div>
                @unless ($editingUser)
                    <div class="col-12"><div class="alert alert-info small mb-0">A temporary password will be generated and emailed to the user.</div></div>
                @endunless
                <div class="col-12 form-check ms-1">
                    <input type="hidden" name="active" value="0">
                    <input id="user-active" type="checkbox" name="active" value="1" class="form-check-input" @checked(old('active', $editingUser?->active ?? true))>
                    <label for="user-active" class="form-check-label">Active</label>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-dark rounded-pill px-4">{{ $editingUser ? 'Update' : 'Create User' }}</button>
                    @if ($editingUser)<a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>@endif
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="content-card bg-white p-4">
            <h2 class="h5 mb-3">Users</h2>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>User</th><th>Roles</th><th>Work Details</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($users as $user)
                    <tr @class(['table-active' => $editingUser?->id === $user->id])>
                        <td><strong>{{ $user->name }}</strong><div class="small text-muted">{{ $user->email }}</div></td>
                        <td>{{ $user->roleLabel() }}</td>
                        <td>{{ $user->designation }}<div class="small text-muted">{{ $user->place_of_work }}</div></td>
                        <td>{{ $user->active ? 'Active' : 'Inactive' }}</td>
                        <td>
                            @if ($isGlobalManager || ! $user->hasGlobalRole())
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('admin.users.index', ['edit' => $user->id]) }}#user-form">Edit</a>
                            @else
                                <span class="small text-muted">ICT admin manages this account</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No users found.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
