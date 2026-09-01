@extends('administrator.layouts.default')

@section('page-title', 'Referrals')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Referrals'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card mb-8">
        <div class="card-header align-items-center py-5 gap-2 gap-md-5">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900 mb-0">Customer referrals</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <form method="GET" class="row g-4 mb-6">
                <div class="col-md-4">
                    <label class="form-label" for="q">Search</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control" placeholder="Code, referrer, or friend" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('administrator.referrals.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed gs-0 gy-4">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase">
                            <th>Referrer</th>
                            <th>Friend</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Qualified order</th>
                            <th>Reward</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($referrals as $referral)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-gray-900">{{ $referral->referrer?->name }}</div>
                                    <div class="text-muted fs-7">{{ $referral->referrer?->email }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-gray-900">{{ $referral->referred?->name }}</div>
                                    <div class="text-muted fs-7">{{ $referral->referred?->email }}</div>
                                </td>
                                <td><code>{{ $referral->referral_code_snapshot }}</code></td>
                                <td>{{ $referral->status?->label() }}</td>
                                <td>
                                    @if ($referral->qualifiedOrder)
                                        {{ $referral->qualifiedOrder->order_number }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($referral->reward)
                                        {{ $referral->reward->displayTitle() }}
                                        <div class="text-muted fs-7">{{ $referral->reward->status?->label() }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $referral->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted">No referrals yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $referrals->links() }}
        </div>
    </div>
@endsection
