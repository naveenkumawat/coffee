@extends('layouts.app', ['title' => 'Customer Register | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="mx-auto max-w-2xl rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
            <div class="mb-8 space-y-3 text-center">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Create Account</p>
                <h1 class="text-3xl font-semibold text-white">Start your Coffee customer account</h1>
                <p class="text-sm leading-7 text-stone-300">Register once to track future orders and keep your basic contact details ready for checkout later.</p>
            </div>

            <form method="POST" action="{{ route('customer.register.store') }}" class="grid gap-5 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2">
                    <label for="name" class="mb-2 block text-sm font-medium text-stone-200">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    @error('name')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-stone-200">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    @error('email')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="phone" class="mb-2 block text-sm font-medium text-stone-200">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    @error('phone')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-stone-200">Password</label>
                    <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    @error('password')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-stone-200">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="w-full rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                        Register
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
