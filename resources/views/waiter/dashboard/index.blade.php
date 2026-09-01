@extends('internal.layouts.default', ['panel' => 'waiter'])

@section('title', 'Waiter Dashboard')

@section('content')
    <div class="row g-5 g-xl-8">
        @foreach ([
            ['Available', $available, 'success'],
            ['Occupied', $occupied, 'warning'],
            ['Bill Requested', $billRequested, 'info'],
            ['Awaiting Payment', $awaitingPayment, 'primary'],
        ] as [$label, $value, $color])
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-gray-500 fw-semibold mb-1">{{ $label }}</div>
                        <div class="fs-2hx fw-bold text-{{ $color }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
