@php
    use App\Enums\OrderPreparationStatus;
    use App\Enums\PreparationStation;
@endphp

@extends('operator.layouts.default')

@section('page-title', 'Preparation Overview')

@section('page-description', 'Read-only view of bar and kitchen station queues.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Preparation'],
    ]" />
@endsection

@section('content')
    @foreach ([
        ['label' => 'Bar Queue', 'station' => PreparationStation::Bar, 'columns' => $barColumns],
        ['label' => 'Kitchen Queue', 'station' => PreparationStation::Kitchen, 'columns' => $kitchenColumns],
    ] as $queue)
        <div class="mb-10">
            <h3 class="fw-bold text-gray-900 mb-5">{{ $queue['label'] }}</h3>
            <div class="row g-5">
                @foreach ([
                    OrderPreparationStatus::Pending,
                    OrderPreparationStatus::Accepted,
                    OrderPreparationStatus::Preparing,
                    OrderPreparationStatus::Ready,
                ] as $status)
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-flush internal-card h-100">
                            <div class="card-header pt-6 pb-0">
                                <div class="card-title">
                                    <h4 class="fw-bold text-gray-900">{{ $status->label() }}</h4>
                                </div>
                                <div class="card-toolbar">
                                    <span class="badge {{ $status->badgeClass() }}">{{ ($queue['columns'][$status->value] ?? collect())->count() }}</span>
                                </div>
                            </div>
                            <div class="card-body pt-4 d-flex flex-column gap-4">
                                @forelse (($queue['columns'][$status->value] ?? collect()) as $ticket)
                                    @include('internal.preparation.ticket-card', [
                                        'ticket' => $ticket,
                                        'canTransition' => false,
                                        'orderShowRouteName' => 'operator.orders.show',
                                    ])
                                @empty
                                    <div class="text-muted fs-7">No {{ strtolower($status->label()) }} tickets.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
