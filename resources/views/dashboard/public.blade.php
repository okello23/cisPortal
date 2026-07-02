@extends('layouts.app', ['title' => 'Public Dashboard'])

@push('styles')
    <style>
        .public-dashboard-shell {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            border-radius: 2rem;
            padding: 1.5rem;
        }

        .public-dashboard-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('/unhls33.jpg') center 38% / cover no-repeat;
            filter: blur(2px) saturate(0.96) brightness(1.04) contrast(1.02);
            transform: scale(1.02);
            z-index: -2;
        }

        .public-dashboard-shell::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(251, 252, 254, 0.92) 0%, rgba(246, 243, 235, 0.94) 100%),
                radial-gradient(circle at top right, rgba(13, 148, 136, 0.16), transparent 24%);
            z-index: -1;
        }

        .metric-card-button {
            border: 0;
            width: 100%;
            text-align: left;
            padding: 0;
            background: transparent;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            cursor: pointer;
        }

        .metric-card-button:hover,
        .metric-card-button:focus-visible {
            transform: translateY(-3px);
        }

        .metric-card-button:focus-visible .metric-card {
            box-shadow: 0 0 0 0.25rem rgba(18, 59, 93, 0.14);
        }

        .metric-card-button .metric-card {
            transition: box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .metric-card-button:hover .metric-card {
            border-color: rgba(18, 59, 93, 0.18);
            box-shadow: 0 18px 40px rgba(18, 59, 93, 0.12);
        }

        .metric-card-hint {
            font-size: 0.78rem;
            color: #5c6c7b;
        }

        .filter-note {
            font-size: 0.84rem;
            color: #5c6c7b;
        }

        .stats-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem 1.5rem;
            padding: 1rem 0 0.25rem;
        }

        .stats-strip-button {
            border: 0;
            background: transparent;
            padding: 0;
            width: 100%;
            text-align: left;
        }

        .stats-strip-button:focus-visible {
            outline: 0;
        }

        .stats-strip-button:focus-visible .stat-tile,
        .stats-strip-button:hover .stat-tile {
            transform: translateY(-2px);
        }

        .stat-tile {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            min-height: 4.75rem;
            transition: transform 0.18s ease;
        }

        .stat-icon-box {
            width: 3rem;
            height: 3rem;
            border-radius: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex: 0 0 auto;
        }

        .stat-copy {
            min-width: 0;
        }

        .stat-value {
            color: #123b5d;
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #0f4c8a;
            font-size: 0.88rem;
            font-weight: 500;
            line-height: 1.35;
        }

        .stat-icon-box svg {
            width: 1.55rem;
            height: 1.55rem;
            stroke: currentColor;
        }

        .stat-icon-box--navy { background: #118ab2; }
        .stat-icon-box--teal { background: #29b765; }
        .stat-icon-box--orange { background: #f08a24; }
        .stat-icon-box--plum { background: #b31e49; }
        .stat-icon-box--gold { background: #ea8a1f; }
        .stat-icon-box--rose { background: #bf1f47; }

        .dashboard-fieldset {
           border: 1px solid #000;
           padding: 0.5rem;
           margin-bottom: 0.5rem;
           border-radius: 0.5rem;
        }

        .dashboard-fieldset legend {
            float: none;
            width: auto;
            padding: 0 10px;
            margin: 0 0 0.5rem;
            font-size: 0.95rem;
            font-weight: 800;
        }

        @media (max-width: 991.98px) {
            .public-dashboard-shell {
                border-radius: 1.5rem;
                padding: 1rem;
            }

            .public-dashboard-shell::before {
                background-position: center center;
            }

            .dashboard-fieldset {
                padding: 0.85rem 1rem 1rem;
                border-radius: 1rem;
            }

            .dashboard-fieldset legend {
                font-size: 0.88rem;
                max-width: 100%;
            }

            .stats-strip {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
@endpush

@section('content')
    <livewire:public-dashboard-board />
@endsection
