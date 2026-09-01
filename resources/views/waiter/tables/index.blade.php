@extends('internal.layouts.default', ['panel' => 'waiter'])

@section('title', 'Tables')

@section('content')
    <div class="row g-5">
        @foreach ($tables as $row)
            @php($table = $row['table'])
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="fw-bold fs-4">{{ $table->displayLabel() }}</div>
                        <div class="text-muted mb-4">{{ str_replace('_', ' ', $row['state']) }}</div>
                        @if ($row['session'])
                            <a class="btn btn-sm btn-primary" href="{{ route('waiter.sessions.show', $row['session']) }}">Open session</a>
                        @elseif ($row['state'] === 'available' && $table->is_active)
                            <form method="POST" action="{{ route('waiter.sessions.store') }}">
                                @csrf
                                <input type="hidden" name="cafe_table_id" value="{{ $table->getKey() }}">
                                <button class="btn btn-sm btn-light-primary" type="submit">Start session</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
