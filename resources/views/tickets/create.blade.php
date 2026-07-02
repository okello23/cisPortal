@extends('layouts.app', ['title' => 'Report ICT Issue'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="content-card bg-white p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <!-- <p class="text-uppercase text-muted fw-semibold small mb-2">Public Support Portal</p> -->
                        <h1 class="h2 mb-1">Report an ICT issue</h1>
                        <p class="text-muted mb-0">No account is required. Provide either your email address so you can track progress later.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="row g-4">
                    @csrf
                    <div class="col-12">
                        <fieldset class="app-fieldset">
                            <legend>System Information</legend>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">System <span class="text-danger">*</span></label>
                                    <select name="system_id" class="form-select" required id="system-select">
                                        <option value="">Select system</option>
                                        @foreach ($systems as $system)
                                            <option value="{{ $system->id }}" @selected(old('system_id') == $system->id)>{{ $system->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Issue Began <span class="text-danger">*</span></label>
                                    <input type="date" name="issue_started_at" class="form-control" value="{{ old('issue_started_at') }}" max="{{ now()->toDateString() }}" required>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-12">
                        <fieldset class="app-fieldset">
                            <legend>User & Facility Information</legend>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Designation <span class="text-danger">*</span></label>
                                    <select name="designation_id" class="form-select" required>
                                        <option value="">Select designation</option>
                                        @foreach ($designations as $designation)
                                            <option value="{{ $designation->id }}" @selected(old('designation_id') == $designation->id)>{{ $designation->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Full Name (Reported By) <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" value="{{ old('full_name') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lab Manager Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="lab_manager_name" class="form-control" value="{{ old('lab_manager_name') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lab Manager Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="lab_manager_email" class="form-control" value="{{ old('lab_manager_email') }}" required>
                                </div>
                                
                                <div class="col-md-12">
                                <livewire:public-ticket-location-selector
                                    :selected-region-id="old('region_id')"
                                    :selected-district-name="old('district_name')"
                                    :selected-facility-id="old('facility_id')"
                                />
                            </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-12">
                        <fieldset class="app-fieldset">
                            <legend>Ticket Information</legend>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Issue Type <span class="text-danger">*</span></label>
                                    <select name="issue_type_id" class="form-select" required>
                                        <option value="">Select issue type</option>
                                        @foreach ($issueTypes as $issueType)
                                            <option value="{{ $issueType->id }}" @selected(old('issue_type_id') == $issueType->id)>{{ $issueType->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Impact on system use <span class="text-danger">*</span></label>
                                    <select name="priority_level_id" class="form-select" required>
                                        <option value="">Select impact on system use</option>
                                        @foreach ($priorityLevels as $priorityLevel)
                                            <option value="{{ $priorityLevel->id }}" @selected(old('priority_level_id') == $priorityLevel->id)>{{ $priorityLevel->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                                    <textarea name="description" rows="5" class="form-control" required>{{ old('description') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Attachment/Screenshot</label>
                                    <input type="file" name="attachment" class="form-control">
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-success btn-lg rounded-pill px-4">Submit Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
