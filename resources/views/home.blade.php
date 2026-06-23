@extends('layouts.app', ['title' => 'CPHL ICT Support Portal'])

@section('content')
    <style>
        .home-hero {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            min-height: 28rem;
            display: flex;
            align-items: center;
        }

        .home-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('/unhls33.jpg') center 38% / cover no-repeat;
            filter: blur(5px) saturate(0.92) brightness(0.74);
            transform: scale(1.05);
            z-index: -2;
        }

        .home-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(10, 28, 43, 0.82) 0%, rgba(10, 28, 43, 0.58) 44%, rgba(10, 28, 43, 0.36) 100%),
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 24%);
            z-index: -1;
        }

        .home-hero .metric-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 1.25rem;
        }

        .home-hero .lead,
        .home-hero p,
        .home-hero h1 {
            max-width: 12ch;
        }

        .home-hero .lead {
            max-width: 32rem;
        }

        @media (max-width: 991.98px) {
            .home-hero {
                min-height: auto;
            }

            .home-hero::before {
                background-position: center center;
            }

            .home-hero::after {
                background:
                    linear-gradient(180deg, rgba(10, 28, 43, 0.78) 0%, rgba(10, 28, 43, 0.58) 100%),
                    radial-gradient(circle at top right, rgba(255, 255, 255, 0.16), transparent 24%);
            }

            .home-hero .lead,
            .home-hero p,
            .home-hero h1 {
                max-width: none;
            }
        }

        .hero-stats-panel {
            padding-left: 1rem;
        }

        .hero-stats-group-title {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero-stats-date {
            color: rgba(255, 255, 255, 0.74);
            font-size: 0.85rem;
        }

        .hero-stats-panel .metric-card .text-muted {
            color: #52606d !important;
        }

        @media (max-width: 991.98px) {
            .hero-stats-panel {
                padding-left: 0;
            }
        }
    </style>

    <section class="hero-panel home-hero p-4 p-lg-5 mb-4">
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
                <div class="hero-stats-panel">
                    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                        <div class="hero-stats-group-title">Today's Snapshot</div>
                        <div class="hero-stats-date">{{ now()->format('d M Y') }}</div>
                    </div>
                    <div class="row g-3 mb-4">
                        @foreach ([
                            'Logged Today' => $todayStats['total'],
                            'Opened Today' => $todayStats['open'],
                            'Resolved Today' => $todayStats['resolved'],
                            'Facilities Today' => $todayStats['facilities'],
                        ] as $label => $value)
                            <div class="col-6">
                                <div class="metric-card p-3 h-100">
                                    <div class="text-muted small">{{ $label }}</div>
                                    <div class="fs-2 fw-bold">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="hero-stats-group-title mb-3">All-Time Overview</div>
                    <div class="row g-3">
                        @foreach ([
                            'Total Tickets' => $overviewStats['total'] ?? 0,
                            'Open Tickets' => $overviewStats['open'] ?? 0,
                            'This Month' => $overviewStats['this_month'] ?? 0,
                            'Avg Hours' => $overviewStats['average_resolution_hours'] ?? 0,
                            'Facilities' => $overviewStats['facilities'] ?? 0,
                            'Systems' => $overviewStats['systems'] ?? 0,
                        ] as $label => $value)
                            <div class="col-6">
                                <div class="metric-card p-3 h-100">
                                    <div class="text-muted small">{{ $label }}</div>
                                    <div class="fs-4 fw-bold">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-lg-4">
            <div class="content-card bg-white p-4 h-100">
                <h2 class="h4">Public Ticket Submission</h2>
                <p class="text-muted">Users can report ICT issues without creating accounts, with the affected system and location captured in one guided form.</p>
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
