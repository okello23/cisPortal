@extends('layouts.app', ['title' => 'In-House System Backups'])

@section('content')
    <div class="container py-4">
        <h1 class="h2 mb-1">In-House System Backups</h1>
        <p class="text-muted mb-4">Configure and download off-site backup scripts for internally hosted systems.</p>
        <livewire:in-house-backups-manager />
    </div>
@endsection
