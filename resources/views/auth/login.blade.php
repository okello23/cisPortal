@extends('layouts.app', ['title' => 'ICT Staff Login'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="content-card bg-white p-4 p-lg-5">
                <p class="text-uppercase text-muted fw-semibold small mb-2">Internal ICT Access</p>
                <h1 class="h3 mb-4">Staff login</h1>
                <form method="POST" action="{{ route('login.store', [], false) }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-12 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Sign In</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
