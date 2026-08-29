@extends('layouts.app', ['title' => 'My Account | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="grid gap-8 lg:grid-cols-[1.05fr,0.95fr]">
            <section class="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
                <div class="mb-6">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Profile</p>
                    <h1 class="mt-3 text-3xl font-semibold text-white">My account</h1>
                    <p class="mt-3 text-sm leading-7 text-stone-300">Manage your basic profile details for future ordering and order tracking.</p>
                </div>

                <form method="POST" action="{{ route('customer.account.profile.update') }}" class="grid gap-5 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-2">
                        <label for="name" class="mb-2 block text-sm font-medium text-stone-200">Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $customer->name) }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                        @error('name')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-stone-200">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                        @error('email')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="phone" class="mb-2 block text-sm font-medium text-stone-200">Phone</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                        @error('phone')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                            Save profile
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
                <div class="mb-6">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Security</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">Change password</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-300">Your customer password remains separate from any internal management login.</p>
                </div>

                <form method="POST" action="{{ route('customer.account.password.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="current_password" class="mb-2 block text-sm font-medium text-stone-200">Current Password</label>
                        <input id="current_password" name="current_password" type="password" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                        @error('current_password')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-stone-200">New Password</label>
                        <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                        @error('password')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-medium text-stone-200">Confirm New Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                    </div>
                    <button type="submit" class="rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                        Update password
                    </button>
                </form>
            </section>
        </div>
    </div>
@endsection
