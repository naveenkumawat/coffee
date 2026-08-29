@extends('layouts.app', ['title' => 'Order Confirmation | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="rounded-[2rem] border border-emerald-400/20 bg-emerald-500/10 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
            <p class="text-xs uppercase tracking-[0.35em] text-emerald-200">Order Created</p>
            <h1 class="mt-3 text-3xl font-semibold text-white">{{ $order->order_number }}</h1>
            <p class="mt-4 text-sm leading-7 text-stone-100">
                Your order has been created successfully and is currently awaiting payment confirmation.
            </p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1.05fr,0.95fr]">
            <section class="space-y-6">
                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Payment Instructions</p>
                    <div class="mt-6 space-y-4">
                        <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                            <p class="text-sm leading-7 text-stone-200">{{ config('coffee.payments.instructions') }}</p>
                            <p class="mt-3 text-sm leading-7 text-stone-300">
                                Send your payment screenshot together with order number <span class="font-semibold text-white">{{ $order->order_number }}</span> on WhatsApp after payment.
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-400">Total Amount</p>
                                <p class="mt-2 text-2xl font-semibold text-emerald-300">Rs {{ number_format((float) $order->total_amount, 2) }}</p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                                <p class="text-xs uppercase tracking-[0.25em] text-stone-400">Payment Status</p>
                                <p class="mt-2 text-2xl font-semibold text-white">Pending Payment</p>
                            </div>
                        </div>
                        @if (filled(config('coffee.payments.upi_id')) || filled(config('coffee.payments.whatsapp_number')))
                            <div class="grid gap-4 sm:grid-cols-2">
                                @if (filled(config('coffee.payments.upi_id')))
                                    <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                                        <p class="text-xs uppercase tracking-[0.25em] text-stone-400">UPI ID</p>
                                        <p class="mt-2 break-all text-lg font-semibold text-white">{{ config('coffee.payments.upi_id') }}</p>
                                    </div>
                                @endif
                                @if (filled(config('coffee.payments.whatsapp_number')))
                                    <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                                        <p class="text-xs uppercase tracking-[0.25em] text-stone-400">WhatsApp</p>
                                        <p class="mt-2 text-lg font-semibold text-white">{{ config('coffee.payments.whatsapp_number') }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">What to Do Next</p>
                    <ol class="mt-6 space-y-3 text-sm leading-7 text-stone-300">
                        <li>1. Complete the payment for the total amount shown above.</li>
                        <li>2. Share your payment screenshot with the order number on WhatsApp.</li>
                        <li>3. Track status updates from My Orders while the cafe team confirms payment and prepares your order.</li>
                    </ol>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Order Summary</p>
                    <div class="mt-6 space-y-4">
                        @foreach ($order->items as $item)
                            <div class="rounded-3xl border border-white/10 bg-black/20 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-base font-semibold text-white">{{ $item->product_name }}</h2>
                                        <p class="mt-1 text-sm text-stone-300">{{ $item->variant_name }}</p>
                                    </div>
                                    <div class="text-right text-sm text-stone-300">
                                        <div>{{ $item->quantity }} x Rs {{ number_format((float) $item->unit_price, 2) }}</div>
                                        <div class="mt-1 font-semibold text-emerald-300">Rs {{ number_format((float) $item->line_subtotal, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 grid gap-3 text-sm text-stone-300">
                        <div class="flex items-center justify-between">
                            <span>Status</span>
                            <span class="font-medium text-white">{{ $order->status->label() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Pickup Name</span>
                            <span class="font-medium text-white">{{ $order->pickup_name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Pickup Phone</span>
                            <span class="font-medium text-white">{{ $order->pickup_phone }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ route('customer.orders.show', $order) }}" class="inline-flex min-h-12 items-center justify-center rounded-full bg-amber-400 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-300">
                        View order detail
                    </a>
                    <a href="{{ route('customer.orders.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-white/15 px-6 py-3 text-sm font-medium text-white transition hover:border-white/40 hover:bg-white/5">
                        Open my orders
                    </a>
                </div>
            </aside>
        </div>
    </div>
@endsection
