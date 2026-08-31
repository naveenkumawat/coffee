@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-[1.2fr,0.8fr] lg:items-center">
            <section class="space-y-8">
                <p class="inline-flex rounded-full border border-amber-300/20 bg-amber-500/10 px-4 py-2 text-xs uppercase tracking-[0.35em] text-amber-200">
                    Laravel 13 Cafe Foundation
                </p>
                <div class="space-y-5">
                    <h1 class="max-w-4xl text-5xl font-semibold tracking-tight text-white sm:text-7xl">
                        Built for a modern cafe that needs speed on the floor and clarity in the back office.
                    </h1>
                    <p class="max-w-2xl text-lg leading-8 text-stone-300">
                        The public menu stays fast through caching, the admin area is role-aware, and the codebase is structured for future modules like orders, inventory, loyalty, and kitchen workflows.
                    </p>
                </div>
                <div class="flex flex-wrap gap-4">
                    @guest('web')
                        <a href="{{ route('customer.register') }}" class="rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                            Create customer account
                        </a>
                        <a href="{{ route('customer.login') }}" class="rounded-full border border-white/15 px-6 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                            Customer sign in
                        </a>
                    @else
                        @if (auth('web')->user()?->hasRole('customer'))
                            <a href="{{ route('customer.account.show') }}" class="rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                                Open my account
                            </a>
                            <a href="{{ route('customer.cart.show') }}" class="rounded-full border border-white/15 px-6 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                                Open my cart
                            </a>
                            <a href="{{ route('customer.orders.index') }}" class="rounded-full border border-white/15 px-6 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                                View my orders
                            </a>
                        @endif
                    @endguest
                    <a href="{{ route('administrator.login') }}" class="rounded-full border border-white/15 px-6 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                        Open administrator panel
                    </a>
                    <a href="{{ route('barista.login') }}" class="rounded-full border border-white/15 px-6 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                        Barista sign in
                    </a>
                    <a href="#menu" class="rounded-full border border-white/15 px-6 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                        Browse menu
                    </a>
                </div>
            </section>

            <aside class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($featuredProducts as $product)
                        <article class="rounded-3xl bg-black/25 p-5">
                            <p class="text-xs uppercase tracking-[0.3em] text-amber-200">{{ $product->category?->name }}</p>
                            <h2 class="mt-3 text-xl font-semibold text-white">{{ $product->name }}</h2>
                            <p class="mt-2 text-sm text-stone-300">{{ $product->short_description ?: $product->description }}</p>
                            <p class="mt-4 text-lg font-semibold text-emerald-300">${{ number_format((float) ($product->defaultVariant?->price ?? 0), 2) }}</p>
                            <div class="mt-4">
                                @php($defaultVariant = $product->defaultVariant)
                                @if ($defaultVariant && auth('web')->user()?->hasRole('customer'))
                                    <form method="POST" action="{{ route('customer.cart.items.store') }}">
                                        @csrf
                                        <input type="hidden" name="product_variant_id" value="{{ $defaultVariant->id }}" />
                                        <input type="hidden" name="quantity" value="1" />
                                        <button type="submit" class="rounded-full bg-amber-400 px-4 py-2 text-sm font-medium text-stone-950 transition hover:bg-amber-300">
                                            Add to cart
                                        </button>
                                    </form>
                                @elseif ($defaultVariant)
                                    <a href="{{ route('customer.login') }}" class="rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                                        Sign in to order
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </aside>
        </div>

        <section id="menu" class="mt-16 space-y-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Current menu</p>
                    <h2 class="mt-2 text-3xl font-semibold text-white">Organized for scale, simple for guests.</h2>
                </div>
                <p class="max-w-xl text-sm leading-7 text-stone-400">
                    This initial foundation uses categories and items as the first domain slice, mirroring the layered patterns we want for future modules.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                @forelse ($categories as $category)
                    <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-2xl font-semibold text-white">{{ $category->name }}</h3>
                                <p class="mt-2 max-w-xl text-sm leading-7 text-stone-300">{{ $category->description }}</p>
                            </div>
                            <span class="rounded-full border border-white/10 px-3 py-1 text-xs uppercase tracking-[0.2em] text-white/60">
                                {{ $category->products->count() }} products
                            </span>
                        </div>
                        <div class="mt-6 space-y-4">
                            @foreach ($category->products as $product)
                                <div class="flex items-start justify-between gap-4 border-t border-white/10 pt-4 first:border-t-0 first:pt-0">
                                    <div>
                                        <h4 class="text-lg font-medium text-white">{{ $product->name }}</h4>
                                        <p class="mt-1 text-sm text-stone-400">{{ $product->short_description ?: $product->description }}</p>
                                    </div>
                            <span class="text-base font-semibold text-emerald-300">${{ number_format((float) ($product->defaultVariant?->price ?? 0), 2) }}</span>
                        </div>
                        @php($defaultVariant = $product->defaultVariant)
                        @if ($defaultVariant)
                            <div class="mt-3 flex flex-wrap gap-3">
                                @if (auth('web')->user()?->hasRole('customer'))
                                    <form method="POST" action="{{ route('customer.cart.items.store') }}">
                                        @csrf
                                        <input type="hidden" name="product_variant_id" value="{{ $defaultVariant->id }}" />
                                        <input type="hidden" name="quantity" value="1" />
                                        <button type="submit" class="rounded-full bg-amber-400 px-4 py-2 text-sm font-medium text-stone-950 transition hover:bg-amber-300">
                                            Add {{ $defaultVariant->name }} to cart
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('customer.login') }}" class="rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                                        Sign in to order
                                    </a>
                                @endif
                            </div>
                        @endif
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="rounded-[2rem] border border-dashed border-white/20 bg-black/20 p-8 text-stone-300 lg:col-span-2">
                        Seed the example menu or create categories in the admin panel to populate the public homepage.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
