@php
    use App\Enums\UserRole;
    use Illuminate\Support\Str;

    $user = auth('admin')->user();
    $panelName = match ($panel) {
        'administrator' => 'Administrator',
        'operator' => 'Operator',
        'chef' => 'Chef',
        'waiter' => 'Waiter',
        default => 'Barista',
    };
    $dashboardRoute = match ($panel) {
        'administrator' => route('administrator.dashboard'),
        'operator' => route('operator.dashboard'),
        'chef' => route('chef.dashboard'),
        'waiter' => route('waiter.dashboard'),
        default => route('barista.dashboard'),
    };
    $logoutRoute = match ($panel) {
        'administrator' => route('administrator.logout'),
        'operator' => route('operator.logout'),
        'chef' => route('chef.logout'),
        'waiter' => route('waiter.logout'),
        default => route('barista.logout'),
    };
    $notificationsReadAllRoute = match ($panel) {
        'administrator' => route('administrator.notifications.read-all'),
        'operator' => route('operator.notifications.read-all'),
        'chef' => route('chef.notifications.read-all'),
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
                        <button
                            type="button"
                            id="coffee-ops-bell"
                            class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative coffee-ops-bell"
                            aria-label="Notifications"
                            aria-haspopup="dialog"
                            aria-controls="coffee-ops-drawer"
                        >
                            <i class="ki-duotone ki-notification-on fs-2" aria-hidden="true">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            <span id="coffee-ops-action-badge" class="coffee-ops-badge is-action" hidden>0</span>
                            <span id="coffee-ops-unread-badge" class="coffee-ops-badge" @if ($staffUnreadCount < 1) hidden @endif>
                                {{ $staffUnreadCount > 99 ? '99+' : max($staffUnreadCount, 0) }}
                            </span>
                        </button>
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
