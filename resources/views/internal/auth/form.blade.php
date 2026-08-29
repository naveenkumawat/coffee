@php
    $panelConfig = $panel === 'administrator'
        ? [
            'title' => 'Administrator sign in',
            'eyebrow' => 'Internal management panel',
            'description' => 'Owners and managers use this panel for catalog, configuration, and operations oversight.',
            'route' => route('administrator.login.store'),
        ]
        : [
            'title' => 'Barista sign in',
            'eyebrow' => 'Cafe station panel',
            'description' => 'Baristas use this panel for station visibility and role-scoped operational tasks.',
            'route' => route('barista.login.store'),
        ];
@endphp

<form class="form w-100" method="POST" action="{{ $panelConfig['route'] }}">
    @csrf
    <div class="text-center mb-11">
        <h1 class="text-gray-900 fw-bolder mb-3">{{ $panelConfig['title'] }}</h1>
        <div class="text-gray-500 fw-semibold fs-6 mb-2">{{ $panelConfig['description'] }}</div>
        <div class="text-muted fw-bold fs-7">{{ $panelConfig['eyebrow'] }}</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
            <i class="ki-outline ki-shield-cross fs-2hx text-danger me-4"></i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-danger">Unable to sign in</h4>
                @foreach ($errors->all() as $error)
                    <span>{{ $error }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="fv-row mb-8">
        <label for="email" class="form-label fw-semibold text-gray-600">Email address</label>
        <input id="email" type="email" placeholder="name@example.com" name="email" autocomplete="email" value="{{ old('email') }}" class="form-control bg-transparent" required autofocus>
    </div>

    <div class="fv-row mb-3">
        <input id="password" type="password" placeholder="Password" name="password" autocomplete="current-password" class="form-control bg-transparent" required>
    </div>

    <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
        <label class="form-check form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" value="1" name="remember">
            <span class="form-check-label text-gray-700">Keep this session signed in</span>
        </label>
        <a href="{{ route('home') }}" class="link-primary">Return to storefront</a>
    </div>

    <div class="d-grid mb-10">
        <x-internal.button label="Sign In" type="submit" variant="success" stretch="true" />
    </div>
    <div class="text-gray-500 text-center fw-semibold fs-6">Shared internal theme, role-scoped access, and a separate Coffee customer frontend.</div>
</form>
