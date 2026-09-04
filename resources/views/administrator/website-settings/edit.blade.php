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
            'payment' => 'Payment methods & display',
            'fulfilment' => 'Fulfilment',
            'tax' => 'Tax / GST',
            'order_security' => 'Order Security',
            'referral' => 'Customer referrals',
            'pages' => 'Static pages',
        ];
        $mediaKeys = [
            \App\Enums\WebsiteSettingKey::HeroImagePath->value,
            \App\Enums\WebsiteSettingKey::PaymentQrImagePath->value,
        ];
        $diagnosticsByCode = collect($paymentMethodDiagnostics ?? [])->keyBy('code');
    @endphp

    <form method="POST" action="{{ route('administrator.website-settings.update') }}" class="form" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="alert alert-primary mb-8">
            Customer-facing café content belongs here. Payment and delivery fields use website settings when filled;
            empty fields fall back to <code>config/coffee.php</code> / env so infrastructure defaults stay separate from operational CMS values.
            Gateway secrets stay in environment variables and are never shown here. Demo seed data is local/testing only and must not be used as production content.
        </div>

        <div class="card card-flush internal-card internal-form-card mb-8">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-900">Payment method readiness</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <p class="text-muted mb-6">
                    Enabled ≠ available. A method appears to customers only when it is enabled, configured, and eligible for the fulfilment type.
                    Online gateway credentials are configured via environment variables (<code>RAZORPAY_*</code>, <code>PAYU_*</code>, <code>PAYTM_*</code>, <code>PHONEPE_*</code>).
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
                        <div class="col-12 col-md-6 col-xl-4">
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
                                @if (($row['enabled'] ?? false) && ($row['mode'] ?? null) === 'test')
                                    <div class="fs-8 text-warning mt-2">Test/sandbox credentials active while method is enabled.</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
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
                                @elseif ($key === \App\Enums\WebsiteSettingKey::ReferralRewardType)
                                    @php
                                        $rewardType = old($key->value, $values[$key->value] ?? 'free_drink');
                                    @endphp
                                    <select
                                        id="{{ $key->value }}"
                                        name="{{ $key->value }}"
                                        class="form-select @error($key->value) is-invalid @enderror"
                                        data-referral-reward-type
                                    >
                                        <option value="free_drink" @selected($rewardType === 'free_drink')>Free Drink</option>
                                        <option value="coupon" @selected($rewardType === 'coupon')>Coupon</option>
                                    </select>
                                    @error($key->value)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if ($key->helpText())
                                        <div class="form-text">{{ $key->helpText() }}</div>
                                    @endif
                                @elseif ($key === \App\Enums\WebsiteSettingKey::ReferralCouponDiscountType)
                                    @php
                                        $couponType = old($key->value, $values[$key->value] ?? 'fixed');
                                    @endphp
                                    <div data-referral-coupon-field>
                                        <select
                                            id="{{ $key->value }}"
                                            name="{{ $key->value }}"
                                            class="form-select @error($key->value) is-invalid @enderror"
                                        >
                                            <option value="fixed" @selected($couponType === 'fixed')>Fixed amount</option>
                                            <option value="percentage" @selected($couponType === 'percentage')>Percentage</option>
                                        </select>
                                        @error($key->value)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @if ($key->helpText())
                                            <div class="form-text">{{ $key->helpText() }}</div>
                                        @endif
                                    </div>
                                @elseif (in_array($key, [
                                    \App\Enums\WebsiteSettingKey::ReferralRewardProductId,
                                    \App\Enums\WebsiteSettingKey::ReferralRewardVariantId,
                                    \App\Enums\WebsiteSettingKey::ReferralRewardQuantity,
                                ], true))
                                    <div data-referral-free-drink-field>
                                        <input
                                            id="{{ $key->value }}"
                                            name="{{ $key->value }}"
                                            type="number"
                                            value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                            class="form-control @error($key->value) is-invalid @enderror"
                                            min="1"
                                            step="1"
                                            inputmode="numeric"
                                        />
                                        @error($key->value)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @if ($key->helpText())
                                            <div class="form-text">{{ $key->helpText() }}</div>
                                        @endif
                                    </div>
                                @elseif (in_array($key, [
                                    \App\Enums\WebsiteSettingKey::ReferralCouponDiscountValue,
                                    \App\Enums\WebsiteSettingKey::ReferralCouponMaxDiscount,
                                    \App\Enums\WebsiteSettingKey::ReferralCouponMinimumSubtotal,
                                ], true))
                                    <div data-referral-coupon-field>
                                        <input
                                            id="{{ $key->value }}"
                                            name="{{ $key->value }}"
                                            type="number"
                                            value="{{ old($key->value, $values[$key->value] ?? '') }}"
                                            class="form-control @error($key->value) is-invalid @enderror"
                                            min="0"
                                            step="0.01"
                                            inputmode="decimal"
                                        />
                                        @error($key->value)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @if ($key->helpText())
                                            <div class="form-text">{{ $key->helpText() }}</div>
                                        @endif
                                    </div>
                                @elseif ($key === \App\Enums\WebsiteSettingKey::TaxInclusive)
                                    @php
                                        $inclusive = filter_var(old($key->value, $values[$key->value] ?? '0'), FILTER_VALIDATE_BOOLEAN);
                                    @endphp
                                    <div class="mb-2 fw-semibold text-gray-800">Pricing</div>
                                    <div class="form-check mb-2">
                                        <input
                                            id="{{ $key->value }}_exclusive"
                                            name="{{ $key->value }}"
                                            type="radio"
                                            value="0"
                                            class="form-check-input @error($key->value) is-invalid @enderror"
                                            @checked(! $inclusive)
                                        />
                                        <label class="form-check-label" for="{{ $key->value }}_exclusive">
                                            Exclusive — GST added to subtotal
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input
                                            id="{{ $key->value }}_inclusive"
                                            name="{{ $key->value }}"
                                            type="radio"
                                            value="1"
                                            class="form-check-input @error($key->value) is-invalid @enderror"
                                            @checked($inclusive)
                                        />
                                        <label class="form-check-label" for="{{ $key->value }}_inclusive">
                                            Inclusive — displayed prices already include GST
                                        </label>
                                    </div>
                                    @error($key->value)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
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
                                        <input
                                            id="{{ $key->value }}"
                                            name="{{ $key->value }}"
                                            type="checkbox"
                                            value="1"
                                            class="form-check-input @error($key->value) is-invalid @enderror"
                                            @checked($checked)
                                        />
                                        <label class="form-check-label" for="{{ $key->value }}">
                                            {{ $key->label() }}
                                        </label>
                                        @error($key->value)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @if ($diag)
                                        @php
                                            $status = $diag['configuration_status'] ?? 'disabled';
                                            $badge = match ($status) {
                                                'ready' => 'badge-light-success',
                                                'incomplete' => 'badge-light-warning',
                                                default => 'badge-light-secondary',
                                            };
                                            $label = match ($status) {
                                                'ready' => 'Configuration: Ready',
                                                'incomplete' => 'Configuration: Incomplete',
                                                default => 'Configuration: Disabled',
                                            };
                                        @endphp
                                        <div class="mt-2"><span class="badge {{ $badge }}">{{ $label }}</span></div>
                                    @endif
                                    @if ($key->helpText())
                                        <div class="form-text">{{ $key->helpText() }}</div>
                                    @endif
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
                                    @if ($key->helpText())
                                        <div class="form-text">{{ $key->helpText() }}</div>
                                    @endif
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
                                        @if ($key === \App\Enums\WebsiteSettingKey::TaxPercent)
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            inputmode="decimal"
                                        @elseif ($key->valueType() === 'integer')
                                            min="1"
                                            max="{{ $key === \App\Enums\WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders ? 20 : ($key === \App\Enums\WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes ? 30 : 60) }}"
                                            step="1"
                                            inputmode="numeric"
                                        @endif
                                    />
                                    @error($key->value)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if ($key->helpText())
                                        <div class="form-text">{{ $key->helpText() }}</div>
                                    @endif
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
