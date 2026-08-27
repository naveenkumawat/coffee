@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Catalog</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Menu items</h1>
            </div>
            <a href="{{ route('admin.menu.items.create') }}" class="rounded-full bg-amber-400 px-5 py-3 font-medium text-stone-950 transition hover:bg-amber-300">
                New item
            </a>
        </div>

        <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/5">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-black/20 text-white/60">
                    <tr>
                        <th class="px-6 py-4 font-medium">Item</th>
                        <th class="px-6 py-4 font-medium">Category</th>
                        <th class="px-6 py-4 font-medium">Price</th>
                        <th class="px-6 py-4 font-medium">Flags</th>
                        <th class="px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($items as $item)
                        <tr class="text-white/85">
                            <td class="px-6 py-5">
                                <p class="font-medium text-white">{{ $item->name }}</p>
                                <p class="mt-1 text-xs text-stone-400">{{ $item->description }}</p>
                            </td>
                            <td class="px-6 py-5">{{ $item->category?->name }}</td>
                            <td class="px-6 py-5">${{ number_format((float) $item->price, 2) }}</td>
                            <td class="px-6 py-5">
                                <div class="flex flex-wrap gap-2 text-xs uppercase tracking-[0.2em]">
                                    <span class="rounded-full border border-white/10 px-2 py-1">{{ $item->is_available ? 'Available' : 'Paused' }}</span>
                                    @if ($item->is_featured)
                                        <span class="rounded-full border border-amber-300/30 px-2 py-1 text-amber-200">Featured</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('admin.menu.items.edit', $item) }}" class="text-amber-200 hover:text-amber-100">Edit</a>
                                    <form method="POST" action="{{ route('admin.menu.items.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-200 hover:text-rose-100">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-stone-400">No menu items created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </div>
@endsection
