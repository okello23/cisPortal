@extends('layouts.app', ['title' => 'Change Password'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="content-card bg-white p-4 p-lg-5">
                <p class="text-uppercase text-muted fw-semibold small mb-2">Internal ICT Access</p>
                <h1 class="h3 mb-3">Change your password</h1>
                <p class="text-muted mb-4">
                    @if ($passwordExpired)
                        Your password is older than 90 days. Please set a new one to continue.
                    @elseif ($requiresPasswordChange)
                        You must change the temporary password sent to your email before you can continue.
                    @else
                        Update your password to keep your account secure.
                    @endif
                </p>

                <form method="POST" action="{{ route('password.change.update', [], false) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-dark rounded-pill px-4">Update password</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('logout', [], false) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary rounded-pill px-4">Sign out</button>
                </form>
            </div>
        </div>
    </div>
@endsection
