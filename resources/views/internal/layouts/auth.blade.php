<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }} | {{ ucfirst($panel) }} Login</title>

        <link rel="stylesheet" href="{{ asset('internal/assets/plugins/global/plugins.bundle.css') }}">
        <link rel="stylesheet" href="{{ asset('internal/assets/css/style.bundle.css') }}">
        <link rel="stylesheet" href="{{ asset('internal/assets/css/custom.css') }}">
    </head>
    <body id="kt_body" class="app-blank">
        <div class="d-flex flex-column flex-root" id="kt_app_root">
            <div class="d-flex flex-column flex-lg-row flex-column-fluid">
                <div class="d-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-2 order-lg-1" style="background-image: url('{{ asset('internal/assets/media/auth/bg10.jpeg') }}')">
                    <div class="d-flex flex-column flex-center py-7 py-lg-15 px-5 px-md-15 w-100">
                        <a href="{{ route('home') }}" class="mb-8">
                            <span class="fs-2hx fw-bold text-white text-hover-warning">{{ config('app.name') }}</span>
                        </a>
                        <img class="mx-auto mw-100 w-275px w-md-50 w-xl-500px mb-10 mb-lg-20" src="{{ asset('internal/assets/media/illustrations/sketchy-1/17.png') }}" alt="Coffee internal panel">
                        <h1 class="text-white fs-2qx fw-bolder text-center mb-7">{{ $headline ?? 'Coffee internal operations' }}</h1>
                        <div class="text-white fs-base text-center">
                            {{ $subheadline ?? 'Administrator and barista workflows share one internal UI foundation while the storefront remains separate.' }}
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-1 order-lg-2">
                    <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                        <div class="w-lg-500px p-10">
                            @include('internal.auth.form', ['panel' => $panel])
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('internal/assets/plugins/global/plugins.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/js/scripts.bundle.js') }}"></script>
    </body>
</html>
