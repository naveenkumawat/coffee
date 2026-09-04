@extends($panel.'.layouts.default')

@section('page-title', $module['title'])

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => $roleLabel.' Panel', 'url' => route($panel.'.dashboard')],
        ['label' => 'Documentation', 'url' => route($panel.'.documentation.index')],
        ['label' => $module['title']],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'All topics', 'url' => route($panel.'.documentation.index'), 'variant' => 'light', 'icon' => 'ki-book'],
    ]" />
@endsection

@section('content')
    <div class="row g-8">
        <div class="col-xl-9">
            <div class="card card-flush internal-card mb-8">
                <div class="card-body">
                    <h1 class="fs-2 fw-bold text-gray-900 mb-3">{{ $module['title'] }}</h1>
                    <p class="fs-5 text-gray-700 mb-0">{{ $module['overview'] }}</p>
                </div>
            </div>

            @include('internal.documentation.partials.section', [
                'title' => 'How it works',
                'items' => $module['how_it_works'] ?? [],
            ])

            @include('internal.documentation.partials.section', [
                'title' => 'How to use',
                'items' => $module['how_to_use'] ?? [],
            ])

            @include('internal.documentation.partials.section', [
                'title' => 'How to configure',
                'items' => $module['how_to_configure'] ?? [],
            ])

            @if (! empty($module['conditions']))
                <div class="card card-flush internal-card mb-8">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Conditions / rules</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-4">
                                <thead>
                                    <tr class="text-muted fw-bold fs-7 text-uppercase">
                                        <th>If</th>
                                        <th>Then</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($module['conditions'] as $condition)
                                        <tr>
                                            <td class="text-gray-800">{{ $condition['if'] }}</td>
                                            <td class="text-gray-800">{{ $condition['then'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if (! empty($module['examples']))
                <div class="card card-flush internal-card mb-8">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Examples / sample data</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="accordion" id="doc-examples">
                            @foreach ($module['examples'] as $index => $example)
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="ex-h-{{ $index }}">
                                        <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#ex-c-{{ $index }}">
                                            {{ $example['title'] }}
                                        </button>
                                    </h2>
                                    <div id="ex-c-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#doc-examples">
                                        <div class="accordion-body text-gray-700" style="white-space: pre-wrap;">{{ $example['body'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if (! empty($module['options']))
                <div class="card card-flush internal-card mb-8">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Available options</h3>
                    </div>
                    <div class="card-body pt-0">
                        @foreach ($module['options'] as $option)
                            <div class="border border-dashed rounded p-5 mb-5">
                                <div class="fw-bold text-gray-900 mb-2">{{ $option['name'] }}</div>
                                <div class="mb-1"><span class="text-muted">What:</span> {{ $option['what'] }}</div>
                                <div class="mb-1"><span class="text-muted">Why:</span> {{ $option['why'] }}</div>
                                <div class="mb-1"><span class="text-muted">When:</span> {{ $option['when'] }}</div>
                                <div><span class="text-muted">Example:</span> {{ $option['example'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @include('internal.documentation.partials.section', [
                'title' => 'Common mistakes / important notes',
                'items' => $module['notes'] ?? [],
            ])

            @if (! empty($module['demo_samples']))
                <div class="card card-flush internal-card mb-8">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Demo samples</h3>
                    </div>
                    <div class="card-body pt-0">
                        <p class="text-muted">Local/testing DemoSeeder examples (not production data):</p>
                        <ul class="mb-0">
                            @foreach ($module['demo_samples'] as $sample)
                                <li>{{ $sample }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-3">
            <div class="card card-flush internal-card">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Topics</h3>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex flex-column gap-2">
                        @foreach ($siblings as $sibling)
                            <a
                                href="{{ route($panel.'.documentation.show', $sibling['slug']) }}"
                                class="{{ $sibling['slug'] === $module['slug'] ? 'fw-bold text-primary' : 'text-gray-700' }}"
                            >{{ $sibling['title'] }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
