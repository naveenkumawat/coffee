@extends('layouts.app', ['title' => 'My Cart | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Customer Cart</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">Your cart</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-300">
                    Review selected drinks before checkout is introduced in the next customer ordering phase.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('home') }}#menu" class="rounded-full border border-white/15 px-5 py-3 font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                    Continue browsing
                </a>
                @if ($summary['item_count'] > 0)
                    <form method="POST" action="{{ route('customer.cart.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-full border border-rose-300/30 bg-rose-500/10 px-5 py-3 font-medium text-rose-100 transition hover:border-rose-200/50 hover:bg-rose-500/20">
                            Clear cart
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if ($summary['has_unavailable_items'])
            <div class="mt-8 rounded-[2rem] border border-amber-300/25 bg-amber-500/10 px-6 py-5 text-sm leading-7 text-amber-100">
                Some saved items are no longer available. They remain visible for review, but they are excluded from the current cart total until they become available again.
            </div>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[1.2fr,0.8fr]">
            <section class="space-y-4">
                @forelse ($cart->items as $item)
                    @php($variant = $item->productVariant)
                    @php($product = $variant?->product)
                    @php($isAvailable = $variant && $variant->is_active && $variant->is_available && $product?->is_active && $product?->is_available)
                    @php($unitPrice = $isAvailable ? number_format((float) $variant->price, 2) : '0.00')
                    @php($lineSubtotal = $isAvailable ? number_format((float) bcmul((string) $variant->price, (string) $item->quantity, 2), 2) : '0.00')
                    <article class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <p class="text-xs uppercase tracking-[0.3em] text-amber-200">{{ $product?->category?->name ?? 'Unavailable' }}</p>
                                <h2 class="text-2xl font-semibold text-white">{{ $product?->name ?? 'Unavailable product' }}</h2>
                                <p class="text-sm text-stone-300">{{ $variant?->name ?? 'Variant unavailable' }}</p>
                                @if ($product?->short_description || $product?->description)
                                    <p class="max-w-2xl text-sm leading-7 text-stone-400">{{ $product?->short_description ?: $product?->description }}</p>
                                @endif
                                @unless ($isAvailable)
                                    <p class="inline-flex rounded-full border border-amber-300/30 bg-amber-500/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.2em] text-amber-100">
                                        Unavailable
                                    </p>
                                @endunless
                            </div>

                            <div class="min-w-56 rounded-[1.5rem] border border-white/10 bg-black/20 p-4">
                                <div class="flex items-center justify-between text-sm text-stone-300">
                                    <span>Unit price</span>
                                    <span class="font-medium text-white">${{ $unitPrice }}</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between text-sm text-stone-300">
                                    <span>Line total</span>
                                    <span class="font-semibold text-emerald-300">${{ $lineSubtotal }}</span>
                                </div>

                                <form method="POST" action="{{ route('customer.cart.items.update', $item) }}" class="mt-4 flex flex-wrap items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <label for="quantity-{{ $item->id }}" class="text-sm text-stone-300">Qty</label>
                                    <input
                                        id="quantity-{{ $item->id }}"
                                        name="quantity"
                                        type="number"
                                        min="1"
                                        value="{{ old('quantity', $item->quantity) }}"
                                        class="w-24 rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50"
                                    />
                                    <button type="submit" class="rounded-full bg-amber-400 px-4 py-3 text-sm font-medium text-stone-950 transition hover:bg-amber-300">
                                        Update
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('customer.cart.items.destroy', $item) }}" class="mt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-white/10 px-4 py-3 text-sm font-medium text-white transition hover:border-white/30 hover:bg-white/5 hover:text-white">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[2rem] border border-dashed border-white/20 bg-black/20 p-8 text-stone-300">
                        Your cart is empty. Add a few drinks from the menu to start building an order.
                    </div>
                @endforelse
            </section>

            <aside class="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Summary</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">Current totals</h2>

                <dl class="mt-8 space-y-4">
                    <div class="flex items-center justify-between text-sm text-stone-300">
                        <dt>Items</dt>
                        <dd class="font-medium text-white">{{ $summary['item_count'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm text-stone-300">
                        <dt>Subtotal</dt>
                        <dd class="font-medium text-white">${{ number_format((float) $summary['subtotal'], 2) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/10 pt-4 text-base text-stone-100">
                        <dt>Total</dt>
                        <dd class="font-semibold text-emerald-300">${{ number_format((float) $summary['total'], 2) }}</dd>
                    </div>
                </dl>

                <button
                    type="button"
                    disabled
                    class="mt-8 w-full cursor-not-allowed rounded-full bg-white/10 px-6 py-3 font-medium text-stone-300 opacity-70"
                >
                    Checkout coming next
                </button>

                <p class="mt-4 text-sm leading-7 text-stone-400">
                    Checkout and order creation stay intentionally unavailable in this phase so we can introduce them cleanly on top of this persistent cart foundation.
                </p>
            </aside>
        </div>
    </div>
@endsection
