@extends('administrator.layouts.default')

@section('page-title', 'Edit '.$page->title)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Pages', 'url' => route('administrator.cms-pages.index')],
        ['label' => $page->title],
    ]" />
@endsection

@section('content')
    @php
        $isFaq = $page->pageKey() === \App\Enums\CmsPageKey::Faq;
        $isContact = $page->pageKey() === \App\Enums\CmsPageKey::Contact;
        $faqItems = old('faq_items');
        if (! is_array($faqItems)) {
            $faqItems = $page->faqItems->map(fn ($item) => [
                'id' => $item->id,
                'question' => $item->question,
                'answer' => $item->answer,
                'sort_order' => $item->sort_order,
                'is_active' => $item->is_active,
            ])->all();
        }
    @endphp

    <form method="POST" action="{{ route('administrator.cms-pages.update', $page) }}" class="form" id="cms-page-form">
        @csrf
        @method('PUT')

        <div class="card card-flush internal-card internal-form-card mb-8">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-900">{{ $page->title }}</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                @if ($isContact)
                    <div class="alert alert-light-primary mb-8">
                        Address, phone, WhatsApp, email, and opening hours come from
                        <a href="{{ route('administrator.website-settings.edit', ['section' => 'business']) }}">Website Settings → Business</a>.
                        Use this page for extra visit copy only.
                    </div>
                @endif

                <div class="row g-6">
                    <div class="col-12 col-md-6">
                        <label for="title" class="form-label">Page title</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $page->title) }}" class="form-control @error('title') is-invalid @enderror" required maxlength="120" />
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="seo_title" class="form-label">SEO title</label>
                        <input id="seo_title" name="seo_title" type="text" value="{{ old('seo_title', $page->seo_title) }}" class="form-control @error('seo_title') is-invalid @enderror" maxlength="120" />
                    </div>
                    <div class="col-12">
                        <label for="meta_description" class="form-label">Meta description</label>
                        <textarea id="meta_description" name="meta_description" rows="2" class="form-control @error('meta_description') is-invalid @enderror" maxlength="320">{{ old('meta_description', $page->meta_description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="is_published" value="0">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input id="is_published" name="is_published" type="checkbox" value="1" class="form-check-input" @checked(old('is_published', $page->is_published)) />
                            <label class="form-check-label" for="is_published">Published</label>
                        </div>
                    </div>

                    @unless ($isFaq)
                        <div class="col-12">
                            <label for="body" class="form-label">Content</label>
                            <textarea id="body" name="body" rows="14" class="form-control" data-cms-editor>{{ old('body', $page->body) }}</textarea>
                            <div class="form-text">Headings, paragraphs, bold/italic, links, and lists are supported. Scripts are stripped on save.</div>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        @if ($isFaq)
            <div class="card card-flush internal-card internal-form-card mb-8">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">FAQ items</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="faq-repeater" class="d-flex flex-column gap-6">
                        @foreach ($faqItems as $index => $item)
                            @include('administrator.cms-pages.partials.faq-item', ['index' => $index, 'item' => $item])
                        @endforeach
                    </div>
                    <div class="mt-6">
                        <x-internal.button id="faq-add" label="Add FAQ" type="button" variant="success" icon="ki-plus" />
                    </div>
                </div>
            </div>
            <template id="faq-item-template">
                @include('administrator.cms-pages.partials.faq-item', ['index' => '__INDEX__', 'item' => ['id' => '', 'question' => '', 'answer' => '', 'sort_order' => 0, 'is_active' => true]])
            </template>
        @endif

        <div class="d-flex justify-content-end internal-form-actions mb-10">
            <x-internal.button-group :items="[
                ['label' => 'Preview', 'url' => $customerPreviewUrl, 'variant' => 'light', 'icon' => 'ki-eye', 'target' => '_blank'],
                ['label' => 'Save page', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ]" />
        </div>
    </form>
@endsection

@push('scripts')
<script>
(() => {
    const editorEl = document.querySelector('[data-cms-editor]');
    if (editorEl && typeof ClassicEditor !== 'undefined') {
        ClassicEditor.create(editorEl, {
            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
        }).then((editor) => {
            const form = document.getElementById('cms-page-form');
            form?.addEventListener('submit', () => {
                editorEl.value = editor.getData();
            });
        }).catch(() => {});
    }

    const repeater = document.getElementById('faq-repeater');
    const template = document.getElementById('faq-item-template');
    const visibleAdd = document.getElementById('faq-add');

    const nextIndex = () => repeater ? repeater.querySelectorAll('[data-faq-item]').length : 0;

    const bindRemove = (root) => {
        root.querySelectorAll('[data-faq-remove]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const item = button.closest('[data-faq-item]');
                if (!item || !window.InternalConfirm) {
                    return;
                }
                const result = await window.InternalConfirm.open({
                    title: 'Remove this FAQ?',
                    body: 'The question will be removed when you save the page.',
                    confirmLabel: 'Remove',
                    confirmClass: 'btn-danger',
                });
                if (result.confirmed) {
                    item.remove();
                }
            });
        });
    };

    if (visibleAdd && repeater && template) {
        visibleAdd.addEventListener('click', (event) => {
            event.preventDefault();
            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex()));
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const node = wrap.firstElementChild;
            if (node) {
                repeater.appendChild(node);
                bindRemove(node);
            }
        });
        bindRemove(repeater);
    }
})();
</script>
@endpush
