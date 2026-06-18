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

        .table thead th {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
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
                    <a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('tickets.create', [], false) }}">Report Issue</a>
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
    @stack('scripts')
</body>
</html>
