@extends('layouts.app', ['title' => 'Forgot Password | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="mx-auto max-w-xl rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
            <div class="mb-8 space-y-3 text-center">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Password Reset</p>
                <h1 class="text-3xl font-semibold text-white">Reset your customer password</h1>
                <p class="text-sm leading-7 text-stone-300">Enter the email used for your Coffee customer account and we’ll send a reset link.</p>
            </div>

            <form method="POST" action="{{ route('customer.password.email') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-stone-200">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    @error('email')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="w-full rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                    Send Reset Link
                </button>
            </form>
        </div>
    </div>
@endsection
