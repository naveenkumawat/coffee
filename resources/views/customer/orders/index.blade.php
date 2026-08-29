@extends('layouts.app', ['title' => 'My Orders | '.config('app.name')])

@section('content')
    <div class="mx-auto max-w-7xl px-6 py-10 sm:py-16">
        <div class="mb-8">
            <p class="text-xs uppercase tracking-[0.35em] text-amber-200">Order History</p>
            <h1 class="mt-3 text-3xl font-semibold text-white">My orders</h1>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-300">Track only the orders linked to your customer account. Internal recipe details and staff-only notes stay hidden.</p>
        </div>

        <div class="space-y-5">
            @forelse ($orders as $order)
                <article class="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-xl shadow-black/10 backdrop-blur">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm uppercase tracking-[0.25em] text-amber-200">{{ $order->order_number }}</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white">Rs {{ number_format((float) $order->total_amount, 2) }}</h2>
                            <p class="mt-2 text-sm text-stone-300">{{ $order->placed_at?->format('d M Y, h:i A') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">
                                {{ $order->status->label() }}
                            </span>
                            <a href="{{ route('customer.orders.show', $order) }}" class="rounded-full border border-white/10 px-5 py-2 text-sm font-medium text-white transition hover:border-white/30 hover:bg-white/5">
                                View details
                            </a>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3 text-sm text-stone-300">
                        @foreach ($order->items as $item)
                            <span class="rounded-full border border-white/10 px-3 py-2">
                                {{ $item->quantity }} x {{ $item->product_name }} - {{ $item->variant_name }}
                            </span>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="rounded-[2rem] border border-dashed border-white/20 bg-black/20 p-8 text-stone-300">
                    You do not have any orders linked to this account yet.
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    </div>
@endsection
