@extends('layouts.app', ['title' => 'Reset Password'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="content-card bg-white p-4 p-lg-5">
                <p class="text-uppercase text-muted fw-semibold small mb-2">Internal ICT Access</p>
                <h1 class="h3 mb-3">Choose a new password</h1>
                <form method="POST" action="{{ route('password.update', [], false) }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Reset password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
