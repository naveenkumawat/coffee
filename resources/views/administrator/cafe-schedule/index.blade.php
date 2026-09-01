@extends('administrator.layouts.default')

@section('page-title', 'Café Schedule')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Café Schedule'],
    ]" />
@endsection

@section('toolbar-actions')
    @if ($canManage)
        <x-internal.button-group :items="[
            ['label' => 'Edit Hours', 'url' => route('administrator.cafe-schedule.hours.edit'), 'variant' => 'dark', 'icon' => 'ki-time'],
            ['label' => 'Add Closure', 'url' => route('administrator.cafe-schedule.closures.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
        ]" />
    @endif
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-8">
        <div class="col-xl-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Current Status</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="badge {{ $status->code->badgeClass() }} fs-7">
                            {{ $status->available ? 'OPEN' : 'CLOSED' }}
                        </span>
                        <span class="text-muted fs-7">{{ $status->code->label() }}</span>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7 mb-1">Today’s hours</div>
                        <div class="fw-bold text-gray-900">{{ $status->todayHoursLabel ?: '—' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7 mb-1">Timezone</div>
                        <div class="fw-semibold text-gray-900">{{ $timezone }}</div>
                    </div>
                    @if (! $status->available)
                        <div class="mb-4">
                            <div class="text-muted fs-7 mb-1">Customer message</div>
                            <div class="text-gray-800">{{ $status->message }}</div>
                        </div>
                        @if ($status->reopensAt)
                            <div class="mb-4">
                                <div class="text-muted fs-7 mb-1">Closed until</div>
                                <div class="fw-bold text-gray-900">{{ $status->reopensAt->timezone($timezone)->format('d M Y, h:i A') }}</div>
                            </div>
                        @elseif ($status->code === \App\Enums\CafeAvailabilityCode::ManualClosed)
                            <div class="mb-4 fw-bold text-gray-900">Until manually reopened</div>
                        @endif
                    @endif

                    @if ($canManage)
                        @if ($status->code === \App\Enums\CafeAvailabilityCode::ManualClosed)
                            <form method="POST" action="{{ route('administrator.cafe-schedule.reopen') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light-success">Reopen Now</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('administrator.cafe-schedule.close') }}" class="border border-gray-300 rounded p-4">
                                @csrf
                                <div class="fw-semibold text-gray-900 mb-3">Put Out of Service</div>
                                <div class="mb-3">
                                    <label class="form-label">Mode</label>
                                    <select name="mode" class="form-select form-select-sm" id="oos-mode">
                                        <option value="indefinite">Until manually reopened</option>
                                        <option value="until">Until specific date/time</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="oos-until-wrap" style="display:none;">
                                    <label class="form-label" for="closed_until">Closed until ({{ $timezone }})</label>
                                    <input type="datetime-local" name="closed_until" id="closed_until" class="form-control form-control-sm">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="customer_message">Customer message (optional)</label>
                                    <input type="text" name="customer_message" id="customer_message" class="form-control form-control-sm" maxlength="500" placeholder="Temporarily unavailable due to maintenance.">
                                </div>
                                <button type="submit" class="btn btn-sm btn-light-danger">Put Out of Service</button>
                            </form>
                            @push('scripts')
                            <script>
                                (() => {
                                    const mode = document.getElementById('oos-mode');
                                    const wrap = document.getElementById('oos-until-wrap');
                                    if (!mode || !wrap) return;
                                    const sync = () => { wrap.style.display = mode.value === 'until' ? '' : 'none'; };
                                    mode.addEventListener('change', sync);
                                    sync();
                                })();
                            </script>
                            @endpush
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Weekly Hours</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-7 gy-3">
                            <tbody>
                                @foreach ($weeklyHours as $day)
                                    <tr>
                                        <td class="fw-semibold text-gray-900">{{ $day['label'] }}</td>
                                        <td class="text-end">
                                            @if ($day['is_open'])
                                                {{ collect($day['intervals'])->map(fn ($i) => $i['opens_at'].' – '.$i['closes_at'])->implode(', ') }}
                                            @else
                                                <span class="badge badge-light-dark">Closed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Holidays & Closures</h3>
            </div>
        </div>
        <div class="card-body pt-5">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead>
                        <tr class="text-start text-muted fw-bold text-uppercase gs-0">
                            <th>When</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            @if ($canManage)
                                <th class="text-end">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($closures as $closure)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-gray-900">
                                        {{ $closure->starts_at?->timezone($timezone)->format('d M Y, H:i') }}
                                        →
                                        {{ $closure->ends_at?->timezone($timezone)->format('d M Y, H:i') }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-gray-900">{{ $closure->title }}</div>
                                    @if ($closure->customer_message)
                                        <div class="text-muted">{{ $closure->customer_message }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $closure->type?->badgeClass() ?? 'badge-light' }}">
                                        {{ $closure->type?->label() ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $closure->is_active ? 'badge-light-success' : 'badge-light-dark' }}">
                                        {{ $closure->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                @if ($canManage)
                                    <td class="text-end">
                                        <x-internal.action-dropdown :items="[
                                            ['label' => 'Edit', 'url' => route('administrator.cafe-schedule.closures.edit', $closure), 'icon' => 'ki-notepad-edit'],
                                            [
                                                'label' => $closure->is_active ? 'Deactivate' : 'Activate',
                                                'url' => route('administrator.cafe-schedule.closures.toggle', $closure),
                                                'method' => 'patch',
                                                'icon' => 'ki-check',
                                            ],
                                            [
                                                'label' => 'Archive',
                                                'url' => route('administrator.cafe-schedule.closures.destroy', $closure),
                                                'method' => 'delete',
                                                'icon' => 'ki-trash',
                                                'confirm' => 'Archive this closure?',
                                            ],
                                        ]" />
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 5 : 4 }}" class="text-center text-muted py-10">No holidays or closures scheduled.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $closures->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
