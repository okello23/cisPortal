@extends('layouts.app', ['title' => 'Manage ICT Users'])

@section('content')
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="content-card bg-white p-4">
                <h1 class="h4 mb-3">Add ICT User</h1>
                <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="col-12 form-check ms-1">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1" checked>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Create User</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="content-card bg-white p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <h2 class="h5 mb-0">Existing ICT Users</h2>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Back to Dashboard</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="small text-muted">{{ $user->email }}</div>
                                        @if ($user->phone)
                                            <div class="small text-muted">{{ $user->phone }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $roles[$user->role] ?? $user->role }}</td>
                                    <td>{{ $user->active ? 'Active' : 'Inactive' }}</td>
                                    <td>
                                        <details>
                                            <summary class="btn btn-sm btn-outline-secondary rounded-pill">Edit</summary>
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="row g-2 mt-3">
                                                @csrf
                                                @method('PUT')
                                                <div class="col-12">
                                                    <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                                                </div>
                                                <div class="col-12">
                                                    <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" name="phone" class="form-control" value="{{ $user->phone }}" placeholder="Phone number">
                                                </div>
                                                <div class="col-12">
                                                    <select name="role" class="form-select" required>
                                                        @foreach ($roles as $value => $label)
                                                            <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <input type="password" name="password" class="form-control" placeholder="New password (optional)">
                                                </div>
                                                <div class="col-12">
                                                    <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm new password">
                                                </div>
                                                <div class="col-12 form-check ms-1">
                                                    <input type="checkbox" class="form-check-input" id="active-{{ $user->id }}" name="active" value="1" @checked($user->active)>
                                                    <label class="form-check-label" for="active-{{ $user->id }}">Active</label>
                                                </div>
                                                <div class="col-12">
                                                    <button class="btn btn-sm btn-dark rounded-pill px-3">Save</button>
                                                </div>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
