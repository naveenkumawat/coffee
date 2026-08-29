<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <base href="" />
        <title>{{ $title ?? config('app.name') }} | {{ ucfirst($panel) }} Login</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="shortcut icon" href="{{ asset('internal/assets/media/logos/favicon.ico') }}">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
        <link href="{{ asset('internal/assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('internal/assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('internal/assets/css/custom.css') }}" rel="stylesheet" type="text/css" />
        @stack('styles')
    </head>
    <body id="body" class="bg-body">
        <div class="d-flex flex-column flex-root">
            <div
                class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed"
                style="background-image: url('{{ asset('internal/assets/media/illustrations/sketchy-1/17.png') }}')"
            >
                <div class="d-flex flex-center flex-column flex-column-fluid p-10 pb-lg-20">
                    <a href="{{ route('home') }}" class="mb-12 text-decoration-none">
                        <span class="text-primary fw-bold fs-1">{{ config('app.name') }}</span>
                    </a>

                    <div class="w-lg-500px bg-body rounded shadow-sm p-10 p-lg-15 mx-auto">
                        @include('internal.auth.form', ['panel' => $panel])
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('internal/assets/plugins/global/plugins.bundle.js') }}"></script>
        <script src="{{ asset('internal/assets/js/scripts.bundle.js') }}"></script>
        @stack('scripts')
        @include('components.flash-toast')
    </body>
</html>
