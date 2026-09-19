@extends('administrator.layouts.default')

@section('page-title', 'Website Settings')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Website Settings'],
        ['label' => $section->label()],
    ]" />
@endsection

@section('content')
    @php
        $mediaInputs = [
            \App\Enums\WebsiteSettingKey::BrandLogoPath->value => [
                'file' => 'brand_logo',
                'remove' => 'remove_brand_logo',
                'allow_url' => false,
            ],
            \App\Enums\WebsiteSettingKey::PaymentQrImagePath->value => [
                'file' => 'payment_qr_image',
                'remove' => 'remove_payment_qr_image',
                'allow_url' => true,
            ],
        ];
        $diagnosticsByCode = collect($paymentMethodDiagnostics ?? [])->keyBy('code');
        $displayMode = old(\App\Enums\WebsiteSettingKey::BrandDisplayMode->value, $values[\App\Enums\WebsiteSettingKey::BrandDisplayMode->value] ?? \App\Enums\BrandDisplayMode::LogoNameTagline->value);
    @endphp

    <div class="row g-8">
        <div class="col-12 col-lg-3">
            <div class="card card-flush internal-card mb-8 mb-lg-0">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Categories</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <nav class="d-none d-lg-flex flex-column gap-1" aria-label="Website settings categories">
                        @foreach ($sections as $navSection)
                            <a
                                href="{{ route('administrator.website-settings.edit', ['section' => $navSection->value]) }}"
                                class="btn btn-sm {{ $navSection === $section ? 'btn-light-primary' : 'btn-light' }} text-start"
                            >
                                {{ $navSection->label() }}
                            </a>
                        @endforeach
                    </nav>
                    <label class="form-label d-lg-none" for="website-settings-section">Category</label>
                    <select
                        id="website-settings-section"
                        class="form-select d-lg-none"
                        onchange="window.location = this.value"
                    >
                        @foreach ($sections as $navSection)
                            <option
                                value="{{ route('administrator.website-settings.edit', ['section' => $navSection->value]) }}"
                                @selected($navSection === $section)
                            >
                                {{ $navSection->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-9">
            @if ($section === \App\Enums\WebsiteSettingSection::Business)
                <div class="alert alert-primary mb-8">
                    Opening hours are managed in
                    <a href="{{ route('administrator.cafe-schedule.index') }}">Café Schedule</a>.
                    This page keeps contact details and the timezone used by that schedule.
                </div>
            @endif
                <form method="POST" action="{{ route('administrator.website-settings.update') }}" class="form" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="{{ $section->value }}">

                    @if ($section === \App\Enums\WebsiteSettingSection::Payments)
                        <div class="card card-flush internal-card internal-form-card mb-8">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold text-gray-900">Payment method readiness</h3>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <p class="text-muted mb-6">
                                    Enabled ≠ available. Gateway secrets stay in environment variables and are never shown here.
                                </p>
                                <div class="row g-4">
                                    @foreach (($paymentMethodDiagnostics ?? []) as $row)
                                        @php
                                            $status = $row['configuration_status'] ?? 'disabled';
                                            $badge = match ($status) {
                                                'ready' => 'badge-light-success',
                                                'incomplete' => 'badge-light-warning',
                                                default => 'badge-light-secondary',
                                            };
                                            $label = match ($status) {
                                                'ready' => 'Ready',
                                                'incomplete' => 'Configuration incomplete',
                                                default => 'Disabled',
                                            };
                                        @endphp
                                        <div class="col-12 col-md-6">
                                            <div class="border rounded p-4 h-100">
                                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                    <div>
                                                        <div class="fw-bold text-gray-900">{{ $row['name'] }}</div>
                                                        <div class="text-muted fs-7">{{ $row['code'] }} · {{ $row['type'] }}</div>
                                                    </div>
                                                    <span class="badge {{ $badge }}">{{ $label }}</span>
                                                </div>
                                                <div class="fs-7 text-gray-700">
                                                    {{ ($row['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}
                                                    @if (! empty($row['mode']))
                                                        · Mode: {{ $row['mode'] }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="card card-flush internal-card internal-form-card mb-8">
                        <div class="card-header">
                            <div class="card-title">
                                <h3 class="fw-bold text-gray-900">{{ $section->label() }}</h3>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row g-6 internal-form-grid">
                                @foreach ($keys as $key)
                                    @continue($key === \App\Enums\WebsiteSettingKey::BrandFaviconPath)
                                    <div class="col-12 {{ $key->valueType() === 'string' && $key !== \App\Enums\WebsiteSettingKey::BrandDisplayMode ? 'col-md-6' : '' }}">
                                        @if ($key !== \App\Enums\WebsiteSettingKey::BrandDisplayMode && $key->formInputType() !== 'checkbox')
                                            <label for="{{ $key->value }}" class="form-label">{{ $key->label() }}</label>
                                        @endif

                                        @if (isset($mediaInputs[$key->value]))
                                            @php
                                                $storedPath = old($key->value, $values[$key->value] ?? null);
                                                $previewUrl = \App\Support\PublicMedia::url(is_string($storedPath) ? $storedPath : null);
                                                $fileInputName = $mediaInputs[$key->value]['file'];
                                                $removeInputName = $mediaInputs[$key->value]['remove'];
                                                $allowUrl = $mediaInputs[$key->value]['allow_url'];
                                            @endphp

                                            @if ($previewUrl)
                                                <div class="mb-3">
                                                    <img
                                                        src="{{ $previewUrl }}"
                                                        alt="{{ $key->label() }}"
                                                        class="rounded border"
                                                        style="max-width: 12rem; max-height: 8rem; object-fit: contain; background: #f5f5f5;"
                                                    />
                                                </div>
                                                @if ($allowUrl)
                                                    <input type="hidden" name="{{ $key->value }}" value="{{ $storedPath }}" />
                                                @endif
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
                                                </div>
                                            @elseif ($allowUrl)
                                                <input
                                                    id="{{ $key->value }}"
                                                    name="{{ $key->value }}"
                                                    type="text"
                                                    value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                                    class="form-control mb-3 @error($key->value) is-invalid @enderror"
                                                    placeholder="Optional absolute URL or leave blank and upload below"
                                                />
                                            @endif

                                            <input
                                                id="{{ $fileInputName }}"
                                                name="{{ $fileInputName }}"
                                                type="file"
                                                @if ($key === \App\Enums\WebsiteSettingKey::BrandLogoPath)
                                                    accept="image/svg+xml,image/jpeg,image/png,image/webp,.svg,.jpg,.jpeg,.png,.webp"
                                                @else
                                                    accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                                @endif
                                                class="form-control @error($fileInputName) is-invalid @enderror"
                                            />
                                            @error($fileInputName)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            @if ($key === \App\Enums\WebsiteSettingKey::BrandLogoPath)
                                                <div class="form-text">{{ $key->helpText() }} Supported: SVG, PNG, JPG, WebP. Maximum: {{ \App\Support\PublicMedia::brandLogoMaxMegabytesLabel() }} MB.</div>
                                            @else
                                                <div class="form-text">{{ $key->helpText() }} JPEG / PNG / WebP, max {{ \App\Support\PublicMedia::maxKilobytes() }} KB. SVG is not accepted.</div>
                                            @endif
                                        @elseif ($key === \App\Enums\WebsiteSettingKey::BrandDisplayMode)
                                            <div class="fw-semibold text-gray-800 mb-3">{{ $key->label() }}</div>
                                            @foreach (\App\Enums\BrandDisplayMode::ordered() as $mode)
                                                <div class="form-check mb-3">
                                                    <input
                                                        id="{{ $key->value }}_{{ $mode->value }}"
                                                        name="{{ $key->value }}"
                                                        type="radio"
                                                        value="{{ $mode->value }}"
                                                        class="form-check-input"
                                                        @checked($displayMode === $mode->value)
                                                    />
                                                    <label class="form-check-label" for="{{ $key->value }}_{{ $mode->value }}">{{ $mode->label() }}</label>
                                                </div>
                                            @endforeach
                                            @if ($key->helpText())
                                                <div class="form-text">{{ $key->helpText() }}</div>
                                            @endif
                                        @elseif ($key === \App\Enums\WebsiteSettingKey::ReferralRewardProductId)
                                            <div data-referral-free-drink-field>
                                                <select
                                                    id="{{ $key->value }}"
                                                    name="{{ $key->value }}"
                                                    class="form-select @error($key->value) is-invalid @enderror"
                                                    data-control="select2"
                                                    data-placeholder="Select a product"
                                                    data-allow-clear="true"
                                                >
                                                    <option value=""></option>
                                                    @foreach ($referralProducts as $product)
                                                        <option value="{{ $product->id }}" @selected((string) old($key->value, $values[$key->value] ?? '') === (string) $product->id)>
                                                            {{ $product->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if ($key->helpText())
                                                    <div class="form-text">{{ $key->helpText() }}</div>
                                                @endif
                                            </div>
                                        @elseif ($key === \App\Enums\WebsiteSettingKey::ReferralRewardType)
                                            @php $rewardType = old($key->value, $values[$key->value] ?? 'free_drink'); @endphp
                                            <select id="{{ $key->value }}" name="{{ $key->value }}" class="form-select" data-referral-reward-type>
                                                <option value="free_drink" @selected($rewardType === 'free_drink')>Free Drink</option>
                                                <option value="coupon" @selected($rewardType === 'coupon')>Coupon</option>
                                            </select>
                                            @if ($key->helpText())
                                                <div class="form-text">{{ $key->helpText() }}</div>
                                            @endif
                                        @elseif ($key === \App\Enums\WebsiteSettingKey::ReferralCouponDiscountType)
                                            @php $couponType = old($key->value, $values[$key->value] ?? 'fixed'); @endphp
                                            <div data-referral-coupon-field>
                                                <select id="{{ $key->value }}" name="{{ $key->value }}" class="form-select">
                                                    <option value="fixed" @selected($couponType === 'fixed')>Fixed amount</option>
                                                    <option value="percentage" @selected($couponType === 'percentage')>Percentage</option>
                                                </select>
                                                @if ($key->helpText())
                                                    <div class="form-text">{{ $key->helpText() }}</div>
                                                @endif
                                            </div>
                                        @elseif (in_array($key, [\App\Enums\WebsiteSettingKey::ReferralRewardVariantId, \App\Enums\WebsiteSettingKey::ReferralRewardQuantity], true))
                                            <div data-referral-free-drink-field>
                                                <input id="{{ $key->value }}" name="{{ $key->value }}" type="number" value="{{ old($key->value, $values[$key->value] ?? '') }}" class="form-control" min="1" step="1" inputmode="numeric" />
                                                @if ($key->helpText())
                                                    <div class="form-text">{{ $key->helpText() }}</div>
                                                @endif
                                            </div>
                                        @elseif (in_array($key, [\App\Enums\WebsiteSettingKey::ReferralCouponDiscountValue, \App\Enums\WebsiteSettingKey::ReferralCouponMaxDiscount, \App\Enums\WebsiteSettingKey::ReferralCouponMinimumSubtotal], true))
                                            <div data-referral-coupon-field>
                                                <input id="{{ $key->value }}" name="{{ $key->value }}" type="number" value="{{ old($key->value, $values[$key->value] ?? '') }}" class="form-control" min="0" step="0.01" inputmode="decimal" />
                                                @if ($key->helpText())
                                                    <div class="form-text">{{ $key->helpText() }}</div>
                                                @endif
                                            </div>
                                        @elseif ($key === \App\Enums\WebsiteSettingKey::TaxInclusive)
                                            @php $inclusive = filter_var(old($key->value, $values[$key->value] ?? '0'), FILTER_VALIDATE_BOOLEAN); @endphp
                                            <div class="mb-2 fw-semibold text-gray-800">Pricing</div>
                                            <div class="form-check mb-2">
                                                <input id="{{ $key->value }}_exclusive" name="{{ $key->value }}" type="radio" value="0" class="form-check-input" @checked(! $inclusive) />
                                                <label class="form-check-label" for="{{ $key->value }}_exclusive">Exclusive — GST added to subtotal</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input id="{{ $key->value }}_inclusive" name="{{ $key->value }}" type="radio" value="1" class="form-check-input" @checked($inclusive) />
                                                <label class="form-check-label" for="{{ $key->value }}_inclusive">Inclusive — displayed prices already include GST</label>
                                            </div>
                                            @if ($key->helpText())
                                                <div class="form-text">{{ $key->helpText() }}</div>
                                            @endif
                                        @elseif ($key->valueType() === 'boolean')
                                            @php
                                                $checked = filter_var(old($key->value, $values[$key->value] ?? '0'), FILTER_VALIDATE_BOOLEAN);
                                                $methodCode = match ($key) {
                                                    \App\Enums\WebsiteSettingKey::PaymentCashEnabled => 'cash',
                                                    \App\Enums\WebsiteSettingKey::PaymentManualUpiEnabled => 'manual_upi',
                                                    \App\Enums\WebsiteSettingKey::PaymentRazorpayEnabled => 'razorpay',
                                                    \App\Enums\WebsiteSettingKey::PaymentPayuEnabled => 'payu',
                                                    \App\Enums\WebsiteSettingKey::PaymentPaytmEnabled => 'paytm',
                                                    \App\Enums\WebsiteSettingKey::PaymentPhonepeEnabled => 'phonepe',
                                                    default => null,
                                                };
                                                $diag = $methodCode ? ($diagnosticsByCode[$methodCode] ?? null) : null;
                                            @endphp
                                            <input type="hidden" name="{{ $key->value }}" value="0">
                                            <div class="form-check form-switch form-check-custom form-check-solid">
                                                <input id="{{ $key->value }}" name="{{ $key->value }}" type="checkbox" value="1" class="form-check-input" @checked($checked) />
                                                <label class="form-check-label" for="{{ $key->value }}">{{ $key->label() }}</label>
                                            </div>
                                            @if ($diag)
                                                @php
                                                    $status = $diag['configuration_status'] ?? 'disabled';
                                                    $badge = match ($status) {
                                                        'ready' => 'badge-light-success',
                                                        'incomplete' => 'badge-light-warning',
                                                        default => 'badge-light-secondary',
                                                    };
                                                @endphp
                                                <div class="mt-2"><span class="badge {{ $badge }}">Configuration: {{ ucfirst($status) }}</span></div>
                                            @endif
                                            @if ($key->helpText())
                                                <div class="form-text">{{ $key->helpText() }}</div>
                                            @endif
                                        @elseif ($key->valueType() === 'text')
                                            <textarea id="{{ $key->value }}" name="{{ $key->value }}" rows="4" class="form-control @error($key->value) is-invalid @enderror">{{ old($key->value, $values[$key->value] ?? '') }}</textarea>
                                            @error($key->value)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            @if ($key->helpText())
                                                <div class="form-text">{{ $key->helpText() }}</div>
                                            @endif
                                            @if ($section === \App\Enums\WebsiteSettingSection::Dining)
                                                @php $fallback = $fulfilmentConfig[str_replace('fulfilment_', '', $key->value)] ?? null; @endphp
                                                @if (filled($fallback))
                                                    <div class="form-text">Config fallback: {{ $fallback }}</div>
                                                @endif
                                            @endif
                                        @else
                                            <input
                                                id="{{ $key->value }}"
                                                name="{{ $key->value }}"
                                                type="{{ $key->formInputType() }}"
                                                value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                                class="form-control @error($key->value) is-invalid @enderror"
                                                @if ($key === \App\Enums\WebsiteSettingKey::TaxPercent) min="0" max="100" step="0.01" inputmode="decimal" @endif
                                            />
                                            @error($key->value)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            @if ($key->helpText())
                                                <div class="form-text">{{ $key->helpText() }}</div>
                                            @endif
                                            @if ($section === \App\Enums\WebsiteSettingSection::Payments)
                                                @php $fallback = $paymentConfig[str_replace('payment_', '', $key->value)] ?? null; @endphp
                                                @if (filled($fallback))
                                                    <div class="form-text">Config fallback: {{ $fallback }}</div>
                                                @endif
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end internal-form-actions mb-10">
                        <x-internal.button-group :items="[
                            ['label' => 'Save '.$section->label(), 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                        ]" />
                    </div>
                </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const typeSelect = document.querySelector('[data-referral-reward-type]');
    if (!typeSelect) {
        return;
    }

    const sync = () => {
        const isFreeDrink = typeSelect.value === 'free_drink';
        document.querySelectorAll('[data-referral-free-drink-field]').forEach((el) => {
            el.closest('.col-12')?.classList.toggle('d-none', !isFreeDrink);
        });
        document.querySelectorAll('[data-referral-coupon-field]').forEach((el) => {
            el.closest('.col-12')?.classList.toggle('d-none', isFreeDrink);
        });
    };

    typeSelect.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
