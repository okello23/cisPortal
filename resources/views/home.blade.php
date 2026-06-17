@extends('layouts.app', ['title' => 'CPHL ICT SupportLink'])

@section('content')
    <section class="hero-panel p-4 p-lg-5 mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="text-uppercase fw-semibold small mb-2">Centralized ICT support for every CPHL system</p>
                <h1 class="hero-title display-5 fw-bold mb-3">Log issues quickly, track them openly, and manage response quality in one place.</h1>
                <p class="lead mb-4">CIS is the official ticket intake and management platform for CPHL-supported digital systems, built for public reporting and internal accountability.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a class="btn btn-light btn-lg rounded-pill px-4" href="{{ route('tickets.create') }}">Submit Ticket</a>
                    <a class="btn btn-outline-light btn-lg rounded-pill px-4" href="{{ route('dashboard.public') }}">View Public Dashboard</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="metric-card p-3 h-100">
                            <div class="text-muted small">Tickets Logged</div>
                            <div class="fs-2 fw-bold">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-card p-3 h-100">
                            <div class="text-muted small">Open Tickets</div>
                            <div class="fs-2 fw-bold">{{ $stats['open'] }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-card p-3 h-100">
                            <div class="text-muted small">Resolved</div>
                            <div class="fs-2 fw-bold">{{ $stats['resolved'] }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-card p-3 h-100">
                            <div class="text-muted small">Logged Today</div>
                            <div class="fs-2 fw-bold">{{ $stats['today'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-lg-4">
            <div class="content-card bg-white p-4 h-100">
                <h2 class="h4">Public Ticket Submission</h2>
                <p class="text-muted">Users can report ICT issues without creating accounts. Source system and module can be prefilled from integrated CPHL applications.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card bg-white p-4 h-100">
                <h2 class="h4">Transparent Tracking</h2>
                <p class="text-muted">Track progress using the ticket number plus email or phone, with status, staff assignment, latest update, and expected resolution date.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card bg-white p-4 h-100">
                <h2 class="h4">ICT Management Visibility</h2>
                <p class="text-muted">Dashboard views support SLA monitoring, repeat issue identification, training recommendations, and staff workload tracking.</p>
            </div>
        </div>
    </section>
@endsection
