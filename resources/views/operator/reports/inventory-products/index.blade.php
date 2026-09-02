@extends('operator.layouts.default')

@section('page-title', 'Inventory & Product Ops')

@section('page-description', 'Today operational inventory and station volume only — no cost or margin.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Inventory & Product Ops'],
    ]" />
@endsection

@section('content')
    <div class="text-muted fs-8 mb-5">
        Today ({{ $overview['timezone'] }})
        · {{ $overview['start_local']->format('d M Y') }}.
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Low Stock" :value="$overview['low_stock_count']" icon="ki-information-5" color="warning" />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Out of Stock" :value="$overview['out_of_stock_count']" icon="ki-cross-circle" color="danger" />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Pending Refills" :value="$overview['pending_refills']" icon="ki-delivery-3" color="info" />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="BAR Units Today" :value="$overview['stations']['bar_units']" icon="ki-cup" color="dark" />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="KITCHEN Units Today" :value="$overview['stations']['kitchen_units']" icon="ki-chef" color="dark" />
        </div>
    </div>

    <div class="row g-5 mb-7">
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Today consumption by unit</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($overview['today_consumption'] as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom border-gray-100">
                            <span>{{ strtoupper($row['unit']) }}</span>
                            <span class="fw-bold">{{ $row['quantity'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No sale consumption today.</p>
                    @endforelse
                    @if (count($overview['today_reversals']) > 0)
                        <h4 class="fw-semibold fs-7 mt-5">Reversals</h4>
                        @foreach ($overview['today_reversals'] as $row)
                            <div class="d-flex justify-content-between py-2 border-bottom border-gray-100">
                                <span>{{ strtoupper($row['unit']) }}</span>
                                <span class="fw-bold">{{ $row['quantity'] }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Top consumed today (by unit)</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($overview['top_consumed']['by_unit'] as $unitBlock)
                        <h4 class="fw-semibold fs-7 mt-3">{{ strtoupper($unitBlock['unit']) }}</h4>
                        @foreach ($unitBlock['rows'] as $row)
                            <div class="d-flex justify-content-between py-2 border-bottom border-gray-100">
                                <span>{{ $row['ingredient'] }}</span>
                                <span class="fw-bold">{{ $row['consumed'] }} {{ $row['unit'] }}</span>
                            </div>
                        @endforeach
                    @empty
                        <p class="text-muted mb-0">No consumption today.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-7">
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Low / OOS ingredients</h3></div></div>
                <div class="card-body pt-2">
                    @forelse (array_merge($overview['out_of_stock'], $overview['low_stock']) as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom border-gray-100">
                            <span>{{ $row['name'] }} <span class="text-muted">({{ $row['status'] }})</span></span>
                            <span class="fw-bold">{{ $row['current_stock'] }} {{ $row['unit'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">All ingredients healthy.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Recent sale movements</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($overview['recent_sale_movements'] as $row)
                        <div class="py-2 border-bottom border-gray-100">
                            <div class="fw-semibold">{{ $row['ingredient'] }} · {{ $row['movement_label'] }}</div>
                            <div class="text-muted fs-8">
                                {{ $row['timestamp'] }} · {{ $row['quantity'] }} {{ $row['unit'] }}
                                @if ($row['order_reference'])
                                    · {{ $row['order_reference'] }}
                                @endif
                                @if ($row['product'])
                                    · {{ $row['product'] }}
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No sale movements today.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
