@extends('administrator.layouts.default')

@section('page-title', 'Dining Sessions')

@section('page-description', 'Table-service sessions overview with reopen and close controls.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Dining Sessions'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.dining-sessions.index') }}" class="row g-4 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed gs-0 gy-4">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase">
                            <th>Session</th>
                            <th>Table</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Opened</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="fw-bold text-gray-900">{{ $session->session_number }}</td>
                                <td>{{ $session->tableDisplayLabel() }}</td>
                                <td>{{ $session->customer_name_snapshot ?: ($session->customer?->name ?: 'Walk-in') }}</td>
                                <td><span class="badge badge-light-primary">{{ $session->status?->label() }}</span></td>
                                <td>{{ $session->opened_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>{{ $session->total_amount !== null ? number_format((float) $session->total_amount, 2) : '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('administrator.dining-sessions.show', $session) }}" class="btn btn-sm btn-light-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No dining sessions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $sessions->links('components.internal.pagination') }}</div>
        </div>
    </div>
@endsection
