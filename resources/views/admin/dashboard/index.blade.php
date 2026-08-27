@extends('layouts.admin')

@section('content')
    <div class="space-y-10">
        <section class="grid gap-4 md:grid-cols-3">
            <x-admin.stat-card label="Menu categories" :value="$categoryCount" tone="amber" />
            <x-admin.stat-card label="Menu items" :value="$itemCount" tone="emerald" />
            <x-admin.stat-card label="Active guard" value="admin" tone="blue" />
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Recent categories</h2>
                    <a href="{{ route('admin.menu.categories.index') }}" class="text-sm text-amber-200 hover:text-amber-100">Manage</a>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($latestCategories as $category)
                        <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-white">{{ $category->name }}</p>
                                    <p class="text-sm text-stone-400">{{ $category->menu_items_count }} linked items</p>
                                </div>
                                <span class="text-xs uppercase tracking-[0.2em] text-white/60">{{ $category->is_active ? 'Active' : 'Hidden' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-stone-400">No categories yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Recent items</h2>
                    <a href="{{ route('admin.menu.items.index') }}" class="text-sm text-amber-200 hover:text-amber-100">Manage</a>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($latestItems as $item)
                        <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-white">{{ $item->name }}</p>
                                    <p class="text-sm text-stone-400">{{ $item->category?->name }} • ${{ number_format((float) $item->price, 2) }}</p>
                                </div>
                                <span class="text-xs uppercase tracking-[0.2em] text-white/60">{{ $item->is_available ? 'Live' : 'Paused' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-stone-400">No items yet.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
