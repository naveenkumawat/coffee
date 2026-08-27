<div class="mx-auto max-w-3xl rounded-[2rem] border border-white/10 bg-white/5 p-8">
    <div class="mb-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Catalog</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">{{ $title }}</h1>
    </div>

    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="menu_category_id" class="mb-2 block text-sm text-white/80">Category</label>
                <select id="menu_category_id" name="menu_category_id" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
                    <option value="">Select a category</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected(old('menu_category_id', $item->menu_category_id) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="price" class="mb-2 block text-sm text-white/80">Price</label>
                <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $item->price) }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div class="md:col-span-2">
                <label for="name" class="mb-2 block text-sm text-white/80">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $item->name) }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div>
                <label for="slug" class="mb-2 block text-sm text-white/80">Slug</label>
                <input id="slug" name="slug" type="text" value="{{ old('slug', $item->slug) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div>
                <label for="sort_order" class="mb-2 block text-sm text-white/80">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $item->sort_order) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div class="md:col-span-2">
                <label for="description" class="mb-2 block text-sm text-white/80">Description</label>
                <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">{{ old('description', $item->description) }}</textarea>
            </div>
        </div>

        <div class="flex flex-wrap gap-6 text-sm text-stone-300">
            <label class="flex items-center gap-3">
                <input type="hidden" name="is_available" value="0">
                <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $item->is_available)) class="rounded border-white/10 bg-white/10">
                Available on the public menu
            </label>
            <label class="flex items-center gap-3">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured)) class="rounded border-white/10 bg-white/10">
                Highlight as featured
            </label>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-full bg-amber-400 px-5 py-3 font-medium text-stone-950 transition hover:bg-amber-300">{{ $submit }}</button>
            <a href="{{ route('admin.menu.items.index') }}" class="rounded-full border border-white/15 px-5 py-3 text-white transition hover:bg-white/5">Back</a>
        </div>
    </form>
</div>
