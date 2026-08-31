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
            'hero' => 'Hero / home branding',
            'business' => 'Business information',
            'payment' => 'Payment display',
            'fulfilment' => 'Fulfilment',
            'pages' => 'Static pages',
        ];
        $mediaKeys = [
            \App\Enums\WebsiteSettingKey::HeroImagePath->value,
            \App\Enums\WebsiteSettingKey::PaymentQrImagePath->value,
        ];
    @endphp

    <form method="POST" action="{{ route('administrator.website-settings.update') }}" class="form" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="alert alert-primary mb-8">
            Customer-facing café content belongs here. Payment and delivery fields use website settings when filled;
            empty fields fall back to <code>config/coffee.php</code> / env so infrastructure defaults stay separate from operational CMS values.
            Demo seed data is local/testing only and must not be used as production content.
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

                                @if (in_array($key->value, $mediaKeys, true))
                                    @php
                                        $storedPath = old($key->value, $values[$key->value] ?? null);
                                        $previewUrl = \App\Support\PublicMedia::url(is_string($storedPath) ? $storedPath : null);
                                        $fileInputName = $key === \App\Enums\WebsiteSettingKey::HeroImagePath ? 'hero_image' : 'payment_qr_image';
                                        $removeInputName = $key === \App\Enums\WebsiteSettingKey::HeroImagePath ? 'remove_hero_image' : 'remove_payment_qr_image';
                                    @endphp

                                    @if ($previewUrl)
                                        <div class="mb-3">
                                            <img
                                                src="{{ $previewUrl }}"
                                                alt="{{ $key->label() }}"
                                                class="rounded border"
                                                style="max-width: 10rem; max-height: 10rem; object-fit: contain; background: #f5f5f5;"
                                            />
                                        </div>
                                        <input type="hidden" name="{{ $key->value }}" value="{{ $storedPath }}" />
                                        <div class="form-check mb-3">
                                            <input
                                                id="{{ $removeInputName }}"
                                                name="{{ $removeInputName }}"
                                                type="checkbox"
                                                value="1"
                                                class="form-check-input @error($removeInputName) is-invalid @enderror"
                                                @checked(old($removeInputName))
                                            />
                                            <label for="{{ $removeInputName }}" class="form-check-label">Remove current image</label>
                                            @error($removeInputName)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @else
                                        <input
                                            id="{{ $key->value }}"
                                            name="{{ $key->value }}"
                                            type="text"
                                            value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                            class="form-control mb-3 @error($key->value) is-invalid @enderror"
                                            placeholder="Optional absolute URL or leave blank and upload below"
                                        />
                                        @error($key->value)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif

                                    <input
                                        id="{{ $fileInputName }}"
                                        name="{{ $fileInputName }}"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                        class="form-control @error($fileInputName) is-invalid @enderror"
                                    />
                                    @error($fileInputName)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        Upload JPEG / PNG / WebP (max {{ \App\Support\PublicMedia::maxKilobytes() }} KB), or set an absolute URL when no file is stored.
                                        @if ($key === \App\Enums\WebsiteSettingKey::PaymentQrImagePath)
                                            Empty falls back to config/env.
                                        @endif
                                    </div>
                                @elseif ($key->valueType() === 'text')
                                    <textarea
                                        id="{{ $key->value }}"
                                        name="{{ $key->value }}"
                                        rows="{{ str_starts_with($key->value, 'pages_') ? 8 : 4 }}"
                                        class="form-control @error($key->value) is-invalid @enderror"
                                    >{{ old($key->value, $values[$key->value] ?? '') }}</textarea>
                                    @error($key->value)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if (str_starts_with($key->value, 'pages_'))
                                        <div class="form-text">Plain text only. HTML and scripts are stripped on save.</div>
                                    @endif
                                    @if ($sectionKey === 'fulfilment')
                                        @php
                                            $configKey = str_replace('fulfilment_', '', $key->value);
                                            $fallback = $fulfilmentConfig[$configKey] ?? null;
                                        @endphp
                                        @if (filled($fallback))
                                            <div class="form-text">Config fallback: {{ $fallback }}</div>
                                        @else
                                            <div class="form-text">No config fallback set for this field.</div>
                                        @endif
                                    @endif
                                @else
                                    <input
                                        id="{{ $key->value }}"
                                        name="{{ $key->value }}"
                                        type="{{ $key->formInputType() }}"
                                        value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                        class="form-control @error($key->value) is-invalid @enderror"
                                    />
                                    @error($key->value)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if ($sectionKey === 'payment')
                                        @php
                                            $configKey = str_replace('payment_', '', $key->value);
                                            $fallback = $paymentConfig[$configKey] ?? null;
                                        @endphp
                                        @if (filled($fallback))
                                            <div class="form-text">Config fallback: {{ $fallback }}</div>
                                        @else
                                            <div class="form-text">No config fallback set for this field.</div>
                                        @endif
                                    @endif
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
