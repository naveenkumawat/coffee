@extends('administrator.layouts.default')

@section('page-title', 'Hero')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Content'],
        ['label' => 'Hero'],
    ]" />
@endsection

@section('content')
    @php
        $storedPath = old(\App\Enums\WebsiteSettingKey::HeroImagePath->value, $imagePath);
        $previewUrl = \App\Support\PublicMedia::url(is_string($storedPath) ? $storedPath : null);
    @endphp

    <form method="POST" action="{{ route('administrator.hero.update') }}" class="form" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card card-flush internal-card internal-form-card mb-8">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-900">Hero</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <p class="text-muted mb-6">
                    Customer homepage hero. Tagline stays in Website Settings → Branding.
                </p>
                <div class="row g-6 internal-form-grid">
                    <div class="col-12 col-md-6">
                        <label for="hero_title" class="form-label">Hero title</label>
                        <input
                            id="hero_title"
                            name="hero_title"
                            type="text"
                            value="{{ old('hero_title', $title ?? '') }}"
                            class="form-control @error('hero_title') is-invalid @enderror"
                        />
                        @error('hero_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label for="hero_image" class="form-label">Hero image</label>
                        @if ($previewUrl)
                            <div class="mb-3">
                                <img
                                    src="{{ $previewUrl }}"
                                    alt="Hero image"
                                    class="rounded border"
                                    style="max-width: 12rem; max-height: 8rem; object-fit: contain; background: #f5f5f5;"
                                />
                            </div>
                            <input type="hidden" name="hero_image_path" value="{{ $storedPath }}" />
                            <div class="form-check mb-3">
                                <input
                                    id="remove_hero_image"
                                    name="remove_hero_image"
                                    type="checkbox"
                                    value="1"
                                    class="form-check-input @error('remove_hero_image') is-invalid @enderror"
                                    @checked(old('remove_hero_image'))
                                />
                                <label for="remove_hero_image" class="form-check-label">Remove current image</label>
                            </div>
                        @else
                            <input
                                id="hero_image_path"
                                name="hero_image_path"
                                type="text"
                                value="{{ old('hero_image_path', $imagePath ?? '') }}"
                                class="form-control mb-3 @error('hero_image_path') is-invalid @enderror"
                                placeholder="Optional absolute URL or leave blank and upload below"
                            />
                        @endif
                        <input
                            id="hero_image"
                            name="hero_image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            class="form-control @error('hero_image') is-invalid @enderror"
                        />
                        @error('hero_image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">JPEG / PNG / WebP, max {{ \App\Support\PublicMedia::maxKilobytes() }} KB. SVG is not accepted.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end internal-form-actions mb-10">
            <x-internal.button-group :items="[
                ['label' => 'Save Hero', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ]" />
        </div>
    </form>
@endsection
