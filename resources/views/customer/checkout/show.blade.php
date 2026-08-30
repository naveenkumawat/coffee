@extends('layouts.app', ['title' => 'Checkout | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Checkout</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">Review and confirm</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-300">
                    Prices and availability are revalidated on the server before your order is created.
                </p>
            </div>
            <a href="{{ route('customer.cart.show') }}" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                Back to cart
            </a>
        </div>

        <form method="POST" action="{{ route('customer.checkout.store') }}" class="grid gap-6 lg:grid-cols-[1.05fr,0.95fr]">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}" />
            <input type="hidden" name="fulfilment_method" value="takeaway" />

            <section class="space-y-6">
                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Customer Details</p>
                    <div class="mt-6 grid gap-4">
                        <div>
                            <label for="customer_name" class="mb-2 block text-sm font-medium text-stone-200">Name</label>
                            <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name', $customer->name) }}" required autocomplete="name" class="min-h-12 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                            @error('customer_name')
                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="customer_email" class="mb-2 block text-sm font-medium text-stone-200">Email</label>
                                <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', $customer->email) }}" required autocomplete="email" inputmode="email" class="min-h-12 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                                @error('customer_email')
                                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="customer_phone" class="mb-2 block text-sm font-medium text-stone-200">Phone</label>
                                <input id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone', $customer->phone) }}" required autocomplete="tel" inputmode="tel" class="min-h-12 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                                @error('customer_phone')
                                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Pickup Details</p>
                    <div class="mt-6 grid gap-4">
                        <div>
                            <label for="pickup_name" class="mb-2 block text-sm font-medium text-stone-200">Pickup Name</label>
                            <input id="pickup_name" name="pickup_name" type="text" value="{{ old('pickup_name', $customer->name) }}" required autocomplete="name" class="min-h-12 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                            @error('pickup_name')
                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="pickup_phone" class="mb-2 block text-sm font-medium text-stone-200">Pickup Phone</label>
                            <input id="pickup_phone" name="pickup_phone" type="tel" value="{{ old('pickup_phone', $customer->phone) }}" required autocomplete="tel" inputmode="tel" class="min-h-12 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" />
                            @error('pickup_phone')
                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="pickup_notes" class="mb-2 block text-sm font-medium text-stone-200">Pickup Notes</label>
                            <textarea id="pickup_notes" name="pickup_notes" rows="3" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50">{{ old('pickup_notes') }}</textarea>
                            @error('pickup_notes')
                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Order Notes</p>
                    <div class="mt-6">
                        <label for="customer_notes" class="mb-2 block text-sm font-medium text-stone-200">Notes</label>
                        <textarea id="customer_notes" name="customer_notes" rows="4" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none transition focus:border-amber-300/50">{{ old('customer_notes') }}</textarea>
                        @error('customer_notes')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                        <p class="mt-3 text-xs leading-6 text-stone-400">Use this for guest-safe notes such as less sweet, no extra topping, or pickup preferences.</p>
                    </div>
                </div>
            </section>

            <aside class="space-y-6 lg:sticky lg:top-6">
                <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Order Summary</p>
                    <div class="mt-6 space-y-4">
                        @foreach ($cart->items as $item)
                            <div class="rounded-3xl border border-white/10 bg-black/20 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-base font-semibold text-white">{{ $item->productVariant?->product?->name }}</h2>
                                        <p class="mt-1 text-sm text-stone-300">{{ $item->productVariant?->name }}</p>
                                    </div>
                                    <div class="text-right text-sm text-stone-300">
                                        <div>{{ $item->quantity }} x Rs {{ number_format((float) $item->productVariant?->price, 2) }}</div>
                                        <div class="mt-1 font-semibold text-emerald-300">
                                            Rs {{ number_format((float) bcmul((string) $item->productVariant?->price, (string) $item->quantity, 2), 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <dl class="mt-6 space-y-3 border-t border-white/10 pt-5 text-sm text-stone-300">
                        <div class="flex items-center justify-between">
                            <dt>Items</dt>
                            <dd class="font-medium text-white">{{ $summary['item_count'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>Subtotal</dt>
                            <dd class="font-medium text-white">Rs {{ number_format((float) $summary['subtotal'], 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-base text-stone-100">
                            <dt>Total</dt>
                            <dd class="font-semibold text-emerald-300">Rs {{ number_format((float) $summary['total'], 2) }}</dd>
                        </div>
                    </dl>

                    <button type="submit" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-full bg-amber-400 px-6 py-3 text-base font-semibold text-stone-950 transition hover:bg-amber-300">
                        Confirm order
                    </button>

                    <p class="mt-4 text-sm leading-7 text-stone-400">
                        After confirmation, you will receive an order number and payment instructions for {{ config('coffee.payments.display_name') }}. Payment stays pending until the cafe team confirms it manually.
                    </p>
                </section>
            </aside>
        </form>
    </div>
@endsection
