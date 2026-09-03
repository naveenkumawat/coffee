@php
    use Illuminate\Support\Str;

    $staffNotifications = $staffNotifications ?? collect();
    $staffUnreadCount = (int) ($staffUnreadCount ?? 0);
    $dashboardRoute = $dashboardRoute ?? url('/');
@endphp

<div id="coffee-ops-drawer-backdrop" class="coffee-ops-drawer-backdrop" hidden></div>

<aside
    id="coffee-ops-drawer"
    class="coffee-ops-drawer"
    role="dialog"
    aria-modal="true"
    aria-labelledby="coffee-ops-drawer-title"
    hidden
>
    <div class="coffee-ops-drawer-header">
        <h2 id="coffee-ops-drawer-title">Notifications</h2>
        <button type="button" class="btn btn-sm btn-icon btn-color-white btn-active-color-primary" data-ops-close aria-label="Close notifications">
            <i class="ki-duotone ki-cross fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </button>
    </div>
    <div class="coffee-ops-drawer-body">
        <h3 class="coffee-ops-section-title">Action required</h3>
        <div id="coffee-ops-action-list"></div>

        <h3 class="coffee-ops-section-title">Recent</h3>
        <div id="coffee-ops-recent-list"></div>

        <h3 class="coffee-ops-section-title">System alerts</h3>
        <div id="coffee-ops-legacy-list" class="scroll-y">
            @forelse ($staffNotifications as $notification)
                @php
                    $data = $notification->data;
                    $title = (string) ($data['title'] ?? 'Notification');
                    $message = (string) ($data['message'] ?? '');
                    $url = (string) ($data['url'] ?? $dashboardRoute);
                @endphp
                <article class="coffee-ops-card {{ $notification->read_at ? '' : 'is-unread' }}">
                    <div class="coffee-ops-card-main">
                        <h4 class="coffee-ops-card-title">
                            <a href="{{ $url }}" class="text-gray-800 text-hover-primary">{{ $title }}</a>
                        </h4>
                        @if ($message !== '')
                            <p class="coffee-ops-card-message">{{ Str::limit($message, 120) }}</p>
                        @endif
                        <div class="coffee-ops-card-meta">
                            <span>{{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="coffee-ops-card-actions">
                        <a href="{{ $url }}" class="btn btn-sm btn-light">Open</a>
                    </div>
                </article>
            @empty
                <div class="text-muted fs-7 py-4 text-center">No system alerts.</div>
            @endforelse
        </div>

        @if ($staffUnreadCount > 0 && ! empty($notificationsReadAllRoute))
            <div class="border-top pt-4 mt-4 text-center">
                <form method="POST" action="{{ $notificationsReadAllRoute }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light">Mark system alerts read</button>
                </form>
            </div>
        @endif
    </div>
</aside>

<div id="coffee-ops-toast-host" class="coffee-ops-toast-host" aria-live="polite"></div>
