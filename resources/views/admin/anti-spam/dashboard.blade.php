@extends('layouts.app', ['title' => 'Anti-Spam Dashboard'])

@section('content')
    <div class="content-card bg-white p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <p class="text-uppercase text-muted fw-semibold small mb-1">Security Operations</p>
                <h1 class="h3 mb-0">Anti-spam dashboard</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.anti-spam.quarantine') }}" class="btn btn-outline-dark rounded-pill px-4">Quarantine</a>
                <a href="{{ route('admin.anti-spam.events') }}" class="btn btn-outline-dark rounded-pill px-4">Security Log</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($summary as $label => $value)
                <div class="col-md-4 col-xl-3">
                    <div class="metric-card p-3 h-100">
                        <div class="text-muted small">{{ str($label)->replace('_', ' ')->title() }}</div>
                        <div class="h3 mb-0">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <h2 class="h5">Active Blocks</h2>
                <form method="POST" action="{{ route('admin.anti-spam.blocks.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col-md-3"><select name="block_type" class="form-select"><option value="ip">IP</option><option value="email">Email</option><option value="domain">Domain</option><option value="phone">Phone</option><option value="fingerprint">Fingerprint</option></select></div>
                    <div class="col-md-4"><input type="text" name="block_value" class="form-control" placeholder="Value" required></div>
                    <div class="col-md-3"><input type="datetime-local" name="expires_at" class="form-control"></div>
                    <div class="col-md-2"><button class="btn btn-dark w-100">Block</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Type</th><th>Value</th><th>Expires</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($blocks as $block)
                                <tr>
                                    <td>{{ strtoupper($block->block_type) }}</td>
                                    <td>{{ $block->block_value }}</td>
                                    <td>{{ optional($block->expires_at)->format('d M Y H:i') ?? 'Manual' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.anti-spam.blocks.destroy', $block) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger rounded-pill">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No active blocks.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <h2 class="h5">Recent Trends</h2>
                <div class="table-responsive mb-4">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Submissions</th></tr></thead>
                        <tbody>
                            @foreach ($trends as $date => $count)
                                <tr><td>{{ $date }}</td><td>{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h2 class="h5">Top Source IPs</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Source IP</th><th>Events</th></tr></thead>
                        <tbody>
                            @forelse ($topSourceIps as $ip => $count)
                                <tr><td>{{ $ip ?: 'Unknown' }}</td><td>{{ $count }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
