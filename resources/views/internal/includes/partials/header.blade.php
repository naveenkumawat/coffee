@php
    $user = auth('admin')->user();
    $panelName = $panel === 'administrator' ? 'Administrator' : 'Barista';
@endphp

<div id="kt_app_header" class="app-header">
    <div class="app-container container-xxl d-flex align-items-stretch justify-content-between" id="kt_app_header_container">
        <div class="d-flex align-items-center d-lg-none ms-n3 me-2" title="Show sidebar menu">
            <div class="btn btn-icon btn-color-gray-700 btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                <i class="ki-outline ki-abstract-14 fs-2"></i>
            </div>
        </div>

        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0 me-lg-15">
            <a href="{{ $panel === 'administrator' ? route('administrator.dashboard') : route('barista.dashboard') }}" class="text-decoration-none">
                <span class="fs-2 fw-bold text-gray-900">{{ config('app.name') }}</span>
                <span class="badge badge-light-warning ms-3">{{ $panelName }}</span>
            </a>
        </div>

        <div class="app-navbar flex-shrink-0">
            <div class="app-navbar-item ms-1 ms-md-4">
                <a href="{{ route('home') }}" class="btn btn-light-primary">
                    <i class="ki-outline ki-shop fs-3"></i>
                    View Storefront
                </a>
            </div>

            @if ($user)
                <div class="app-navbar-item ms-1 ms-md-4">
                    <div class="btn btn-flex btn-light align-items-center gap-3">
                        <span class="symbol symbol-35px symbol-circle">
                            <span class="symbol-label bg-warning text-inverse-warning fw-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </span>
                        <span class="d-none d-md-flex flex-column align-items-start">
                            <span class="text-gray-800 fs-7 fw-bold">{{ $user->name }}</span>
                            <span class="text-muted fs-8">{{ $user->role->label() }}</span>
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
