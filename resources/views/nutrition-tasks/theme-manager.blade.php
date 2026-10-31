<section class="content-card bg-white p-4 mb-4">
    <h2 class="h4">Theme Manager Dashboard</h2>
    <form method="GET" action="{{ route('nutrition-tasks.dashboard') }}" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="manager">
        <div class="col-md-3"><label class="form-label" for="report-start">Start Date</label><input id="report-start" type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label" for="report-end">End Date</label><input id="report-end" type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label" for="report-theme">Theme</label><select id="report-theme" name="theme_id" class="form-select"><option value="">All Themes</option>@foreach($themes as $theme)<option value="{{ $theme->id }}" @selected($filters['theme_id'] === $theme->id)>{{ $theme->code }} — {{ $theme->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-success rounded-pill w-100">Apply Filters</button></div>
    </form>
    <p class="small text-muted mt-3 mb-0">Status and backlog show the current position for selected themes. Creation, completion, timeliness and duration charts use {{ $filters['start_date'] }} to {{ $filters['end_date'] }} (inclusive). Unclassified work is excluded until a theme is selected. Main tasks and subtasks are counted as distinct work items.</p>
</section>
<div class="row g-3 mb-4">
    @foreach ($managerReport['cards'] as $label => $value)
        <div class="col-6 col-lg-2"><div class="metric-card metric-card-accent p-3 h-100" style="--metric-accent:#198754"><div class="fs-3 fw-bold">{{ $value }}</div><div class="text-muted small">{{ $label }}</div></div></div>
    @endforeach
</div>
<div class="row g-4">
    @foreach ($managerReport['charts'] as $chart)
        <div class="col-xl-6">
            <section class="content-card bg-white p-4 h-100">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2"><h3 class="h5">{{ $chart['title'] }}</h3><button type="button" class="btn btn-sm btn-outline-dark rounded-pill" data-download-chart="{{ $chart['id'] }}">Download PNG</button></div>
                <p class="small text-muted">{{ $chart['note'] }}</p>
                <div style="max-height: 30rem; overflow: auto"><canvas id="{{ $chart['id'] }}" role="img" aria-label="{{ $chart['title'] }}. Values are listed in the chart data table below." style="width:100%;height:auto"></canvas></div>
                <details class="mt-3"><summary>View chart data</summary><div class="table-responsive"><table class="table table-sm"><thead><tr><th>{{ $chart['type'] === 'donut' ? 'Status' : 'Theme / Period' }}</th>@foreach($chart['datasets'] as $dataset)<th>{{ $dataset['label'] }}</th>@endforeach</tr></thead><tbody>@foreach($chart['labels'] as $i => $label)<tr><td>{{ $label }}</td>@foreach($chart['datasets'] as $dataset)<td>{{ $dataset['data'][$i] ?? 'No data' }}</td>@endforeach</tr>@endforeach</tbody></table></div></details>
            </section>
        </div>
    @endforeach
</div>
<section class="content-card bg-white p-4 mt-4"><h3 class="h5">Theme Key</h3><div class="row g-2">@foreach($themeRows as $row)<div class="col-md-6"><strong>{{ $row['code'] }}</strong> — {{ $row['name'] }}</div>@endforeach</div></section>
@push('scripts')
    <script type="application/json" id="theme-chart-data">@json(['charts' => $managerReport['charts'], 'period' => $filters['start_date'].' to '.$filters['end_date']])</script>
    <script src="{{ asset('js/nutrition-theme-reports.js') }}" defer></script>
@endpush
