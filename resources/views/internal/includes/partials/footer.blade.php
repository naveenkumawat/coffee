<div id="kt_app_footer" class="app-footer">
    <div class="app-container container-fluid d-flex flex-column flex-md-row flex-center flex-md-stack py-3">
        <div class="text-dark order-2 order-md-1">
            <span class="text-muted fw-semibold me-1">{{ now()->year }} &copy; All Rights Reserved by</span>
            <span class="text-gray-800">{{ config('app.name') }}</span>
        </div>
        <ul class="menu menu-gray-600 menu-hover-primary fw-semibold order-1">
            <li class="menu-item">
                <a href="{{ route('home') }}" class="menu-link px-2">Storefront</a>
            </li>
        </ul>
    </div>
</div>

<div class="modal fade" id="dynamicModalPopup" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content"></div>
    </div>
</div>

<div class="modal fade" tabindex="-1" id="internalFoundationModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Internal UI Foundation</h2>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>
            <div class="modal-body py-lg-10 px-lg-10">
                <div class="mb-8">
                    <h3 class="fs-4 fw-bold mb-3">Shared between roles</h3>
                    <p class="text-muted mb-0">Layouts, styles, components, responsive behavior, and internal plugins are centralized in one shared layer so Administrator and Barista stay visually aligned without duplicated assets.</p>
                </div>
                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
                    <i class="ki-outline ki-message-text-2 fs-2tx text-warning me-4"></i>
                    <div class="d-flex flex-stack flex-grow-1">
                        <div class="fw-semibold">
                            <h4 class="text-gray-900 fw-bold">Storefront intentionally separate</h4>
                            <div class="fs-6 text-muted">The public customer experience keeps its own Coffee Cafe theme and does not reuse the internal staff panel shell.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
