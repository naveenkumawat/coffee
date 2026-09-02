@extends('operator.layouts.default')

@section('page-title', 'Operational Performance')

@section('page-description', 'Today BAR/KITCHEN timing and dining operational queues — no cost or ranking.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Operational Performance'],
    ]" />
@endsection

@section('content')
    @php
        $fmt = function (?int $seconds): string {
            if ($seconds === null) {
                return '—';
            }
            $seconds = abs($seconds);
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return $m > 0 ? sprintf('%dm %02ds', $m, $s) : sprintf('%ds', $s);
        };
    @endphp

    <div class="text-muted fs-8 mb-5">
        Today ({{ $overview['timezone'] }})
        · {{ $overview['start_local']->format('d M Y') }}.
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-4 col-xl-2"><x-internal.stat-card label="BAR Pending" :value="$overview['bar']['pending']" icon="ki-cup" color="warning" /></div>
        <div class="col-md-4 col-xl-2"><x-internal.stat-card label="BAR Preparing" :value="$overview['bar']['preparing']" icon="ki-cup" color="dark" /></div>
        <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Avg BAR Prep" :value="$fmt($overview['bar']['avg_prep_seconds'])" icon="ki-timer" color="info" /></div>
        <div class="col-md-4 col-xl-2"><x-internal.stat-card label="KITCHEN Pending" :value="$overview['kitchen']['pending']" icon="ki-chef" color="warning" /></div>
        <div class="col-md-4 col-xl-2"><x-internal.stat-card label="KITCHEN Preparing" :value="$overview['kitchen']['preparing']" icon="ki-chef" color="dark" /></div>
        <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Avg KITCHEN Prep" :value="$fmt($overview['kitchen']['avg_prep_seconds'])" icon="ki-timer" color="info" /></div>
    </div>

    <div class="row g-5 mb-7">
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Oldest active tickets</h3></div></div>
                <div class="card-body pt-2 fs-7">
                    <div class="mb-3">
                        <div class="text-muted">BAR</div>
                        @if ($overview['bar']['oldest_active'])
                            <div class="fw-bold">{{ $overview['bar']['oldest_active']['order_number'] }} · {{ $fmt($overview['bar']['oldest_active']['queue_age_seconds']) }}</div>
                        @else
                            <div class="text-muted">None</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-muted">KITCHEN</div>
                        @if ($overview['kitchen']['oldest_active'])
                            <div class="fw-bold">{{ $overview['kitchen']['oldest_active']['order_number'] }} · {{ $fmt($overview['kitchen']['oldest_active']['queue_age_seconds']) }}</div>
                        @else
                            <div class="text-muted">None</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Mixed orders waiting on another station</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($overview['mixed_waiting'] as $row)
                        <div class="fs-7 py-2 border-bottom border-gray-100">
                            {{ $row['order_number'] }}
                            · ready {{ implode(', ', $row['ready_stations']) ?: '—' }}
                            · waiting {{ implode(', ', $row['waiting_stations']) ?: '—' }}
                        </div>
                    @empty
                        <p class="text-muted mb-0">None.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-7">
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Ready to Serve rounds</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($overview['ready_to_serve_rounds'] as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom border-gray-100 fs-7">
                            <span>{{ $row['order_number'] }} · {{ $row['table'] }} · R{{ $row['round_number'] }}</span>
                            <span class="fw-bold">{{ $fmt($row['ready_to_serve_age_seconds']) }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">None.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Bill requested sessions</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($overview['bill_requested_sessions'] as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom border-gray-100 fs-7">
                            <span>{{ $row['session_number'] }} · {{ $row['table'] }}</span>
                            <span class="fw-bold">{{ $fmt($row['bill_requested_elapsed_seconds']) }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">None.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
