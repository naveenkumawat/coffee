@extends('layouts.app', ['title' => 'Customer Login | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="mx-auto max-w-xl rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
            <div class="mb-8 space-y-3 text-center">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Customer Account</p>
                <h1 class="text-3xl font-semibold text-white">Sign in to track your orders</h1>
                <p class="text-sm leading-7 text-stone-300">Customer access stays separate from the Administrator and Barista panels.</p>
            </div>

            <form method="POST" action="{{ route('customer.login.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="login" class="mb-2 block text-sm font-medium text-stone-200">Email or mobile number</label>
                    <input id="login" name="login" type="text" value="{{ old('login', old('email')) }}" required autofocus autocomplete="username" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    @error('login')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                    @error('email')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="text-sm font-medium text-stone-200">Password</label>
                        <a href="{{ route('customer.password.request') }}" class="text-sm text-amber-200 transition hover:text-amber-100">Forgot password?</a>
                    </div>
                    <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                </div>
                <label class="flex items-center gap-3 text-sm text-stone-300">
                    <input type="checkbox" name="remember" value="1" class="rounded border-white/20 bg-black/20 text-amber-300" />
                    <span>Keep me signed in</span>
                </label>
                <button type="submit" class="w-full rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                    Login
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-stone-300">
                New here?
                <a href="{{ route('customer.register') }}" class="text-amber-200 transition hover:text-amber-100">Create an account</a>
            </p>
        </div>
    </div>
@endsection
