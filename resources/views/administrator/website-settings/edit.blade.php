@extends('administrator.layouts.default')

@section('page-title', 'Website Settings')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Website Settings'],
    ]" />
@endsection

@section('content')
    @php
        $sections = [
            'hero' => 'Hero',
            'business' => 'Business information',
            'payment' => 'Payment display',
            'pages' => 'Static pages',
        ];
    @endphp

    <form method="POST" action="{{ route('administrator.website-settings.update') }}" class="form">
        @csrf
        @method('PUT')

        <div class="alert alert-primary mb-8">
            Payment fields use website settings when filled. Empty payment fields fall back to <code>config/coffee.php</code> / env values so values are not duplicated without precedence.
        </div>

        @foreach ($sections as $sectionKey => $sectionLabel)
            <div class="card card-flush internal-card internal-form-card mb-8">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">{{ $sectionLabel }}</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-6 internal-form-grid">
                        @foreach ($keys as $key)
                            @continue($key->section() !== $sectionKey)
                            <div class="col-12 {{ $key->valueType() === 'string' ? 'col-md-6' : '' }}">
                                <label for="{{ $key->value }}" class="form-label">{{ $key->label() }}</label>
                                @if ($key->valueType() === 'text')
                                    <textarea
                                        id="{{ $key->value }}"
                                        name="{{ $key->value }}"
                                        rows="{{ str_starts_with($key->value, 'pages_') ? 8 : 4 }}"
                                        class="form-control @error($key->value) is-invalid @enderror"
                                    >{{ old($key->value, $values[$key->value] ?? '') }}</textarea>
                                @else
                                    <input
                                        id="{{ $key->value }}"
                                        name="{{ $key->value }}"
                                        type="{{ $key->formInputType() }}"
                                        value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                        class="form-control @error($key->value) is-invalid @enderror"
                                    />
                                @endif
                                @error($key->value)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if ($sectionKey === 'payment')
                                    @php
                                        $configKey = str_replace('payment_', '', $key->value);
                                        $fallback = $paymentConfig[$configKey] ?? null;
                                    @endphp
                                    @if ($key === \App\Enums\WebsiteSettingKey::PaymentQrImagePath)
                                        <div class="form-text">Absolute URL or public path shown on the customer payment card. Empty falls back to config/env.</div>
                                    @elseif (filled($fallback))
                                        <div class="form-text">Config fallback: {{ $fallback }}</div>
                                    @else
                                        <div class="form-text">No config fallback set for this field.</div>
                                    @endif
                                @elseif ($key === \App\Enums\WebsiteSettingKey::HeroImagePath)
                                    <div class="form-text">Absolute URL or site-relative path. Leave blank to use the PWA default art.</div>
                                @elseif (str_starts_with($key->value, 'pages_'))
                                    <div class="form-text">Plain text only. HTML and scripts are stripped on save.</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-end internal-form-actions mb-10">
            <x-internal.button-group :items="[
                ['label' => 'Save settings', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ]" />
        </div>
    </form>
@endsection
