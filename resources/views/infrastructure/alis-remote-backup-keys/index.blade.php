@extends('layouts.app', ['title' => 'A-LIS Remote Backups & Configurations'])

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted fw-semibold small mb-1">Infrastructure</p>
            <h1 class="h2 mb-0">A-LIS Backups &amp; Configurations</h1>
        </div>
    </div>

    <livewire:alis-remote-backup-keys-manager />
@endsection
