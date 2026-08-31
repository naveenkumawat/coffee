@php
    $user = auth('admin')->user();
    $dashboardRoute = $panel === 'administrator' ? route('administrator.dashboard') : route('barista.dashboard');

    $navigation = match ($panel) {
        'administrator' => [
            [
                'heading' => 'Dashboard',
                'items' => [
                    ['label' => 'Analytics', 'route' => 'administrator.dashboard', 'pattern' => 'administrator.dashboard*', 'icon' => 'ki-chart-line'],
                ],
            ],
            [
                'heading' => 'Products',
                'items' => $user?->canManageProducts() ? [
                    ['label' => 'Categories', 'route' => 'administrator.products.categories.index', 'pattern' => 'administrator.products.categories.*', 'icon' => 'ki-category'],
                    ['label' => 'Flavours', 'route' => 'administrator.products.flavours.index', 'pattern' => 'administrator.products.flavours.*', 'icon' => 'ki-cup'],
                    ['label' => 'Tags', 'route' => 'administrator.products.tags.index', 'pattern' => 'administrator.products.tags.*', 'icon' => 'ki-price-tag'],
                    ['label' => 'Products', 'route' => 'administrator.products.index', 'pattern' => ['administrator.products.index', 'administrator.products.create', 'administrator.products.store', 'administrator.products.show', 'administrator.products.edit', 'administrator.products.update'], 'icon' => 'ki-basket'],
                    ['label' => 'Recipes', 'route' => 'administrator.recipes.index', 'pattern' => 'administrator.recipes.*', 'icon' => 'ki-book'],
                ] : [],
            ],
            [
                'heading' => 'Ingredients',
                'items' => $user?->canManageIngredients() ? [
                    ['label' => 'Categories', 'route' => 'administrator.ingredients.categories.index', 'pattern' => 'administrator.ingredients.categories.*', 'icon' => 'ki-book-open'],
                    ['label' => 'Brands', 'route' => 'administrator.ingredients.brands.index', 'pattern' => 'administrator.ingredients.brands.*', 'icon' => 'ki-tag'],
                    ['label' => 'Ingredients', 'route' => 'administrator.ingredients.index', 'pattern' => 'administrator.ingredients.*', 'icon' => 'ki-chef'],
                ] : [],
            ],
            [
                'heading' => 'Orders',
                'items' => $user?->canViewOrders() ? [
                    ['label' => 'Orders', 'route' => 'administrator.orders.index', 'pattern' => 'administrator.orders.*', 'icon' => 'ki-delivery-2'],
                ] : [],
            ],
            [
                'heading' => 'Inventory',
                'items' => $user?->canViewInventory() ? [
                    ['label' => 'Overview', 'route' => 'administrator.inventory.index', 'pattern' => 'administrator.inventory.index', 'icon' => 'ki-abstract-41'],
                    ['label' => 'History', 'route' => 'administrator.inventory.history', 'pattern' => 'administrator.inventory.history', 'icon' => 'ki-time'],
                    ['label' => 'Refill Requests', 'route' => 'administrator.inventory.refill-requests.index', 'pattern' => 'administrator.inventory.refill-requests.*', 'icon' => 'ki-delivery-3'],
                ] : [],
            ],
            [
                'heading' => 'Administration',
                'items' => array_values(array_filter([
                    $user?->canManageUsers()
                        ? ['label' => 'Users', 'route' => 'administrator.users.index', 'pattern' => 'administrator.users.*', 'icon' => 'ki-profile-circle']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Website Settings', 'route' => 'administrator.website-settings.edit', 'pattern' => 'administrator.website-settings.*', 'icon' => 'ki-setting-2']
                        : null,
                ])),
            ],
        ],
        'barista' => [
            [
                'heading' => 'Station',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'barista.dashboard', 'pattern' => 'barista.dashboard*', 'icon' => 'ki-abstract-26'],
                    ['label' => 'Orders', 'route' => 'barista.orders.index', 'pattern' => 'barista.orders.*', 'icon' => 'ki-delivery-2'],
                    ['label' => 'Products', 'route' => 'barista.products.index', 'pattern' => 'barista.products.*', 'icon' => 'ki-basket'],
                    ['label' => 'Recipes', 'route' => 'barista.recipes.index', 'pattern' => 'barista.recipes.*', 'icon' => 'ki-book'],
                    ['label' => 'Inventory', 'route' => 'barista.inventory.index', 'pattern' => 'barista.inventory.*', 'icon' => 'ki-abstract-41'],
                    ['label' => 'Refill Requests', 'route' => 'barista.refill-requests.index', 'pattern' => 'barista.refill-requests.*', 'icon' => 'ki-delivery-3'],
                ],
            ],
        ],
    };

    $logoutRoute = $panel === 'administrator' ? route('administrator.logout') : route('barista.logout');
@endphp

<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ $dashboardRoute }}" class="text-decoration-none d-flex flex-column">
            <span class="fs-3 fw-bold text-white">{{ config('app.name') }}</span>
            <span class="text-gray-400 fs-8 text-uppercase">{{ $panel === 'administrator' ? 'Administrator' : 'Barista' }}</span>
        </a>

        <div id="kt_app_sidebar_toggle" class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary body-bg h-30px w-30px position-absolute top-50 start-100 translate-middle rotate" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="app-sidebar-minimize">
            <i class="ki-duotone ki-double-left fs-2 rotate-180">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </div>
    </div>

    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper hover-scroll-overlay-y my-5" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer" data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
            <div class="menu menu-column menu-rounded menu-sub-indention px-3" id="kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">
                @foreach ($navigation as $section)
                    @if (filled($section['items']))
                        <div class="menu-item pt-5">
                            <div class="menu-content">
                                <span class="menu-heading fw-bold text-uppercase fs-7">{{ $section['heading'] }}</span>
                            </div>
                        </div>

                        @foreach ($section['items'] as $item)
                            @php
                                $isActive = collect((array) $item['pattern'])->contains(
                                    fn (string $pattern): bool => request()->routeIs($pattern),
                                );
                            @endphp
                            <div class="menu-item">
                                <a class="menu-link {{ $isActive ? 'active here show' : '' }}" href="{{ route($item['route']) }}">
                                    <span class="menu-icon">
                                        <i class="ki-duotone {{ $item['icon'] }} fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                            <span class="path6"></span>
                                        </i>
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
                    <a class="menu-link" href="{{ route('home') }}">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-shop fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Storefront</span>
                    </a>
                </div>

                <div class="menu-item">
                    <button class="menu-link border-0 bg-transparent w-100 text-start" data-bs-toggle="modal" data-bs-target="#internalFoundationModal" type="button">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-information-5 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                        <span class="menu-title">UI Foundation</span>
                    </button>
                </div>

                <div class="menu-item">
                    <form method="POST" action="{{ $logoutRoute }}">
                        @csrf
                        <button class="menu-link border-0 bg-transparent w-100 text-start" type="submit">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-exit-right fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
