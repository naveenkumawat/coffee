@extends('waiter.layouts.default')

@section('page-title', 'Dining Sessions')

@section('page-description', 'Open and recent table-service sessions.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Waiter Panel', 'url' => route('waiter.dashboard')],
        ['label' => 'Sessions'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Tables', 'url' => route('waiter.tables.index'), 'variant' => 'default', 'icon' => 'ki-tablet'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed gs-0 gy-4">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase">
                            <th>Session</th>
                            <th>Table</th>
                            <th>Status</th>
                            <th>Opened</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="fw-bold text-gray-900">{{ $session->session_number }}</td>
                                <td>{{ $session->tableDisplayLabel() }}</td>
                                <td><x-internal.dining-session-status-badge :status="$session->status" /></td>
                                <td>{{ $session->opened_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Open', 'url' => route('waiter.sessions.show', $session), 'icon' => 'ki-eye'],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-internal.empty-state message="No dining sessions yet." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $sessions->links() }}</div>
        </div>
    </div>
@endsection
