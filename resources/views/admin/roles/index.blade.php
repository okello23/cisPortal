@extends('layouts.app', ['title' => 'Manage Roles'])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div><h1 class="h3 mb-1">Manage Roles</h1><p class="text-muted mb-0">Manage Global and Micronutrient roles for your team.</p></div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-dark rounded-pill">Back to Home</a>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="content-card bg-white p-4">
            <h2 class="h5 mb-3">Add Role</h2>
            <form method="POST" action="{{ route('admin.roles.store') }}" class="row g-3">
                @csrf
                @include('admin.roles.fields', ['role' => null, 'formId' => 'new'])
                <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Create Role</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        @foreach ($categories as $category => $label)
            <div class="content-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">{{ $label }} Roles</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Role</th><th>Access level</th><th>Users</th><th>Actions</th></tr></thead>
                        <tbody>
                        @forelse ($roles->where('category', $category) as $role)
                            <tr>
                                <td>{{ $role->name }} @if ($role->isBuiltIn())<span class="badge text-bg-light">Built-in</span>@endif</td>
                                <td>{{ \App\Models\User::roleLabels()[$role->access_role] }}</td>
                                <td>{{ $role->users_count }}</td>
                                <td>
                                    <details>
                                        <summary class="btn btn-sm btn-outline-secondary rounded-pill">Edit</summary>
                                        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="row g-2 mt-2" style="min-width: 16rem">
                                            @csrf @method('PUT')
                                            @include('admin.roles.fields', ['formId' => $role->id])
                                            @if ($role->isBuiltIn() || $role->users_count)
                                                <div class="col-12 small text-muted">Only the name can change while this role is built-in or assigned to users.</div>
                                            @endif
                                            <div class="col-12"><button class="btn btn-sm btn-dark rounded-pill">Save Role</button></div>
                                        </form>
                                    </details>
                                    @if (! $role->isBuiltIn() && ! $role->users_count)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="mt-2" onsubmit="return confirm('Delete this role?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger rounded-pill">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No roles in this category yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-role-category]').forEach(category => {
        const access = category.form.querySelector('[data-role-access]');
        const refresh = () => {
            access.querySelectorAll('optgroup').forEach(group => {
                group.hidden = group.dataset.category !== category.value;
                group.disabled = group.hidden;
            });
            if (access.selectedOptions[0]?.parentElement.disabled) {
                access.value = access.querySelector('optgroup:not([disabled]) option').value;
            }
        };
        category.addEventListener('change', refresh);
        refresh();
    });
</script>
@endpush
