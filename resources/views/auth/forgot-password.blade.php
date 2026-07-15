@extends('layouts.app', ['title' => 'Forgot Password'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="content-card bg-white p-4 p-lg-5">
                <p class="text-uppercase text-muted fw-semibold small mb-2">Internal ICT Access</p>
                <h1 class="h3 mb-3">Reset your password</h1>
                <p class="text-muted mb-4">Enter your email address and we will send you a password reset link.</p>

                <form method="POST" action="{{ route('password.email', [], false) }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-dark rounded-pill px-4">Send reset link</button>
                        <a href="{{ route('login', [], false) }}" class="btn btn-outline-secondary rounded-pill px-4">Back to login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
