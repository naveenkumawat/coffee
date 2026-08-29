@extends('layouts.app', ['title' => $order->order_number.' | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Order Detail</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">{{ $order->order_number }}</h1>
                <p class="mt-3 text-sm leading-7 text-stone-300">Customer-safe order progress, totals, and item snapshots only.</p>
            </div>
            <a href="{{ route('customer.orders.index') }}" class="rounded-full border border-white/10 px-5 py-2 text-sm font-medium text-white transition hover:border-white/30 hover:bg-white/5">
                Back to orders
            </a>
        </div>

        <div class="grid gap-8 lg:grid-cols-[0.95fr,1.05fr]">
            <section class="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200">Status</p>
                        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $order->status->label() }}</h2>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200">Total</p>
                        <h2 class="mt-2 text-2xl font-semibold text-white">Rs {{ number_format((float) $order->total_amount, 2) }}</h2>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200">Placed</p>
                        <p class="mt-2 text-stone-300">{{ $order->placed_at?->format('d M Y, h:i A') }}</p>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200">Notes</p>
                        <p class="mt-2 text-stone-300">{{ $order->customer_notes ?: 'No order notes provided.' }}</p>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200">Pickup Name</p>
                        <p class="mt-2 text-stone-300">{{ $order->pickup_name ?: 'Primary account contact' }}</p>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200">Pickup Phone</p>
                        <p class="mt-2 text-stone-300">{{ $order->pickup_phone ?: ($order->customer_phone ?: 'Not provided') }}</p>
                    </div>
                </div>

                @if ($order->status === \App\Enums\OrderStatus::PendingPayment)
                    <div class="mt-8 rounded-3xl border border-amber-300/20 bg-amber-500/10 p-5">
                        <p class="text-sm font-medium text-amber-100">Payment is still pending.</p>
                        <p class="mt-2 text-sm leading-7 text-stone-300">{{ config('coffee.payments.instructions') }}</p>
                        @if (filled(config('coffee.payments.upi_id')) || filled(config('coffee.payments.whatsapp_number')))
                            <div class="mt-4 flex flex-wrap gap-3 text-sm text-stone-200">
                                @if (filled(config('coffee.payments.upi_id')))
                                    <span class="rounded-full border border-white/10 px-3 py-2">UPI: {{ config('coffee.payments.upi_id') }}</span>
                                @endif
                                @if (filled(config('coffee.payments.whatsapp_number')))
                                    <span class="rounded-full border border-white/10 px-3 py-2">WhatsApp: {{ config('coffee.payments.whatsapp_number') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-8 space-y-4">
                    @foreach ($order->items as $item)
                        <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-white">{{ $item->product_name }}</h3>
                                    <p class="mt-1 text-sm text-stone-300">{{ $item->variant_name }}</p>
                                    <p class="mt-2 text-sm text-stone-400">{{ $item->customer_ingredient_summary ?: 'Ingredients summary coming soon.' }}</p>
                                </div>
                                <div class="text-sm text-stone-200 sm:text-right">
                                    <div>{{ $item->quantity }} x Rs {{ number_format((float) $item->unit_price, 2) }}</div>
                                    <div class="mt-2 font-semibold text-emerald-300">Rs {{ number_format((float) $item->line_subtotal, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur">
                <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Status Timeline</p>
                <div class="mt-6 space-y-5">
                    @foreach ($order->statusHistory as $entry)
                        <div class="relative rounded-3xl border border-white/10 bg-black/20 p-5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">{{ $entry->to_status->label() }}</h3>
                                    @if ($entry->from_status)
                                        <p class="text-sm text-stone-400">Updated from {{ $entry->from_status->label() }}</p>
                                    @else
                                        <p class="text-sm text-stone-400">Order created</p>
                                    @endif
                                </div>
                                <div class="text-sm text-stone-300">{{ $entry->created_at?->format('d M Y, h:i A') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
