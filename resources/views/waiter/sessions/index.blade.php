@extends('internal.layouts.default', ['panel' => 'waiter'])

@section('title', 'Dining Sessions')

@section('content')
    <div class="card card-flush">
        <div class="card-body">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Table</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                        <tr>
                            <td>{{ $session->session_number }}</td>
                            <td>{{ $session->tableDisplayLabel() }}</td>
                            <td>{{ $session->status?->label() }}</td>
                            <td><a href="{{ route('waiter.sessions.show', $session) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $sessions->links() }}
        </div>
    </div>
@endsection
