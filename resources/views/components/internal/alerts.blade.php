@if (session('status'))
    <div class="alert alert-success d-flex align-items-center p-5 mb-10">
        <i class="ki-outline ki-check-circle fs-2hx text-success me-4"></i>
        <div class="d-flex flex-column">
            <h4 class="mb-1 text-success">Success</h4>
            <span>{{ session('status') }}</span>
        </div>
    </div>
@endif
