@php
    $startsLocal = old('starts_at', $reward->starts_at?->format('Y-m-d\TH:i'));
    $endsLocal = old('ends_at', $reward->ends_at?->format('Y-m-d\TH:i'));
@endphp

<form method="POST" action="{{ $action }}" class="form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">{{ $title }} — Basic</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $reward->name) }}" required maxlength="255" class="form-control @error('name') is-invalid @enderror" />
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="status" class="required form-label">Status</label>
                    <select id="status" name="status" required class="form-select @error('status') is-invalid @enderror">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $reward->status?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="points_cost" class="required form-label">Points cost</label>
                    <input id="points_cost" name="points_cost" type="number" min="1" step="1" value="{{ old('points_cost', $reward->points_cost) }}" required class="form-control @error('points_cost') is-invalid @enderror" />
                    @error('points_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="reward_type" class="required form-label">Reward type</label>
                    <select id="reward_type" name="reward_type" required class="form-select @error('reward_type') is-invalid @enderror">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('reward_type', $reward->reward_type?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reward_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="priority" class="form-label">Priority</label>
                    <input id="priority" name="priority" type="number" step="1" value="{{ old('priority', $reward->priority ?? 0) }}" class="form-control @error('priority') is-invalid @enderror" />
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="customer_description" class="form-label">Customer description</label>
                    <input id="customer_description" name="customer_description" type="text" value="{{ old('customer_description', $reward->customer_description) }}" maxlength="500" class="form-control @error('customer_description') is-invalid @enderror" />
                    @error('customer_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="internal_note" class="form-label">Internal note</label>
                    <textarea id="internal_note" name="internal_note" rows="2" maxlength="500" class="form-control @error('internal_note') is-invalid @enderror">{{ old('internal_note', $reward->internal_note) }}</textarea>
                    @error('internal_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Value & limits</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-4">
                    <label for="discount_amount" class="form-label">Fixed discount amount</label>
                    <input id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="{{ $configDiscountAmount }}" class="form-control @error('discount_amount') is-invalid @enderror" />
                    @error('discount_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="percent" class="form-label">Percent off</label>
                    <input id="percent" name="percent" type="number" min="0" max="100" step="0.01" value="{{ $configPercent }}" class="form-control @error('percent') is-invalid @enderror" />
                    @error('percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="maximum_discount_amount" class="form-label">Max discount (percent)</label>
                    <input id="maximum_discount_amount" name="maximum_discount_amount" type="number" min="0" step="0.01" value="{{ $configMaximumDiscountAmount }}" class="form-control @error('maximum_discount_amount') is-invalid @enderror" />
                    @error('maximum_discount_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="minimum_spend" class="form-label">Minimum spend</label>
                    <input id="minimum_spend" name="minimum_spend" type="number" min="0" step="0.01" value="{{ old('minimum_spend', $reward->minimum_spend) }}" class="form-control @error('minimum_spend') is-invalid @enderror" />
                    @error('minimum_spend')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="starts_at" class="form-label">Starts at</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ $startsLocal }}" class="form-control @error('starts_at') is-invalid @enderror" />
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="ends_at" class="form-label">Ends at</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ $endsLocal }}" class="form-control @error('ends_at') is-invalid @enderror" />
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="usage_limit" class="form-label">Global usage limit</label>
                    <input id="usage_limit" name="usage_limit" type="number" min="1" step="1" value="{{ old('usage_limit', $reward->usage_limit) }}" class="form-control @error('usage_limit') is-invalid @enderror" />
                    @error('usage_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="usage_limit_per_customer" class="form-label">Per-customer limit</label>
                    <input id="usage_limit_per_customer" name="usage_limit_per_customer" type="number" min="1" step="1" value="{{ old('usage_limit_per_customer', $reward->usage_limit_per_customer) }}" class="form-control @error('usage_limit_per_customer') is-invalid @enderror" />
                    @error('usage_limit_per_customer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="usage_limit_per_customer_period_days" class="form-label">Per-customer period (days)</label>
                    <input id="usage_limit_per_customer_period_days" name="usage_limit_per_customer_period_days" type="number" min="1" step="1" value="{{ old('usage_limit_per_customer_period_days', $reward->usage_limit_per_customer_period_days) }}" class="form-control @error('usage_limit_per_customer_period_days') is-invalid @enderror" />
                    @error('usage_limit_per_customer_period_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Eligible products</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-4">
                    <label for="product_ids" class="form-label">Products</label>
                    <select id="product_ids" name="product_ids[]" multiple size="8" class="form-select @error('product_ids') is-invalid @enderror">
                        @foreach ($productOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedProductIds, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="product_category_ids" class="form-label">Categories</label>
                    <select id="product_category_ids" name="product_category_ids[]" multiple size="8" class="form-select @error('product_category_ids') is-invalid @enderror">
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedCategoryIds, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="add_on_ids" class="form-label">Add-ons</label>
                    <select id="add_on_ids" name="add_on_ids[]" multiple size="8" class="form-select @error('add_on_ids') is-invalid @enderror">
                        @foreach ($addOnOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedAddOnIds, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('add_on_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <x-internal.button-group :items="[
        ['label' => 'Save reward', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
        ['label' => 'Cancel', 'url' => route('administrator.loyalty-rewards.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
</form>
