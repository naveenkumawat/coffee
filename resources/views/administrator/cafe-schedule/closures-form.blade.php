@extends('administrator.layouts.default')

@section('page-title', $submit)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Café Schedule', 'url' => route('administrator.cafe-schedule.index')],
        ['label' => $submit],
    ]" />
@endsection

@section('content')
    @php
        $startsLocal = old('starts_at', $closure->starts_at?->timezone($timezone)->format('Y-m-d\TH:i'));
        $endsLocal = old('ends_at', $closure->ends_at?->timezone($timezone)->format('Y-m-d\TH:i'));
    @endphp

    <form method="POST" action="{{ $action }}" class="form">
        @csrf
        @method($method)

        <div class="card card-flush internal-card internal-form-card mb-8">
            <div class="card-body">
                <div class="row g-6 internal-form-grid">
                    <div class="col-md-6">
                        <label class="form-label" for="title">Title</label>
                        <input id="title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $closure->title) }}" required maxlength="120">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="type">Type</label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $closure->type?->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="starts_at">Starts at ({{ $timezone }})</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" value="{{ $startsLocal }}" required>
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ends_at">Ends at ({{ $timezone }})</label>
                        <input id="ends_at" name="ends_at" type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" value="{{ $endsLocal }}" required>
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="customer_message">Customer message</label>
                        <input id="customer_message" name="customer_message" type="text" class="form-control @error('customer_message') is-invalid @enderror" value="{{ old('customer_message', $closure->customer_message) }}" maxlength="500" placeholder="Closed for Diwali.">
                        @error('customer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Shown in the customer app. Keep it short and friendly.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="internal_note">Internal note</label>
                        <textarea id="internal_note" name="internal_note" rows="2" class="form-control @error('internal_note') is-invalid @enderror" maxlength="1000">{{ old('internal_note', $closure->internal_note) }}</textarea>
                        @error('internal_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Never shown to customers.</div>
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $closure->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end internal-form-actions mb-10">
            <x-internal.button-group :items="[
                ['label' => 'Cancel', 'url' => route('administrator.cafe-schedule.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ]" />
        </div>
    </form>
@endsection
