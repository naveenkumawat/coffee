@extends('administrator.layouts.default')

@section('page-title', 'Financial Report')

@section('page-description', 'Canonical paid sales, GST snapshots, discounts, and payment reconciliation.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Financial Report'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Export CSV',
            'url' => route('administrator.reports.financial.export', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
    ]" />
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $payments = $report['payments'];
        $gst = $report['gst'];
        $discounts = $report['discounts'];
        $cancellations = $report['cancellations'];
        $channels = $report['channels'];
    @endphp

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.reports.financial.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-2 col-md-4">
                    <label for="preset" class="form-label">Date range</label>
                    <select id="preset" name="preset" class="form-select">
                        <option value="today" @selected($filters['preset'] === 'today')>Today</option>
                        <option value="yesterday" @selected($filters['preset'] === 'yesterday')>Yesterday</option>
                        <option value="last_7_days" @selected($filters['preset'] === 'last_7_days')>Last 7 days</option>
                        <option value="this_month" @selected($filters['preset'] === 'this_month')>This month</option>
                        <option value="custom" @selected($filters['preset'] === 'custom')>Custom</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="from" class="form-label">From</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-control" />
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="to" class="form-label">To</label>
                    <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="form-control" />
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="channel" class="form-label">Channel</label>
                    <select id="channel" name="channel" class="form-select">
                        <option value="all" @selected($filters['channel'] === 'all')>All channels</option>
                        <option value="takeaway" @selected($filters['channel'] === 'takeaway')>Takeaway</option>
                        <option value="delivery" @selected($filters['channel'] === 'delivery')>Delivery</option>
                        <option value="dining" @selected($filters['channel'] === 'dining')>Dining</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="payment_method" class="form-label">Payment</label>
                    <select id="payment_method" name="payment_method" class="form-select">
                        <option value="all" @selected($filters['payment_method'] === 'all')>All methods</option>
                        <option value="cash" @selected($filters['payment_method'] === 'cash')>Cash</option>
                        <option value="manual" @selected($filters['payment_method'] === 'manual')>UPI / QR</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Apply', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.reports.financial.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
            <div class="text-muted fs-8 mt-4">
                Business timezone: {{ $report['timezone'] }}
                · {{ $report['start_local']->format('d M Y H:i') }}
                – {{ $report['end_local']->format('d M Y H:i') }}
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Gross Paid Sales" :value="'₹'.$summary['gross_paid_sales']" icon="ki-chart-line" color="primary" description="Merchandise subtotal snapshots." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Discounts" :value="'₹'.$summary['discounts']" icon="ki-discount" color="warning" description="Persisted discount totals." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="GST Collected" :value="'₹'.$summary['gst_collected']" icon="ki-bill" color="info" description="Tax amount snapshots." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Net / Final Collected" :value="'₹'.$summary['net_final_collected']" icon="ki-wallet" color="success" description="Confirmed paid totals." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Transactions" :value="$summary['transaction_count']" icon="ki-delivery-2" color="dark" description="Paid retail orders + dining sessions." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Avg Transaction" :value="'₹'.$summary['average_transaction_value']" icon="ki-chart" color="primary" description="Net ÷ transaction count." />
        </div>
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-lg-4">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Takeaway</h3></div></div>
                <div class="card-body pt-2">
                    <div class="fs-7 text-muted">Transactions</div>
                    <div class="fw-bold fs-3 mb-3">{{ $channels['takeaway']['transactions'] }}</div>
                    <div class="fs-7 text-muted">Paid sales</div>
                    <div class="fw-bold mb-3">₹{{ $channels['takeaway']['paid_sales'] }}</div>
                    <div class="fs-7 text-muted">Average value</div>
                    <div class="fw-bold">₹{{ $channels['takeaway']['average_value'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Delivery</h3></div></div>
                <div class="card-body pt-2">
                    <div class="fs-7 text-muted">Transactions</div>
                    <div class="fw-bold fs-3 mb-3">{{ $channels['delivery']['transactions'] }}</div>
                    <div class="fs-7 text-muted">Paid sales</div>
                    <div class="fw-bold mb-3">₹{{ $channels['delivery']['paid_sales'] }}</div>
                    <div class="fs-7 text-muted">Average value</div>
                    <div class="fw-bold">₹{{ $channels['delivery']['average_value'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Dining</h3></div></div>
                <div class="card-body pt-2">
                    <div class="fs-7 text-muted">Paid sessions</div>
                    <div class="fw-bold fs-3 mb-3">{{ $channels['dining']['paid_sessions'] }}</div>
                    <div class="fs-7 text-muted">Session sales</div>
                    <div class="fw-bold mb-3">₹{{ $channels['dining']['sales'] }}</div>
                    <div class="fs-7 text-muted">Average session value</div>
                    <div class="fw-bold mb-3">₹{{ $channels['dining']['average_session_value'] }}</div>
                    <div class="fs-8 text-muted">Rounds (informational only): {{ $channels['dining']['round_count'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Payment reconciliation</h3></div></div>
                <div class="card-body pt-2">
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Cash collected</span><span class="fw-bold">₹{{ $payments['cash_collected'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>UPI confirmed</span><span class="fw-bold">₹{{ $payments['upi_confirmed'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Pending payment ({{ $payments['pending_payment_count'] }})</span><span class="fw-bold">₹{{ $payments['pending_payment'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Rejected / failed proof ({{ $payments['rejected_failed_proof_count'] }})</span><span class="fw-bold">₹{{ $payments['rejected_failed_proof'] }}</span>
                    </div>
                    <div class="fs-8 text-muted mt-4">
                        Takeaway cash ₹{{ $payments['takeaway_cash']['amount'] }}
                        · Dining cash ₹{{ $payments['dining_cash']['amount'] }}
                        · Retail UPI ₹{{ $payments['retail_upi']['amount'] }}
                        · Dining UPI ₹{{ $payments['dining_upi']['amount'] }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">GST & discounts</h3></div></div>
                <div class="card-body pt-2">
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Taxable base</span><span class="fw-bold">₹{{ $gst['taxable_base'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>GST amount</span><span class="fw-bold">₹{{ $gst['gst_amount'] }}</span>
                    </div>
                    <div class="fs-8 text-muted mb-4">
                        Inclusive txns {{ $gst['inclusive_transaction_count'] }}
                        · Exclusive txns {{ $gst['exclusive_transaction_count'] }}
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Promotion discounts</span><span class="fw-bold">₹{{ $discounts['promotion_discounts'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Referral coupon discounts</span><span class="fw-bold">₹{{ $discounts['referral_coupon_discounts'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                        <span>Free-drink benefit value</span><span class="fw-bold">₹{{ $discounts['free_drink_benefit_value'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Other discount totals</span><span class="fw-bold">₹{{ $discounts['other_discount_totals'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card mb-7">
        <div class="card-header pt-6">
            <div class="card-title"><h3 class="fw-bold">Cancellations / rejections</h3></div>
        </div>
        <div class="card-body pt-2">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-muted fs-8">Cancelled</div>
                    <div class="fw-bold">{{ $cancellations['cancelled']['count'] }} · ₹{{ $cancellations['cancelled']['value'] }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-8">Rejected</div>
                    <div class="fw-bold">{{ $cancellations['rejected']['count'] }} · ₹{{ $cancellations['rejected']['value'] }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted fs-8">Paid cancellation exceptions</div>
                    <div class="fw-bold">{{ $cancellations['paid_cancellation_exceptions']['count'] }} · ₹{{ $cancellations['paid_cancellation_exceptions']['value'] }}</div>
                </div>
            </div>
            <div class="fs-8 text-muted mt-4">{{ $cancellations['note'] }}</div>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header pt-6">
            <div class="card-title"><h3 class="fw-bold">Paid transactions</h3></div>
        </div>
        <div class="card-body pt-2">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead>
                        <tr class="text-start text-muted fw-bold text-uppercase gs-0">
                            <th>Date / time</th>
                            <th>Reference</th>
                            <th>Channel</th>
                            <th>Payment</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">GST</th>
                            <th class="text-end">Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['transactions'] as $txn)
                            <tr>
                                <td>{{ $txn['date_time'] }}</td>
                                <td>
                                    <a href="{{ $txn['url'] }}" class="text-gray-800 text-hover-primary fw-semibold">
                                        {{ $txn['reference'] }}
                                    </a>
                                </td>
                                <td>{{ $txn['channel'] }}</td>
                                <td>{{ $txn['payment_method'] }}</td>
                                <td class="text-end">₹{{ $txn['subtotal'] }}</td>
                                <td class="text-end">₹{{ $txn['discount'] }}</td>
                                <td class="text-end">₹{{ $txn['gst'] }}</td>
                                <td class="text-end fw-bold">₹{{ $txn['final_total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-10">No paid transactions in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="fs-8 text-muted">Dining rows are one paid session each — round orders are never listed as revenue.</div>
        </div>
    </div>
@endsection
