@extends('layouts.app', ['title' => 'Rate Support'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card bg-white p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted fw-semibold small mb-1">Customer Satisfaction Survey</p>
                        <h1 class="h3 mb-0">Rate support for {{ $ticket->ticket_number }}</h1>
                    </div>
                    <span class="badge text-bg-{{ $ticket->status?->color ?? 'secondary' }} fs-6">{{ $ticket->status?->name }}</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6"><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name ?? 'Pending assignment' }}</div>
                    <div class="col-md-6"><strong>System:</strong> {{ $ticket->system->name }}</div>
                    <div class="col-12"><strong>Resolution Summary:</strong> {{ $ticket->resolution_summary ?? 'Resolution details were not provided.' }}</div>
                </div>

                @if ($ticket->feedback)
                    <div class="alert alert-success rounded-4 mb-0">
                        <strong>Feedback already submitted.</strong>
                        Thank you for rating this support experience.
                    </div>
                @elseif ($ticket->status?->code !== 'resolved')
                    <div class="alert alert-warning rounded-4 mb-0">
                        Feedback is only available while the ticket is in the resolved stage.
                    </div>
                @else
                    <form method="POST" action="{{ $ticket->feedbackUrl('tickets.feedback.store') }}" class="row g-4">
                        @csrf
                        <div class="col-12">
                            <label class="form-label fw-semibold">Timeliness</label>
                            <select name="timeliness_rating" class="form-select" required>
                                <option value="">Select rating</option>
                                @for ($score = 5; $score >= 0; $score--)
                                    <option value="{{ $score }}" @selected(old('timeliness_rating') == $score)>{{ $score }} / 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Completeness</label>
                            <select name="completeness_rating" class="form-select" required>
                                <option value="">Select rating</option>
                                @for ($score = 5; $score >= 0; $score--)
                                    <option value="{{ $score }}" @selected(old('completeness_rating') == $score)>{{ $score }} / 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Overall Satisfaction</label>
                            <select name="overall_satisfaction_rating" class="form-select" required>
                                <option value="">Select rating</option>
                                @for ($score = 5; $score >= 0; $score--)
                                    <option value="{{ $score }}" @selected(old('overall_satisfaction_rating') == $score)>{{ $score }} / 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Comments</label>
                            <textarea name="comments" rows="4" class="form-control" placeholder="Share anything that went well or could be improved.">{{ old('comments') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-cis-orange rounded-pill px-4">Submit Feedback</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
