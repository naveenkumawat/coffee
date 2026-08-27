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
            <div class="md:col-span-2">
                <label for="name" class="mb-2 block text-sm text-white/80">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div>
                <label for="slug" class="mb-2 block text-sm text-white/80">Slug</label>
                <input id="slug" name="slug" type="text" value="{{ old('slug', $category->slug) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div>
                <label for="sort_order" class="mb-2 block text-sm text-white/80">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">
            </div>
            <div class="md:col-span-2">
                <label for="description" class="mb-2 block text-sm text-white/80">Description</label>
                <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-amber-300/50">{{ old('description', $category->description) }}</textarea>
            </div>
        </div>

        <label class="flex items-center gap-3 text-sm text-stone-300">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="rounded border-white/10 bg-white/10">
            Category is active on the public menu
        </label>

        <div class="flex gap-3">
            <button type="submit" class="rounded-full bg-amber-400 px-5 py-3 font-medium text-stone-950 transition hover:bg-amber-300">{{ $submit }}</button>
            <a href="{{ route('admin.menu.categories.index') }}" class="rounded-full border border-white/15 px-5 py-3 text-white transition hover:bg-white/5">Back</a>
        </div>
    </form>
</div>
