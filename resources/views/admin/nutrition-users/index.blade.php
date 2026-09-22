@extends('layouts.app', ['title' => 'Manage Nutrition Team Users'])

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="content-card bg-white p-4">
            <h1 class="h4 mb-3">Add Nutrition Team User</h1>
            <form method="POST" action="{{ route('admin.nutrition-users.store') }}" class="row g-3">
                @csrf
                @foreach ([['name','Full Name','text'],['email','Email Address','email'],['phone','Phone Number','text'],['designation','Designation','text'],['place_of_work','Place of Work','text']] as [$name,$label,$type])
                    <div class="col-12"><label class="form-label">{{ $label }}</label><input type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" class="form-control" {{ $name !== 'phone' ? 'required' : '' }}></div>
                @endforeach
                <div class="col-12"><label class="form-label">Role</label><select name="role" class="form-select" required>@foreach($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-12"><div class="alert alert-info small mb-0">A temporary password will be generated and emailed to the user.</div></div>
                <div class="col-12 form-check ms-1"><input type="checkbox" class="form-check-input" id="active" name="active" value="1" checked><label for="active" class="form-check-label">Active</label></div>
                <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Create User</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="content-card bg-white p-4">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><h2 class="h5 mb-0">Nutrition Team Users</h2><a href="{{ auth()->user()->isNutritionUser() ? route('nutrition-tasks.dashboard') : route('dashboard') }}" class="btn btn-outline-dark rounded-pill">Back to Dashboard</a></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>User</th><th>Role</th><th>Work Details</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($profiles as $profile)
                <tr><td><strong>{{ $profile->user->name }}</strong><div class="small text-muted">{{ $profile->user->email }}</div></td><td>{{ $roles[$profile->user->role] ?? $profile->user->role }}</td><td>{{ $profile->designation }}<div class="small text-muted">{{ $profile->place_of_work }}</div></td><td>{{ $profile->user->active ? 'Active' : 'Inactive' }}</td><td><details><summary class="btn btn-sm btn-outline-secondary rounded-pill">Edit</summary>
                    <form method="POST" action="{{ route('admin.nutrition-users.update', $profile) }}" class="row g-2 mt-3" style="min-width: 18rem">@csrf @method('PUT')
                        @foreach ([['name',$profile->user->name,'Full name','text'],['email',$profile->user->email,'Email','email'],['phone',$profile->user->phone,'Phone','text'],['designation',$profile->designation,'Designation','text'],['place_of_work',$profile->place_of_work,'Place of work','text']] as [$name,$value,$placeholder,$type])
                            <div class="col-12"><input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" class="form-control" {{ $name !== 'phone' ? 'required' : '' }}></div>
                        @endforeach
                        <div class="col-12"><select name="role" class="form-select">@foreach($roles as $value => $label)<option value="{{ $value }}" @selected($profile->user->role === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-12"><input type="password" name="password" class="form-control" placeholder="New password (optional)"></div><div class="col-12"><input type="password" name="password_confirmation" class="form-control" placeholder="Confirm password"></div>
                        <div class="col-12 form-check ms-1"><input type="checkbox" name="active" value="1" class="form-check-input" id="active-{{ $profile->id }}" @checked($profile->user->active)><label class="form-check-label" for="active-{{ $profile->id }}">Active</label></div>
                        <div class="col-12"><button class="btn btn-sm btn-dark rounded-pill px-3">Save</button></div>
                    </form></details></td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted">No Nutrition team users yet.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </div>
</div>
@endsection
