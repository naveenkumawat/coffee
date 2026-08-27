<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Admin' }} | {{ config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-100">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.18),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.14),_transparent_22%),linear-gradient(180deg,_#0c0a09_0%,_#111827_100%)]">
            <header class="border-b border-white/10 bg-black/20 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
                    <div>
                        <a href="{{ route('admin.dashboard') }}" class="text-lg font-semibold tracking-[0.25em] text-amber-200 uppercase">{{ config('app.name') }}</a>
                        <p class="text-sm text-white/60">Cafe operations foundation</p>
                    </div>
                    <nav class="flex items-center gap-4 text-sm text-white/70">
                        <a href="{{ route('home') }}" class="hover:text-white">Public menu</a>
                        <a href="{{ route('admin.menu.categories.index') }}" class="hover:text-white">Categories</a>
                        <a href="{{ route('admin.menu.items.index') }}" class="hover:text-white">Items</a>
                        @auth('admin')
                            <span class="rounded-full border border-white/10 px-3 py-1 text-xs uppercase tracking-[0.2em] text-white/70">{{ auth('admin')->user()->role->label() }}</span>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="rounded-full bg-white/10 px-4 py-2 text-white transition hover:bg-white/20">Logout</button>
                            </form>
                        @endauth
                    </nav>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-6 py-10">
                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </body>
</html>
