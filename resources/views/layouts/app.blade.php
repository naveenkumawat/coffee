<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-100">
        <div class="relative isolate overflow-hidden">
            <div class="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.35),_transparent_45%),radial-gradient(circle_at_20%_20%,_rgba(14,165,233,0.18),_transparent_28%),linear-gradient(180deg,_#0c0a09_0%,_#1c1917_100%)]"></div>
            <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
                <a href="{{ route('home') }}" class="text-lg font-semibold tracking-[0.28em] text-amber-200 uppercase">
                    Coffee
                </a>

                <nav class="flex flex-wrap items-center gap-3 text-sm text-stone-300">
                    <a href="{{ route('home') }}#menu" class="rounded-full border border-white/10 px-4 py-2 transition hover:border-white/30 hover:bg-white/5 hover:text-white">
                        Menu
                    </a>

                    @auth
                        <a href="{{ route('customer.orders.index') }}" class="rounded-full border border-white/10 px-4 py-2 transition hover:border-white/30 hover:bg-white/5 hover:text-white">
                            My Orders
                        </a>
                        <a href="{{ route('customer.account.show') }}" class="rounded-full bg-amber-400 px-4 py-2 font-medium text-stone-950 transition hover:bg-amber-300">
                            Account
                        </a>
                        <form method="POST" action="{{ route('customer.logout') }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-white/10 px-4 py-2 transition hover:border-white/30 hover:bg-white/5 hover:text-white">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('customer.login') }}" class="rounded-full border border-white/10 px-4 py-2 transition hover:border-white/30 hover:bg-white/5 hover:text-white">
                            Login
                        </a>
                        <a href="{{ route('customer.register') }}" class="rounded-full bg-amber-400 px-4 py-2 font-medium text-stone-950 transition hover:bg-amber-300">
                            Register
                        </a>
                    @endauth
                </nav>
            </header>

            <main>
                @if (session('status'))
                    <div class="mx-auto max-w-7xl px-6 pb-2">
                        <div class="rounded-3xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </body>
</html>
