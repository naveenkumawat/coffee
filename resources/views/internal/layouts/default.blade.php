<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <base href="" />
        <title>{{ $title ?? config('app.name') }} | {{ ucfirst($panel) }} Panel</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
        <link href="{{ asset('internal/assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('internal/assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('internal/assets/css/custom.css') }}" rel="stylesheet" type="text/css" />
        @stack('styles')
    </head>
    <body
        id="kt_app_body"
        data-kt-app-layout="dark-sidebar"
        data-kt-app-header-fixed="true"
        data-kt-app-sidebar-enabled="true"
        data-kt-app-sidebar-fixed="true"
        data-kt-app-sidebar-hoverable="true"
        data-kt-app-sidebar-push-header="true"
        data-kt-app-sidebar-push-toolbar="true"
        data-kt-app-sidebar-push-footer="true"
        data-kt-app-toolbar-enabled="true"
        data-kt-app-toolbar-fixed="true"
        class="app-default"
    >
        <script>
            var defaultThemeMode = 'light';
            var themeMode;

            if (document.documentElement) {
                if (document.documentElement.hasAttribute('data-bs-theme-mode')) {
                    themeMode = document.documentElement.getAttribute('data-bs-theme-mode');
                } else if (localStorage.getItem('data-bs-theme') !== null) {
                    themeMode = localStorage.getItem('data-bs-theme');
                } else {
                    themeMode = defaultThemeMode;
                }

                if (themeMode === 'system') {
                    themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }

                document.documentElement.setAttribute('data-bs-theme', themeMode);
            }
        </script>

        <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
            <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
                @include('internal.includes.partials.header', ['panel' => $panel])

                <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                    @include('internal.includes.partials.sidebar', ['panel' => $panel])

                    <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                        <div class="d-flex flex-column flex-column-fluid">
                            @hasSection('breadcrumb')
                                @yield('breadcrumb')
                            @else
                                <x-internal.page-header :title="trim($__env->yieldContent('page-title')) ?: config('app.name')">
                                    <x-slot:breadcrumbs>
                                        @hasSection('breadcrumbs')
                                            @yield('breadcrumbs')
                                        @else
                                            <x-internal.breadcrumbs :items="[['label' => ucfirst($panel) . ' Panel']]" />
                                        @endif
                                    </x-slot:breadcrumbs>

                                    @hasSection('page-description')
                                        <x-slot:description>
                                            @yield('page-description')
                                        </x-slot:description>
                                    @endif

                                    @hasSection('toolbar-actions')
                                        <x-slot:actions>
                                            @yield('toolbar-actions')
                                        </x-slot:actions>
                                    @endif
                                </x-internal.page-header>
                            @endif

                            <div id="kt_app_content" class="app-content flex-column-fluid">
                                <div id="kt_app_content_container" class="app-container container-fluid">
                                    <x-internal.alerts />
                                    @yield('content')
                                </div>
                            </div>
                        </div>

                        @include('internal.includes.partials.footer', ['panel' => $panel])
                    </div>
                </div>
            </div>
        </div>

        <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
            <i class="ki-duotone ki-arrow-up">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </div>

        <script src="{{ asset('internal/assets/plugins/global/plugins.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/js/scripts.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/plugins/custom/ckeditor/ckeditor-classic.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/js/custom.js') }}"></script>
        <script src="{{ asset('internal/assets/js/confirm-modal.js') }}"></script>
        <script src="{{ asset('internal/assets/js/config/app-config.js') }}"></script>
        @include('internal.partials.realtime-bootstrap', ['panel' => $panel])
        @include('internal.partials.operational-notification-ui', [
            'dashboardRoute' => match ($panel) {
                'administrator' => route('administrator.dashboard'),
                'operator' => route('operator.dashboard'),
                'chef' => route('chef.dashboard'),
                'waiter' => route('waiter.dashboard'),
                default => route('barista.dashboard'),
            },
            'notificationsReadAllRoute' => match ($panel) {
                'administrator' => route('administrator.notifications.read-all'),
                'operator' => route('operator.notifications.read-all'),
                'chef' => route('chef.notifications.read-all'),
                'waiter' => route('waiter.notifications.read-all'),
                default => route('barista.notifications.read-all'),
            },
        ])
        <x-internal.confirm-modal />
        @stack('scripts')
        @include('components.flash-toast')
    </body>
</html>
