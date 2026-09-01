@php
    use App\Enums\OrderPreparationStatus;

    $panel = $panel ?? 'barista';
    $station = $station ?? null;
    $canTransition = $canTransition ?? false;
    $orderShowRouteName = $orderShowRouteName ?? null;
    $acceptRouteName = $acceptRouteName ?? null;
    $preparingRouteName = $preparingRouteName ?? null;
    $readyRouteName = $readyRouteName ?? null;
    $layout = $panel.'.layouts.default';
    $stationLabel = $station?->label() ?? 'Preparation';
@endphp

@extends($layout)

@section('page-title', $stationLabel.' Queue')

@section('page-description', 'Station tickets for pending, accepted, preparing, and ready work.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => ucfirst($panel).' Panel', 'url' => route($panel.'.dashboard')],
        ['label' => $stationLabel.' Queue'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
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
                            <h3 class="fw-bold text-gray-900">{{ $status->label() }}</h3>
                        </div>
                        <div class="card-toolbar">
                            <span class="badge {{ $status->badgeClass() }}">{{ ($columns[$status->value] ?? collect())->count() }}</span>
                        </div>
                    </div>
                    <div class="card-body pt-4 d-flex flex-column gap-4">
                        @forelse (($columns[$status->value] ?? collect()) as $ticket)
                            @include('internal.preparation.ticket-card', [
                                'ticket' => $ticket,
                                'canTransition' => $canTransition,
                                'acceptRouteName' => $acceptRouteName,
                                'preparingRouteName' => $preparingRouteName,
                                'readyRouteName' => $readyRouteName,
                                'orderShowRouteName' => $orderShowRouteName,
                            ])
                        @empty
                            <div class="text-muted fs-7">No {{ strtolower($status->label()) }} tickets.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
