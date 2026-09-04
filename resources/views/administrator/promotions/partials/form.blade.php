@php
    $startsLocal = old('starts_at', $promotion->starts_at?->format('Y-m-d\TH:i'));
    $endsLocal = old('ends_at', $promotion->ends_at?->format('Y-m-d\TH:i'));
    $dailyStarts = old('daily_starts_at', $promotion->daily_starts_at ? substr((string) $promotion->daily_starts_at, 0, 5) : null);
    $dailyEnds = old('daily_ends_at', $promotion->daily_ends_at ? substr((string) $promotion->daily_ends_at, 0, 5) : null);
@endphp

<form method="POST" action="{{ $action }}" class="form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($usageCount !== null)
        <div class="alert alert-light-primary mb-8">
            Applied on <strong>{{ $usageCount }}</strong> order{{ $usageCount === 1 ? '' : 's' }} so far.
        </div>
    @endif

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">{{ $title }} — Basic</h3>
            </div>
            <div class="card-toolbar">
                <a href="{{ route('administrator.documentation.show', 'promotions') }}" class="btn btn-sm btn-light" target="_blank" rel="noopener">
                    ? Promotion conditions &amp; examples
                </a>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $promotion->name) }}" required maxlength="255" class="form-control @error('name') is-invalid @enderror" />
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="type" class="required form-label">Type</label>
                    <select id="type" name="type" required class="form-select @error('type') is-invalid @enderror">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $promotion->type?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="code" class="form-label">Promo code</label>
                    <input id="code" name="code" type="text" value="{{ old('code', $promotion->code) }}" maxlength="40" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="Required for coupons" />
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Leave blank for automatic offers. Codes are stored uppercase.</div>
                </div>
                <div class="col-md-6">
                    <label for="priority" class="form-label">Priority</label>
                    <input id="priority" name="priority" type="number" step="1" value="{{ old('priority', $promotion->priority ?? 0) }}" class="form-control @error('priority') is-invalid @enderror" />
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Higher priority wins when multiple offers compete.</div>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="2" maxlength="1000" class="form-control @error('description') is-invalid @enderror">{{ old('description', $promotion->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="customer_message" class="form-label">Customer message</label>
                    <input id="customer_message" name="customer_message" type="text" value="{{ old('customer_message', $promotion->customer_message) }}" maxlength="500" class="form-control @error('customer_message') is-invalid @enderror" placeholder="Shown on cart / checkout" />
                    @error('customer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="internal_note" class="form-label">Internal note</label>
                    <textarea id="internal_note" name="internal_note" rows="2" maxlength="1000" class="form-control @error('internal_note') is-invalid @enderror">{{ old('internal_note', $promotion->internal_note) }}</textarea>
                    @error('internal_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Discount</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-4">
                    <label for="discount_type" class="required form-label">Discount type</label>
                    <select id="discount_type" name="discount_type" required class="form-select @error('discount_type') is-invalid @enderror">
                        @foreach ($discountTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('discount_type', $promotion->discount_type?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('discount_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="discount_value" class="required form-label">Discount value</label>
                    <input id="discount_value" name="discount_value" type="number" min="0.01" step="0.01" value="{{ old('discount_value', $promotion->discount_value) }}" required class="form-control @error('discount_value') is-invalid @enderror" />
                    @error('discount_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Percentage (e.g. 10) or fixed ₹ amount.</div>
                </div>
                <div class="col-md-4">
                    <label for="maximum_discount_amount" class="form-label">Max discount (₹)</label>
                    <input id="maximum_discount_amount" name="maximum_discount_amount" type="number" min="0" step="0.01" value="{{ old('maximum_discount_amount', $promotion->maximum_discount_amount) }}" class="form-control @error('maximum_discount_amount') is-invalid @enderror" />
                    @error('maximum_discount_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="minimum_subtotal" class="form-label">Minimum subtotal (₹)</label>
                    <input id="minimum_subtotal" name="minimum_subtotal" type="number" min="0" step="0.01" value="{{ old('minimum_subtotal', $promotion->minimum_subtotal) }}" class="form-control @error('minimum_subtotal') is-invalid @enderror" />
                    @error('minimum_subtotal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="fulfilment_scope" class="required form-label">Fulfilment scope</label>
                    <select id="fulfilment_scope" name="fulfilment_scope" required class="form-select @error('fulfilment_scope') is-invalid @enderror">
                        @foreach ($fulfilmentScopeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('fulfilment_scope', $promotion->fulfilment_scope?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('fulfilment_scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Eligibility</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <input type="hidden" name="applies_to_all_products" value="0">
                    <div class="form-check form-switch form-check-custom form-check-solid mb-4">
                        <input class="form-check-input" type="checkbox" id="applies_to_all_products" name="applies_to_all_products" value="1" @checked(old('applies_to_all_products', $promotion->applies_to_all_products))>
                        <label class="form-check-label" for="applies_to_all_products">Applies to all products</label>
                    </div>
                    <label for="product_ids" class="form-label">Products</label>
                    <select id="product_ids" name="product_ids[]" class="form-select @error('product_ids') is-invalid @enderror" data-control="select2" multiple data-placeholder="Select products">
                        @foreach ($productOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedProductIds, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="product_category_ids" class="form-label">Categories</label>
                    <select id="product_category_ids" name="product_category_ids[]" class="form-select @error('product_category_ids') is-invalid @enderror" data-control="select2" multiple data-placeholder="Select categories">
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedCategoryIds, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">Used when the offer is limited to specific products or categories.</div>
                </div>
                <div class="col-md-6">
                    <input type="hidden" name="applies_to_all_customers" value="0">
                    <div class="form-check form-switch form-check-custom form-check-solid mb-4">
                        <input class="form-check-input" type="checkbox" id="applies_to_all_customers" name="applies_to_all_customers" value="1" @checked(old('applies_to_all_customers', $promotion->applies_to_all_customers))>
                        <label class="form-check-label" for="applies_to_all_customers">Applies to all customers</label>
                    </div>
                    <label for="customer_ids" class="form-label">Customers</label>
                    <select id="customer_ids" name="customer_ids[]" class="form-select @error('customer_ids') is-invalid @enderror" data-control="select2" multiple data-placeholder="Select customers">
                        @foreach ($customerOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedCustomerIds, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('customer_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <input type="hidden" name="first_order_only" value="0">
                    <div class="form-check form-switch form-check-custom form-check-solid mb-4">
                        <input class="form-check-input" type="checkbox" id="first_order_only" name="first_order_only" value="1" @checked(old('first_order_only', $promotion->first_order_only))>
                        <label class="form-check-label" for="first_order_only">First order only</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Schedule</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <label for="starts_at" class="form-label">Starts at</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ $startsLocal }}" class="form-control @error('starts_at') is-invalid @enderror" />
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="ends_at" class="form-label">Ends at</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ $endsLocal }}" class="form-control @error('ends_at') is-invalid @enderror" />
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="daily_starts_at" class="form-label">Daily starts at</label>
                    <input id="daily_starts_at" name="daily_starts_at" type="time" value="{{ $dailyStarts }}" class="form-control @error('daily_starts_at') is-invalid @enderror" />
                    @error('daily_starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="daily_ends_at" class="form-label">Daily ends at</label>
                    <input id="daily_ends_at" name="daily_ends_at" type="time" value="{{ $dailyEnds }}" class="form-control @error('daily_ends_at') is-invalid @enderror" />
                    @error('daily_ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Weekdays</label>
                    <div class="d-flex flex-wrap gap-4">
                        @foreach ($weekdayOptions as $value => $label)
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="weekdays[]" id="weekday_{{ $value }}" value="{{ $value }}" @checked(in_array((string) $value, $selectedWeekdays, true))>
                                <label class="form-check-label" for="weekday_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('weekdays')<div class="text-danger fs-7 mt-2">{{ $message }}</div>@enderror
                    <div class="form-text">Leave all unchecked to allow every day.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Limits</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <label for="usage_limit" class="form-label">Total usage limit</label>
                    <input id="usage_limit" name="usage_limit" type="number" min="1" step="1" value="{{ old('usage_limit', $promotion->usage_limit) }}" class="form-control @error('usage_limit') is-invalid @enderror" />
                    @error('usage_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="usage_limit_per_customer" class="form-label">Per-customer limit</label>
                    <input id="usage_limit_per_customer" name="usage_limit_per_customer" type="number" min="1" step="1" value="{{ old('usage_limit_per_customer', $promotion->usage_limit_per_customer) }}" class="form-control @error('usage_limit_per_customer') is-invalid @enderror" />
                    @error('usage_limit_per_customer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Behavior</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6">
                <div class="col-md-4">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $promotion->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <input type="hidden" name="stackable" value="0">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" id="stackable" name="stackable" value="1" @checked(old('stackable', $promotion->stackable))>
                        <label class="form-check-label" for="stackable">Stackable with other offers</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end internal-form-actions mb-10">
        <x-internal.button-group :items="[
            ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ['label' => 'Cancel', 'url' => route('administrator.promotions.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ]" />
    </div>
</form>
