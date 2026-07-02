<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CPHL ICT Support Portal' }}</title>
    <link rel="icon" type="image/png" href="/coa2.png">
    <link rel="apple-touch-icon" href="/coa2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cis-navy: #123b5d;
            --cis-teal: #0d9488;
            --cis-sand: #f6f3eb;
            --cis-ink: #163047;
        }

        body {
            font-family: 'Outfit', sans-serif;
            color: var(--cis-ink);
            background:
                radial-gradient(circle at top right, rgba(13, 148, 136, 0.16), transparent 26%),
                linear-gradient(180deg, #fbfcfe 0%, var(--cis-sand) 100%);
            min-height: 100vh;
        }

        .navbar-brand,
        .hero-title {
            letter-spacing: -0.03em;
        }

        .hero-panel,
        .content-card {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 50px rgba(18, 59, 93, 0.08);
        }

        .hero-panel {
            background: linear-gradient(135deg, rgba(18, 59, 93, 0.96), rgba(13, 148, 136, 0.88));
            color: #fff;
        }

        .metric-card {
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(18, 59, 93, 0.08);
        }

        .metric-card-accent {
            border-top: 4px solid var(--metric-accent, rgba(18, 59, 93, 0.2));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .scheduler-border {
            border-top: 4px solid #0d9488 !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .app-fieldset {
            border: 1px solid #0e3873;
            border-radius: 1rem;
            padding: 1.25rem;
            margin: 0;
            background: rgba(255, 255, 255, 0.92);
        }

        .app-fieldset legend {
            width: auto;
            margin: 0 0 0.25rem;
            padding: 0 0.65rem;
            color: #0e3873;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.72rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1px solid transparent;
            color: #111;
        }

        .stats-badge::before {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: var(--badge-dot, currentColor);
            opacity: 0.85;
        }

        .stats-badge--navy {
            background: rgba(18, 59, 93, 0.12);
            border-color: rgba(18, 59, 93, 0.16);
            --badge-dot: #123b5d;
        }

        .stats-badge--teal {
            background: rgba(13, 148, 136, 0.14);
            border-color: rgba(13, 148, 136, 0.2);
            --badge-dot: #0d7f75;
        }

        .stats-badge--orange {
            background: rgba(242, 140, 40, 0.16);
            border-color: rgba(242, 140, 40, 0.22);
            --badge-dot: #c86f16;
        }

        .stats-badge--gold {
            background: rgba(190, 140, 42, 0.16);
            border-color: rgba(190, 140, 42, 0.22);
            --badge-dot: #9b6d12;
        }

        .stats-badge--slate {
            background: rgba(82, 96, 109, 0.14);
            border-color: rgba(82, 96, 109, 0.2);
            --badge-dot: #52606d;
        }

        .stats-badge--plum {
            background: rgba(123, 63, 108, 0.14);
            border-color: rgba(123, 63, 108, 0.2);
            --badge-dot: #7b3f6c;
        }

        .table thead th {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-cis-orange {
            background: #f28c28;
            border-color: #f28c28;
            color: #fff;
        }

        .btn-cis-orange:hover,
        .btn-cis-orange:focus-visible,
        .btn-cis-orange:active {
            background: #dc7d21 !important;
            border-color: #dc7d21 !important;
            color: #fff !important;
        }
    </style>
    @livewireStyles
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-uppercase" href="{{ route('home', [], false) }}">CPHL ICT Support Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <div class="ms-auto d-flex gap-2 align-items-center">
                    @if (request()->routeIs('dashboard.public'))
                        <a class="btn btn-cis-orange rounded-pill px-4" href="{{ route('tickets.create', [], false) }}">Log New Issue</a>
                    @endif
                    <a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('tickets.track', [], false) }}">Track Ticket</a>
                    <a class="btn btn-dark rounded-pill px-4" href="{{ auth()->check() ? route('dashboard', [], false) : route('login', [], false) }}">
                        {{ auth()->check() ? 'ICT Dashboard' : 'Staff Login' }}
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-lg-5">
        @if (session('status'))
            <div class="alert alert-success rounded-4">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger rounded-4">
                <strong>Please review the form.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @livewireScripts
    @stack('scripts')
</body>
</html>
