<?php

namespace App\Http\Requests\Cms;

use App\Enums\CmsPageKey;
use App\Http\Requests\AbstractRequest;
use App\Models\CmsPage;

class CmsPageUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $page = $this->route('cms_page');

        return $page instanceof CmsPage
            && ($this->user('admin')?->can('update', $page) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->route('cms_page');
        $isFaq = $page instanceof CmsPage && $page->pageKey() === CmsPageKey::Faq;

        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'is_published' => ['nullable', 'boolean'],
            'body' => ['nullable', 'string', 'max:50000'],
        ];

        if ($isFaq) {
            $rules['faq_items'] = ['nullable', 'array', 'max:50'];
            $rules['faq_items.*.id'] = ['nullable', 'integer', 'min:1'];
            $rules['faq_items.*.question'] = ['nullable', 'string', 'max:255'];
            $rules['faq_items.*.answer'] = ['nullable', 'string', 'max:5000'];
            $rules['faq_items.*.sort_order'] = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules['faq_items.*.is_active'] = ['nullable', 'boolean'];
            $rules['faq_items.*._delete'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_published' => $this->boolean('is_published'),
        ]);

        $items = $this->input('faq_items', []);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[$index]['is_active'] = $this->boolean('faq_items.'.$index.'.is_active');
            $items[$index]['_delete'] = $this->boolean('faq_items.'.$index.'._delete');
        }

        $this->merge(['faq_items' => $items]);
    }
}
