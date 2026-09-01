@extends('waiter.layouts.default')

@section('page-title', 'Tables')

@section('page-description', 'Operational floor view for seating and active table service.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Waiter Panel', 'url' => route('waiter.dashboard')],
        ['label' => 'Tables'],
    ]" />
@endsection

@section('content')
    <div class="row g-5">
        @forelse ($tables as $row)
            <div class="col-sm-6 col-md-4 col-xl-3">
                @include('internal.dining.partials.table-card', [
                    'row' => $row,
                    'sessionShowRoute' => 'waiter.sessions.show',
                    'startSessionRoute' => route('waiter.sessions.store'),
                ])
            </div>
        @empty
            <div class="col-12">
                <div class="card card-flush internal-card">
                    <div class="card-body">
                        <x-internal.empty-state message="No café tables are configured yet." />
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
