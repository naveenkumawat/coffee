<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }} | {{ ucfirst($panel) }} Panel</title>

        <link rel="stylesheet" href="{{ asset('internal/assets/plugins/global/plugins.bundle.css') }}">
        <link rel="stylesheet" href="{{ asset('internal/assets/css/style.bundle.css') }}">
        <link rel="stylesheet" href="{{ asset('internal/assets/css/custom.css') }}">
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
        class="app-default"
    >
        <script>
            if (document.documentElement) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        </script>

        <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
            <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
                @include('internal.includes.partials.header', ['panel' => $panel])

                <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                    @include('internal.includes.partials.sidebar', ['panel' => $panel])

                    <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                        <div class="d-flex flex-column flex-column-fluid">
                            <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
                                <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-4">
                                    <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                                        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                                            @yield('page-title', config('app.name'))
                                        </h1>
                                        @hasSection('breadcrumbs')
                                            @yield('breadcrumbs')
                                        @else
                                            <x-internal.breadcrumbs :items="[['label' => ucfirst($panel) . ' Panel']]" />
                                        @endif
                                    </div>

                                    @hasSection('toolbar-actions')
                                        <div class="d-flex align-items-center gap-3">
                                            @yield('toolbar-actions')
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div id="kt_app_content" class="app-content flex-column-fluid">
                                <div id="kt_app_content_container" class="app-container container-xxl">
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

        <script src="{{ asset('internal/assets/plugins/global/plugins.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/js/scripts.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/js/custom.js') }}"></script>
        @stack('scripts')
    </body>
</html>
