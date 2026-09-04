@if ($items !== [])
    <div class="card card-flush internal-card mb-8">
        <div class="card-header">
            <h3 class="card-title fw-bold">{{ $title }}</h3>
        </div>
        <div class="card-body pt-0">
            <ul class="mb-0">
                @foreach ($items as $item)
                    <li class="mb-2 text-gray-800">{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
