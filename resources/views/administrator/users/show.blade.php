@extends('administrator.layouts.default')

@section('page-title', 'User Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Users', 'url' => route('administrator.users.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.users.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'Edit User', 'url' => route('administrator.users.edit', $managedUser), 'variant' => 'success', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-8">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Profile</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Name</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Role</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->managementRoleLabel() }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Email</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Mobile number</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->phone ?: 'Not provided' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <div>
                                <span class="badge {{ $managedUser->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                    {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Created</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->created_at?->format('d M Y, h:i A') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush internal-card mb-5">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Activity</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Last activity</div>
                        <div class="fw-bold text-gray-900">{{ $managedUser->last_login_at?->format('d M Y, h:i A') ?? 'No recorded login yet' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Relative time</div>
                        <div class="text-gray-700">{{ $managedUser->last_login_at?->diffForHumans() ?? 'Activity tracking will appear after the first login.' }}</div>
                    </div>
                </div>
            </div>

            @if ($managedUser->hasRole('customer'))
                <div class="card card-flush internal-card mb-5">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="fw-bold text-gray-900">Order Security</h3>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="text-muted fs-7">Ordering</div>
                            <span class="badge {{ $managedUser->ordering_blocked ? 'badge-light-danger' : 'badge-light-success' }}">
                                {{ $managedUser->ordering_blocked ? 'BLOCKED' : 'ACTIVE' }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="text-muted fs-7">Open unpaid</div>
                            <div class="fw-bold text-gray-900">{{ $openUnpaidOrders }}</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="text-muted fs-7">Cash Takeaway</div>
                            <span class="badge {{ $managedUser->cash_takeaway_allowed ? 'badge-light-success' : 'badge-light-dark' }}">
                                {{ $managedUser->cash_takeaway_allowed ? 'Allowed' : 'Not allowed' }}
                            </span>
                        </div>

                        @if ($managedUser->ordering_blocked)
                            <div class="mb-4">
                                <div class="text-muted fs-7 mb-1">Reason (internal)</div>
                                <div class="fw-semibold text-gray-900">{{ $managedUser->ordering_blocked_reason ?: '—' }}</div>
                            </div>
                            <div class="mb-5">
                                <div class="text-muted fs-7 mb-1">Blocked at</div>
                                <div class="fw-semibold text-gray-900">{{ $managedUser->ordering_blocked_at?->format('d M Y, h:i A') ?? '—' }}</div>
                            </div>
                            <form method="POST" action="{{ route('administrator.users.unblock-ordering', $managedUser) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light-success">
                                    Unblock Ordering
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('administrator.users.block-ordering', $managedUser) }}" class="mt-2">
                                @csrf
                                <label for="ordering_blocked_reason" class="form-label fs-7 text-muted">Internal reason (optional)</label>
                                <textarea
                                    id="ordering_blocked_reason"
                                    name="ordering_blocked_reason"
                                    rows="2"
                                    class="form-control form-control-sm mb-3 @error('ordering_blocked_reason') is-invalid @enderror"
                                    maxlength="500"
                                >{{ old('ordering_blocked_reason') }}</textarea>
                                @error('ordering_blocked_reason')
                                    <div class="invalid-feedback d-block mb-3">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-sm btn-light-danger">
                                    Block Ordering
                                </button>
                            </form>
                        @endif

                        <div class="form-text mt-4">
                            Cash Takeaway trust is managed on Edit User and does not bypass pending-order or rate limits.
                        </div>
                    </div>
                </div>
            @endif

            @if ($managedUser->hasRole('customer'))
                <div class="card card-flush internal-card mb-5">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="fw-bold text-gray-900">Loyalty</h3>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="row g-4 mb-6">
                            <div class="col-4">
                                <div class="text-muted fs-7 mb-1">Available</div>
                                <div class="fw-bold text-gray-900 fs-3">{{ $loyaltyAccount?->available_points ?? 0 }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted fs-7 mb-1">Lifetime earned</div>
                                <div class="fw-bold text-gray-900">{{ $loyaltyAccount?->lifetime_earned_points ?? 0 }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted fs-7 mb-1">Lifetime redeemed</div>
                                <div class="fw-bold text-gray-900">{{ $loyaltyAccount?->lifetime_redeemed_points ?? 0 }}</div>
                            </div>
                        </div>

                        @if ($loyaltyTransactions === null || $loyaltyTransactions->isEmpty())
                            <div class="text-muted">No loyalty transactions yet.</div>
                        @else
                            <div class="table-responsive internal-table-wrapper">
                                <table class="table align-middle table-row-dashed fs-7 gy-3 internal-table">
                                    <thead>
                                        <tr class="text-start text-muted fw-bold text-uppercase gs-0">
                                            <th>When</th>
                                            <th>Type</th>
                                            <th>Points</th>
                                            <th>Source</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-700">
                                        @foreach ($loyaltyTransactions as $txn)
                                            <tr>
                                                <td>{{ $txn->occurred_at?->format('d M Y, h:i A') }}</td>
                                                <td>{{ $txn->type?->label() ?? $txn->type }}</td>
                                                <td>{{ $txn->points > 0 ? '+'.$txn->points : $txn->points }}</td>
                                                <td>
                                                    {{ $txn->source_type?->label() ?? '—' }}
                                                    @if ($txn->source_id)
                                                        #{{ $txn->source_id }}
                                                    @endif
                                                </td>
                                                <td>{{ $txn->reason_code ?: ($txn->description ?: '—') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            {{ $loyaltyTransactions->links('components.internal.pagination') }}
                        @endif
                    </div>
                </div>

                @can('update', $managedUser)
                    <div class="card card-flush internal-card mb-5">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="fw-bold text-gray-900">Adjust points</h3>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <form method="POST" action="{{ route('administrator.users.loyalty-adjust', $managedUser) }}">
                                @csrf
                                <label for="loyalty_points" class="form-label fs-7 text-muted">Points (+ earn / − deduct)</label>
                                <input
                                    id="loyalty_points"
                                    name="points"
                                    type="number"
                                    step="1"
                                    class="form-control form-control-sm mb-3 @error('points') is-invalid @enderror"
                                    value="{{ old('points') }}"
                                    required
                                />
                                @error('points')
                                    <div class="invalid-feedback d-block mb-3">{{ $message }}</div>
                                @enderror
                                <label for="loyalty_reason" class="form-label fs-7 text-muted">Reason</label>
                                <textarea
                                    id="loyalty_reason"
                                    name="reason"
                                    rows="2"
                                    maxlength="500"
                                    class="form-control form-control-sm mb-3 @error('reason') is-invalid @enderror"
                                    required
                                >{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback d-block mb-3">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-sm btn-light-primary">
                                    Apply adjustment
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Customer Order History</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
                        <i class="ki-outline ki-information fs-2tx text-warning me-4"></i>
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-900 fw-bold mb-1">Pending order module</h4>
                            <span class="fs-6 text-gray-700">Customer order history will be connected here after the Orders phase is implemented.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
