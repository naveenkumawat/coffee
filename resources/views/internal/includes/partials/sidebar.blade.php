@php
    $user = auth('admin')->user();

    $navigation = match ($panel) {
        'administrator' => [
            [
                'heading' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'administrator.dashboard', 'pattern' => 'administrator.dashboard*', 'icon' => 'ki-element-11'],
                ],
            ],
            [
                'heading' => 'Catalog',
                'items' => $user?->canManageMenuCatalog() ? [
                    ['label' => 'Menu Categories', 'route' => 'administrator.menu.categories.index', 'pattern' => 'administrator.menu.categories.*', 'icon' => 'ki-category'],
                    ['label' => 'Menu Items', 'route' => 'administrator.menu.items.index', 'pattern' => 'administrator.menu.items.*', 'icon' => 'ki-basket'],
                ] : [],
            ],
        ],
        'barista' => [
            [
                'heading' => 'Station',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'barista.dashboard', 'pattern' => 'barista.dashboard*', 'icon' => 'ki-abstract-26'],
                ],
            ],
        ],
    };

    $logoutRoute = $panel === 'administrator' ? route('administrator.logout') : route('barista.logout');
@endphp

<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ $panel === 'administrator' ? route('administrator.dashboard') : route('barista.dashboard') }}" class="text-decoration-none">
            <span class="fs-3 fw-bold text-white">{{ config('app.name') }}</span>
        </a>
    </div>

    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper hover-scroll-overlay-y my-5" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_logo" data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px">
            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold px-3" id="kt_app_sidebar_menu" data-kt-menu="true">
                @foreach ($navigation as $section)
                    @if (filled($section['items']))
                        <div class="menu-item pt-5">
                            <div class="menu-content">
                                <span class="menu-heading fw-bold text-uppercase fs-7">{{ $section['heading'] }}</span>
                            </div>
                        </div>

                        @foreach ($section['items'] as $item)
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs($item['pattern']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                    <span class="menu-icon">
                                        <i class="ki-outline {{ $item['icon'] }} fs-2"></i>
                                    </span>
                                    <span class="menu-title">{{ $item['label'] }}</span>
                                </a>
                            </div>
                        @endforeach
                    @endif
                @endforeach

                <div class="menu-item pt-5">
                    <div class="menu-content">
                        <span class="menu-heading fw-bold text-uppercase fs-7">Session</span>
                    </div>
                </div>

                <div class="menu-item">
                    <button class="menu-link border-0 bg-transparent w-100 text-start" data-bs-toggle="modal" data-bs-target="#internalFoundationModal" type="button">
                        <span class="menu-icon">
                            <i class="ki-outline ki-information-5 fs-2"></i>
                        </span>
                        <span class="menu-title">UI Foundation</span>
                    </button>
                </div>

                <div class="menu-item">
                    <form method="POST" action="{{ $logoutRoute }}">
                        @csrf
                        <button class="menu-link border-0 bg-transparent w-100 text-start" type="submit">
                            <span class="menu-icon">
                                <i class="ki-outline ki-exit-right fs-2"></i>
                            </span>
                            <span class="menu-title">Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
