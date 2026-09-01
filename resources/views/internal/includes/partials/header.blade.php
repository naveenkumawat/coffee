@php
    use App\Enums\UserRole;
    use Illuminate\Support\Str;

    $user = auth('admin')->user();
    $panelName = match ($panel) {
        'administrator' => 'Administrator',
        'waiter' => 'Waiter',
        default => 'Barista',
    };
    $dashboardRoute = match ($panel) {
        'administrator' => route('administrator.dashboard'),
        'waiter' => route('waiter.dashboard'),
        default => route('barista.dashboard'),
    };
    $logoutRoute = match ($panel) {
        'administrator' => route('administrator.logout'),
        'waiter' => route('waiter.logout'),
        default => route('barista.logout'),
    };
    $notificationsReadAllRoute = match ($panel) {
        'administrator' => route('administrator.notifications.read-all'),
        'waiter' => route('waiter.notifications.read-all'),
        default => route('barista.notifications.read-all'),
    };
    $roleLabel = $user?->role instanceof UserRole ? $user->role->label() : null;
    $staffNotifications = $staffNotifications ?? collect();
    $staffUnreadCount = (int) ($staffUnreadCount ?? 0);
@endphp

<div id="kt_app_header" class="app-header">
    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">
        <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Show sidebar menu">
            <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                <i class="ki-duotone ki-abstract-14 fs-2 fs-md-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </div>
        </div>

        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <a href="{{ $dashboardRoute }}" class="d-lg-none text-decoration-none">
                <span class="fw-bold text-dark">{{ config('app.name') }}</span>
            </a>
        </div>

        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">
            <div
                class="app-header-menu app-header-mobile-drawer align-items-stretch"
                data-kt-drawer="true"
                data-kt-drawer-name="app-header-menu"
                data-kt-drawer-activate="{default: true, lg: false}"
                data-kt-drawer-overlay="true"
                data-kt-drawer-width="250px"
                data-kt-drawer-direction="end"
                data-kt-drawer-toggle="#kt_app_header_menu_toggle"
                data-kt-swapper="true"
                data-kt-swapper-mode="{default: 'append', lg: 'prepend'}"
                data-kt-swapper-parent="{default: '#kt_app_body', lg: '#kt_app_header_wrapper'}"
            ></div>

            <div class="app-navbar flex-shrink-0">
                <div class="app-navbar-item ms-1 ms-md-4">
                    <x-internal.button
                        label="Storefront"
                        :url="route('home')"
                        variant="default"
                        icon="ki-shop"
                    />
                </div>

                @if ($user)
                    <div class="app-navbar-item ms-1 ms-md-4">
                        <div
                            class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative"
                            data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                            data-kt-menu-attach="parent"
                            data-kt-menu-placement="bottom-end"
                            title="Notifications"
                        >
                            <i class="ki-duotone ki-notification-on fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            @if ($staffUnreadCount > 0)
                                <span class="bullet bullet-dot bg-success h-6px w-6px position-absolute translate-middle top-0 start-50 animation-blink"></span>
                                <span class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-danger" style="min-width:1.1rem;height:1.1rem;line-height:1.1rem;font-size:0.65rem;">
                                    {{ $staffUnreadCount > 99 ? '99+' : $staffUnreadCount }}
                                </span>
                            @endif
                        </div>

                        <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px" data-kt-menu="true">
                            <div class="d-flex flex-column bgi-no-repeat rounded-top" style="background-color:#3d2918;">
                                <h3 class="text-white fw-semibold px-9 mt-6 mb-6">
                                    Notifications
                                    <span class="fs-8 opacity-75 ps-3">{{ $staffUnreadCount }} unread</span>
                                </h3>
                            </div>

                            <div class="scroll-y mh-325px my-2 px-4">
                                @forelse ($staffNotifications as $notification)
                                    @php
                                        $data = $notification->data;
                                        $title = (string) ($data['title'] ?? 'Notification');
                                        $message = (string) ($data['message'] ?? '');
                                        $url = (string) ($data['url'] ?? $dashboardRoute);
                                        $readRoute = match ($panel) {
                                            'administrator' => route('administrator.notifications.read', $notification),
                                            'waiter' => route('waiter.notifications.read', $notification),
                                            default => route('barista.notifications.read', $notification),
                                        };
                                    @endphp
                                    <div class="d-flex flex-stack py-4 {{ $notification->read_at ? '' : 'bg-light-primary rounded px-2' }}">
                                        <div class="d-flex align-items-center">
                                            <div class="mb-0 me-2">
                                                <a href="{{ $url }}" class="fs-6 text-gray-800 text-hover-primary fw-bold">
                                                    {{ $title }}
                                                </a>
                                                @if (! empty($data['severity']))
                                                    <span class="badge {{ \App\Enums\StaffNotificationSeverity::tryFrom((string) $data['severity'])?->badgeClass() ?? 'badge-light' }} fs-9 ms-1">
                                                        {{ ucfirst((string) $data['severity']) }}
                                                    </span>
                                                @endif
                                                @if ($message !== '')
                                                    <div class="text-gray-500 fs-7">{{ Str::limit($message, 90) }}</div>
                                                @endif
                                                <div class="text-muted fs-8">{{ $notification->created_at?->diffForHumans() }}</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-end gap-1">
                                            <a href="{{ $url }}" class="btn btn-sm btn-light">Open</a>
                                            @unless ($notification->read_at)
                                                <form method="POST" action="{{ $readRoute }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-link px-0">Mark read</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted fs-7 py-10">No notifications yet.</div>
                                @endforelse
                            </div>

                            @if ($staffUnreadCount > 0)
                                <div class="py-3 text-center border-top">
                                    <form method="POST" action="{{ $notificationsReadAllRoute }}">
                                        @csrf
                                        <button type="submit" class="btn btn-color-gray-600 btn-active-color-primary">Mark all as read</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
                        <div
                            class="cursor-pointer symbol symbol-35px"
                            data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                            data-kt-menu-attach="parent"
                            data-kt-menu-placement="bottom-end"
                        >
                            <div class="symbol-label fs-3 bg-light-primary text-primary rounded-3">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                        </div>

                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <div class="menu-content d-flex align-items-center px-3">
                                    <div class="symbol symbol-50px me-5">
                                        <div class="symbol-label fs-2 bg-light-primary text-primary rounded-3">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column">
                                        <div class="fw-bold d-flex align-items-center fs-5">
                                            {{ $user->name }}
                                            <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">{{ $panelName }}</span>
                                        </div>
                                        <span class="fw-semibold text-muted fs-7">{{ $user->email }}</span>
                                        @if ($roleLabel)
                                            <span class="text-gray-500 fs-8 mt-1">{{ $roleLabel }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="separator my-2"></div>

                            <div class="menu-item px-5">
                                <a href="{{ $dashboardRoute }}" class="menu-link px-5">Dashboard</a>
                            </div>

                            <div class="menu-item px-5">
                                <a href="{{ route('home') }}" class="menu-link px-5">View Storefront</a>
                            </div>

                            <div class="separator my-2"></div>

                            <div class="menu-item px-5">
                                <form method="POST" action="{{ $logoutRoute }}">
                                    @csrf
                                    <button type="submit" class="menu-link px-5 border-0 bg-transparent w-100 text-start">Sign Out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
