@extends($panel.'.layouts.default')

@section('page-title', $roleLabel.' Documentation')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => $roleLabel.' Panel', 'url' => route($panel.'.dashboard')],
        ['label' => 'Documentation'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card mb-8">
        <div class="card-body">
            <form method="GET" action="{{ route($panel.'.documentation.index') }}" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="q" class="form-label">Search help topics</label>
                    <input id="q" name="q" type="search" value="{{ $query }}" class="form-control" placeholder="Orders, loyalty, tables…" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    @if ($query !== '')
                        <a href="{{ route($panel.'.documentation.index') }}" class="btn btn-light">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @forelse ($grouped as $group => $modules)
        <div class="mb-10">
            <h2 class="fs-3 fw-bold text-gray-900 mb-4">{{ $group }}</h2>
            <div class="row g-5">
                @foreach ($modules as $module)
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route($panel.'.documentation.show', $module['slug']) }}" class="card card-flush internal-card h-100 text-decoration-none">
                            <div class="card-body">
                                <div class="fw-bold text-gray-900 mb-2">{{ $module['title'] }}</div>
                                <div class="text-muted fs-7">{{ \Illuminate\Support\Str::limit($module['overview'], 120) }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="alert alert-light">No documentation topics matched your search.</div>
    @endforelse
@endsection
