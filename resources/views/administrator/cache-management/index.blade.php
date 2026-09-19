@extends('administrator.layouts.default')

@section('page-title', 'Cache Management')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Cache Management'],
    ]" />
@endsection

@section('content')
    @php($cache = $publicCache ?? ['cache_version' => null, 'invalidated_at' => null, 'reason' => null])
    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Cache Management</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <p class="text-gray-700">Server cache speeds up backend public data generation. Customer cache is menu, public content and media stored on customer devices for faster visits. Refresh Customer Cache does not reach browsers directly — devices update on their next connection or realtime event.</p>
            <div class="mb-6">
                <div class="fw-semibold text-gray-800">Application cache</div>
                <div class="text-gray-700 fs-7">Public catalog, merchandising, CMS and availability payloads on the server.</div>
            </div>
            <div class="mb-6">
                <div class="fw-semibold text-gray-800">Public client cache version</div>
                <div class="text-gray-800 font-monospace">{{ $cache['cache_version'] ?? '—' }}</div>
            </div>
            <div class="mb-8">
                <div class="fw-semibold text-gray-800">Last invalidated at</div>
                <div class="text-gray-700">{{ $cache['invalidated_at'] ?? 'Never' }}</div>
                @if (! empty($cache['reason']))
                    <div class="text-gray-600 fs-7">Reason: {{ $cache['reason'] }}</div>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-3">
                <form method="POST" action="{{ route('administrator.cache-management.clear-server') }}" data-confirm-title="Clear server cache?" data-confirm-body="This clears server-side public catalog and content caches. Customer devices keep their copies until the public cache version changes." data-confirm-label="Clear Server Cache">
                    @csrf
                    <button type="submit" class="btn btn-light">Clear Server Cache</button>
                </form>
                <form method="POST" action="{{ route('administrator.cache-management.refresh-customer') }}" data-confirm-title="Refresh customer cache?" data-confirm-body="Customer devices will download the latest menu, content and images when they next connect. Carts, orders and dining sessions are not cleared." data-confirm-label="Refresh Customer Cache">
                    @csrf
                    <button type="submit" class="btn btn-light-primary">Refresh Customer Cache</button>
                </form>
                <form method="POST" action="{{ route('administrator.cache-management.clear-all') }}" data-confirm-title="Refresh all application caches?" data-confirm-body="Cached public website content will be cleared. Customer devices will download the latest menu, content and images when they next connect." data-confirm-label="Refresh caches" data-confirm-class="btn-warning">
                    @csrf
                    <button type="submit" class="btn btn-warning">Clear All Caches</button>
                </form>
            </div>
        </div>
    </div>
@endsection
