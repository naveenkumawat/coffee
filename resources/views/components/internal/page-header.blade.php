@props([
    'title',
])

<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div
        id="kt_app_toolbar_container"
        class="app-container container-fluid internal-page-toolbar d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center justify-content-between gap-4"
    >
        <div
            class="page-title d-flex flex-column justify-content-center flex-wrap min-w-0 flex-grow-1 me-lg-3"
            data-kt-swapper="true"
            data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
            data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_toolbar_container'}"
        >
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0 min-w-0 text-break">
                {{ $title }}
            </h1>

            @isset($breadcrumbs)
                <div class="min-w-0">
                    {{ $breadcrumbs }}
                </div>
            @endisset

            @isset($description)
                <div class="text-muted fs-7 fw-semibold mt-2 min-w-0 text-break">
                    {{ $description }}
                </div>
            @endisset
        </div>

        @isset($actions)
            <div class="internal-page-toolbar-actions d-flex flex-wrap align-items-stretch align-items-md-center justify-content-start justify-content-lg-end gap-3">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
