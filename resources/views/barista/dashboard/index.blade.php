@extends('barista.layouts.default')

@section('page-title', 'Barista Dashboard')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Dashboard'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-3">
            <x-internal.stat-card
                label="Cafe Ordering"
                :value="$cafeAvailability->available ? 'OPEN' : 'CLOSED'"
                icon="ki-shop"
                :color="$cafeAvailability->available ? 'success' : 'danger'"
                :description="$cafeAvailability->message"
            />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Active Station" :value="$activeShift['station']" icon="ki-coffee" color="warning" description="Shared internal theme applied to barista workflows without duplicating assets." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Shift Started" :value="$activeShift['started_at']" icon="ki-timer" color="primary" description="Prepared for role-specific station modules once cafe operations are implemented." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Focus" :value="$activeShift['focus']" icon="ki-flag" color="success" description="Current dashboard is a foundation screen only, not a finished business workflow." />
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Queue preview</h3>
            </div>
        </div>
        <div class="card-body pt-5">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Ticket</th>
                            <th>Guest</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @foreach ($queue as $ticket)
                            <tr>
                                <td class="text-gray-900 fw-bold">{{ $ticket['ticket'] }}</td>
                                <td>{{ $ticket['guest'] }}</td>
                                <td><span class="badge badge-light-warning">{{ $ticket['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
