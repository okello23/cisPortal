@extends('layouts.app', ['title' => 'Request an External Task'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="hero-panel p-4 p-lg-5 mb-4">
                <p class="text-uppercase fw-semibold small mb-2 opacity-75">Nutrition Task Tracker</p>
                <h1 class="hero-title display-6 fw-bold">Request an external task</h1>
                <p class="lead mb-0">Submit a visualisation, dataset, analysis, or other Nutrition support request without creating an account.</p>
            </div>

            <div class="content-card bg-white p-4 p-lg-5">
                <form method="POST" action="{{ route('nutrition-tasks.store') }}" class="row g-4">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Date of Task Entry <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Designation of Requestor <span class="text-danger">*</span></label>
                        <input name="requestor_designation" value="{{ old('requestor_designation') }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Place of Work <span class="text-danger">*</span></label>
                        <input name="requestor_place_of_work" value="{{ old('requestor_place_of_work') }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input name="requestor_name" value="{{ old('requestor_name') }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="requestor_email" value="{{ old('requestor_email') }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" name="requestor_phone" value="{{ old('requestor_phone') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type of Request <span class="text-danger">*</span></label>
                        <select name="request_type" id="requestType" class="form-select" required>
                            <option value="">Select request type</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('request_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 {{ old('request_type') === 'other' ? '' : 'd-none' }}" id="otherTypeGroup">
                        <label class="form-label">Other Task Wanted <span class="text-danger">*</span></label>
                        <input name="other_request_type" id="otherRequestType" value="{{ old('other_request_type') }}" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description of Task <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="5" maxlength="5000" required>{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Preferred Assignee Name</label>
                        <input name="requested_assignee_name" value="{{ old('requested_assignee_name') }}" class="form-control">
                        <div class="form-text">The Task Admin will confirm the final assignment.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Preferred Assignee Email</label>
                        <input type="email" name="requested_assignee_email" value="{{ old('requested_assignee_email') }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                        <select name="priority" class="form-select" required>
                            <option value="">Select priority</option>
                            @foreach ($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-cis-orange btn-lg rounded-pill px-5">Submit Task Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const type = document.getElementById('requestType');
        const group = document.getElementById('otherTypeGroup');
        const input = document.getElementById('otherRequestType');
        const toggle = () => {
            const show = type.value === 'other';
            group.classList.toggle('d-none', !show);
            input.required = show;
            if (!show) input.value = '';
        };
        type.addEventListener('change', toggle);
        toggle();
    });
</script>
@endpush
