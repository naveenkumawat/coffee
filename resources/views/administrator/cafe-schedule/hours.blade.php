@extends('administrator.layouts.default')

@section('page-title', 'Weekly Operating Hours')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Café Schedule', 'url' => route('administrator.cafe-schedule.index')],
        ['label' => 'Weekly Hours'],
    ]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('administrator.cafe-schedule.hours.update') }}" class="form">
        @csrf
        @method('PUT')

        <div class="alert alert-primary mb-8">
            Times use café timezone <strong>{{ $timezone }}</strong>. Leave a day disabled to mark it closed. Multiple intervals per day can be added later without schema changes.
        </div>

        <div class="card card-flush internal-card internal-form-card mb-8">
            <div class="card-body">
                <div class="row g-6">
                    @foreach ($weeklyHours as $day)
                        @php
                            $weekday = $day['weekday'];
                            $enabled = old("days.$weekday.enabled", $day['is_open']);
                            $opens = old("days.$weekday.opens_at", $day['intervals'][0]['opens_at'] ?? '08:00');
                            $closes = old("days.$weekday.closes_at", $day['intervals'][0]['closes_at'] ?? '22:00');
                        @endphp
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="border border-gray-300 rounded p-4 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="fw-bold text-gray-900">{{ $day['label'] }}</div>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="hidden" name="days[{{ $weekday }}][enabled]" value="0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="days[{{ $weekday }}][enabled]"
                                            value="1"
                                            @checked(filter_var($enabled, FILTER_VALIDATE_BOOLEAN))
                                        >
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">Open</label>
                                        <input type="time" name="days[{{ $weekday }}][opens_at]" value="{{ $opens }}" class="form-control">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Close</label>
                                        <input type="time" name="days[{{ $weekday }}][closes_at]" value="{{ $closes }}" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end internal-form-actions mb-10">
            <x-internal.button-group :items="[
                ['label' => 'Cancel', 'url' => route('administrator.cafe-schedule.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ['label' => 'Save hours', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ]" />
        </div>
    </form>
@endsection
