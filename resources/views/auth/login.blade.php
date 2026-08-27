@extends('layouts.app')

@section('content')
    <div class="mx-auto flex min-h-screen max-w-7xl items-center px-6 py-16">
        <div class="grid w-full gap-8 lg:grid-cols-[0.95fr,1.05fr]">
            <section class="rounded-[2rem] border border-white/10 bg-white/5 p-8 backdrop-blur">
                <p class="text-sm uppercase tracking-[0.35em] text-amber-200">Admin access</p>
                <h1 class="mt-4 text-4xl font-semibold text-white">Sign in to manage the cafe foundation.</h1>
                <p class="mt-4 max-w-xl text-sm leading-7 text-stone-300">
                    Use an owner or manager account from your configured database seed. This guard is intentionally separate from the future customer storefront flow.
                </p>
                <ul class="mt-8 space-y-3 text-sm text-stone-300">
                    <li>Role-aware dashboard and menu administration</li>
                    <li>Structured logs with request correlation</li>
                    <li>Room for future orders, inventory, and staff operations</li>
                </ul>
            </section>

            <section class="rounded-[2rem] border border-white/10 bg-stone-950/70 p-8 shadow-2xl shadow-black/20">
                <h2 class="text-2xl font-semibold text-white">Admin login</h2>
                <form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="mb-2 block text-sm text-white/80">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-amber-300/50">
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-sm text-white/80">Password</label>
                        <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-amber-300/50">
                    </div>
                    <label class="flex items-center gap-3 text-sm text-stone-300">
                        <input type="checkbox" name="remember" value="1" class="rounded border-white/10 bg-white/10">
                        Keep me signed in on this device
                    </label>
                    <button type="submit" class="w-full rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                        Sign in
                    </button>
                </form>
            </section>
        </div>
    </div>
@endsection
