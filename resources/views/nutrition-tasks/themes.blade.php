<div class="row g-4">
    <div class="col-xl-3">
        <section class="content-card bg-white p-4">
            <h2 class="h5 mb-3">{{ $editingTheme ? 'Edit Theme' : 'Add Theme' }}</h2>
            <form method="POST" action="{{ $editingTheme ? route('nutrition-themes.update', $editingTheme) : route('nutrition-themes.store') }}" class="row g-3">
                @csrf
                @if ($editingTheme) @method('PUT') @endif
                <div class="col-12"><label for="theme-code" class="form-label">Activity Code</label><input id="theme-code" name="code" value="{{ old('code', $editingTheme?->code) }}" class="form-control" maxlength="20" pattern="[0-9]+[a-z]?" placeholder="e.g. 18 or 18a" required></div>
                <div class="col-12"><label for="theme-name" class="form-label">Planned Activity</label><textarea id="theme-name" name="name" class="form-control" rows="5" maxlength="2000" required>{{ old('name', $editingTheme?->name) }}</textarea></div>
                <div class="col-12"><label for="theme-order" class="form-label">Display Order</label><input id="theme-order" type="number" name="sort_order" value="{{ old('sort_order', $editingTheme?->sort_order ?? ($themes->max('sort_order') + 1)) }}" min="1" max="100000" class="form-control" required></div>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-success rounded-pill">{{ $editingTheme ? 'Update Theme' : 'Add Theme' }}</button>@if($editingTheme)<a class="btn btn-outline-dark rounded-pill" href="{{ route('nutrition-tasks.dashboard', ['tab' => 'themes']) }}">Cancel</a>@endif</div>
            </form>
        </section>
    </div>
    <div class="col-xl-9">
        <section class="content-card bg-white p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div><h2 class="h4">Manage Themes</h2><p class="text-muted mb-0">Execution of planned activities, in workplan order.</p></div>
                <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-dark rounded-pill" href="{{ route('nutrition-themes.export') }}">Download Excel</a><button type="button" class="btn btn-outline-dark rounded-pill" id="download-themes-image">Download Image</button></div>
            </div>
            <p class="small text-muted">Counts include directly linked main tasks and subtasks. Overdue is a subset of unfinished work. Each theme is complete only when all of its linked work is complete; no linked work means Not Started. Theme codes group activities without automatically counting child themes twice.</p>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Code</th><th>Theme</th><th>Not Started</th><th>Started</th><th>In Progress</th><th>Completed</th><th>Overdue</th><th>Assigned Members</th><th>Completion Rate</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($themeRows as $row)
                        <tr><td class="fw-semibold">{{ $row['code'] }}</td><td style="min-width: 16rem">{{ $row['name'] }}<div><span class="badge {{ $row['status'] === 'completed' ? 'text-bg-success' : 'text-bg-light border text-dark' }}">{{ \App\Models\NutritionTask::STATUSES[$row['status']] }}</span></div></td>
                            <td>{{ $row['not_started'] }}</td><td>{{ $row['started'] }}</td><td>{{ $row['in_progress'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['overdue'] }}</td><td style="min-width: 12rem">{{ implode(', ', $row['members']) ?: 'Unassigned' }}</td><td>{{ number_format($row['completion_rate'], 1) }}%</td>
                            <td><a href="{{ route('nutrition-tasks.dashboard', ['tab' => 'themes', 'edit_theme' => $row['id']]) }}" class="btn btn-sm btn-outline-dark rounded-pill">Edit</a></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div id="theme-export-status" class="small text-muted" role="status" aria-live="polite"></div>
        </section>
    </div>
</div>
@php
    $themeImageData = [
        'headers' => ['Code', 'Theme', 'Status', 'Not Started', 'Started', 'In Progress', 'Completed', 'Overdue', 'Assigned Members', 'Completion %'],
        'rows' => $themeRows->map(fn ($row) => [$row['code'], $row['name'], \App\Models\NutritionTask::STATUSES[$row['status']], $row['not_started'], $row['started'], $row['in_progress'], $row['completed'], $row['overdue'], implode(', ', $row['members']) ?: 'Unassigned', number_format($row['completion_rate'], 1).'%'])->values()->all(),
        'date' => now()->format('d M Y H:i'),
    ];
@endphp
@push('scripts')
    <script type="application/json" id="theme-table-data">@json($themeImageData)</script>
    <script src="{{ asset('js/nutrition-theme-reports.js') }}" defer></script>
@endpush
